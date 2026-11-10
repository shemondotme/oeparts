<?php

namespace App\Filament\Pages\Settings;

use App\Models\AiCrawlerHit;
use App\Services\AiCrawlerDetector;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * AI Crawlers — which AI crawlers read the site, how often, and what robots.txt tells each of them. Holds no
 * settings of its own (the policy is edited in the SEO Control Center).
 */
class AiCrawlers extends Page
{
    protected string $view = 'filament.pages.settings.ai-crawlers';

    protected static ?string $title = 'AI Crawlers';

    protected static ?string $slug = 'seo-settings/ai-crawlers';

    protected static bool $shouldRegisterNavigation = false;

    public const DAYS = 30;

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Which AI crawlers visited in the last '.self::DAYS.' days, and what robots.txt tells them.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSeoSettings')
                ->label('Back to SEO Control Center')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(SeoControlCenter::getUrl()),
        ];
    }

    /**
     * @return array{
     *     tracking: bool,
     *     total: int,
     *     bots: list<array{name: string, operator: string, purpose: string, hits: int, last_seen: ?string, policy: string}>,
     *     days: list<array{date: string, hits: int}>
     * }
     */
    public function getData(): array
    {
        $since = now()->subDays(self::DAYS - 1)->toDateString();
        $rows = AiCrawlerHit::query()->where('hit_date', '>=', $since)->get();
        $policy = $this->policyByBot();

        $bots = $rows->groupBy('bot')->map(function ($group, string $name) use ($policy) {
            $info = AiCrawlerDetector::info($name);

            return [
                'name' => $name,
                'operator' => $info[1] ?? '—',
                'purpose' => AiCrawlerDetector::PURPOSES[$info[2] ?? ''] ?? '',
                'hits' => (int) $group->sum('hits'),
                'last_seen' => $group->max('last_seen_at')?->diffForHumans(),
                'policy' => $policy[strtolower($name)] ?? 'No rule (follows the general rules)',
            ];
        })->sortByDesc('hits')->values()->all();

        $perDay = $rows->groupBy(fn ($r) => $r->hit_date->toDateString())->map(fn ($g) => (int) $g->sum('hits'));
        $days = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $days[] = ['date' => $date, 'hits' => (int) ($perDay[$date] ?? 0)];
        }

        return [
            'tracking' => filter_var(settings('seo.ai_crawler_tracking', true), FILTER_VALIDATE_BOOLEAN),
            'total' => (int) $rows->sum('hits'),
            'bots' => $bots,
            'days' => $days,
        ];
    }

    /** @return array<string, string> lower-cased bot => "Allowed" / "Blocked" from the robots.txt AI rules */
    private function policyByBot(): array
    {
        $raw = settings('crawlers.ai_bot_rules', '[]');
        $rules = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $policy = [];

        foreach ($rules as $rule) {
            $agent = strtolower(trim((string) ($rule['user_agent'] ?? '')));
            if ($agent !== '') {
                $policy[$agent] = ($rule['action'] ?? 'allow') === 'block' || ($rule['action'] ?? '') === 'disallow' ? 'Blocked in robots.txt' : 'Allowed in robots.txt';
            }
        }

        return $policy;
    }
}
