<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2"
                        style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-adjustments-horizontal class="w-5 h-5" style="color: var(--warning-500, #f59e0b);" />
                        Bulk Update Products
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted-on-accent, rgba(228, 228, 231, 0.72));">
                        Change price, stock, or details across every product matching a filter — not just
                        the rows on the current page. Always preview before applying.
                    </p>
                </div>
                <a href="{{ \App\Filament\Pages\Catalog\BulkUpdateLogPage::getUrl() }}"
                    class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider shrink-0"
                    style="border: 1px solid rgba(255, 255, 255, 0.2); color: var(--color-text-on-accent, #ffffff);">
                    <x-heroicon-o-document-check class="w-3.5 h-3.5" />
                    View Log
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <div class="op-card relative overflow-hidden p-6"
            style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="flex items-center justify-between mb-4">
                <div class="text-sm font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted, #6b7280);">
                    1. Filter Products
                </div>
                <button wire:click="resetFilters" type="button"
                    class="op-focus-ring text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">
                    Reset
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Manufacturer</label>
                    <select wire:model.live="manufacturerId"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <option value="">All manufacturers</option>
                        @foreach($this->manufacturerOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Condition</label>
                    <select wire:model.live="conditionId"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <option value="">All conditions</option>
                        @foreach($this->conditionOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Car Model</label>
                    <select wire:model.live="carModelId"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <option value="">All car models</option>
                        @foreach($this->carModelOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">OEM Number Contains</label>
                    <input type="text" wire:model.live.debounce.400ms="oemSearch" placeholder="e.g. 1K0-615"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Stock Status</label>
                    <select wire:model.live="stockFilter"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <option value="">All</option>
                        <option value="in">In Stock</option>
                        <option value="out">Out of Stock</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Visibility</label>
                    <select wire:model.live="activeFilter"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <option value="">All</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Price Min</label>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="priceMin"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Price Max</label>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="priceMax"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Added After</label>
                        <input type="date" wire:model.live="dateFrom"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Added Before</label>
                        <input type="date" wire:model.live="dateTo"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Action --}}
        <div class="op-card relative overflow-hidden p-6"
            style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
            <div class="text-sm font-bold uppercase tracking-widest font-mono mb-4" style="color: var(--color-text-muted, #6b7280);">
                2. Choose Update
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Action</label>
                    <select wire:model.live="actionType"
                        class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                        style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <option value="">Select an action…</option>
                        @foreach($this->availableActions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('actionType')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    @if(empty($this->availableActions()))
                        <p class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">You don't have permission to run any bulk update action.</p>
                    @endif
                </div>

                @if(in_array($actionType, ['price_increase', 'price_decrease'], true))
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Percentage</label>
                        <input type="number" min="0.01" max="100" step="0.01" wire:model="percentage" placeholder="e.g. 10"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                        @error('percentage')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    </div>
                @elseif($actionType === 'price_set')
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Fixed Price</label>
                        <input type="number" min="0" max="999999.99" step="0.01" wire:model="fixedPrice" placeholder="e.g. 49.99"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                        @error('fixedPrice')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    </div>
                @elseif($actionType === 'condition_set')
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">New Condition</label>
                        <select wire:model="newConditionId"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                            <option value="">Select a condition…</option>
                            @foreach($this->conditionOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('newConditionId')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    </div>
                @elseif($actionType === 'delivery_time_set')
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Delivery Time</label>
                        <input type="text" maxlength="50" wire:model="newDeliveryTime" placeholder="e.g. 3-5 days"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                        @error('newDeliveryTime')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    </div>
                @elseif($actionType === 'moq_set')
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">Minimum Order Qty</label>
                        <input type="number" min="1" step="1" wire:model="newMoq" placeholder="e.g. 1"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                        @error('newMoq')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                <button wire:click="runPreview" wire:loading.attr="disabled" wire:target="runPreview"
                    class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                    style="background: var(--primary-600, #2563eb); color: white;">
                    <x-heroicon-o-eye class="w-3.5 h-3.5" wire:loading.remove wire:target="runPreview" />
                    <svg wire:loading wire:target="runPreview" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Preview Changes
                </button>

                @if($actionType)
                    <button wire:click="downloadCsv" wire:loading.attr="disabled" wire:target="downloadCsv"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);">
                        <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                        Download CSV (all matches)
                    </button>
                @endif
            </div>
        </div>

        {{-- Preview --}}
        @if($preview)
            <div class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--primary-600, #2563eb);">
                <div class="text-sm font-bold uppercase tracking-widest font-mono mb-4" style="color: var(--color-text-muted, #6b7280);">
                    3. Preview & Confirm
                </div>

                @if($preview['count'] === 0)
                    <p class="text-sm" style="color: var(--color-text-muted, #6b7280);">No products match these filters. Nothing to update.</p>
                @else
                    <p class="text-sm mb-4" style="color: var(--color-text-primary, #111827);">
                        This will affect <span class="font-bold">{{ number_format($preview['count']) }}</span> product(s).
                        Showing the first {{ count($preview['samples']) }} as a sample — use "Download CSV" above for the full list.
                    </p>

                    @if($preview['count'] > 500)
                        <div class="mb-4 p-3 rounded-xl text-sm" style="background: #FEF2F2; border: 1px solid var(--danger-500, #dc2626); color: #7F1D1D;">
                            <strong>Large batch:</strong> this affects {{ number_format($preview['count']) }} products — well above typical scope.
                            Super admins will be emailed automatically once this is applied.
                        </div>
                    @endif

                    <div class="overflow-x-auto mb-4">
                        <table class="w-full text-xs">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);">
                                    <th class="text-left py-2 pr-3 font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">OEM Number</th>
                                    <th class="text-left py-2 pr-3 font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">Manufacturer</th>
                                    <th class="text-left py-2 pr-3 font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">Before → After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($preview['samples'] as $row)
                                    <tr style="border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);">
                                        <td class="py-2 pr-3 font-mono" style="color: var(--color-text-primary, #111827);">{{ $row['oem_number'] }}</td>
                                        <td class="py-2 pr-3" style="color: var(--color-text-primary, #111827);">{{ $row['manufacturer'] }}</td>
                                        <td class="py-2 pr-3 font-mono" style="color: var(--color-text-primary, #111827);">
                                            {{ $row['old'] }} → <span class="font-bold" style="color: var(--success-600, #16a34a);">{{ $row['new'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <label class="flex items-center gap-2 text-sm mb-3" style="color: var(--color-text-primary, #111827);">
                        <input type="checkbox" wire:model.live="confirmed" class="op-focus-ring rounded" />
                        I've reviewed the preview and want to apply this change to {{ number_format($preview['count']) }} product(s).
                    </label>

                    @if($preview['count'] > 500)
                        <label class="flex items-center gap-2 text-sm mb-4" style="color: #7F1D1D;">
                            <input type="checkbox" wire:model.live="largeBatchAck" class="op-focus-ring rounded" />
                            I understand this is a large batch ({{ number_format($preview['count']) }} products) and want to proceed anyway.
                        </label>
                    @endif

                    <button wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                        @disabled(! $confirmed || ($preview['count'] > 500 && ! $largeBatchAck))
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="background: var(--danger-500, #dc2626); color: white; {{ (! $confirmed || ($preview['count'] > 500 && ! $largeBatchAck)) ? 'opacity: 0.5; cursor: not-allowed;' : '' }}">
                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" wire:loading.remove wire:target="apply" />
                        <svg wire:loading wire:target="apply" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Apply to {{ number_format($preview['count']) }} Products
                    </button>
                @endif
            </div>
        @endif

        {{-- Result --}}
        @if($result)
            <div class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--success-600, #16a34a);">
                <div class="text-sm font-bold uppercase tracking-widest font-mono mb-2" style="color: var(--color-text-muted, #6b7280);">
                    Done
                </div>
                <p class="text-sm" style="color: var(--color-text-primary, #111827);">
                    {{ number_format($result['count']) }} product(s) updated. See the
                    <a href="{{ \App\Filament\Pages\Catalog\BulkUpdateLogPage::getUrl() }}" class="font-bold underline" style="color: var(--primary-600, #2563eb);">Bulk Update Log</a>
                    for the full audit record — you can revert this change from there if needed.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
