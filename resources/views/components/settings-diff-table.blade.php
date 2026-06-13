@props([
    'changes' => [],
    'heading' => 'Changed settings',
])

<div class="space-y-2">
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $heading }}</p>
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Setting</th>
                <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Current</th>
                <th class="py-2 font-medium text-gray-500 dark:text-gray-400">New</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($changes as $key => $diff)
                <tr>
                    <td class="py-2 pr-4 font-mono text-xs font-medium text-gray-900 dark:text-gray-100">
                        {{ $key }}
                    </td>
                    <td class="py-2 pr-4">
                        @if ($diff['masked'] ?? false)
                            <span class="text-gray-400 dark:text-gray-500">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400 line-through">{{ Str::limit($diff['old'], 60) }}</span>
                        @endif
                    </td>
                    <td class="py-2">
                        @if ($diff['masked'] ?? false)
                            <span class="text-gray-400 dark:text-gray-500">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                        @else
                            <span class="text-success-600 dark:text-success-400">{{ Str::limit($diff['new'], 60) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
