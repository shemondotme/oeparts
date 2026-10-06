<x-filament-panels::page>
    @php
        $devices = $this->devices();
        $topics = $this->availableTopics()->groupBy('group');
    @endphp

    <x-filament::section heading="My devices" description="Browsers and phones that receive your alerts. Add this one from the bell icon in the top bar.">
        @if ($devices->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No device has alerts turned on yet. Click the bell icon in the top bar and choose “Enable alerts on this device”.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10" data-testid="alert-devices">
                @foreach ($devices as $device)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <div class="font-medium text-gray-950 dark:text-white">{{ $device->device_label ?: 'Device' }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Added {{ $device->created_at?->diffForHumans() }}
                                @if ($device->last_success_at)
                                    · last alert delivered {{ $device->last_success_at->diffForHumans() }}
                                @endif
                                @if ($device->failure_count > 0)
                                    · <span class="text-danger-600 dark:text-danger-400">{{ $device->failure_count }} recent failure(s)</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="testDevice({{ $device->id }})">Send test</x-filament::button>
                            <x-filament::button size="sm" color="danger" wire:click="removeDevice({{ $device->id }})" wire:confirm="Remove this device? It will stop receiving alerts.">Remove</x-filament::button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        <x-filament::section heading="How alerts reach me">
            <div class="space-y-4">
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="pushEnabled" class="fi-checkbox-input mt-1">
                    <span>
                        <span class="block font-medium text-gray-950 dark:text-white">Send alerts to my devices</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Master switch. Off = nothing is pushed to any of my devices (the bell in the panel still works).</span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="soundEnabled" class="fi-checkbox-input mt-1">
                    <span>
                        <span class="block font-medium text-gray-950 dark:text-white">Play a sound while the admin panel is open</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">Phones and computers play their own notification sound when the panel is closed.</span>
                    </span>
                </label>

                <div>
                    <label for="hideDetails" class="block font-medium text-gray-950 dark:text-white">Details on the lock screen</label>
                    <select id="hideDetails" wire:model="hideDetails" class="fi-select-input mt-1 block w-full max-w-sm rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                        <option value="default">Follow the site default</option>
                        <option value="hide">Hide customer and amount details</option>
                        <option value="show">Show full details</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">“Hide” shows only the kind of event (e.g. “New order placed”), not names, order totals or messages.</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Quiet hours" description="During quiet hours only urgent alerts come through. Everything else is held back and delivered as one summary when quiet hours end.">
            <div class="space-y-4">
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model.live="quietEnabled" class="fi-checkbox-input mt-1">
                    <span class="font-medium text-gray-950 dark:text-white">Use quiet hours</span>
                </label>

                @if ($quietEnabled)
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <label for="quietStart" class="block text-sm font-medium text-gray-950 dark:text-white">From</label>
                            <input id="quietStart" type="time" wire:model="quietStart" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            @error('quietStart') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="quietEnd" class="block text-sm font-medium text-gray-950 dark:text-white">Until</label>
                            <input id="quietEnd" type="time" wire:model="quietEnd" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            @error('quietEnd') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Times use the store timezone ({{ settings('general.timezone', config('app.timezone')) }}).</p>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section heading="What I want to be alerted about" description="Only the kinds your role can receive and that are switched on site-wide are listed. Urgent kinds ignore quiet hours.">
            <div class="space-y-6">
                @foreach ($topics as $group => $groupTopics)
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group }}</div>
                        <div class="space-y-3">
                            @foreach ($groupTopics as $topic)
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" wire:model="topicOn.{{ $topic->key }}" class="fi-checkbox-input mt-1">
                                    <span>
                                        <span class="block font-medium text-gray-950 dark:text-white">
                                            {{ $topic->label }}
                                            @if ($topic->isUrgent())
                                                <span class="ml-1 rounded bg-danger-50 px-1.5 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Urgent</span>
                                            @endif
                                        </span>
                                        @if ($topic->description)
                                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $topic->description }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div>
            <x-filament::button type="submit">Save preferences</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
