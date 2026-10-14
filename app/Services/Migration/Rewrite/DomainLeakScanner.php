<?php

namespace App\Services\Migration\Rewrite;

use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Looks for the old address everywhere text is stored and says where it still is. Report only: it changes nothing.
 *
 * Every text column of every table a package can carry (not the system tables) is asked "do you still mention the
 * old name?", and each answer is filed under what the registry decided about that place:
 *
 *   registered_rewritten  a place the rewrite changes; what remains there is a mention it chose not to touch (a bare
 *                         name in a sentence, an email address)
 *   preserved_historical  a place that must keep what it says (an old order note, a log, a legal document)
 *   scan_only             scripts and instructions: looked at, never edited, so the owner has to decide
 *   unregistered_leak     a column nobody decided anything about: a gap in the rewrite
 *   mailbox               a setting holding an email address of the old domain
 *
 * On a big table only TEXT-sized columns are asked (a short column cannot hold a page of links, and asking all of
 * them would read a million rows several times over); on MySQL each question carries a time limit, and a column that
 * ran out of time is reported as incomplete rather than silently counted as clean.
 */
class DomainLeakScanner
{
    public const REWRITTEN = 'registered_rewritten';

    public const PRESERVED = 'preserved_historical';

    public const SCAN_ONLY = 'scan_only';

    public const LEAK = 'unregistered_leak';

    public const MAILBOX = 'mailbox';

    private const TEXTUAL = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'json'];

    private const TEXT_SIZED = ['text', 'mediumtext', 'longtext', 'json'];

    public function __construct(
        private readonly DomainRewriteRegistry $registry = new DomainRewriteRegistry,
        private readonly ?string $connection = null,
        private readonly int $bigTableRows = 200_000,
        private readonly int $timeoutMs = 20_000,
    ) {}

    /**
     * @param  array<int, string>  $hosts  old hosts (as UrlRewriter normalises them)
     */
    public function scan(array $hosts): ScanReport
    {
        $needles = array_values(array_unique(array_map(static fn (string $h): string => explode(':', $h)[0], $hosts)));
        $entries = [];
        $skipped = [];

        foreach ($this->tables() as $table) {
            $big = $this->rowEstimate($table) >= $this->bigTableRows;

            foreach (Schema::connection($this->connection)->getColumns($table) as $info) {
                $column = (string) $info['name'];
                $type = (string) $info['type_name'];

                if (! in_array($type, self::TEXTUAL, true)) {
                    continue;
                }

                if ($table === 'settings' && $column === 'value') {
                    array_push($entries, ...$this->scanSettings($needles));

                    continue;
                }

                $class = $this->classOf($table, $column);

                if ($class === null) {
                    // Decided to be noise (a colour, a part-number snapshot): nothing to look for.
                    continue;
                }

                if ($big && ! in_array($type, self::TEXT_SIZED, true)) {
                    $skipped[] = "{$table}.{$column}";

                    continue;
                }

                $entry = $this->ask($table, $column, $class, $needles);

                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        }

        usort($entries, static fn (array $a, array $b): int => [$a['class'], $a['table'], $a['column']] <=> [$b['class'], $b['table'], $b['column']]);

        return new ScanReport($hosts, $entries, $skipped);
    }

    /**
     * Names the site was probably reached under that were not mentioned: what is stored as its address now, and
     * localhost where it still shows up. For the operator to offer as extra old names.
     *
     * @param  array<int, string>  $already
     * @return array<int, string>
     */
    public function suggestLegacyHosts(array $already = []): array
    {
        $suggested = [];

        try {
            $rows = DB::connection($this->connection)->table('settings')
                ->where(function ($q): void {
                    $q->where(['group' => 'general', 'key' => 'site_url'])->orWhere(['group' => 'seo', 'key' => 'canonical_host']);
                })
                ->pluck('value');

            foreach ($rows as $value) {
                $host = strtolower((string) parse_url(str_contains((string) $value, '://') ? (string) $value : '//'.$value, PHP_URL_HOST));
                $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

                if ($host !== '') {
                    $suggested[] = $host;
                }
            }
        } catch (\Throwable) {
            // The settings table may not exist yet: nothing to suggest from it.
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '') {
            $suggested[] = $appHost;
        }

        $already = array_map('strtolower', $already);

        return array_values(array_diff(array_unique($suggested), $already));
    }

    /** @return array<int, string> */
    protected function tables(): array
    {
        $tables = (new GroupRegistry)->exportableTables();
        sort($tables);

        return $tables;
    }

    /** The class a place is filed under, or null when it was decided to be noise. */
    private function classOf(string $table, string $column): ?string
    {
        $target = $this->registry->target($table);

        if ($target !== null && in_array($column, $target->columnNames(), true)) {
            return self::REWRITTEN;
        }

        $classification = $this->registry->classification($table, $column);

        if ($classification === null) {
            return self::LEAK;
        }

        return match ($classification[0]) {
            RewriteKind::Preserve => self::PRESERVED,
            RewriteKind::ScanOnly => self::SCAN_ONLY,
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $needles
     * @return array{table: string, column: string, class: string, rows: int, incomplete: bool}|null
     */
    private function ask(string $table, string $column, string $class, array $needles): ?array
    {
        try {
            $rows = $this->countMentions($table, $column, $needles);
        } catch (\Throwable $e) {
            if (! $this->isTimeout($e)) {
                throw $e;
            }

            return ['table' => $table, 'column' => $column, 'class' => $class, 'rows' => 0, 'incomplete' => true];
        }

        return $rows > 0 ? ['table' => $table, 'column' => $column, 'class' => $class, 'rows' => $rows, 'incomplete' => false] : null;
    }

    /**
     * How many rows of a column mention one of the names. Protected so a test can make it run out of time.
     *
     * @param  array<int, string>  $needles
     */
    protected function countMentions(string $table, string $column, array $needles): int
    {
        $db = DB::connection($this->connection);
        $grammar = $db->getQueryGrammar();

        $conditions = implode(' OR ', array_fill(0, count($needles), $grammar->wrap($column).' LIKE ?'));
        $hint = $db->getDriverName() === 'mysql' ? '/*+ MAX_EXECUTION_TIME('.$this->timeoutMs.') */ ' : '';

        $row = $db->selectOne(
            'SELECT '.$hint.'COUNT(*) AS c FROM '.$grammar->wrapTable($table).' WHERE '.$conditions,
            array_map(static fn (string $n): string => '%'.$n.'%', $needles),
        );

        return (int) ($row->c ?? 0);
    }

    protected function isTimeout(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'maximum statement execution time') || str_contains($message, 'max_statement_time') || str_contains($message, '3024');
    }

    /** Estimated rows: the server's own figure on MySQL (free), an exact count elsewhere. */
    private function rowEstimate(string $table): int
    {
        $db = DB::connection($this->connection);

        try {
            if ($db->getDriverName() === 'mysql') {
                $row = $db->selectOne('SELECT TABLE_ROWS AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);

                return (int) ($row->n ?? 0);
            }

            return (int) $db->table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * The settings table is one column holding unrelated things: each row is filed by its own rule.
     *
     * @param  array<int, string>  $needles
     * @return array<int, array{table: string, column: string, class: string, rows: int, incomplete: bool}>
     */
    private function scanSettings(array $needles): array
    {
        $db = DB::connection($this->connection);
        $counts = [];

        $query = $db->table('settings')->where('is_encrypted', false)->where(function ($q) use ($needles): void {
            foreach ($needles as $needle) {
                $q->orWhere('value', 'like', '%'.$needle.'%');
            }
        });

        foreach ($query->get(['group', 'key']) as $row) {
            $rule = $this->registry->settingRule((string) $row->group, (string) $row->key);
            $class = match ($rule[0] ?? null) {
                SettingAction::Email => self::MAILBOX,
                SettingAction::ScanOnly => self::SCAN_ONLY,
                SettingAction::Ignore => null,
                default => self::REWRITTEN,
            };

            if ($class !== null) {
                $name = "settings.{$row->group}.{$row->key}";
                $counts[$name] = $class;
            }
        }

        $entries = [];

        foreach ($counts as $name => $class) {
            $entries[] = ['table' => 'settings', 'column' => substr($name, strlen('settings.')), 'class' => $class, 'rows' => 1, 'incomplete' => false];
        }

        return $entries;
    }
}
