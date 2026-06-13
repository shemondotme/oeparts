<div
    x-data="{ hasError: false, errorInfo: null, dismissed: false }"
    x-init="
        window.addEventListener('error', (e) => {
            hasError = true;
            errorInfo = { message: e.message, source: e.filename, line: e.lineno };
        });
        window.addEventListener('unhandledrejection', (e) => {
            hasError = true;
            errorInfo = { message: e.reason?.message || 'Unknown error', source: 'promise' };
        });
    "
>
    @if(isset($slot))
        {{ $slot }}
    @endif

    <div
        x-show="hasError && !dismissed"
        x-transition
        class="fixed bottom-4 right-4 z-50 max-w-sm p-4 rounded-lg shadow-lg"
        style="background: var(--color-danger-500); color: white;"
    >
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-bold">JavaScript Error</p>
                <p class="text-xs opacity-75" x-text="errorInfo?.message"></p>
            </div>
            <button @click="dismissed = true" class="ml-3 opacity-75 hover:opacity-100" aria-label="Dismiss error">&times;</button>
        </div>
    </div>
</div>
