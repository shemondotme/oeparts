<?php

namespace Tests\Unit\Cleanup;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Source-level guard rails for the Cleanup Engine. These do not test behaviour; they stop a future
 * edit from quietly introducing the exact patterns the engine was designed to exclude. They read
 * the code with comments removed, so explaining a rule in a docblock never trips them.
 */
class CleanupEngineSafetyTest extends TestCase
{
    /** @return array<string, string> relative path => source without comments */
    private function engineSources(): array
    {
        $files = [];
        $dir = app_path('Services/Cleanup');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            // SchemaAuditor is the read-only schema REPORT: it parses migration text with regexes that
            // mention Schema::dropIfExists, which is not the engine dropping anything.
            if ($file->getExtension() === 'php' && $file->getFilename() !== 'SchemaAuditor.php') {
                $files[$file->getPathname()] = $file->getPathname();
            }
        }
        foreach (['RunCleanup', 'RunAutoCleanup', 'CleanupStaleCleanups'] as $command) {
            $files[app_path("Console/Commands/{$command}.php")] = app_path("Console/Commands/{$command}.php");
        }

        $out = [];
        foreach ($files as $path) {
            $out[str_replace(base_path().DIRECTORY_SEPARATOR, '', $path)] = $this->withoutComments((string) file_get_contents($path));
        }

        return $out;
    }

    private function withoutComments(string $php): string
    {
        $out = '';
        foreach (token_get_all($php) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    #[Test]
    public function the_engine_sources_were_found(): void
    {
        $this->assertGreaterThan(15, count($this->engineSources()));
    }

    #[Test]
    public function the_engine_never_truncates_or_uses_delete_limit_or_drops_anything(): void
    {
        foreach ($this->engineSources() as $path => $code) {
            $this->assertDoesNotMatchRegularExpression('/->truncate\s*\(/i', $code, "$path: ->truncate() is DDL (implicit commit, no row count)");
            $this->assertDoesNotMatchRegularExpression('/\bTRUNCATE\s+TABLE\b/i', $code, "$path: TRUNCATE TABLE");
            $this->assertDoesNotMatchRegularExpression('/\bDELETE\s+FROM\b[^;\'"]*\bLIMIT\b/i', $code, "$path: DELETE ... LIMIT is unsafe under statement-based replication");
            $this->assertDoesNotMatchRegularExpression('/Schema::(drop|dropIfExists|dropColumns?)\s*\(/', $code, "$path: the engine never changes the schema");
            $this->assertDoesNotMatchRegularExpression('/DB::(unprepared|statement)\s*\(/', $code, "$path: no raw statements (use the query builder; add an explicit allow-list entry if one is ever really needed)");
        }
    }

    #[Test]
    public function no_user_input_can_reach_the_engine_services(): void
    {
        foreach ($this->engineSources() as $path => $code) {
            $this->assertDoesNotMatchRegularExpression('/\$_(GET|POST|REQUEST|COOKIE|FILES)\b/', $code, "$path: superglobal");
            $this->assertDoesNotMatchRegularExpression('/\brequest\(\)\s*->\s*(input|get|query|all|post|file|only)\b|\bRequest::(input|get|query|all)\b/', $code, "$path: reads request input");
        }
    }

    #[Test]
    public function every_catch_block_logs_rethrows_or_reports_and_none_is_empty(): void
    {
        foreach ($this->engineSources() as $path => $code) {
            preg_match_all('/catch\s*\([^)]*\)\s*\{/', $code, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$text, $offset]) {
                $body = $this->braceBody($code, $offset + strlen($text) - 1);

                $this->assertNotSame('', trim($body), "$path: empty catch block hides failures");
                $this->assertMatchesRegularExpression(
                    '/Log::|throw\s|report\(|->(error|warn)\(|abandon\(|fail\(/',
                    $body,
                    "$path: a catch block must log, rethrow or report (silent catches hid real bugs before)\n".trim($body),
                );
            }
        }
    }

    #[Test]
    public function only_the_manager_and_the_targets_may_remove_files(): void
    {
        $allowed = ['CleanupManager.php', 'Targets'.DIRECTORY_SEPARATOR];

        foreach ($this->engineSources() as $path => $code) {
            $isAllowed = false;
            foreach ($allowed as $needle) {
                $isAllowed = $isAllowed || str_contains($path, $needle);
            }

            if (! $isAllowed) {
                $this->assertDoesNotMatchRegularExpression('/\b(unlink|rmdir|deleteDirectory|File::delete|->delete\(\s*[\'"$])/', $code, "$path: file deletion belongs behind ProtectedPath in a target");
            }
        }
    }

    /** The text between the brace at $openAt and its matching close brace. */
    private function braceBody(string $code, int $openAt): string
    {
        $depth = 0;
        for ($i = $openAt, $n = strlen($code); $i < $n; $i++) {
            if ($code[$i] === '{') {
                $depth++;
            } elseif ($code[$i] === '}' && --$depth === 0) {
                return substr($code, $openAt + 1, $i - $openAt - 1);
            }
        }

        return '';
    }
}
