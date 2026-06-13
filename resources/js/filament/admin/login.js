/**
 * OeParts Admin — Command Gate Login Interactivity
 * Handles: page-load animation, lifecycle diagram, mouse parallax, pulse syncer, submit state machine
 */

document.addEventListener('DOMContentLoaded', () => {
    initPageLoadAnimation();
    initParallax();
    initPulseSyncer();
    initSubmitStateMachine();

    // Clean up body class when SPA navigates away from the login page
    document.addEventListener('livewire:navigating', () => {
        document.body.classList.remove('op-login-animate');
    });
});

/* ── Page-load staggered animation ─────────────────────────────────────────── */

function initPageLoadAnimation() {
    if (prefersReducedMotion()) return;
    // Double RAF ensures the CSS invisible initial state has already painted
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.body.classList.add('op-login-animate');
        });
    });
}

/* ── Mouse parallax on banner ──────────────────────────────────────────────── */

function initParallax() {
    if (prefersReducedMotion()) return;

    const banner = document.querySelector('.op-login-banner');
    const hero   = document.getElementById('op-lc-hero');
    const grid   = document.getElementById('op-lc-grid');

    if (!banner || !hero || !grid) return;

    let rafId = null;
    let targetX = 0;
    let targetY = 0;
    let currentX = 0;
    let currentY = 0;

    banner.addEventListener('mousemove', (e) => {
        const rect = banner.getBoundingClientRect();
        targetX = ((e.clientX - rect.left) / rect.width  - 0.5);
        targetY = ((e.clientY - rect.top)  / rect.height - 0.5);
        if (!rafId) rafId = requestAnimationFrame(animateParallax);
    });

    banner.addEventListener('mouseleave', () => {
        targetX = 0;
        targetY = 0;
        if (!rafId) rafId = requestAnimationFrame(animateParallax);
    });

    function animateParallax() {
        currentX += (targetX - currentX) * 0.08;
        currentY += (targetY - currentY) * 0.08;

        hero.style.transform = `translate(${currentX * 14}px, ${currentY * 9}px)`;
        grid.style.transform = `translate(${currentX * -6}px, ${currentY * -4}px)`;

        const stillMoving = Math.abs(targetX - currentX) > 0.0005 || Math.abs(targetY - currentY) > 0.0005;
        rafId = stillMoving ? requestAnimationFrame(animateParallax) : null;
    }
}

/* ── Pulse "Last synced Xs ago" counter ────────────────────────────────────── */

function initPulseSyncer() {
    const el = document.getElementById('op-pulse-sync');
    if (!el) return;

    let seconds = 3;
    const interval = setInterval(() => {
        seconds += 3;
        if (seconds >= 30) seconds = 3;
        el.textContent = `Last synced ${seconds}s ago`;
    }, 3000);

    // Clean up interval when SPA navigates away
    document.addEventListener('livewire:navigating', () => clearInterval(interval), { once: true });
}

/* ── Submit button state machine ────────────────────────────────────────────── */

function initSubmitStateMachine() {
    const form = document.querySelector('.fi-simple-layout form');
    const btn  = form?.querySelector('[type="submit"]');

    if (!form || !btn) return;

    // Remove lingering error state on next attempt
    btn.addEventListener('click', () => {
        btn.classList.remove('op-btn-error');
    });

    // Livewire 3 hook API — fires on every component request/response cycle
    function attachLivewireHooks() {
        if (typeof window.Livewire === 'undefined') return;

        Livewire.hook('commit', ({ succeed, fail }) => {
            // Only intercept while the login form is still in the DOM
            if (!document.querySelector('.fi-simple-layout')) return;

            setLoading(btn, true);

            succeed(() => {
                // If the form is gone, a redirect navigation is underway — leave spinner on
                if (!document.querySelector('.fi-simple-layout form')) return;

                setLoading(btn, false);
                const hasErrors = form.querySelector('[role="alert"], .fi-fo-field-wrp-error-message');
                if (hasErrors) triggerErrorShake(btn);
            });

            fail(() => {
                setLoading(btn, false);
                triggerErrorShake(btn);
            });
        });
    }

    // Livewire 3 dispatches 'livewire:init' when the runtime is ready
    document.addEventListener('livewire:init', attachLivewireHooks);
    // Also attach immediately if Livewire is already running (e.g. after a warm navigate back)
    if (window.Livewire) attachLivewireHooks();
}

function setLoading(btn, loading) {
    if (loading) {
        btn.setAttribute('data-loading', '');
        btn.setAttribute('aria-disabled', 'true');
    } else {
        btn.removeAttribute('data-loading');
        btn.removeAttribute('aria-disabled');
    }
}

function triggerErrorShake(btn) {
    btn.classList.remove('op-btn-error');
    void btn.offsetWidth; // force reflow so animation restarts if triggered twice
    btn.classList.add('op-btn-error');
    btn.addEventListener('animationend', () => {
        btn.classList.remove('op-btn-error');
    }, { once: true });
}

/* ── Utility ────────────────────────────────────────────────────────────────── */

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
