<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $search = $this->searchAnalytics();
            $content = $this->contentHealth();
            $adoption = $this->featureAdoption();
            $indexNow = $this->indexNowActivity();
            $redirects = $this->redirectHealth();
            $gsc = $this->googleSearchConsole();
            $cwv = $this->coreWebVitals();
        @endphp

        {{-- ── Search & Content ─────────────────────────────────────────── --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Search &amp; Content
                </h3>
            </div>

            <div class="p-6">
                <div class="op-tile-grid">
                    <div class="op-tile">
                        <div class="op-tile-label">Searches (30d)</div>
                        <div class="op-tile-value">{{ number_format($search['total']) }}</div>
                        <div class="op-tile-meta">{{ $search['ratioLabel'] }}</div>
                    </div>

                    @php
                        $zrTone = $search['zeroResultRate'] > 20 ? 'op-status-pill-down' : ($search['zeroResultRate'] > 5 ? 'op-status-pill-warn' : 'op-status-pill-ok');
                    @endphp
                    <div class="op-tile">
                        <div class="op-tile-label">Zero-Result Rate</div>
                        <div class="op-tile-value">{{ $search['zeroResultRate'] }}%</div>
                        <span class="op-status-pill {{ $zrTone }} op-tile-meta-pill">{{ number_format($search['zeroResult']) }} found nothing</span>
                    </div>

                    <div class="op-tile">
                        <div class="op-tile-label">Translation Coverage</div>
                        <div class="op-tile-value" style="font-size: 1rem;">{{ $content['translationSummary'] }}</div>
                        <div class="op-tile-meta">Genuine (non-fallback) name per locale</div>
                    </div>

                    @php
                        $mdTone = $content['manualPercent'] < 20 ? 'op-status-pill-warn' : 'op-status-pill-ok';
                    @endphp
                    <div class="op-tile">
                        <div class="op-tile-label">Manual Descriptions</div>
                        <div class="op-tile-value">{{ $content['manualPercent'] }}%</div>
                        <span class="op-status-pill {{ $mdTone }} op-tile-meta-pill">{{ number_format($content['manualCount']) }} / {{ number_format($content['total']) }} products</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Feature Adoption ─────────────────────────────────────────── --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-rocket-launch class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Feature Adoption
                </h3>
            </div>

            <div class="p-6">
                <div class="op-tile-grid">
                    <div class="op-tile">
                        <div class="op-tile-label">Per-Product Detail Pages</div>
                        <div class="op-tile-value" style="font-size: 1.15rem;">{{ $adoption['detailPagesEnabled'] ? 'Enabled' : 'Disabled' }}</div>
                        <span class="op-status-pill {{ $adoption['detailPagesEnabled'] ? 'op-status-pill-ok' : 'op-status-pill-muted' }} op-tile-meta-pill">
                            {{ $adoption['detailPagesEnabled'] ? '/parts/{oem}/{id}-slug is live' : 'Toggle on the Control Center' }}
                        </span>
                    </div>

                    @php
                        $imgTone = $adoption['ownImagePercent'] < 10 ? 'op-status-pill-warn' : 'op-status-pill-ok';
                    @endphp
                    <div class="op-tile">
                        <div class="op-tile-label">Own Product Images</div>
                        <div class="op-tile-value">{{ $adoption['ownImagePercent'] }}%</div>
                        <span class="op-status-pill {{ $imgTone }} op-tile-meta-pill">{{ number_format($adoption['withOwnImage']) }} / {{ number_format($adoption['total']) }} products</span>
                    </div>

                    <div class="op-tile">
                        <div class="op-tile-label">IndexNow</div>
                        <div class="op-tile-value" style="font-size: 1.15rem;">{{ $adoption['indexNowEnabled'] ? 'Enabled' : 'Disabled' }}</div>
                        <span class="op-status-pill {{ $adoption['indexNowEnabled'] ? 'op-status-pill-ok' : 'op-status-pill-muted' }} op-tile-meta-pill">
                            {{ $adoption['indexNowEnabled'] ? 'Pushing to Bing/Yandex/Naver/Seznam' : 'Enable on the Crawlers & AI tab' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Technical Health ─────────────────────────────────────────── --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-signal class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Technical Health
                </h3>
            </div>

            <div class="op-health-row op-health-row-head">
                <span>Check</span>
                <span>Status</span>
                <span>Detail</span>
                <span></span>
                <span style="text-align: right;">&nbsp;</span>
            </div>

            @php
                $redirectState = $redirects['loopCount'] > 0 ? 'down' : 'ok';
                $notFoundState = $redirects['unresolved404s'] > 0 ? 'warn' : 'ok';
                $indexNowState = ! $indexNow['enabled'] ? 'muted' : ($indexNow['recentFailures'] > 0 ? 'warn' : 'ok');
            @endphp

            <div class="op-health-row op-table-row op-health-{{ $redirectState }}">
                <span class="op-health-name">
                    <span class="op-dot {{ $redirectState === 'ok' ? 'op-dot-live' : '' }}" style="{{ $redirectState !== 'ok' ? 'background: var(--color-text-disabled);' : '' }}"></span>
                    <span>
                        <span style="display: block; font-weight: 600;">Redirects</span>
                        <span class="op-widget-title" style="text-transform: none; font-weight: 400; opacity: 1; letter-spacing: normal;">Loop sweep across active redirects</span>
                    </span>
                </span>
                <span class="op-status-pill op-status-pill-{{ $redirectState }}">{{ $redirects['loopCount'] > 0 ? $redirects['loopCount'].' loop(s)' : 'Clean' }}</span>
                <span style="color: var(--text-secondary); font-size: 0.8rem;">{{ number_format($redirects['activeRedirects']) }} active redirects</span>
                <span></span>
                <span style="text-align: right;"></span>
            </div>

            <div class="op-health-row op-table-row op-health-{{ $notFoundState }}">
                <span class="op-health-name">
                    <span class="op-dot {{ $notFoundState === 'ok' ? 'op-dot-live' : '' }}" style="{{ $notFoundState !== 'ok' ? 'background: var(--color-text-disabled);' : '' }}"></span>
                    <span>
                        <span style="display: block; font-weight: 600;">Unresolved 404s</span>
                        <span class="op-widget-title" style="text-transform: none; font-weight: 400; opacity: 1; letter-spacing: normal;">Dead-link paths without a redirect</span>
                    </span>
                </span>
                <span class="op-status-pill op-status-pill-{{ $notFoundState }}">{{ number_format($redirects['unresolved404s']) }}</span>
                <span style="color: var(--text-secondary); font-size: 0.8rem;">Candidates for a new Redirect row</span>
                <span></span>
                <span style="text-align: right;"></span>
            </div>

            <div class="op-health-row op-table-row op-health-{{ $indexNowState }}">
                <span class="op-health-name">
                    <span class="op-dot {{ $indexNowState === 'ok' ? 'op-dot-live' : '' }}" style="{{ $indexNowState !== 'ok' ? 'background: var(--color-text-disabled);' : '' }}"></span>
                    <span>
                        <span style="display: block; font-weight: 600;">IndexNow Activity</span>
                        <span class="op-widget-title" style="text-transform: none; font-weight: 400; opacity: 1; letter-spacing: normal;">{{ $indexNow['lastLabel'] }}</span>
                    </span>
                </span>
                <span class="op-status-pill op-status-pill-{{ $indexNowState }}">
                    {{ $indexNow['enabled'] ? ($indexNow['recentFailures'] > 0 ? $indexNow['recentFailures'].' failure(s)' : 'Healthy') : 'Disabled' }}
                </span>
                <span style="color: var(--text-secondary); font-size: 0.8rem;">Last 7 days</span>
                <span></span>
                <span style="text-align: right;"></span>
            </div>
        </div>

        {{-- ── External Connections ─────────────────────────────────────── --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-globe-alt class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    External Connections
                </h3>
            </div>

            <div class="p-6 space-y-5">
                {{-- Google Search Console --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span style="font-weight: 600; font-size: 0.875rem;">Google Search Console</span>
                        @if (! $gsc['configured'])
                            <span class="op-status-pill op-status-pill-muted">Not Connected</span>
                        @elseif (isset($gsc['error']))
                            <span class="op-status-pill op-status-pill-down">Connection Error</span>
                        @else
                            <span class="op-status-pill {{ $gsc['errors'] > 0 ? 'op-status-pill-down' : ($gsc['warnings'] > 0 ? 'op-status-pill-warn' : 'op-status-pill-ok') }}">
                                {{ $gsc['errors'] }} error(s) / {{ $gsc['warnings'] }} warning(s)
                            </span>
                        @endif
                    </div>

                    @if (! $gsc['configured'])
                        <p class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; font-size: 0.8rem;">
                            Add OAuth credentials on the Control Center's Structured Data tab to see indexed/submitted counts here.
                        </p>
                    @elseif (isset($gsc['error']))
                        <p style="color: var(--danger-600, #dc2626); font-size: 0.8rem;">{{ $gsc['error'] }}</p>
                    @else
                        <div class="op-tile-grid" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                            <div class="op-tile" style="padding: 0.75rem 1rem;">
                                <div class="op-tile-label">Indexed vs Submitted</div>
                                <div class="op-tile-value" style="font-size: 1.15rem;">{{ number_format($gsc['indexed']) }} / {{ number_format($gsc['submitted']) }}</div>
                            </div>
                            <div class="op-tile" style="padding: 0.75rem 1rem;">
                                <div class="op-tile-label">Sitemap Errors / Warnings</div>
                                <div class="op-tile-value" style="font-size: 1.15rem;">{{ $gsc['errors'] }} / {{ $gsc['warnings'] }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div style="border-top: 1px solid var(--color-border-subtle);"></div>

                {{-- Core Web Vitals --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span style="font-weight: 600; font-size: 0.875rem;">Core Web Vitals</span>
                        @if (! $cwv['configured'])
                            <span class="op-status-pill op-status-pill-muted">Not Connected</span>
                        @elseif (isset($cwv['insufficientData']))
                            <span class="op-status-pill op-status-pill-muted">Insufficient Data</span>
                        @elseif (isset($cwv['error']))
                            <span class="op-status-pill op-status-pill-down">Connection Error</span>
                        @endif
                    </div>

                    @if (! $cwv['configured'])
                        <p class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; font-size: 0.8rem;">
                            Add a CrUX API key on the Control Center's Structured Data tab to see real-user LCP/CLS/INP here.
                        </p>
                    @elseif (isset($cwv['insufficientData']))
                        <p class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; font-size: 0.8rem;">
                            CrUX has no real-user data yet for this origin — needs enough Chrome traffic, not a bug in this integration.
                        </p>
                    @elseif (isset($cwv['error']))
                        <p style="color: var(--danger-600, #dc2626); font-size: 0.8rem;">{{ $cwv['error'] }}</p>
                    @else
                        <div class="op-tile-grid" style="grid-template-columns: repeat(3, minmax(0,1fr));">
                            @foreach ([['LCP (p75)', $cwv['lcp']], ['CLS (p75)', $cwv['cls']], ['INP (p75)', $cwv['inp']]] as [$label, $metric])
                                @php
                                    $val = $metric['value'];
                                    $display = $val === null ? '—' : (is_float($val) ? number_format($val, 2) : number_format($val)) . $metric['unit'];
                                    $tone = $this->ratingTone($metric['rating']);
                                @endphp
                                <div class="op-tile" style="padding: 0.75rem 1rem;">
                                    <div class="op-tile-label">{{ $label }}</div>
                                    <div class="op-tile-value" style="font-size: 1.15rem;">{{ $display }}</div>
                                    <span class="op-status-pill op-status-pill-{{ $tone }} op-tile-meta-pill">{{ $metric['rating'] ? ucwords(str_replace('-', ' ', $metric['rating'])) : 'No data' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
