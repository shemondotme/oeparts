{{--
Toast Notification System (Modern Glassmorphism Design)
Position: Bottom-Right
--}}
<div
    x-data="toastComponent()"
    x-init="init()"
    class="fixed bottom-8 right-8 flex flex-col gap-3 pointer-events-none"
    style="z-index: 99999 !important;"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-500 transform"
            x-transition:enter-start="opacity-0 translate-y-12 scale-90 blur-sm"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100 blur-0"
            x-transition:leave="transition ease-in duration-300 transform"
            x-transition:leave-start="opacity-100 scale-100 blur-0"
            x-transition:leave-end="opacity-0 translate-x-12 scale-95 blur-sm"
            @mouseenter="pauseToast(toast.id)"
            @mouseleave="resumeToast(toast.id)"
            class="pointer-events-auto min-w-[340px] max-w-[440px] overflow-hidden rounded-[24px] 
                   bg-white/80 backdrop-blur-xl 
                   shadow-[0_25px_50px_-12px_rgba(0,0,0,0.15),0_0_15px_rgba(0,0,0,0.02)] 
                   border border-white/40 ring-1 ring-black/5"
        >
            <div class="relative p-4 flex items-start gap-4">
                {{-- Type indicator (Icon Box) --}}
                <div 
                    class="flex-shrink-0 w-12 h-12 rounded-2xl flex items-center justify-center shadow-inner"
                    :class="{
                        'bg-emerald-500/10 text-emerald-600': toast.type === 'success',
                        'bg-red-500/10 text-red-600': toast.type === 'error',
                        'bg-amber-500/10 text-amber-600': toast.type === 'warning',
                        'bg-navy/10 text-navy': toast.type === 'info'
                    }"
                >
                    {{-- Success Icon --}}
                    <template x-if="toast.type === 'success'">
                        <div class="relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span class="absolute -inset-1 rounded-full bg-emerald-500/20 animate-ping"></span>
                        </div>
                    </template>
                    {{-- Error Icon --}}
                    <template x-if="toast.type === 'error'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </template>
                    {{-- Warning Icon --}}
                    <template x-if="toast.type === 'warning'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </template>
                    {{-- Info Icon --}}
                    <template x-if="toast.type === 'info'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </template>
                </div>

                {{-- Content Area --}}
                <div class="flex-1 min-w-0 py-1">
                    <h4 class="text-xs font-black uppercase tracking-widest mb-0.5 opacity-60"
                        :class="{
                            'text-emerald-800': toast.type === 'success',
                            'text-red-800': toast.type === 'error',
                            'text-amber-800': toast.type === 'warning',
                            'text-navy/80': toast.type === 'info'
                        }"
                        x-text="toast.title || (toast.type === 'success' ? 'Confirmed' : 'System Note')"></h4>
                    
                    <p class="text-[14px] font-bold text-navy leading-tight" x-text="toast.message"></p>
                    
                    {{-- Specialized view for cart-toast meta info if available --}}
                    <template x-if="toast.context === 'cart'">
                        <div class="mt-2 flex items-center gap-2">
                             <div class="px-2 py-0.5 rounded-full bg-navy/5 border border-navy/10 text-[10px] font-bold text-navy/60 uppercase tracking-tighter">
                                 Added to Shopping Bag
                             </div>
                        </div>
                    </template>
                </div>

                {{-- Close Button --}}
                <button
                    @click="removeToast(toast.id)"
                    class="flex-shrink-0 -mt-1 -mr-1 p-2 rounded-full text-navy/20 hover:text-navy/60 hover:bg-navy/5 transition-all duration-200"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Slim Progress Bar --}}
            <div class="px-5 pb-4">
                <div class="h-1 w-full bg-navy/5 rounded-full overflow-hidden">
                    <div
                        class="h-full transition-all duration-100 ease-linear rounded-full shadow-[0_0_8px_rgba(0,0,0,0.1)]"
                        :class="{
                            'bg-emerald-500': toast.type === 'success',
                            'bg-red-500': toast.type === 'error',
                            'bg-amber-500': toast.type === 'warning',
                            'bg-navy': toast.type === 'info'
                        }"
                        :style="{ width: toast.progress + '%' }"
                    ></div>
                </div>
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
                const { message, type = 'info', title, duration = 4000 } = e.detail;
                this.showToast(message, type, title, duration);
            });
            window.addEventListener('cart-toast', (e) => {
                const { productName, quantity } = e.detail;
                this.showCartToast(productName, quantity);
            });
        },
        showToast(message, type = 'info', title = null, duration = 4000, context = null) {
            const now = Date.now();
            if (this.toasts.length > 0) {
                const lastToast = this.toasts[this.toasts.length - 1];
                if (lastToast.message === message && (now - lastToast.id < 1000)) {
                    return;
                }
            }

            const id = now + Math.random();
            const toast = {
                id,
                message,
                type,
                title: title || (type === 'success' ? 'SUCCESS' : type.toUpperCase()),
                visible: true,
                progress: 100,
                duration,
                remaining: duration,
                paused: false,
                context: context
            };
            this.toasts.push(toast);
            this.startProgress(id);
        },
        showCartToast(productName, quantity) {
            this.showToast(
                `${productName} × ${quantity}`,
                'success',
                'PRODUCT ADDED',
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
                }, 500);
            }
        }
    };
}
</script>
