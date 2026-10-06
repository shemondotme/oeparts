<x-filament-panels::page>
    @php
        $stats = $this->stats();
        $problems = $this->recentProblems();
        $roleOptions = $this->roleOptions();
        $grouped = collect($topics)->groupBy('group');
    @endphp

    <div class="grid grid-cols-2 gap-4 md:grid-cols-5" data-testid="alert-stats">
        @foreach ([
            ['Devices registered', $stats['devices'].' ('.$stats['admins'].' admins)'],
            ['Delivered, last 24 h', $stats['sent']],
            ['Failed, last 24 h', $stats['failed']],
            ['Dead devices removed', $stats['expired']],
            ['Held for quiet hours', $stats['deferred']],
        ] as [$label, $value])
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-filament::section heading="Site-wide">
            <div class="space-y-4">
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="enabled" class="fi-checkbox-input mt-1">
                    <span>
                        <span class="block font-medium text-gray-950 dark:text-white">Push alerts to admin devices</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Master switch for the whole site. The bell inside the panel is not affected.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="hideDetails" class="fi-checkbox-input mt-1">
                    <span>
                        <span class="block font-medium text-gray-950 dark:text-white">Hide details on lock screens by default</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Pushes show only the kind of event, never customer names, totals or message text. Each admin can override this for themselves.</span>
                    </span>
                </label>
            </div>
        </x-filament::section>

        <x-filament::section heading="Notification kinds" description="New kinds appear here automatically the first time they are sent. Urgent kinds ignore each admin’s quiet hours and stay on screen until dismissed.">
            <div class="space-y-8">
                @foreach ($grouped as $group => $rows)
                    <div>
                        <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group }}</div>
                        <div class="space-y-4">
                            @foreach ($rows as $row)
                                @php $i = array_search($row['id'], array_column($topics, 'id')); @endphp
                                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10" wire:key="topic-{{ $row['id'] }}">
                                    <div class="grid gap-4 md:grid-cols-12 md:items-start">
                                        <div class="md:col-span-4">
                                            <input type="text" wire:model="topics.{{ $i }}.label" class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" aria-label="Name">
                                            @error('topics.'.$i.'.label') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                <code>{{ $row['key'] }}</code>
                                                @if ($row['is_auto']) · <span class="text-warning-600 dark:text-warning-400">discovered automatically</span> @endif
                                            </p>
                                            @if ($row['description'])
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['description'] }}</p>
                                            @endif
                                        </div>

                                        <div class="space-y-2 md:col-span-2">
                                            <label class="flex items-center gap-2 text-sm text-gray-950 dark:text-white">
                                                <input type="checkbox" wire:model="topics.{{ $i }}.push_enabled" class="fi-checkbox-input"> Push to devices
                                            </label>
                                            <label class="flex items-center gap-2 text-sm text-gray-950 dark:text-white">
                                                <input type="checkbox" wire:model="topics.{{ $i }}.sound_enabled" class="fi-checkbox-input"> Play sound
                                            </label>
                                        </div>

                                        <div class="md:col-span-3">
                                            <label class="block text-xs text-gray-500 dark:text-gray-400">Urgency</label>
                                            <select wire:model="topics.{{ $i }}.urgency" class="mt-1 block w-full min-w-[8rem] rounded-lg pe-8 border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                                <option value="normal">Normal</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </div>

                                        <div class="md:col-span-3">
                                            <label class="block text-xs text-gray-500 dark:text-gray-400">Who receives it <span class="opacity-70">(none ticked = all roles)</span></label>
                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                @foreach ($roleOptions as $roleName => $roleLabel)
                                                    <label class="flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                                                        <input type="checkbox" wire:model="topics.{{ $i }}.allowed_roles" value="{{ $roleName }}" class="fi-checkbox-input"> {{ $roleLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">Save</x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="sendMyTest">Send a test to my devices</x-filament::button>
        </div>
    </form>

    @if ($problems->isNotEmpty())
        <x-filament::section heading="Recent delivery problems" description="Devices the push service rejected. Dead ones are removed automatically.">
            <ul class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                @foreach ($problems as $problem)
                    <li class="py-2">
                        <span class="font-medium {{ $problem->status === 'expired' ? 'text-warning-600' : 'text-danger-600' }}">{{ ucfirst($problem->status) }}</span>
                        · {{ $problem->topic ?: 'n/a' }} · {{ $problem->created_at?->diffForHumans() }}
                        @if ($problem->error) · <span class="text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($problem->error, 140) }}</span> @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
