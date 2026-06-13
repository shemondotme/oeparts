@props([])

<div
    x-data="{
        toasts: [],
        addToast(event) {
            const toast = {
                id: Date.now(),
                type: event.detail.type || 'success',
                message: event.detail.message,
                duration: event.detail.duration || 4000
            };
            this.toasts.push(toast);
            setTimeout(() => this.removeToast(toast.id), toast.duration);
        },
        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }"
    @toast.window="addToast($event)"
    class="fixed bottom-4 right-4 z-[200] flex flex-col gap-2 pointer-events-none"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            @click="removeToast(toast.id)"
            class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg cursor-pointer transition-all hover:shadow-xl max-w-sm"
            :style="{
                'background: var(--color-success-50, #f0fdf4); border: 1px solid var(--color-success-200, #bbf7d0); color: var(--color-success-800, #166534);': toast.type === 'success',
                'background: var(--color-danger-50, #fef2f2); border: 1px solid var(--color-danger-200, #fecaca); color: var(--color-danger-800, #991b1b);': toast.type === 'error',
                'background: var(--color-warning-50, #fffbeb); border: 1px solid var(--color-warning-200, #fde68a); color: var(--color-warning-800, #92400e);': toast.type === 'warning',
                'background: var(--color-info-50, #eff6ff); border: 1px solid var(--color-info-200, #bfdbfe); color: var(--color-info-800, #1e40af);': toast.type === 'info'
            }"
        >
            <template x-if="toast.type === 'success'">
                <svg class="w-5 h-5 flex-shrink-0" style="color: var(--color-success-500, #22c55e);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </template>
            <template x-if="toast.type === 'error'">
                <svg class="w-5 h-5 flex-shrink-0" style="color: var(--color-danger-500, #ef4444);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </template>
            <template x-if="toast.type === 'warning'">
                <svg class="w-5 h-5 flex-shrink-0" style="color: var(--color-warning-500, #f59e0b);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </template>
            <template x-if="toast.type === 'info'">
                <svg class="w-5 h-5 flex-shrink-0" style="color: var(--color-info-500, #3b82f6);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
            </template>
            <span x-text="toast.message" class="text-sm font-medium flex-1"></span>
            <button @click.stop="removeToast(toast.id)" class="ml-2 opacity-60 hover:opacity-100 transition-opacity">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
