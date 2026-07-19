<x-filament-panels::page>
    @if($runningBackupId)
        <div wire:poll.2s="pollBackup"></div>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
