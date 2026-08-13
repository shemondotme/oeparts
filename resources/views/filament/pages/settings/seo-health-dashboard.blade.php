<style>
    /* Clarity — light, minimal, table-row-driven layout the business owner
     * picked from a set of design directions. Scoped under .op-clarity so it
     * never leaks into the rest of the admin's op-* vocabulary; built from
     * the same theme tokens (--color-*) as everywhere else, so dark mode
     * still holds — only the layout/visual language is bespoke to this page. */
    .op-clarity .oc-kpis {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1px;
        background: var(--color-border-subtle);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    .op-clarity .oc-kpi { background: var(--color-bg-surface); padding: 1.1rem 1.25rem; }
    .op-clarity .oc-kpi-label { font-size: 0.72rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.6rem; }
    .op-clarity .oc-kpi-value { font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -0.01em; color: var(--color-text-primary); margin-bottom: 0.35rem; }
    .op-clarity .oc-kpi-meta { font-size: 0.75rem; color: var(--color-text-disabled); }
    .op-clarity .oc-tick { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; font-weight: 600; padding: 0.1rem 0.5rem; border-radius: 6px; }
    .op-clarity .oc-tick::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    .op-clarity .oc-card {
        background: var(--color-bg-surface);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: 1.1rem 1.25rem;
    }
    .op-clarity .oc-flag-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.6rem 1.5rem; margin-top: 0.6rem; }
    .op-clarity .oc-flag-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }

    .op-clarity .oc-section { background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); border-radius: var(--radius-md); overflow: hidden; }
    .op-clarity .oc-section-head { padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--color-border-subtle); font-size: 0.8rem; font-weight: 700; color: var(--color-text-primary); display: flex; align-items: center; gap: 0.5rem; }
    .op-clarity .oc-section-head svg { color: var(--color-text-link); width: 1rem; height: 1rem; flex: none; }

    .op-clarity .oc-row { display: grid; grid-template-columns: 1.4fr 0.8fr 1.8fr; align-items: center; gap: 0.75rem; padding: 0.8rem 1.25rem; border-bottom: 1px solid var(--color-border-subtle); }
    .op-clarity .oc-row:last-child { border-bottom: none; }
    .op-clarity .oc-row .oc-name { font-size: 0.8rem; font-weight: 600; color: var(--color-text-primary); }
    .op-clarity .oc-row .oc-subtext { font-size: 0.72rem; color: var(--color-text-disabled); margin-top: 0.1rem; }
    .op-clarity .oc-row .oc-detail { font-size: 0.75rem; color: var(--color-text-muted); text-align: right; }

    .op-clarity .oc-badge { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.68rem; font-weight: 700; padding: 0.18rem 0.55rem; border-radius: 6px; width: fit-content; }
    .op-clarity .oc-badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .op-clarity .oc-badge-ok { color: #16a34a; background: rgba(34, 197, 94, 0.14); }
    .op-clarity .oc-badge-warn { color: #b45309; background: rgba(245, 158, 11, 0.14); }
    .op-clarity .oc-badge-down { color: #dc2626; background: rgba(239, 68, 68, 0.14); }
    .op-clarity .oc-badge-off { color: var(--color-text-disabled); background: var(--color-bg-inset); }

    .op-clarity .oc-ext-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .op-clarity .oc-ext-top { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.5rem; }
    .op-clarity .oc-ext-name { font-size: 0.8rem; font-weight: 700; color: var(--color-text-primary); }
    .op-clarity .oc-ext-desc { font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.5; }
    .op-clarity .oc-ext-link { font-size: 0.72rem; font-weight: 700; color: var(--color-text-link); margin-top: 0.6rem; display: inline-block; }
    .op-clarity .oc-ext-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.6rem; margin-top: 0.75rem; }
    .op-clarity .oc-ext-stat-label { font-size: 0.66rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-muted); margin-bottom: 0.3rem; }
    .op-clarity .oc-ext-stat-value { font-size: 1rem; font-weight: 700; color: var(--color-text-primary); }

    .op-clarity .oc-lists { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; background: var(--color-border-subtle); border-top: 1px solid var(--color-border-subtle); }
    .op-clarity .oc-list-card { background: var(--color-bg-surface); }
    .op-clarity .oc-list-head { padding: 0.7rem 1.25rem; font-size: 0.68rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--color-border-subtle); }
    .op-clarity .oc-list-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 1.25rem; border-bottom: 1px solid var(--color-border-subtle); font-size: 0.78rem; }
    .op-clarity .oc-list-row:last-child { border-bottom: none; }
    .op-clarity .oc-list-row .oc-list-label { color: var(--color-text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
    .op-clarity .oc-list-row .oc-list-meta { color: var(--color-text-muted); font-variant-numeric: tabular-nums; flex: none; white-space: nowrap; font-size: 0.72rem; }
    .op-clarity .oc-empty { padding: 0.9rem 1.25rem; font-size: 0.78rem; color: var(--color-text-disabled); }

    .op-clarity .oc-trend { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.5rem; }
    .op-clarity .oc-trend-label { font-size: 0.66rem; color: var(--color-text-muted); flex: none; }

    @media (max-width: 860px) {
        .op-clarity .oc-kpis, .op-clarity .oc-ext-grid, .op-clarity .oc-lists { grid-template-columns: 1fr; }
        .op-clarity .oc-row { grid-template-columns: 1fr; row-gap: 0.3rem; }
        .op-clarity .oc-row .oc-detail { text-align: left; }
    }
</style>

<x-filament-panels::page>
    <div class="op-clarity space-y-4">
        @php
            $search = $this->searchAnalytics();
            $content = $this->contentHealth();
            $onPage = $this->onPageAudit();
            $adoption = $this->featureAdoption();
            $indexNow = $this->indexNowActivity();
            $redirects = $this->redirectHealth();
            $gsc = $this->googleSearchConsole();
            $gscAnalytics = $this->googleSearchAnalytics();
            $cwv = $this->coreWebVitals();
            $cwvHistory = $this->coreWebVitalsHistory();

            $zrTone = $search['zeroResultRate'] > 20 ? 'down' : ($search['zeroResultRate'] > 5 ? 'warn' : 'ok');
            $mdTone = $content['manualPercent'] < 20 ? 'warn' : 'ok';
            $toneColor = fn (string $tone) => match ($tone) {
                'ok' => '#16a34a', 'warn' => '#b45309', 'down' => '#dc2626', default => 'var(--color-text-disabled)',
            };
            $toneBg = fn (string $tone) => match ($tone) {
                'ok' => 'rgba(34, 197, 94, 0.14)', 'warn' => 'rgba(245, 158, 11, 0.14)', 'down' => 'rgba(239, 68, 68, 0.14)', default => 'var(--color-bg-inset)',
            };
        @endphp

        {{-- ── Key metrics strip ────────────────────────────────────────── --}}
        <div class="oc-kpis">
            <div class="oc-kpi">
                <div class="oc-kpi-label">Searches (30d)</div>
                <div class="oc-kpi-value">{{ number_format($search['total']) }}</div>
                <div class="oc-kpi-meta">{{ $search['ratioLabel'] }}</div>
            </div>
            <div class="oc-kpi">
                <div class="oc-kpi-label">Zero-Result Rate</div>
                <div class="oc-kpi-value">{{ $search['zeroResultRate'] }}%</div>
                <span class="oc-tick" style="color: {{ $toneColor($zrTone) }}; background: {{ $toneBg($zrTone) }};">{{ number_format($search['zeroResult']) }} found nothing</span>
            </div>
            <div class="oc-kpi">
                <div class="oc-kpi-label">Manual Descriptions</div>
                <div class="oc-kpi-value">{{ $content['manualPercent'] }}%</div>
                <span class="oc-tick" style="color: {{ $toneColor($mdTone) }}; background: {{ $toneBg($mdTone) }};">{{ number_format($content['manualCount']) }} / {{ number_format($content['total']) }} products</span>
            </div>
        </div>

        {{-- ── Translation coverage ─────────────────────────────────────── --}}
        <div class="oc-card">
            <div class="oc-kpi-label">Translation Coverage</div>
            @if (empty($content['translations']))
                <div class="oc-kpi-meta" style="margin-top: 0.5rem;">Only one active locale configured</div>
            @else
                <div class="oc-flag-grid">
                    @foreach ($content['translations'] as $t)
                        <div class="oc-flag-row">
                            <img src="{{ asset('flags/' . $t['code'] . '.svg') }}" alt="" width="20" height="15" style="border-radius: 2px; box-shadow: 0 0 0 1px var(--color-border-subtle); flex: none;">
                            <span style="flex: 1; color: var(--color-text-secondary);">{{ $t['name'] }}</span>
                            <span style="font-weight: 700; font-variant-numeric: tabular-nums; color: {{ $t['percent'] < 50 ? '#b45309' : '#16a34a' }};">{{ $t['percent'] }}%</span>
                        </div>
                    @endforeach
                </div>
                <div class="oc-kpi-meta" style="margin-top: 0.6rem;">Genuine (non-fallback) name per locale</div>
            @endif
        </div>

        {{-- ── Search Performance (Google Search Console) ──────────────────── --}}
        <div class="oc-section">
            <div class="oc-section-head">
                <x-heroicon-o-chart-bar-square />
                Search Performance — Google Search Console (28d)
            </div>
            @if (! $gscAnalytics['configured'])
                <div class="oc-empty">
                    Connect Google Search Console to see real Google search clicks, impressions, and top-performing queries/pages here.
                    <a href="{{ \App\Filament\Pages\Settings\SeoControlCenter::getUrl() }}" class="oc-ext-link" style="display: block; margin-top: 0.4rem;">Configure in Control Center &rarr;</a>
                </div>
            @elseif (isset($gscAnalytics['error']))
                <div class="oc-empty" style="color: #dc2626;">{{ $gscAnalytics['error'] }}</div>
            @else
                <div class="oc-ext-stats" style="padding: 1rem 1.25rem;">
                    <div>
                        <div class="oc-ext-stat-label">Clicks</div>
                        <div class="oc-ext-stat-value">{{ number_format($gscAnalytics['totalClicks']) }}</div>
                    </div>
                    <div>
                        <div class="oc-ext-stat-label">Impressions</div>
                        <div class="oc-ext-stat-value">{{ number_format($gscAnalytics['totalImpressions']) }}</div>
                    </div>
                    <div>
                        <div class="oc-ext-stat-label">Avg CTR</div>
                        <div class="oc-ext-stat-value">{{ number_format($gscAnalytics['avgCtr'] * 100, 1) }}%</div>
                    </div>
                    <div>
                        <div class="oc-ext-stat-label">Avg Position</div>
                        <div class="oc-ext-stat-value">{{ number_format($gscAnalytics['avgPosition'], 1) }}</div>
                    </div>
                </div>

                <div class="oc-lists">
                    <div class="oc-list-card">
                        <div class="oc-list-head">Top Queries</div>
                        @forelse (array_slice($gscAnalytics['topQueries'], 0, 6) as $row)
                            <div class="oc-list-row">
                                <span class="oc-list-label" title="{{ $row['query'] }}">{{ $row['query'] }}</span>
                                <span class="oc-list-meta">{{ number_format($row['clicks']) }} clicks · pos {{ number_format($row['position'], 1) }}</span>
                            </div>
                        @empty
                            <div class="oc-empty">No query data for this period yet.</div>
                        @endforelse
                    </div>
                    <div class="oc-list-card">
                        <div class="oc-list-head">Top Pages</div>
                        @forelse (array_slice($gscAnalytics['topPages'], 0, 6) as $row)
                            <div class="oc-list-row">
                                <span class="oc-list-label" title="{{ $row['page'] }}">{{ \Illuminate\Support\Str::after($row['page'], '://') }}</span>
                                <span class="oc-list-meta">{{ number_format($row['clicks']) }} clicks</span>
                            </div>
                        @empty
                            <div class="oc-empty">No page data for this period yet.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Feature Adoption ─────────────────────────────────────────── --}}
        @php
            $detailPagesState = $adoption['detailPagesEnabled'] ? 'ok' : 'off';
            $imgState = $adoption['ownImagePercent'] < 10 ? 'warn' : 'ok';
            $indexNowAdoptionState = $adoption['indexNowEnabled'] ? 'ok' : 'off';
        @endphp
        <div class="oc-section">
            <div class="oc-section-head">
                <x-heroicon-o-rocket-launch />
                Feature Adoption
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Per-Product Detail Pages</div>
                    <div class="oc-subtext">Individual product pages beyond the search hub</div>
                </div>
                <span class="oc-badge oc-badge-{{ $detailPagesState }}">{{ $adoption['detailPagesEnabled'] ? 'Enabled' : 'Disabled' }}</span>
                <div class="oc-detail">{{ $adoption['detailPagesEnabled'] ? '/parts/{oem}/{id}-slug is live' : 'Toggle on the Control Center' }}</div>
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Own Product Images</div>
                    <div class="oc-subtext">Products with a real uploaded photo</div>
                </div>
                <span class="oc-badge oc-badge-{{ $imgState }}">{{ $adoption['ownImagePercent'] }}%</span>
                <div class="oc-detail">{{ number_format($adoption['withOwnImage']) }} / {{ number_format($adoption['total']) }} products</div>
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">IndexNow</div>
                    <div class="oc-subtext">Instant search-engine indexing pings</div>
                </div>
                <span class="oc-badge oc-badge-{{ $indexNowAdoptionState }}">{{ $adoption['indexNowEnabled'] ? 'Enabled' : 'Disabled' }}</span>
                <div class="oc-detail">{{ $adoption['indexNowEnabled'] ? 'Pushing to Bing/Yandex/Naver/Seznam' : 'Enable on the Crawlers & AI tab' }}</div>
            </div>
        </div>

        {{-- ── Technical Health ─────────────────────────────────────────── --}}
        @php
            $redirectState = $redirects['loopCount'] > 0 ? 'down' : 'ok';
            $notFoundState = $redirects['unresolved404s'] > 0 ? 'warn' : 'ok';
            $indexNowHealthState = ! $indexNow['enabled'] ? 'off' : ($indexNow['recentFailures'] > 0 ? 'warn' : 'ok');
        @endphp
        <div class="oc-section">
            <div class="oc-section-head">
                <x-heroicon-o-signal />
                Technical Health
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Redirects</div>
                    <div class="oc-subtext">Loop sweep across active redirects</div>
                </div>
                <span class="oc-badge oc-badge-{{ $redirectState }}">{{ $redirects['loopCount'] > 0 ? $redirects['loopCount'].' loop(s)' : 'Clean' }}</span>
                <div class="oc-detail">{{ number_format($redirects['activeRedirects']) }} active redirects</div>
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Unresolved 404s</div>
                    <div class="oc-subtext">Dead-link paths without a redirect</div>
                </div>
                <span class="oc-badge oc-badge-{{ $notFoundState }}">{{ number_format($redirects['unresolved404s']) }}</span>
                <div class="oc-detail">Candidates for a new Redirect row</div>
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">IndexNow Activity</div>
                    <div class="oc-subtext">{{ $indexNow['lastLabel'] }}</div>
                </div>
                <span class="oc-badge oc-badge-{{ $indexNowHealthState }}">
                    {{ $indexNow['enabled'] ? ($indexNow['recentFailures'] > 0 ? $indexNow['recentFailures'].' failure(s)' : 'Healthy') : 'Disabled' }}
                </span>
                <div class="oc-detail">Last 7 days</div>
            </div>
        </div>

        {{-- ── On-Page SEO Audit ────────────────────────────────────────── --}}
        @php
            $customTitleState = $onPage['customTitlePercent'] < 20 ? 'warn' : 'ok';
            $customDescState = $onPage['customDescriptionPercent'] < 20 ? 'warn' : 'ok';
            $dupTitleState = $onPage['duplicateTitleProducts'] > 0 ? 'warn' : 'ok';
        @endphp
        <div class="oc-section">
            <div class="oc-section-head">
                <x-heroicon-o-document-magnifying-glass />
                On-Page SEO Audit
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Custom Meta Titles</div>
                    <div class="oc-subtext">Products overriding the auto-generated search-result title</div>
                </div>
                <span class="oc-badge oc-badge-{{ $customTitleState }}">{{ $onPage['customTitlePercent'] }}%</span>
                <div class="oc-detail">{{ number_format($onPage['customTitleCount']) }} / {{ number_format($onPage['total']) }} products</div>
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Custom Meta Descriptions</div>
                    <div class="oc-subtext">Products overriding the auto-generated search-result snippet</div>
                </div>
                <span class="oc-badge oc-badge-{{ $customDescState }}">{{ $onPage['customDescriptionPercent'] }}%</span>
                <div class="oc-detail">{{ number_format($onPage['customDescriptionCount']) }} / {{ number_format($onPage['total']) }} products</div>
            </div>
            <div class="oc-row">
                <div>
                    <div class="oc-name">Duplicate Meta Titles</div>
                    <div class="oc-subtext">Products competing with an identical search-result title</div>
                </div>
                <span class="oc-badge oc-badge-{{ $dupTitleState }}">{{ $onPage['duplicateTitleProducts'] > 0 ? number_format($onPage['duplicateTitleProducts']).' product(s)' : 'None' }}</span>
                <div class="oc-detail">{{ $onPage['duplicateTitleGroups'] > 0 ? 'Across '.number_format($onPage['duplicateTitleGroups']).' duplicate title(s)' : 'Every custom title is unique' }}</div>
            </div>
        </div>

        {{-- ── External Connections ─────────────────────────────────────── --}}
        <div class="oc-ext-grid">
            {{-- Google Search Console --}}
            <div class="oc-card">
                <div class="oc-ext-top">
                    <span class="oc-ext-name">Google Search Console</span>
                    @if (! $gsc['configured'])
                        <span class="oc-badge oc-badge-off">Not Connected</span>
                    @elseif (isset($gsc['error']))
                        <span class="oc-badge oc-badge-down">Connection Error</span>
                    @else
                        <span class="oc-badge oc-badge-{{ $gsc['errors'] > 0 ? 'down' : ($gsc['warnings'] > 0 ? 'warn' : 'ok') }}">{{ $gsc['errors'] }} error(s) / {{ $gsc['warnings'] }} warning(s)</span>
                    @endif
                </div>

                @if (! $gsc['configured'])
                    <p class="oc-ext-desc">See indexed-vs-submitted counts and crawl errors once connected.</p>
                    <a href="{{ \App\Filament\Pages\Settings\SeoControlCenter::getUrl() }}" class="oc-ext-link">Configure in Control Center &rarr;</a>
                @elseif (isset($gsc['error']))
                    <p class="oc-ext-desc" style="color: #dc2626;">{{ $gsc['error'] }}</p>
                @else
                    <div class="oc-ext-stats">
                        <div>
                            <div class="oc-ext-stat-label">Indexed vs Submitted</div>
                            <div class="oc-ext-stat-value">{{ number_format($gsc['indexed']) }} / {{ number_format($gsc['submitted']) }}</div>
                        </div>
                        <div>
                            <div class="oc-ext-stat-label">Sitemap Errors / Warnings</div>
                            <div class="oc-ext-stat-value">{{ $gsc['errors'] }} / {{ $gsc['warnings'] }}</div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Core Web Vitals --}}
            <div class="oc-card">
                <div class="oc-ext-top">
                    <span class="oc-ext-name">Core Web Vitals</span>
                    @if (! $cwv['configured'])
                        <span class="oc-badge oc-badge-off">Not Connected</span>
                    @elseif (isset($cwv['insufficientData']))
                        <span class="oc-badge oc-badge-off">Insufficient Data</span>
                    @elseif (isset($cwv['error']))
                        <span class="oc-badge oc-badge-down">Connection Error</span>
                    @endif
                </div>

                @if (! $cwv['configured'])
                    <p class="oc-ext-desc">Real-user LCP, CLS and INP ratings via the CrUX API.</p>
                    <a href="{{ \App\Filament\Pages\Settings\SeoControlCenter::getUrl() }}" class="oc-ext-link">Configure in Control Center &rarr;</a>
                @elseif (isset($cwv['insufficientData']))
                    <p class="oc-ext-desc">CrUX has no real-user data yet for this origin — needs enough Chrome traffic, not a bug in this integration.</p>
                @elseif (isset($cwv['error']))
                    <p class="oc-ext-desc" style="color: #dc2626;">{{ $cwv['error'] }}</p>
                @else
                    <div class="oc-ext-stats">
                        @foreach ([['LCP (p75)', $cwv['lcp']], ['CLS (p75)', $cwv['cls']], ['INP (p75)', $cwv['inp']]] as [$label, $metric])
                            @php
                                $val = $metric['value'];
                                $display = $val === null ? '—' : (is_float($val) ? number_format($val, 2) : number_format($val)) . $metric['unit'];
                                $cwvTone = $this->ratingTone($metric['rating']) === 'muted' ? 'off' : $this->ratingTone($metric['rating']);
                            @endphp
                            <div>
                                <div class="oc-ext-stat-label">{{ $label }}</div>
                                <div class="oc-ext-stat-value">{{ $display }}</div>
                                <span class="oc-badge oc-badge-{{ $cwvTone }}" style="margin-top: 0.35rem;">{{ $metric['rating'] ? ucwords(str_replace('-', ' ', $metric['rating'])) : 'No data' }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if (count($cwvHistory['lcp']) >= 2)
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--color-border-subtle);">
                            <div class="oc-ext-stat-label" style="margin-bottom: 0.5rem;">{{ count($cwvHistory['lcp']) }}-Week Trend</div>
                            @foreach (['LCP' => 'lcp', 'CLS' => 'cls', 'INP' => 'inp'] as $label => $key)
                                <div class="oc-trend">
                                    <span class="oc-trend-label">{{ $label }}</span>
                                    <span class="op-spark-bars">
                                        @foreach ($cwvHistory[$key] as $rating)
                                            <span class="op-spark-bar op-spark-bar-{{ $this->ratingTone($rating) }}"></span>
                                        @endforeach
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
