{{--
Toast Notification — Industrial Blueprint
Position: Bottom-Right · Stack: vertical
Types: success · error · warning · info
--}}
@php
    $flashToast = null;
    if (session('error')) {
        $flashToast = ['message' => session('error'), 'type' => 'error'];
    } elseif (session('success')) {
        $flashToast = ['message' => session('success'), 'type' => 'success'];
    } elseif (session('status')) {
        $flashToast = ['message' => session('status'), 'type' => 'info'];
    }
@endphp
<div
    x-data="toastComponent()"
    x-init="init()"
    class="fixed bottom-24 sm:bottom-6 right-6 sm:right-8 flex flex-col gap-3 pointer-events-none"
    style="z-index: 99999 !important;"
>
    {{-- Dismiss-all button (visible when 3+ toasts) --}}
    <template x-if="toasts.length >= 3">
        <div class="pointer-events-auto flex justify-end mb-0.5">
            <button @click="dismissAll()"
                    class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted hover:text-ink
                           bg-paper border border-ink px-3 py-1.5 bp-shadow-sm
                           transition-colors"
                    aria-label="Dismiss all notifications">
                Clear All · <span x-text="toasts.length"></span>
            </button>
        </div>
    </template>

    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 translate-x-8 translate-y-2"
            x-transition:enter-end="opacity-100 translate-x-0 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-8"
            @mouseenter="pauseToast(toast.id)"
            @mouseleave="resumeToast(toast.id)"
            @focusin="pauseToast(toast.id)"
            @focusout="if (!$el.contains($event.relatedTarget)) resumeToast(toast.id)"
            class="pointer-events-auto relative w-[360px] max-w-[calc(100vw-3rem)] bg-paper border border-ink
                   bp-shadow motion-reduce:transition-none"
            :role="toast.type === 'error' ? 'alert' : 'status'"
            :aria-live="toast.type === 'error' ? 'assertive' : 'polite'"
        >
            {{-- Type-coloured tick strip --}}
            <div
                class="h-1 w-full shrink-0"
                :class="{
                    'bg-amber': toast.type === 'success',
                    'bg-red-600': toast.type === 'error',
                    'bg-amber-ink': toast.type === 'warning',
                    'bg-ink': toast.type === 'info'
                }"
                aria-hidden="true"
            ></div>

            {{-- Corner register marks --}}
            <span class="pointer-events-none absolute top-2 left-2 w-2.5 h-2.5 border-l border-t border-rule-strong" aria-hidden="true"></span>
            <span class="pointer-events-none absolute top-2 right-2 w-2.5 h-2.5 border-r border-t border-rule-strong" aria-hidden="true"></span>
            <span class="pointer-events-none absolute bottom-2 left-2 w-2.5 h-2.5 border-l border-b border-rule-strong" aria-hidden="true"></span>
            <span class="pointer-events-none absolute bottom-2 right-2 w-2.5 h-2.5 border-r border-b border-rule-strong" aria-hidden="true"></span>

            <div class="relative px-4 py-4 flex items-start gap-3.5">
                {{-- Icon tile --}}
                <div
                    class="flex-shrink-0 w-10 h-10 border flex items-center justify-center"
                    :class="{
                        'border-ink bg-ivory-alt text-ink': toast.type === 'success',
                        'border-red-600 bg-red-50 text-red-700': toast.type === 'error',
                        'border-amber-ink bg-amber/10 text-amber-ink': toast.type === 'warning',
                        'border-ink bg-paper text-ink': toast.type === 'info'
                    }"
                >
                    <template x-if="toast.type === 'success'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M12 9v4m0 3.5h.01M4.062 19.5h15.876c1.54 0 2.502-1.667 1.732-3L13.732 4.5c-.77-1.333-2.694-1.333-3.464 0L2.33 16.5c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0 pt-0.5">
                    {{-- Doc header: TAG · TYPE --}}
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-block w-3 h-[2px]"
                              :class="{
                                  'bg-amber': toast.type === 'success',
                                  'bg-red-600': toast.type === 'error',
                                  'bg-amber-ink': toast.type === 'warning',
                                  'bg-ink': toast.type === 'info'
                              }"></span>
                        <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase"
                              :class="{
                                  'text-amber-ink': toast.type === 'success',
                                  'text-red-700': toast.type === 'error',
                                  'text-amber-ink': toast.type === 'warning',
                                  'text-ink-muted': toast.type === 'info'
                              }"
                              x-text="toast.title"></span>
                    </div>

                    {{-- Message --}}
                    <p class="text-[13px] font-semibold text-ink leading-snug tracking-tight" x-text="toast.message"></p>

                    {{-- context-specific action link --}}
                    <template x-if="toast.context === 'cart'">
                        <a href="{{ url('/'.app()->getLocale().'/cart') }}"
                           class="mt-2 inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase
                                  text-ink border-b border-amber pb-0.5 hover:text-amber-ink hover:border-ink transition-colors">
                            <span>View Cart</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="square" stroke-linejoin="miter" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </template>

                    {{-- generic action link --}}
                    <template x-if="toast.action">
                        <a :href="toast.action.url"
                           class="mt-2 inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase
                                  text-ink border-b border-amber pb-0.5 hover:text-amber-ink hover:border-ink transition-colors">
                            <span x-text="toast.action.label"></span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="square" stroke-linejoin="miter" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </template>
                </div>

                {{-- Close button --}}
                <button
                    @click="removeToast(toast.id)"
                    class="flex-shrink-0 w-7 h-7 -mt-0.5 border border-rule-strong text-ink-muted
                           hover:bg-ink hover:text-amber hover:border-ink transition-colors
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-1"
                    aria-label="Dismiss"
                >
                    <svg class="w-3.5 h-3.5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="square" stroke-linejoin="miter" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Bottom: progress leader line --}}
            <div class="h-[2px] w-full bg-rule overflow-hidden">
                <div
                    class="h-full transition-all duration-100 ease-linear"
                    :class="{
                        'bg-amber': toast.type === 'success',
                        'bg-red-600': toast.type === 'error',
                        'bg-amber-ink': toast.type === 'warning',
                        'bg-ink': toast.type === 'info'
                    }"
                    :style="{ width: toast.progress + '%' }"
                ></div>
            </div>
        </div>
    </template>
</div>

<script>
function toastComponent() {
    return {
        toasts: [],
        init() {
            window.addEventListener('toast', (e) => {
                const { message, type = 'info', title, duration = 4000, action } = e.detail;
                this.showToast(message, type, title, duration, null, action);
            });
            window.addEventListener('cart-toast', (e) => {
                const { productName, quantity } = e.detail;
                this.showCartToast(productName, quantity);
            });

            // Server-side flash messages (session('error')/'success'/'status') are
            // set by full-page redirects on views that don't render their own flash
            // banner — surfaced here as a toast so the message is never silently lost.
            const flash = @json($flashToast);
            if (flash) {
                this.showToast(flash.message, flash.type);
            }
        },
        defaultTitle(type) {
            switch (type) {
                case 'success': return 'OK · CONFIRMED';
                case 'error':   return 'ERR · FAILED';
                case 'warning': return 'ATT · NOTICE';
                case 'info':
                default:        return 'INFO · SYSTEM';
            }
        },
        showToast(message, type = 'info', title = null, duration = 4000, context = null, action = null) {
            const now = Date.now();
            if (this.toasts.length > 0) {
                const last = this.toasts[this.toasts.length - 1];
                if (last.message === message && (now - last.id < 1000)) return;
            }

            const id = now + Math.random();
            const toast = {
                id,
                message,
                type,
                title: title || this.defaultTitle(type),
                visible: true,
                progress: 100,
                duration,
                remaining: duration,
                paused: false,
                context,
                action,
            };
            this.toasts.push(toast);
            this.startProgress(id);
        },
        showCartToast(productName, quantity) {
            this.showToast(
                `${productName} × ${quantity} added to cart`,
                'success',
                'CART · UPDATED',
                5000,
                'cart'
            );
        },
        startProgress(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (!toast) return;
            const interval = 50;
            const step = 100 / (toast.duration / interval);
            const timer = setInterval(() => {
                const t = this.toasts.find(t => t.id === id);
                if (!t) { clearInterval(timer); return; }
                if (t.paused) return;
                t.progress -= step;
                if (t.progress <= 0) {
                    clearInterval(timer);
                    this.removeToast(id);
                }
            }, interval);
        },
        pauseToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) toast.paused = true;
        },
        resumeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) toast.paused = false;
        },
        removeToast(id) {
            const index = this.toasts.findIndex(t => t.id === id);
            if (index > -1) {
                this.toasts[index].visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        },
        dismissAll() {
            this.toasts.forEach(t => t.visible = false);
            setTimeout(() => {
                this.toasts = [];
            }, 300);
        }
    };
}
</script>
