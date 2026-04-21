{{--
  Auth modal — Login / Register
  Opened by dispatching: $dispatch('open-auth-modal') or $dispatch('open-auth-modal', { tab: 'register' })
  Triggers OTP modal after register: $dispatch('open-otp-modal', { email, purpose: 'email_verify' })
--}}
<div
    x-data="{
        show: false,
        tab: 'login',
        loading: false,
        error: '',

        open(tab = 'login') {
            this.tab = tab;
            this.show = true;
            this.error = '';
            this.$nextTick(() => this.$refs.firstInput?.focus());
        },
        close() {
            this.show = false;
            this.error = '';
            this.loading = false;
        },
    }"
    @open-auth-modal.window="open($event.detail?.tab ?? 'login')"
    @keydown.escape.window="close()"
    x-cloak
>
    {{-- Backdrop with blur --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 z-50 bg-navy/60 backdrop-blur-sm"
    ></div>

    {{-- Modal panel --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close()"
    >
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden" role="dialog" aria-modal="true">

            {{-- Decorative background elements --}}
            <div class="absolute top-0 right-0 w-64 h-64 bg-amber/5 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-navy/5 rounded-full translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>

            {{-- Header with gradient --}}
            <div class="relative bg-gradient-to-r from-navy to-blue-600 px-8 pt-8 pb-6">
                {{-- Close button --}}
                <button @click="close()" class="absolute top-4 right-4 text-white/60 hover:text-white p-2 rounded-xl hover:bg-white/10 transition-all" aria-label="Close">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>

                {{-- Logo/Brand --}}
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-sm flex items-center justify-center border border-white/20">
                        <x-heroicon-o-key class="w-6 h-6 text-amber" />
                    </div>
                    <div>
                        <h2 class="font-display font-bold text-xl text-white">
                            <span x-show="tab === 'login'">Welcome Back</span>
                            <span x-show="tab === 'register'" x-cloak>Join OEMHub</span>
                        </h2>
                        <p class="text-white/60 text-xs">
                            <span x-show="tab === 'login'">Sign in to your account</span>
                            <span x-show="tab === 'register'" x-cloak>Create your free account</span>
                        </p>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="flex gap-2 mt-6">
                    <button
                        @click="tab = 'login'"
                        :class="tab === 'login' ? 'bg-white text-navy shadow-lg' : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white'"
                        class="flex-1 py-2.5 px-4 rounded-xl text-sm font-semibold transition-all duration-300"
                    >Sign in</button>
                    <button
                        @click="tab = 'register'"
                        :class="tab === 'register' ? 'bg-white text-navy shadow-lg' : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white'"
                        class="flex-1 py-2.5 px-4 rounded-xl text-sm font-semibold transition-all duration-300"
                    >Register</button>
                </div>
            </div>

            {{-- Error alert --}}
            <div x-show="error" x-transition class="mx-6 mt-6">
                <div class="flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                    <x-heroicon-o-exclamation-circle class="w-5 h-5 shrink-0 mt-0.5" />
                    <span x-text="error"></span>
                </div>
            </div>

            {{-- LOGIN FORM --}}
            <div x-show="tab === 'login'" class="px-8 py-6 relative z-10">
                <form
                    method="POST"
                    action="/login"
                    @submit.prevent="
                        loading = true; error = '';
                        fetch('/login', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ email: $refs.loginEmail.value, password: $refs.loginPassword.value })
                        })
                        .then(r => r.json())
                        .then(d => {
                            if(d.success) { window.location.reload(); }
                            else { error = d.message || 'Invalid credentials'; loading = false; }
                        })
                        .catch(() => { error = 'Something went wrong. Please try again.'; loading = false; });
                    "
                    class="space-y-5"
                >
                    <div>
                        <label class="block text-sm font-semibold text-navy mb-2" for="login-email">Email address</label>
                        <div class="relative">
                            <x-heroicon-o-envelope class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" />
                            <input
                                id="login-email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                x-ref="loginEmail"
                                x-ref.first="firstInput"
                                required
                                class="w-full rounded-xl border-2 border-gray-200 pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-amber transition-all"
                                placeholder="you@example.com"
                            >
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-semibold text-navy" for="login-password">Password</label>
                            <a href="/{{ app()->getLocale() }}/reset-password" class="text-xs text-amber-text hover:underline font-medium">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <x-heroicon-o-lock-closed class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" />
                            <input
                                id="login-password"
                                type="password"
                                autocomplete="current-password"
                                x-ref="loginPassword"
                                required
                                class="w-full rounded-xl border-2 border-gray-200 pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-amber transition-all"
                                placeholder="••••••••"
                            >
                        </div>
                    </div>

                    {{-- Honeypot --}}
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3.5 bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold rounded-xl hover:shadow-lg hover:shadow-amber/30 hover:scale-[1.02] disabled:opacity-60 disabled:hover:scale-100 transition-all duration-300"
                    >
                        <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="loading ? 'Signing in…' : 'Sign in'"></span>
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-muted">
                    Don't have an account?
                    <button @click="tab = 'register'" class="text-amber-text font-semibold hover:underline">Create one free</button>
                </p>
            </div>

            {{-- REGISTER FORM --}}
            <div x-show="tab === 'register'" x-cloak class="px-8 py-6 relative z-10">
                <form
                    method="POST"
                    action="/register"
                    @submit.prevent="
                        loading = true; error = '';
                        fetch('/register', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ name: $refs.regName.value, email: $refs.regEmail.value, password: $refs.regPassword.value, password_confirmation: $refs.regConfirm.value })
                        })
                        .then(r => r.json())
                        .then(d => {
                            if(d.success) {
                                close();
                                $dispatch('open-otp-modal', { email: $refs.regEmail.value, purpose: 'email_verify' });
                            } else {
                                error = d.message || Object.values(d.errors || {})[0]?.[0] || 'Registration failed';
                                loading = false;
                            }
                        })
                        .catch(() => { error = 'Something went wrong. Please try again.'; loading = false; });
                    "
                    class="space-y-5"
                >
                    <div>
                        <label class="block text-sm font-semibold text-navy mb-2" for="reg-name">Full name</label>
                        <div class="relative">
                            <x-heroicon-o-user class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" />
                            <input
                                id="reg-name"
                                type="text"
                                autocomplete="name"
                                x-ref="regName"
                                required
                                class="w-full rounded-xl border-2 border-gray-200 pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-amber transition-all"
                                placeholder="John Smith"
                            >
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-navy mb-2" for="reg-email">Email address</label>
                        <div class="relative">
                            <x-heroicon-o-envelope class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" />
                            <input
                                id="reg-email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                x-ref="regEmail"
                                required
                                class="w-full rounded-xl border-2 border-gray-200 pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-amber transition-all"
                                placeholder="you@example.com"
                            >
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-navy mb-2" for="reg-password">Password</label>
                        <div class="relative">
                            <x-heroicon-o-lock-closed class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" />
                            <input
                                id="reg-password"
                                type="password"
                                autocomplete="new-password"
                                x-ref="regPassword"
                                required
                                minlength="{{ settings('auth.customer_password_min', 8) }}"
                                class="w-full rounded-xl border-2 border-gray-200 pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-amber transition-all"
                                placeholder="Min {{ settings('auth.customer_password_min', 8) }} characters"
                            >
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-navy mb-2" for="reg-confirm">Confirm password</label>
                        <div class="relative">
                            <x-heroicon-o-lock-closed class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" />
                            <input
                                id="reg-confirm"
                                type="password"
                                autocomplete="new-password"
                                x-ref="regConfirm"
                                required
                                class="w-full rounded-xl border-2 border-gray-200 pl-12 pr-4 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-amber transition-all"
                                placeholder="••••••••"
                            >
                        </div>
                    </div>

                    {{-- Honeypot --}}
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3.5 bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold rounded-xl hover:shadow-lg hover:shadow-amber/30 hover:scale-[1.02] disabled:opacity-60 disabled:hover:scale-100 transition-all duration-300"
                    >
                        <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="loading ? 'Creating account…' : 'Create account'"></span>
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-muted">
                    Already have an account?
                    <button @click="tab = 'login'" class="text-amber-text font-semibold hover:underline">Sign in</button>
                </p>
            </div>

            {{-- Footer --}}
            <div class="px-8 pb-6 pt-2 text-center text-xs text-muted relative z-10">
                By continuing, you agree to our
                <a href="/{{ app()->getLocale() }}/terms-of-service" class="text-amber-text hover:underline font-medium">Terms</a>
                and
                <a href="/{{ app()->getLocale() }}/privacy-policy" class="text-amber-text hover:underline font-medium">Privacy Policy</a>.
            </div>
        </div>
    </div>
</div>
