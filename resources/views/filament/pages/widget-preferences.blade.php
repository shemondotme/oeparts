@php
    use Filament\Support\View\Components\ToggleComponent;
    use Illuminate\Support\Arr;

    $firstSlug = array_key_first($this->groupedWidgets);

    // Native Filament toggle classes — themed by the panel (light + dark),
    // compiled in Filament's own CSS, so they never depend on a theme rebuild.
    $toggleOn = Arr::toCssClasses([
        'fi-toggle fi-toggle-on',
        ...\Filament\Support\get_component_color_classes(ToggleComponent::class, 'primary'),
    ]);
    $toggleOff = Arr::toCssClasses([
        'fi-toggle fi-toggle-off',
        ...\Filament\Support\get_component_color_classes(ToggleComponent::class, 'gray'),
    ]);
@endphp

<x-filament-panels::page>
    <div class="space-y-6" @if($firstSlug) x-data="{ tab: @js($firstSlug) }" @endif>
        {{-- Page title/subheading and the Back/Reset actions render in the
             native page header (WidgetPreferences::getHeaderActions). --}}

        {{-- Tabs --}}
        @if(!empty($this->groupedWidgets))
            <x-filament::tabs>
                @foreach($this->groupedWidgets as $slug => $group)
                    <x-filament::tabs.item
                        :alpine-active="'tab === \'' . $slug . '\''"
                        x-on:click="tab = '{{ $slug }}'"
                    >
                        {{ $group['label'] ?? 'Group' }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            @foreach($this->groupedWidgets as $slug => $group)
                <div x-show="tab === @js($slug)" x-cloak class="space-y-3">
                    @forelse($group['widgets'] ?? [] as $widget)
                        @php $isVisible = $this->visibility[$widget['id']] ?? false; @endphp
                        <div
                            wire:key="widget-pref-{{ $widget['id'] }}"
                            class="flex items-start gap-4 rounded-xl border p-4"
                            style="background: var(--color-bg-surface); border-color: var(--color-border-default);"
                        >
                            {{-- Native Filament toggle (server-rendered, Livewire-driven) --}}
                            <button
                                type="button"
                                role="switch"
                                aria-checked="{{ $isVisible ? 'true' : 'false' }}"
                                aria-label="Toggle {{ $widget['label'] }}"
                                wire:click="toggleWidget('{{ $widget['id'] }}')"
                                wire:loading.attr="disabled"
                                class="mt-0.5 shrink-0 {{ $isVisible ? $toggleOn : $toggleOff }}"
                            >
                                <div>
                                    <div aria-hidden="true"></div>
                                    <div aria-hidden="true"></div>
                                </div>
                            </button>

                            {{-- Widget info --}}
                            <div class="flex-1">
                                <button
                                    type="button"
                                    wire:click="toggleWidget('{{ $widget['id'] }}')"
                                    class="block cursor-pointer text-left text-sm font-medium"
                                    style="color: var(--color-text-primary);"
                                >
                                    {{ $widget['label'] }}
                                </button>
                                <p class="mt-1 text-sm" style="color: var(--color-text-secondary);">
                                    {{ $widget['description'] }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-sm" style="color: var(--color-text-secondary);">
                            No widgets in this group.
                        </p>
                    @endforelse
                </div>
            @endforeach
        @else
            <div class="rounded-xl border p-8 text-center" style="border-color: var(--color-border-default);">
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    No widgets available for your role.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
