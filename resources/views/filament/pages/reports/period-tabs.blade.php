{{--
  Segmented pill period selector for report pages — matches the dashboard
  chart period control (chart-with-period.blade.php). Bound to the page's
  public $period via $set(); the report view re-mounts its widgets (keyed by
  $period) when it changes.
--}}
<div class="flex justify-end">
    <div class="flex items-center gap-0.5 rounded-lg p-1"
         style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
        @foreach (['1' => 'Today', '7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y'] as $value => $label)
            @php $active = (string) $period === (string) $value; @endphp
            <button
                type="button"
                wire:click="$set('period', '{{ $value }}')"
                class="op-focus-ring rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
                @style([
                    'background: var(--primary-600); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2);' => $active,
                    'color: var(--color-text-secondary);' => ! $active,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
