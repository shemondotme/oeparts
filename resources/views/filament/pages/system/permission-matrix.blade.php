<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-key class="w-5 h-5" style="color: var(--color-warning-500);" />
                        Permission Matrix
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Visual grid of roles × permissions. Toggle permissions by clicking the checkboxes. Changes apply immediately.
                    </p>
                </div>
            </div>
        </div>

        {{-- Matrix Table --}}
        @php
            $roles = $this->getRoles();
            $grouped = $this->getPermissionsGrouped();
        @endphp

        @if($roles->isEmpty() || empty($grouped))
            <div class="op-card p-8 text-center" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <x-heroicon-o-key class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                <p class="text-sm font-medium" style="color: var(--color-text-muted);">No roles or permissions found.</p>
            </div>
        @else
            <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                    <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                        <x-heroicon-o-table-cells class="w-4 h-4" />
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                        Role × Permission Matrix
                    </h3>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-border-default);">
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono sticky left-0 z-10" style="color: var(--color-text-muted); background: var(--color-bg-surface); min-width: 200px;">
                                    Permission
                                </th>
                                @foreach($roles as $role)
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest font-mono min-w-[120px]" style="color: var(--color-text-muted);">
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grouped as $module => $permissions)
                                {{-- Module Header --}}
                                <tr style="background: var(--color-bg-inset);">
                                    <td colspan="{{ $roles->count() + 1 }}" class="px-4 py-2">
                                        <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-accent-500);">
                                            {{ $module }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Permissions --}}
                                @foreach($permissions as $permission)
                                    <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                                        <td class="px-4 py-3 sticky left-0 z-10" style="background: var(--color-bg-surface);">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-xs font-medium" style="color: var(--color-text-primary);">
                                                    {{ $permission['action'] }}
                                                </span>
                                            </div>
                                            <div class="text-xs font-mono mt-0.5" style="color: var(--color-text-muted);">
                                                {{ $permission['name'] }}
                                            </div>
                                        </td>
                                        @foreach($roles as $role)
                                            <td class="px-4 py-3 text-center">
                                                @if($role->name === 'super_admin')
                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full" style="background: var(--color-success-600);">
                                                        <x-heroicon-o-check class="w-3.5 h-3.5 text-white" />
                                                    </span>
                                                @else
                                                    <button
                                                        wire:click="togglePermission({{ $role->id }}, {{ $permission['id'] }})"
                                                        class="inline-flex items-center justify-center w-6 h-6 rounded-full transition-all duration-200 op-focus-ring"
                                                        aria-pressed="{{ $this->hasPermission($role->id, $permission['id']) ? 'true' : 'false' }}"
                                                        aria-label="Toggle {{ $permission['name'] }} permission"
                                                        style="
                                                            @if($this->hasPermission($role->id, $permission['id']))
                                                                background: var(--color-success-600);
                                                            @else
                                                                background: var(--color-bg-inset);
                                                                border: 1px solid var(--color-border-subtle);
                                                            @endif
                                                        "
                                                    >
                                                        @if($this->hasPermission($role->id, $permission['id']))
                                                            <x-heroicon-o-check class="w-3.5 h-3.5 text-white" />
                                                        @else
                                                            <x-heroicon-o-x-mark class="w-3.5 h-3.5" style="color: var(--color-text-muted);" />
                                                        @endif
                                                    </button>
                                                    <span wire:loading wire:target="togglePermission" class="ml-2">
                                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                        </svg>
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
