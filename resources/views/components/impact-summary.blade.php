@props([
    'changes' => [],
    'heading' => 'Impact summary',
])

<div class="space-y-2">
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $heading }}</p>
    @if (count($changes) > 5)
        <p class="text-xs text-gray-400 dark:text-gray-500">
            Showing 5 of {{ count($changes) }} affected records
        </p>
    @endif
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Record</th>
                <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Current</th>
                <th class="py-2 font-medium text-gray-500 dark:text-gray-400">New</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach (array_slice($changes, 0, 5) as $change)
                <tr>
                    <td class="py-2 pr-4 font-mono text-xs font-medium text-gray-900 dark:text-gray-100">
                        {{ $change['key'] ?? '—' }}
                    </td>
                    <td class="py-2 pr-4">
                        @if ($change['masked'] ?? false)
                            <span class="text-gray-400 dark:text-gray-500">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400 line-through">{{ Str::limit($change['old'] ?? '—', 40) }}</span>
                        @endif
                    </td>
                    <td class="py-2">
                        @if ($change['masked'] ?? false)
                            <span class="text-gray-400 dark:text-gray-500">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                        @else
                            <span class="text-success-600 dark:text-success-400">{{ Str::limit($change['new'] ?? '—', 40) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
