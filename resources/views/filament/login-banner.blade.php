@php
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\DB;

    try {
        $stats = Cache::remember('login_banner_stats', 300, function () {
            return [
                'searches' => DB::table('search_logs')->count(),
                'products' => DB::table('products')->where('is_active', true)->count(),
                'orders'   => DB::table('orders')->whereMonth('created_at', now()->month)->count(),
                'carriers' => DB::table('carriers')->where('is_active', true)->count(),
            ];
        });
    } catch (\Throwable $e) {
        $stats = ['searches' => 0, 'products' => 0, 'orders' => 0, 'carriers' => 0];
    }

    $fmt = fn(int $n): string => $n >= 1000
        ? number_format($n / 1000, 1) . 'k'
        : (string) $n;
@endphp

{{--
    Command Gate — Login Banner
    Always-dark left panel. Theme-independent.
    Injected via SIMPLE_LAYOUT_START render hook in AdminPanelProvider.
--}}
<aside
    class="op-login-banner"
    aria-hidden="true"
    role="presentation"
>
    {{-- ── Background layers ─────────────────────────────────────────────── --}}
    <div class="op-login-banner-grid" id="op-lc-grid" aria-hidden="true"></div>
    <div class="op-login-banner-noise" aria-hidden="true"></div>
    <div class="op-login-banner-glow" aria-hidden="true"></div>

    {{-- ── Top bar ────────────────────────────────────────────────────────── --}}
    <header class="op-login-banner-header op-lc-anim" style="--anim-delay: 80ms">
        <a href="/" class="op-login-brand" tabindex="-1">
            <span class="op-login-monogram">OE</span>
            <span class="op-login-brand-name">OE<span class="op-login-brand-accent">PARTS</span></span>
        </a>
        <span class="op-login-status-badge">
            <span class="op-login-status-dot" aria-hidden="true">
                <span class="op-login-status-ping"></span>
                <span class="op-login-status-inner"></span>
            </span>
            All Services Nominal
        </span>
    </header>

    {{-- ── Hero ───────────────────────────────────────────────────────────── --}}
    <div class="op-login-hero" id="op-lc-hero">
        <span class="op-login-badge op-lc-anim" style="--anim-delay: 160ms">
            Enterprise Console v1.0
        </span>
        <h1 class="op-login-heading op-lc-anim" style="--anim-delay: 240ms">
            OeParts Operations Gateway
        </h1>
        <p class="op-login-body op-lc-anim" style="--anim-delay: 320ms">
            The unified control center for European OEM auto parts.
            Monitor inventory, manage manufacturers, and coordinate
            B2B logistics from a single command surface.
        </p>

        {{-- ── Lifecycle diagram ──────────────────────────────────────────── --}}
        <div class="op-lifecycle op-lc-anim" style="--anim-delay: 400ms" id="op-lifecycle" aria-hidden="true">

            {{-- Node 1: Search --}}
            <div class="op-lc-node">
                <div class="op-lc-circle">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="op-lc-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </div>
                <span class="op-lc-label">Search</span>
                <span class="op-lc-count">{{ $fmt($stats['searches']) }}</span>
            </div>

            {{-- Connector 1→2 --}}
            <div class="op-lc-connector" aria-hidden="true">
                <div class="op-lc-line"></div>
                <div class="op-lc-dot" style="animation-delay: 0ms"></div>
            </div>

            {{-- Node 2: Catalog --}}
            <div class="op-lc-node">
                <div class="op-lc-circle">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="op-lc-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                    </svg>
                </div>
                <span class="op-lc-label">Catalog</span>
                <span class="op-lc-count">{{ $fmt($stats['products']) }}</span>
            </div>

            {{-- Connector 2→3 --}}
            <div class="op-lc-connector" aria-hidden="true">
                <div class="op-lc-line"></div>
                <div class="op-lc-dot" style="animation-delay: 800ms"></div>
            </div>

            {{-- Node 3: Orders --}}
            <div class="op-lc-node">
                <div class="op-lc-circle op-lc-circle--active">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="op-lc-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm6.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                    </svg>
                </div>
                <span class="op-lc-label">Orders</span>
                <span class="op-lc-count op-lc-count--active">{{ $fmt($stats['orders']) }}<span class="op-lc-mo">mo</span></span>
            </div>

            {{-- Connector 3→4 --}}
            <div class="op-lc-connector" aria-hidden="true">
                <div class="op-lc-line"></div>
                <div class="op-lc-dot" style="animation-delay: 1600ms"></div>
            </div>

            {{-- Node 4: Payment --}}
            <div class="op-lc-node">
                <div class="op-lc-circle">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="op-lc-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                    </svg>
                </div>
                <span class="op-lc-label">Payment</span>
                <span class="op-lc-count">Airwallex</span>
            </div>

            {{-- Connector 4→5 --}}
            <div class="op-lc-connector" aria-hidden="true">
                <div class="op-lc-line"></div>
                <div class="op-lc-dot" style="animation-delay: 2400ms"></div>
            </div>

            {{-- Node 5: Shipping --}}
            <div class="op-lc-node">
                <div class="op-lc-circle">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="op-lc-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.514-8.25A1.125 1.125 0 0018.899 7.5h-11.25a1.125 1.125 0 00-1.125 1.125v1.5m-5.25 0H6.75m0 0v1.5m0-1.5H3.375c-.621 0-1.125.504-1.125 1.125v9.75c0 .621.504 1.125 1.125 1.125H6"/>
                    </svg>
                </div>
                <span class="op-lc-label">Shipping</span>
                <span class="op-lc-count">{{ $fmt($stats['carriers']) }}<span class="op-lc-mo"> carriers</span></span>
            </div>
        </div>
    </div>

    {{-- ── System Pulse Bar ───────────────────────────────────────────────── --}}
    <div class="op-login-pulse op-lc-anim" style="--anim-delay: 520ms">
        <span class="op-pulse-label">SYSTEM PULSE</span>
        <div class="op-pulse-indicators">
            <div class="op-pulse-item" title="API Gateway · Nginx · HTTP/3">
                <span class="op-pulse-dot op-pulse-dot--green">
                    <span class="op-pulse-ping"></span>
                </span>
                <span class="op-pulse-name">API</span>
            </div>
            <span class="op-pulse-sep" aria-hidden="true">·</span>
            <div class="op-pulse-item" title="MySQL 8.0 · Primary · Read/Write">
                <span class="op-pulse-dot op-pulse-dot--green">
                    <span class="op-pulse-ping"></span>
                </span>
                <span class="op-pulse-name">MySQL</span>
            </div>
            <span class="op-pulse-sep" aria-hidden="true">·</span>
            <div class="op-pulse-item" title="Redis · In-Memory Cache · Queue Backend">
                <span class="op-pulse-dot op-pulse-dot--green">
                    <span class="op-pulse-ping"></span>
                </span>
                <span class="op-pulse-name">Redis</span>
            </div>
            <span class="op-pulse-sep" aria-hidden="true">·</span>
            <div class="op-pulse-item" title="Airwallex · Payment Gateway · EU Region">
                <span class="op-pulse-dot op-pulse-dot--green">
                    <span class="op-pulse-ping"></span>
                </span>
                <span class="op-pulse-name">Payments</span>
            </div>
        </div>
        <span class="op-pulse-sync" id="op-pulse-sync">All systems nominal</span>
    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────────── --}}
    <footer class="op-login-banner-footer op-lc-anim" style="--anim-delay: 600ms">
        <span>&copy; {{ date('Y') }} OeParts. All rights reserved.</span>
        <div class="op-footer-secure">
            <svg class="op-footer-lock" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
            <span>Secured · TLS 1.3</span>
        </div>
    </footer>
</aside>
