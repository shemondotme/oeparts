import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import NProgress from 'nprogress';
import otpInput from './otp-input';
import countup from './countup';
import clipboard from './clipboard';

// Alpine.js — the ONLY JS interactivity layer (no Vue, React, Livewire, jQuery)
window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.plugin(focus);

// Shared store: OEM search results table / compact view (syncs filter bar + listing)
document.addEventListener('alpine:init', () => {
    let initial = 'table';
    try {
        initial = localStorage.getItem('oemhub_view') || 'table';
    } catch {
        /* ignore */
    }
    Alpine.store('oemSearchView', {
        view: initial,
        setView(v) {
            this.view = v;
            try {
                localStorage.setItem('oemhub_view', v);
            } catch {
                /* ignore */
            }
        },
    });
});

window.addEventListener('storage', (e) => {
    if (e.key !== 'oemhub_view' || !e.newValue || !window.Alpine) {
        return;
    }
    try {
        window.Alpine.store('oemSearchView').view = e.newValue;
    } catch {
        /* ignore */
    }
});

// Register Alpine data components globally
Alpine.data('otpInput', otpInput);
Alpine.data('countup', countup);
Alpine.data('clipboard', clipboard);

Alpine.start();

// NProgress — page loading bar (amber color configured in CSS)
NProgress.configure({ showSpinner: false });

document.addEventListener('DOMContentLoaded', () => {
    NProgress.done();
});

// Start NProgress on navigation link clicks
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const href = link.getAttribute('href');
    // Only for same-origin, non-anchor, non-JS links
    if (
        href &&
        !href.startsWith('#') &&
        !href.startsWith('javascript:') &&
        !href.startsWith('mailto:') &&
        !href.startsWith('tel:') &&
        !link.target
    ) {
        NProgress.start();
    }
});
