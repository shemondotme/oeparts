@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@php
    $visibleWidgets = collect($widgets ?? [])->reject(fn ($widget) => $widget['hidden'] ?? false);

    $widgetIcon = [
        'total_orders' => 'heroicon-o-shopping-bag',
        'total_revenue' => 'heroicon-o-banknotes',
        'total_customers' => 'heroicon-o-users',
        'total_products' => 'heroicon-o-cube',
        'sales_chart' => 'heroicon-o-chart-bar-square',
        'search_popularity' => 'heroicon-o-magnifying-glass',
        'system_alerts' => 'heroicon-o-bell-alert',
        'recent_orders' => 'heroicon-o-shopping-cart',
        'recent_inquiries' => 'heroicon-o-question-mark-circle',
        'recent_contacts' => 'heroicon-o-envelope',
        'health_strip' => 'heroicon-o-heart',
        'activity_log' => 'heroicon-o-clipboard-document-list',
        'failed_jobs' => 'heroicon-o-exclamation-triangle',
        'cron_status' => 'heroicon-o-clock',
        'top_searches' => 'heroicon-o-bars-arrow-up',
        'newsletter_stats' => 'heroicon-o-megaphone',
        'ip_blocklist' => 'heroicon-o-shield-exclamation',
        'translation_progress' => 'heroicon-o-language',
        'admin_activity' => 'heroicon-o-user-circle',
        'cart_abandonment' => 'heroicon-o-shopping-cart',
        'product_condition' => 'heroicon-o-tag',
        'order_status' => 'heroicon-o-list-bullet',
        'customer_growth' => 'heroicon-o-arrow-trending-up',
        'search_zero_results' => 'heroicon-o-no-symbol',
        'checkout_dropoff' => 'heroicon-o-arrow-down-tray',
        'vat_compliance' => 'heroicon-o-document-check',
    ];

    $spanClass = function (array $widget): string {
        $span = min(4, max(1, (int) data_get($widget, 'meta.col_span', 1)));

        return match ($span) {
            2 => 'lg:col-span-2',
            3 => 'lg:col-span-3',
            4 => 'lg:col-span-4',
            default => 'lg:col-span-1',
        };
    };

    $statusClass = fn (?string $status): string => match ($status) {
        'healthy', 'success' => 'text-emerald-600',
        'warning' => 'text-amber-ink',
        'danger', 'failed' => 'text-red-600',
        default => 'text-ink-muted',
    };
@endphp

@section('header_actions')
    <button type="button" class="bp-btn-outline" x-data @click="$dispatch('open-dashboard-preferences')">
        <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
        Widgets
    </button>
@endsection

@section('content')
<div
    class="space-y-8"
    x-data="dashboardPreferences({{ Js::from($preferences ?? []) }})"
    @open-dashboard-preferences.window="preferencesOpen = true"
>
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="bp-spec text-amber-ink">§ Admin · Command Surface</p>
                <h2 class="mt-1 font-display text-xl font-bold tracking-[-0.02em] text-ink">
                    Operational dashboard<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted">
                {{ $visibleWidgets->count() }} visible widgets
            </p>
        </header>
    </section>

    <div
        class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4"
        x-ref="dashboardGrid"
        data-dashboard-sortable
    >
        @foreach($visibleWidgets as $id => $widget)
            @php
                $title = $widget['title'] ?? str($id)->replace('_', ' ')->title();
                $icon = $widgetIcon[$id] ?? 'heroicon-o-squares-2x2';
                $items = collect($widget['items'] ?? []);
                $data = collect($widget['data'] ?? []);
                $values = collect($widget['values'] ?? []);
                $labels = collect($widget['labels'] ?? []);
                $maxValue = max(1, (float) $values->max());
            @endphp

            <article class="bp-card {{ $spanClass($widget) }} min-h-[180px] overflow-hidden" data-widget-id="{{ $id }}">
                <header class="bp-card-header flex items-center justify-between gap-3">
                    <div>
                        <p class="bp-spec text-ink-muted">{{ $title }}</p>
                        <p class="mt-1 font-mono text-[10px] uppercase tracking-[0.18em] text-ink-muted">{{ str($id)->replace('_', ' ') }}</p>
                    </div>
                    <x-dynamic-component :component="$icon" class="h-5 w-5 text-ink" />
                </header>

                <div class="p-5">
                    @if(array_key_exists('value', $widget))
                        <div class="flex items-end justify-between gap-4">
                            <p class="font-mono text-3xl font-bold tabular-nums text-ink">{{ $widget['value'] }}</p>
                            @if(isset($widget['change']))
                                <span class="font-mono text-xs {{ ($widget['change'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ ($widget['change'] ?? 0) >= 0 ? '+' : '' }}{{ $widget['change'] }}%
                                </span>
                            @endif
                        </div>
                        @if(!empty($widget['subtitle']))
                            <p class="mt-3 text-sm text-ink-muted">{{ $widget['subtitle'] }}</p>
                        @endif
                    @elseif($id === 'sales_chart')
                        <div class="h-48 border border-rule bg-ivory-alt p-4">
                            @if($values->isNotEmpty())
                                <div class="flex h-full items-end gap-2">
                                    @foreach($values as $index => $value)
                                        @php $height = max(4, ((float) $value / $maxValue) * 100); @endphp
                                        <div class="group flex flex-1 flex-col items-center justify-end gap-2">
                                            <div class="w-full bg-ink/15 transition-colors group-hover:bg-amber"
                                                 style="height: {{ $height }}%"
                                                 title="{{ $labels[$index] ?? '' }} · {{ format_money($value) }}"></div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex h-full items-center justify-center text-center text-sm text-ink-muted">
                                    No sales data for the last 30 days.
                                </div>
                            @endif
                        </div>
                    @elseif($id === 'search_popularity' || $id === 'top_searches')
                        <ul class="space-y-3">
                            @forelse(($widget['data'] ?? $widget['items'] ?? []) as $item)
                                <li class="flex items-center justify-between gap-4 border-b border-rule pb-2 last:border-b-0">
                                    <span class="font-mono text-xs text-ink">{{ $item->query ?? $item->search_query ?? 'Unknown' }}</span>
                                    <span class="font-mono text-xs text-ink-muted tabular-nums">{{ $item->count ?? 0 }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-ink-muted">No search data yet.</li>
                            @endforelse
                        </ul>
                    @elseif($id === 'system_alerts')
                        <ul class="space-y-3">
                            @forelse($widget['alerts'] ?? [] as $alert)
                                <li class="border border-rule bg-ivory-alt px-3 py-2 text-sm text-ink">
                                    <span class="font-mono text-xs uppercase tracking-wider {{ $statusClass($alert['type'] ?? null) }}">{{ $alert['type'] ?? 'info' }}</span>
                                    <p class="mt-1">{{ $alert['message'] }}</p>
                                </li>
                            @empty
                                <li class="text-sm text-ink-muted">No active system alerts.</li>
                            @endforelse
                        </ul>
                    @elseif($id === 'health_strip')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($widget['checks'] ?? [] as $check)
                                <div class="flex items-center justify-between border border-rule bg-ivory-alt px-3 py-2">
                                    <span class="text-sm text-ink">{{ $check['label'] }}</span>
                                    <span class="font-mono text-xs uppercase tracking-wider {{ $statusClass($check['status'] ?? null) }}">{{ $check['status'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @elseif($id === 'recent_orders')
                        <div class="overflow-x-auto">
                            <table class="bp-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $order)
                                        <tr>
                                            <td class="font-mono">
                                                <a href="{{ route('admin.orders.show', $order) }}" class="hover:text-amber-ink hover:underline">
                                                    {{ $order->order_number }}
                                                </a>
                                            </td>
                                            <td>{{ $order->shipping_name ?? $order->user?->email ?? 'Guest' }}</td>
                                            <td class="font-mono tabular-nums">{{ format_money($order->grand_total) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-ink-muted">No recent orders.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif($id === 'recent_inquiries' || $id === 'recent_contacts' || $id === 'activity_log')
                        <ul class="space-y-3">
                            @forelse($items as $item)
                                <li class="border-b border-rule pb-3 last:border-b-0">
                                    <p class="text-sm font-medium text-ink">
                                        {{ $item->email ?? $item->message ?? $item->description ?? $item->action ?? 'Record' }}
                                    </p>
                                    <p class="mt-1 font-mono text-xs text-ink-muted">
                                        {{ optional($item->created_at)->diffForHumans() ?? optional($item->updated_at)->diffForHumans() ?? 'No timestamp' }}
                                    </p>
                                </li>
                            @empty
                                <li class="text-sm text-ink-muted">No records yet.</li>
                            @endforelse
                        </ul>
                    @elseif($id === 'order_status')
                        <ul class="space-y-3">
                            @forelse($data as $row)
                                <li class="flex items-center justify-between border-b border-rule pb-2 last:border-b-0">
                                    <span class="font-mono text-xs uppercase tracking-wider text-ink">{{ $row->status instanceof \BackedEnum ? $row->status->value : $row->status }}</span>
                                    <span class="font-mono text-xs text-ink-muted">{{ $row->count }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-ink-muted">No order status data yet.</li>
                            @endforelse
                        </ul>
                    @else
                        <p class="text-sm text-ink-muted">Widget data is available but does not require a detailed renderer.</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div
        x-show="preferencesOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/70 p-4"
    >
        <div class="bp-card max-h-[90vh] w-full max-w-2xl overflow-y-auto bg-paper">
            <header class="bp-card-header flex items-center justify-between">
                <div>
                    <p class="bp-spec text-amber-ink">§ Dashboard · Preferences</p>
                    <h3 class="mt-1 font-display text-lg font-bold text-ink">Widget visibility</h3>
                </div>
                <button type="button" class="bp-btn-ghost" @click="preferencesOpen = false">Close</button>
            </header>
            <div class="space-y-3 p-5">
                <template x-for="widget in preferences" :key="widget.id">
                    <label class="flex items-center justify-between border border-rule px-4 py-3">
                        <span class="font-mono text-xs uppercase tracking-wider text-ink" x-text="widget.id.replaceAll('_', ' ')"></span>
                        <input type="checkbox" class="rounded-none border-rule text-amber focus:ring-amber" x-model="widget.visible">
                    </label>
                </template>
                <div class="flex justify-end gap-3 pt-3">
                    <button type="button" class="bp-btn-outline" @click="preferencesOpen = false">Cancel</button>
                    <button type="button" class="bp-btn-primary" @click="savePreferences">Save Widgets</button>
                </div>
                <p x-show="message" class="font-mono text-xs text-ink-muted" x-text="message"></p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function dashboardPreferences(initialPreferences) {
    return {
        preferencesOpen: false,
        preferences: initialPreferences,
        message: '',
        init() {
            this.$nextTick(() => {
                if (!window.Sortable || !this.$refs.dashboardGrid) return;

                window.Sortable.create(this.$refs.dashboardGrid, {
                    animation: 150,
                    handle: '.bp-card-header',
                    onEnd: () => this.syncOrderFromDom(),
                });
            });
        },
        syncOrderFromDom() {
            const orderedIds = Array.from(this.$refs.dashboardGrid.querySelectorAll('[data-widget-id]'))
                .map((el) => el.dataset.widgetId);

            this.preferences.sort((a, b) => {
                const aIndex = orderedIds.indexOf(a.id);
                const bIndex = orderedIds.indexOf(b.id);
                return (aIndex === -1 ? 999 : aIndex) - (bIndex === -1 ? 999 : bIndex);
            });
        },
        async savePreferences() {
            this.message = 'Saving...';
            const response = await fetch('{{ route('admin.dashboard.preferences.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ preferences: this.preferences }),
            });

            if (!response.ok) {
                this.message = 'Unable to save preferences.';
                return;
            }

            this.message = 'Preferences saved. Reloading...';
            window.location.reload();
        },
    };
}
</script>
@endpush
@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@php
    $visibleWidgets = collect($preferences)
        ->filter(fn ($preference) => ($preference['visible'] ?? false) && isset($widgets[$preference['id']]))
        ->values();

    $statusClasses = [
        'healthy' => 'text-emerald-600',
        'success' => 'text-emerald-600',
        'warning' => 'text-amber-ink',
        'danger' => 'text-red-600',
        'neutral' => 'text-ink-muted',
    ];

    $dotClasses = [
        'healthy' => 'bg-emerald-500',
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber',
        'danger' => 'bg-red-600',
        'neutral' => 'bg-ink-muted',
    ];

    $iconFor = fn (array $widget) => 'heroicon-o-' . ($widget['icon'] ?? 'squares-2x2');
    $colSpanFor = fn (array $meta) => match ((int) ($meta['col_span'] ?? 1)) {
        2 => 'lg:col-span-2',
        3 => 'lg:col-span-3',
        4 => 'lg:col-span-4',
        default => 'lg:col-span-1',
    };
    $statusText = function ($status): string {
        if ($status instanceof \BackedEnum) {
            return method_exists($status, 'label') ? $status->label() : $status->value;
        }

        return ucwords(str_replace('_', ' ', (string) $status));
    };
@endphp

@section('header_actions')
    <button type="button" class="bp-btn-outline" x-data @click="$dispatch('toggle-dashboard-settings')">
        <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
        Widgets
    </button>
@endsection

@section('content')
<div
    x-data="dashboardPreferences(@json($preferences))"
    @toggle-dashboard-settings.window="settingsOpen = !settingsOpen"
    class="space-y-8"
>
    <section
        x-show="settingsOpen"
        x-transition
        class="bp-card"
    >
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Console · Widget Matrix</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Dashboard widgets<span class="text-amber">.</span>
                </h2>
            </div>
            <button type="button" class="bp-btn-primary" @click="save()">
                <x-heroicon-o-check class="w-4 h-4" />
                Save Layout
            </button>
        </header>
        <div class="p-5">
            <p class="text-sm text-ink-muted mb-4">
                Toggle widgets and drag rows to reorder the dashboard. Hidden widgets stay available here.
            </p>
            <div x-ref="sortable" class="grid gap-2">
                <template x-for="widget in preferences" :key="widget.id">
                    <div class="flex items-center justify-between gap-4 border border-rule bg-paper px-4 py-3" :data-id="widget.id">
                        <label class="flex items-center gap-3 min-w-0">
                            <input type="checkbox" class="rounded-none border-rule text-amber focus:ring-amber" x-model="widget.visible">
                            <span class="font-mono text-xs uppercase tracking-[0.18em] text-ink truncate" x-text="widget.id.replaceAll('_', ' ')"></span>
                        </label>
                        <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-muted">Drag</span>
                    </div>
                </template>
            </div>
            <p x-show="message" x-text="message" class="mt-4 font-mono text-xs uppercase tracking-[0.18em] text-amber-ink"></p>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($visibleWidgets as $preference)
            @php
                $widget = $widgets[$preference['id']];
                $meta = $widget['meta'] ?? $preference;
                $colSpan = $colSpanFor($meta);
                $status = $widget['status'] ?? 'neutral';
                $statusClass = $statusClasses[$status] ?? 'text-ink-muted';
                $dotClass = $dotClasses[$status] ?? 'bg-ink-muted';
            @endphp

            <article class="bp-card p-5 {{ $colSpan }}" data-widget-id="{{ $preference['id'] }}">
                <header class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <p class="bp-spec text-ink-muted">{{ $widget['title'] ?? ucwords(str_replace('_', ' ', $preference['id'])) }}</p>
                        @isset($widget['value'])
                            <p class="mt-2 font-mono text-3xl font-bold text-ink tabular-nums">{{ $widget['value'] }}</p>
                        @endisset
                    </div>
                    @isset($widget['icon'])
                        <div class="p-2 bg-ivory-alt border border-rule shrink-0">
                            <x-dynamic-component :component="$iconFor($widget)" class="w-6 h-6 text-ink" />
                        </div>
                    @endisset
                </header>

                @isset($widget['change'])
                    <div class="flex items-center gap-2 font-mono text-xs {{ ($widget['change'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        <span class="w-2 h-2 rounded-full {{ ($widget['change'] ?? 0) >= 0 ? 'bg-emerald-500' : 'bg-red-600' }}"></span>
                        <span>{{ $widget['change'] }}% vs previous 30 days</span>
                    </div>
                @endisset

                @isset($widget['subtitle'])
                    <p class="mt-2 text-sm text-ink-muted">{{ $widget['subtitle'] }}</p>
                @endisset

                @if(($widget['title'] ?? '') === 'Sales Last 30 Days')
                    @php
                        $values = collect($widget['values'] ?? [])->map(fn ($value) => (float) $value);
                        $max = max($values->max() ?: 0, 1);
                        $labels = collect($widget['labels'] ?? []);
                    @endphp
                    <div class="mt-5 h-64 border border-rule bg-ivory-alt p-4 flex items-end gap-2">
                        @forelse($values as $index => $value)
                            <div class="flex-1 min-w-2 bg-ink/15 hover:bg-amber/30 transition-colors relative" style="height: {{ max(4, ($value / $max) * 100) }}%">
                                <span class="sr-only">{{ $labels[$index] ?? 'Day' }}: {{ format_money($value) }}</span>
                            </div>
                        @empty
                            <div class="m-auto text-center text-sm text-ink-muted">No sales in the last 30 days.</div>
                        @endforelse
                    </div>
                    <div class="mt-3 flex justify-between font-mono text-xs text-ink-muted">
                        <span>{{ $labels->first() ?? now()->subDays(30)->format('Y-m-d') }}</span>
                        <span>{{ $labels->last() ?? now()->format('Y-m-d') }}</span>
                    </div>
                @elseif(($widget['type'] ?? null) === 'bar')
                    @php
                        $items = collect($widget['data'] ?? []);
                        $max = max((int) $items->max('count'), 1);
                    @endphp
                    <div class="mt-4 space-y-3">
                        @forelse($items as $item)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs font-mono text-ink-muted">
                                    <span class="truncate">{{ $item->query ?? 'Unknown' }}</span>
                                    <span>{{ $item->count }}</span>
                                </div>
                                <div class="mt-1 h-2 bg-ivory-alt border border-rule">
                                    <div class="h-full bg-amber" style="width: {{ ((int) $item->count / $max) * 100 }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No search activity yet.</p>
                        @endforelse
                    </div>
                @elseif(isset($widget['checks']))
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($widget['checks'] as $check)
                            @php
                                $checkStatus = $check['status'] ?? 'neutral';
                                $checkClass = $statusClasses[$checkStatus] ?? 'text-ink-muted';
                                $checkDot = $dotClasses[$checkStatus] ?? 'bg-ink-muted';
                            @endphp
                            <div class="flex items-center justify-between gap-3 border border-rule bg-ivory-alt px-3 py-2">
                                <span class="text-sm text-ink-muted">{{ $check['label'] }}</span>
                                <span class="flex items-center gap-2 font-mono text-xs {{ $checkClass }}">
                                    <span class="w-2 h-2 rounded-full {{ $checkDot }}"></span>
                                    {{ strtoupper($checkStatus) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif(isset($widget['alerts']))
                    <div class="space-y-3">
                        @forelse($widget['alerts'] as $alert)
                            <div class="border border-rule bg-ivory-alt px-3 py-2 text-sm text-ink">
                                <span class="font-mono text-xs uppercase tracking-[0.18em] text-amber-ink">{{ $alert['type'] ?? 'info' }}</span>
                                <p class="mt-1">{{ $alert['message'] }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No active alerts.</p>
                        @endforelse
                    </div>
                @elseif(isset($widget['items']))
                    <div class="space-y-3">
                        @forelse($widget['items'] as $item)
                            <div class="border-b border-rule pb-3 last:border-b-0 last:pb-0">
                                @if($item instanceof \App\Models\Order)
                                    <a href="{{ route('admin.orders.show', $item) }}" class="font-mono text-sm text-ink hover:text-amber-ink hover:underline">
                                        #{{ $item->order_number }}
                                    </a>
                                    <div class="mt-1 flex items-center justify-between gap-3 text-xs text-ink-muted">
                                        <span>{{ $item->shipping_name ?? $item->guest_email ?? 'Guest' }}</span>
                                        <span class="font-mono">{{ format_money($item->grand_total) }}</span>
                                    </div>
                                @elseif($item instanceof \App\Models\PartInquiry)
                                    <p class="font-mono text-sm text-ink">{{ $item->oem_number }}</p>
                                    <p class="mt-1 text-xs text-ink-muted">{{ $item->email }} · {{ $statusText($item->status) }}</p>
                                @elseif($item instanceof \App\Models\ContactMessage)
                                    <p class="font-medium text-ink">{{ $item->name }}</p>
                                    <p class="mt-1 text-xs text-ink-muted">{{ $item->email }} · {{ $statusText($item->status) }}</p>
                                @elseif($item instanceof \App\Models\ActivityLog)
                                    <p class="text-sm text-ink">{{ $item->description ?? $item->action ?? 'Activity recorded' }}</p>
                                    <p class="mt-1 font-mono text-xs text-ink-muted">{{ optional($item->created_at)->diffForHumans() }}</p>
                                @else
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm text-ink">{{ $item->query ?? $item->name ?? 'Record' }}</span>
                                        <span class="font-mono text-xs text-ink-muted">{{ $item->count ?? '' }}</span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No records yet.</p>
                        @endforelse
                    </div>
                @elseif(isset($widget['data']))
                    <div class="space-y-3">
                        @forelse($widget['data'] as $row)
                            @php
                                $rowStatus = $row->status ?? 'Unknown';
                            @endphp
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-ink-muted">{{ $statusText($rowStatus) }}</span>
                                <span class="font-mono text-ink">{{ $row->count ?? 0 }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-ink-muted">No distribution data yet.</p>
                        @endforelse
                    </div>
                @elseif(! isset($widget['value']))
                    <p class="text-sm text-ink-muted">No data available yet.</p>
                @endif
            </article>
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
<script>
    function dashboardPreferences(initialPreferences) {
        return {
            settingsOpen: false,
            preferences: initialPreferences,
            message: '',
            init() {
                this.$nextTick(() => {
                    if (!this.$refs.sortable || !window.Sortable) {
                        return;
                    }

                    window.Sortable.create(this.$refs.sortable, {
                        animation: 150,
                        onEnd: () => {
                            const order = Array.from(this.$refs.sortable.children).map((el) => el.dataset.id);
                            this.preferences = order
                                .map((id) => this.preferences.find((widget) => widget.id === id))
                                .filter(Boolean);
                        },
                    });
                });
            },
            save() {
                fetch('{{ route('admin.dashboard.preferences.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ preferences: this.preferences }),
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Dashboard layout could not be saved.');
                        }

                        return response.json();
                    })
                    .then((payload) => {
                        this.message = payload.message || 'Dashboard preferences saved.';
                    })
                    .catch((error) => {
                        this.message = error.message;
                    });
            },
        };
    }
</script>
@endpush
@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@php
    $visibleWidgets = collect($widgets ?? [])->reject(fn ($widget) => $widget['hidden'] ?? false);

    $kpiWidgets = $visibleWidgets->only(['total_orders', 'total_revenue', 'total_customers', 'total_products']);
    $mainWidgets = $visibleWidgets->except(['total_orders', 'total_revenue', 'total_customers', 'total_products']);

    $colSpanClass = function (array $widget): string {
        return match ((int) data_get($widget, 'meta.col_span', 1)) {
            2 => 'xl:col-span-2',
            3 => 'xl:col-span-3',
            4 => 'xl:col-span-4',
            default => 'xl:col-span-1',
        };
    };

    $statusClass = function (?string $status): string {
        return match ($status) {
            'success', 'healthy' => 'text-emerald-600',
            'warning' => 'text-amber-ink',
            'danger', 'failed' => 'text-red-600',
            default => 'text-ink-muted',
        };
    };

    $dotClass = function (?string $status): string {
        return match ($status) {
            'success', 'healthy' => 'bg-emerald-500',
            'warning' => 'bg-amber',
            'danger', 'failed' => 'bg-red-600',
            default => 'bg-ink-muted',
        };
    };

    $changeLabel = function ($change): string {
        if (! is_numeric($change)) {
            return 'No trend data';
        }

        $prefix = $change > 0 ? '+' : '';

        return $prefix . $change . '% vs previous 30 days';
    };

    $maxChartValue = function ($values): float {
        $max = collect($values)->map(fn ($value) => (float) $value)->max();

        return $max && $max > 0 ? $max : 1.0;
    };
@endphp

@section('header_actions')
    <button
        type="button"
        class="bp-btn-outline"
        x-data
        @click="$dispatch('toggle-dashboard-preferences')"
    >
        <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
        Widgets
    </button>
@endsection

@section('content')
<div
    class="space-y-8"
    x-data="dashboardPreferences({{ Js::from($preferences ?? []) }})"
    x-init="init()"
    @toggle-dashboard-preferences.window="panelOpen = !panelOpen"
>
    <section
        x-show="panelOpen"
        x-transition
        class="bp-card p-5"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="bp-spec text-amber-ink">§ Dashboard · Layout</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Widget Preferences<span class="text-amber">.</span>
                </h2>
                <p class="mt-2 text-sm text-ink-muted">
                    Toggle widgets and drag rows to reorder the admin dashboard.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <p x-show="saveMessage" class="font-mono text-xs text-emerald-600" x-text="saveMessage"></p>
                <button type="button" class="bp-btn-primary" @click="save()" :disabled="saving">
                    <span x-text="saving ? 'Saving...' : 'Save Layout'"></span>
                </button>
            </div>
        </div>

        <div x-ref="widgetList" class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="widget in preferences" :key="widget.id">
                <div class="flex items-center justify-between border border-rule bg-ivory-alt px-4 py-3" :data-widget-id="widget.id">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" class="rounded-none border-rule text-amber focus:ring-amber" x-model="widget.visible">
                        <span class="font-mono text-xs font-bold uppercase tracking-[0.16em] text-ink" x-text="label(widget.id)"></span>
                    </label>
                    <x-heroicon-o-bars-2 class="h-4 w-4 text-ink-muted" />
                </div>
            </template>
        </div>
    </section>

    @if($kpiWidgets->isNotEmpty())
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach($kpiWidgets as $id => $widget)
                <article class="bp-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="bp-spec">{{ $widget['title'] }}</p>
                            <p class="mt-2 font-mono text-3xl font-bold text-ink tabular-nums">{{ $widget['value'] }}</p>
                        </div>
                        <div class="border border-rule bg-ivory-alt p-2">
                            @switch($id)
                                @case('total_orders')
                                    <x-heroicon-o-shopping-bag class="h-6 w-6 text-ink" />
                                    @break
                                @case('total_revenue')
                                    <x-heroicon-o-banknotes class="h-6 w-6 text-ink" />
                                    @break
                                @case('total_customers')
                                    <x-heroicon-o-users class="h-6 w-6 text-ink" />
                                    @break
                                @default
                                    <x-heroicon-o-cube class="h-6 w-6 text-ink" />
                            @endswitch
                        </div>
                    </div>
                    <p class="mt-4 flex items-center gap-2 font-mono text-xs text-ink-muted">
                        <span class="h-2 w-2 rounded-full {{ ($widget['change'] ?? 0) >= 0 ? 'bg-emerald-500' : 'bg-red-600' }}"></span>
                        {{ $changeLabel($widget['change'] ?? null) }}
                    </p>
                </article>
            @endforeach
        </section>
    @endif

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-4">
        @foreach($mainWidgets as $id => $widget)
            <article class="bp-card {{ $colSpanClass($widget) }}">
                <header class="bp-card-header flex items-center justify-between gap-4">
                    <div>
                        <p class="bp-spec text-amber-ink">§ Widget · {{ str_replace('_', ' ', $id) }}</p>
                        <h2 class="mt-1 font-display text-lg font-bold text-ink tracking-[-0.02em]">
                            {{ $widget['title'] }}<span class="text-amber">.</span>
                        </h2>
                    </div>
                </header>

                <div class="p-5">
                    @if($id === 'sales_chart')
                        @php
                            $values = collect($widget['values'] ?? []);
                            $labels = collect($widget['labels'] ?? []);
                            $max = $maxChartValue($values);
                        @endphp
                        <div class="relative flex h-64 items-end gap-2 border border-rule bg-ivory-alt p-4">
                            <div class="absolute inset-0 bg-grid-ivory-fine opacity-50 pointer-events-none"></div>
                            @forelse($values as $index => $value)
                                @php $height = max(4, ((float) $value / $max) * 100); @endphp
                                <div class="relative z-10 flex flex-1 flex-col items-center justify-end gap-2">
                                    <div class="w-full bg-ink/15 hover:bg-amber/40 transition-colors" style="height: {{ $height }}%"></div>
                                    <span class="font-mono text-[9px] text-ink-muted">{{ optional(\Carbon\Carbon::parse($labels[$index] ?? now()))->format('d M') }}</span>
                                </div>
                            @empty
                                <div class="relative z-10 flex h-full w-full items-center justify-center text-sm text-ink-muted">
                                    No sales data for the last 30 days.
                                </div>
                            @endforelse
                        </div>
                    @elseif($id === 'health_strip')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($widget['checks'] ?? [] as $check)
                                <div class="flex items-center justify-between border border-rule bg-ivory-alt px-4 py-3">
                                    <span class="text-sm text-ink-muted">{{ $check['label'] }}</span>
                                    <span class="flex items-center gap-2 font-mono text-xs {{ $statusClass($check['status'] ?? null) }}">
                                        <span class="h-2 w-2 rounded-full {{ $dotClass($check['status'] ?? null) }}"></span>
                                        {{ strtoupper($check['status'] ?? 'unknown') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @elseif($id === 'recent_orders')
                        <div class="overflow-x-auto">
                            <table class="bp-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($widget['items'] ?? [] as $order)
                                        <tr>
                                            <td class="font-mono">
                                                <a href="{{ route('admin.orders.show', $order) }}" class="hover:text-amber-ink hover:underline">
                                                    {{ $order->order_number }}
                                                </a>
                                            </td>
                                            <td>{{ $order->shipping_name ?? $order->user?->email ?? 'Guest' }}</td>
                                            <td class="font-mono tabular-nums">{{ format_money($order->grand_total) }}</td>
                                            <td class="font-mono text-xs uppercase">{{ $order->status->label() }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-ink-muted">No recent orders.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif(isset($widget['alerts']))
                        <div class="space-y-3">
                            @forelse($widget['alerts'] as $alert)
                                <div class="border border-rule bg-ivory-alt px-4 py-3 text-sm text-ink">
                                    {{ $alert['message'] }}
                                </div>
                            @empty
                                <p class="text-sm text-ink-muted">No active alerts.</p>
                            @endforelse
                        </div>
                    @elseif(isset($widget['items']))
                        <div class="space-y-3">
                            @forelse($widget['items'] as $item)
                                <div class="border border-rule bg-ivory-alt px-4 py-3">
                                    <p class="text-sm font-medium text-ink">
                                        {{ $item->search_query ?? $item->oem_number ?? $item->email ?? $item->description ?? $item->job_name ?? 'Record #' . $item->id }}
                                    </p>
                                    @if(isset($item->count))
                                        <p class="mt-1 font-mono text-xs text-ink-muted">{{ $item->count }} hits</p>
                                    @elseif($item->created_at ?? null)
                                        <p class="mt-1 font-mono text-xs text-ink-muted">{{ $item->created_at->diffForHumans() }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-ink-muted">No records yet.</p>
                            @endforelse
                        </div>
                    @elseif(isset($widget['data']))
                        <div class="space-y-3">
                            @forelse($widget['data'] as $row)
                                <div class="flex items-center justify-between border border-rule bg-ivory-alt px-4 py-3">
                                    <span class="text-sm text-ink">{{ $row->status instanceof \BackedEnum ? $row->status->label() : ($row->query ?? $row->status ?? 'Item') }}</span>
                                    <span class="font-mono text-sm text-ink">{{ $row->count ?? $row->revenue ?? 0 }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-ink-muted">No data available.</p>
                            @endforelse
                        </div>
                    @else
                        <p class="font-mono text-2xl font-bold text-ink tabular-nums">{{ $widget['value'] ?? 'N/A' }}</p>
                        @if($widget['subtitle'] ?? null)
                            <p class="mt-2 text-sm text-ink-muted">{{ $widget['subtitle'] }}</p>
                        @endif
                        @if($widget['status'] ?? null)
                            <p class="mt-3 flex items-center gap-2 font-mono text-xs {{ $statusClass($widget['status']) }}">
                                <span class="h-2 w-2 rounded-full {{ $dotClass($widget['status']) }}"></span>
                                {{ strtoupper($widget['status']) }}
                            </p>
                        @endif
                    @endif
                </div>
            </article>
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
<script>
function dashboardPreferences(initialPreferences) {
    return {
        panelOpen: false,
        preferences: initialPreferences,
        saving: false,
        saveMessage: '',
        init() {
            this.$nextTick(() => {
                if (!this.$refs.widgetList || !window.Sortable) return;

                window.Sortable.create(this.$refs.widgetList, {
                    animation: 150,
                    onEnd: () => {
                        const orderedIds = Array.from(this.$refs.widgetList.querySelectorAll('[data-widget-id]'))
                            .map((item) => item.dataset.widgetId);

                        this.preferences = orderedIds
                            .map((id) => this.preferences.find((widget) => widget.id === id))
                            .filter(Boolean);
                    },
                });
            });
        },
        label(id) {
            return id.replaceAll('_', ' ');
        },
        async save() {
            this.saving = true;
            this.saveMessage = '';

            try {
                const response = await fetch('{{ route('admin.dashboard.preferences.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ preferences: this.preferences }),
                });

                if (!response.ok) {
                    throw new Error('Unable to save dashboard preferences.');
                }

                this.saveMessage = 'Saved';
                window.setTimeout(() => window.location.reload(), 500);
            } catch (error) {
                this.saveMessage = error.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
