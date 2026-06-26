@props([])

<style>
.shortcut-row:hover { background: var(--color-bg-inset) !important; }
</style>

<div
    x-data="{
        shortcuts: [
            { keys: ['⌘', 'K'], label: 'Command Palette', action: 'spotlight', section: 'Global' },
            { keys: ['⌘', 'N'], label: 'New Product', action: 'create-product', section: 'Commerce' },
            { keys: ['⌘', 'O'], label: 'View Orders', action: 'navigate-orders', section: 'Commerce' },
            { keys: ['⌘', '⇧', 'R'], label: 'Refund Requests', action: 'navigate-refunds', section: 'Commerce' },
            { keys: ['⌘', ','], label: 'Settings', action: 'navigate-settings', section: 'System' },
            { keys: ['⌘', '⇧', 'S'], label: 'System Health', action: 'navigate-health', section: 'System' },
            { keys: ['⌘', '⇧', 'M'], label: 'Messages', action: 'navigate-messages', section: 'Customers' },
            { keys: ['⌘', 'E'], label: 'Export CSV', action: 'export-csv', section: 'Actions' },
            { keys: ['?'], label: 'Show Shortcuts', action: 'show-help', section: 'Help' },
            { keys: ['Esc'], label: 'Close', action: 'close', section: 'Help' },
        ],
        showPanel: false,
        query: '',
        get filteredShortcuts() {
            if (!this.query) return this.shortcuts;
            return this.shortcuts.filter(s =>
                s.label.toLowerCase().includes(this.query.toLowerCase()) ||
                s.keys.join('').toLowerCase().includes(this.query.toLowerCase())
            );
        },
        get groupedShortcuts() {
            const groups = {};
            this.filteredShortcuts.forEach(s => {
                if (!groups[s.section]) groups[s.section] = [];
                groups[s.section].push(s);
            });
            return groups;
        },
        init() {
            window.addEventListener('open-keyboard-shortcuts', () => {
                this.showPanel = true;
                this.query = '';
            });

            window.addEventListener('keydown', (e) => {
                const isMeta = e.metaKey || e.ctrlKey;

                // Cmd+K → Spotlight
                if (isMeta && e.key === 'k') {
                    e.preventDefault();
                    this.dispatchEvent('spotlight');
                    return;
                }

                // Cmd+N → New Product
                if (isMeta && e.key === 'n') {
                    e.preventDefault();
                    this.navigate('/admin/products/create');
                    return;
                }

                // Cmd+O → Orders
                if (isMeta && e.key === 'o') {
                    e.preventDefault();
                    this.navigate('/admin/orders');
                    return;
                }

                // Cmd+Shift+R → Refunds
                if (isMeta && e.shiftKey && e.key === 'R') {
                    e.preventDefault();
                    this.navigate('/admin/refund-requests');
                    return;
                }

                // Cmd+, → Settings
                if (isMeta && e.key === ',') {
                    e.preventDefault();
                    this.navigate('/admin/settings/general-settings');
                    return;
                }

                // Cmd+Shift+S → Health
                if (isMeta && e.shiftKey && e.key === 'S') {
                    e.preventDefault();
                    this.navigate('/admin/health-check');
                    return;
                }

                // Cmd+Shift+M → Messages
                if (isMeta && e.shiftKey && e.key === 'M') {
                    e.preventDefault();
                    this.navigate('/admin/contact-messages');
                    return;
                }

                // ? → Show shortcuts panel
                if (e.key === '?' && !this.isInputFocused()) {
                    e.preventDefault();
                    this.showPanel = !this.showPanel;
                    this.query = '';
                }

                // Esc → Close panel
                if (e.key === 'Escape') {
                    this.showPanel = false;
                }
            });
        },
        isInputFocused() {
            const el = document.activeElement;
            return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable);
        },
        navigate(url) {
            window.location.href = url;
        },
        formatKey(key) {
            const map = { '⌘': '⌘', '⇧': '⇧', '⌥': '⌥', '⌃': '⌃' };
            return map[key] || key.toUpperCase();
        }
    }"
    x-show="showPanel"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[300] flex items-start justify-center pt-[15vh]"
    @keydown.escape.window="showPanel = false"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0" style="background: var(--color-bg-overlay); backdrop-filter: blur(8px);" @click="showPanel = false"></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-lg op-modal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); border-radius: var(--radius-lg); box-shadow: var(--shadow-5);">

        {{-- Search --}}
        <div class="flex items-center gap-3 px-4 py-3" style="border-bottom: 1px solid var(--color-border-subtle);">
            <svg class="w-4 h-4 shrink-0" style="color: var(--color-text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                type="text"
                x-model="query"
                placeholder="Search shortcuts..."
                class="flex-1 bg-transparent text-sm outline-none"
                style="color: var(--color-text-primary);"
                x-ref="searchInput"
                @keydown.escape="showPanel = false"
            />
            <kbd class="px-1.5 py-0.5 text-[9px] font-mono font-bold rounded"
                style="background: var(--color-bg-inset); border: 1px solid var(--color-border-default); color: var(--color-text-muted);">
                ESC
            </kbd>
        </div>

        {{-- Shortcuts --}}
        <div class="max-h-[50vh] overflow-y-auto p-2">
            <template x-if="filteredShortcuts.length === 0">
                <div class="py-8 text-center">
                    <p class="text-sm" style="color: var(--color-text-muted);">No shortcuts match your search.</p>
                </div>
            </template>

            <template x-for="(shortcuts, section) in groupedShortcuts" :key="section">
                <div class="mb-2">
                    <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest font-mono"
                        style="color: var(--color-text-muted);" x-text="section"></div>
                    <template x-for="shortcut in shortcuts" :key="shortcut.label">
                        <div class="shortcut-row flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer transition-colors duration-150"
                            style="color: var(--color-text-primary);"
                            @click="showPanel = false">
                            <span class="text-sm font-medium" x-text="shortcut.label"></span>
                            <div class="flex items-center gap-1">
                                <template x-for="key in shortcut.keys" :key="key">
                                    <kbd class="inline-flex items-center justify-center min-w-[22px] h-5 px-1 text-[10px] font-mono font-bold rounded"
                                        style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); color: var(--color-text-muted);"
                                        x-text="formatKey(key)"></kbd>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="px-4 py-2.5 flex items-center gap-4 text-[10px] font-mono"
            style="border-top: 1px solid var(--color-border-subtle); color: var(--color-text-muted);">
            <span class="flex items-center gap-1">
                <kbd class="px-1 py-0.5 rounded" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-default);">?</kbd>
                toggle
            </span>
            <span class="flex items-center gap-1">
                <kbd class="px-1 py-0.5 rounded" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-default);">⌘K</kbd>
                spotlight
            </span>
        </div>
    </div>
</div>
