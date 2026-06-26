/**
 * Admin dashboard modular canvas — gridstack integration v2 (2026 Enterprise).
 *
 * Coexistence rules:
 * - The canvas container carries wire:ignore.self; Livewire never morphs the
 *   grid wrappers. Widgets inside are independent Livewire children.
 * - Initialized on page load AND livewire:navigated (SPA mode); destroyed on
 *   navigate-away to avoid stale instances.
 * - Layout persists only in edit mode, debounced, and only while the grid is
 *   in full 12-column mode (mobile single-column would corrupt x/w values).
 */
import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';

/* ── Brand-gradient fill plugin for Chart.js ────────────────────────────── */
// Datasets can include op_gradient: ['rgba(top)', 'rgba(bottom)'] to get a
// vertical canvas gradient instead of a flat fill colour.
const _opGradientPlugin = {
    id: 'op-gradient',
    afterLayout(chart) {
        chart.data.datasets.forEach((dataset) => {
            if (!dataset.op_gradient) return;
            const ctx = chart.ctx;
            const { top, bottom } = chart.chartArea;
            if (top === undefined) return;
            const grad = ctx.createLinearGradient(0, top, 0, bottom);
            grad.addColorStop(0, dataset.op_gradient[0]);
            grad.addColorStop(1, dataset.op_gradient[1]);
            dataset.backgroundColor = grad;
        });
    },
};

// Register only once — guard against HMR double-registration
if (window.Chart && !Chart.registry.plugins.get('op-gradient')) {
    Chart.register(_opGradientPlugin);
}

let grid = null;
let saveTimer = null;
let keyboardDragActive = false;
let keyboardDragItem = null;

function canvasEl() {
    return document.getElementById('dashboard-canvas');
}

/** Widget type constraints for bento grid validation. */
const WIDGET_CONSTRAINTS = {
    kpi:    { minW: 2, maxW: 6,  minH: 2 },
    chart:  { minW: 4, maxW: 12, minH: 4 },
    table:  { minW: 2, maxW: 12, minH: 2 },
    strip:  { minW: 12, maxW: 12, minH: 1 },
    header: { minW: 12, maxW: 12, minH: 2, maxH: 2 },
    widget: { minW: 2, maxW: 12, minH: 2 },
};

function getWidgetType(el) {
    return el.getAttribute('data-widget-type') || 'widget';
}

/* ── Number count-up on first paint ────────────────────────────────────── */
const countedNodes = new WeakSet();

function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function animateCountUp(el) {
    if (countedNodes.has(el)) return;
    countedNodes.add(el);

    const original = (el.textContent || '').trim();
    const match = original.match(/-?[\d.,]+/);
    if (!match) return;

    const raw = match[0];
    const value = parseFloat(raw.replace(/,/g, ''));
    if (!isFinite(value) || value === 0) return;
    if (prefersReducedMotion()) return;

    const prefix = original.slice(0, match.index);
    const suffix = original.slice(match.index + raw.length);
    const decimals = (raw.split('.')[1] || '').length;
    const hadThousands = raw.includes(',');
    const duration = 900;
    const start = performance.now();

    function frame(now) {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        if (t < 1) {
            const current = value * eased;
            let display = decimals > 0 ? current.toFixed(decimals) : String(Math.round(current));
            if (hadThousands) {
                const parts = display.split('.');
                parts[0] = Number(parts[0]).toLocaleString('en-US');
                display = parts.join('.');
            }
            el.textContent = prefix + display + suffix;
            requestAnimationFrame(frame);
        } else {
            el.textContent = original;
        }
    }

    requestAnimationFrame(frame);
}

function runCountUps() {
    document
        .querySelectorAll('#dashboard-canvas .fi-wi-stats-overview-stat-value, #dashboard-canvas [data-countup]')
        .forEach(animateCountUp);
}

function livewireComponent(el) {
    const root = el.closest('[wire\\:id]');
    if (!root || !window.Livewire) return null;
    return window.Livewire.find(root.getAttribute('wire:id'));
}

function triggerChartResizes() {
    document.querySelectorAll('[wire\\:id]').forEach((el) => {
        el.dispatchEvent(new CustomEvent('gridstack-resized', { detail: { grid } }));
    });
    if (window.dispatchEvent) {
        window.dispatchEvent(new Event('resize'));
    }
}

function persistLayout() {
    const el = canvasEl();
    if (!grid || !el) return;
    if (el.dataset.editMode !== '1') return;
    if (grid.getColumn() !== 12) return;

    const component = livewireComponent(el);
    if (!component) return;

    const items = grid.save(false).map((item) => ({
        id: item.id,
        x: item.x ?? 0,
        y: item.y ?? 0,
        w: item.w ?? 1,
        h: item.h ?? 1,
    }));

    component.call('saveLayout', items);
}

/** Enforce bento grid rules on resize/drag stop. */
function applyConstraints(el) {
    if (!grid || !el) return;

    const type = getWidgetType(el);
    const constraints = WIDGET_CONSTRAINTS[type] || WIDGET_CONSTRAINTS.widget;
    const node = el.gridstackNode;
    if (!node) return;

    let needsUpdate = false;

    if (node.w !== undefined) {
        if (constraints.maxW && node.w > constraints.maxW) {
            node.w = constraints.maxW;
            needsUpdate = true;
        }
        if (constraints.minW && node.w < constraints.minW) {
            node.w = constraints.minW;
            needsUpdate = true;
        }
    }

    if (node.h !== undefined) {
        if (constraints.maxH && node.h > constraints.maxH) {
            node.h = constraints.maxH;
            needsUpdate = true;
        }
        if (constraints.minH && node.h < constraints.minH) {
            node.h = constraints.minH;
            needsUpdate = true;
        }
    }

    if (needsUpdate) {
        grid.update(node, { w: node.w, h: node.h });
        el.classList.add('gs-constrained');
        setTimeout(() => el.classList.remove('gs-constrained'), 600);
    }
}

function initCanvas() {
    const el = canvasEl();
    if (!el || el.gridstack) return;

    const editing = el.dataset.editMode === '1';

    grid = GridStack.init(
        {
            column: 12,
            cellHeight: 84,
            margin: '20px 12px',
            float: false,
            staticGrid: !editing,
            animate: true,
            draggable: {
                handle: '.op-drag-handle',
            },
            columnOpts: {
                breakpoints: [
                    { w: 1280, c: 12 },
                    { w: 768, c: 6 },
                    { w: 0, c: 1 },
                ],
            },
        },
        el,
    );

    grid.on('change', () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persistLayout, 500);
        triggerChartResizes();
    });

    grid.on('resizestop', (event, el) => {
        applyConstraints(el);
    });

    grid.on('dragstop', (event, el) => {
        applyConstraints(el);
    });

    // Staggered fade-in animation
    setTimeout(() => {
        document.querySelectorAll('.grid-stack-item').forEach((item, i) => {
            item.style.setProperty('--stagger-delay', `${i * 100}ms`);
            item.classList.add('op-fade-in');
        });
    }, 50);

    // Staggered entry for Tab 1 KPI cards (fade-in-up, 50ms apart)
    const ccKpiIds = ['revenue_kpi', 'new_orders_kpi', 'pending_orders_kpi', 'parts_inquiry'];
    ccKpiIds.forEach((id, i) => {
        const inner = el.querySelector(`[gs-id="${id}"] .fi-wi`);
        if (inner) {
            inner.style.opacity = '0';
            inner.style.animation = `fade-in-up 0.3s ease-out ${100 + i * 60}ms forwards`;
        }
    });

    // Count-up the KPI numbers once the widgets have rendered.
    setTimeout(runCountUps, 200);

    // Keyboard drag support
    setupKeyboardSupport(el);
}

function destroyCanvas() {
    if (grid) {
        clearTimeout(saveTimer);
        grid.destroy(false);
        grid = null;
    }
}

/* ── Keyboard drag-and-drop support ────────────────────────────────────── */
function setupKeyboardSupport(el) {
    el.addEventListener('keydown', (e) => {
        if (!keyboardDragActive) return;
        if (!keyboardDragItem) return;

        const item = keyboardDragItem;
        const step = e.shiftKey ? 2 : 1;

        switch (e.key) {
            case 'ArrowUp':
                grid.update(item, { y: (item.gridstackNode?.y || 0) - step });
                e.preventDefault();
                break;
            case 'ArrowDown':
                grid.update(item, { y: (item.gridstackNode?.y || 0) + step });
                e.preventDefault();
                break;
            case 'ArrowLeft':
                grid.update(item, { x: Math.max(0, (item.gridstackNode?.x || 0) - step) });
                e.preventDefault();
                break;
            case 'ArrowRight':
                grid.update(item, { x: Math.min(11, (item.gridstackNode?.x || 0) + step) });
                e.preventDefault();
                break;
            case 'Enter':
            case 'Escape':
                keyboardDragActive = false;
                keyboardDragItem = null;
                item.classList.remove('gs-keyboard-drag');
                announcePosition(`${item.getAttribute('gs-id')} placed`);
                e.preventDefault();
                break;
        }
    });
}

function announcePosition(message) {
    const announcer = document.getElementById('op-aria-live')
        || (() => {
            const el = document.createElement('div');
            el.id = 'op-aria-live';
            el.setAttribute('aria-live', 'polite');
            el.setAttribute('aria-atomic', 'true');
            el.className = 'sr-only';
            document.body.appendChild(el);
            return el;
        })();
    announcer.textContent = message;
}

// Setup keyboard handlers on drag handles
document.addEventListener('click', (e) => {
    const handle = e.target.closest('.op-drag-handle');
    if (!handle || !grid) return;

    const item = handle.closest('.grid-stack-item');
    if (!item) return;

    const isEditing = canvasEl()?.dataset.editMode === '1';
    if (!isEditing) return;

    if (keyboardDragActive && keyboardDragItem === item) {
        keyboardDragActive = false;
        keyboardDragItem = null;
        item.classList.remove('gs-keyboard-drag');
        announcePosition(`${item.getAttribute('gs-id')} placed`);
    } else {
        keyboardDragActive = true;
        keyboardDragItem = item;
        item.classList.add('gs-keyboard-drag');
        announcePosition(`${item.getAttribute('gs-id')} picked up. Use arrow keys to move, Enter to place.`);
    }
});

document.addEventListener('DOMContentLoaded', initCanvas);
/* ── Blob file download (CSV / PDF) ─────────────────────────────────────── */
window.addEventListener('op:download-blob', (e) => {
    const { content, mime, filename } = e.detail ?? {};
    if (!content || !filename) return;
    const bytes = Uint8Array.from(atob(content), c => c.charCodeAt(0));
    const blob = new Blob([bytes], { type: mime || 'application/octet-stream' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
});
/* ── Chart PNG export ───────────────────────────────────────────────────── */
window.addEventListener('op:chart-export-png', (e) => {
    const { widgetId, filename } = e.detail ?? {};
    if (!widgetId || !filename) return;

    // Locate the gridstack item — widgetId may be a gs-id slug or a Livewire wire:id
    const widgetEl = document.querySelector(`[gs-id="${widgetId}"]`)
        ?? document.querySelector(`[wire\\:id="${widgetId}"]`)?.closest('[gs-id]')
        ?? document.querySelector(`[data-widget-id="${widgetId}"]`);
    if (!widgetEl) {
        console.warn('[op:chart-export-png] widget not found:', widgetId);
        return;
    }

    const canvas = widgetEl.querySelector('canvas');
    if (!canvas) {
        console.warn('[op:chart-export-png] no canvas in widget:', widgetId);
        return;
    }

    canvas.toBlob((blob) => {
        if (!blob) return;
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 'image/png');
});

document.addEventListener('livewire:navigated', () => {
    destroyCanvas();
    initCanvas();
});
document.addEventListener('livewire:navigate', destroyCanvas);

/* ── In-place tab switching (no SPA redirect) ───────────────────────────── */
// Show skeleton overlay immediately when a tab button is clicked.
document.addEventListener('click', (e) => {
    if (e.target.closest('.op-dashboard-tab')) {
        document.body.classList.add('op-tab-switching');
    }
});

// After Livewire has morphed the DOM, destroy the stale gridstack instance
// and reinitialise with the new widgets. Then remove the skeleton overlay.
window.addEventListener('dashboard-switched', () => {
    destroyCanvas();
    requestAnimationFrame(() => {
        initCanvas();
        // Brief delay so staggered fade-in CSS animations have a frame to start
        setTimeout(() => document.body.classList.remove('op-tab-switching'), 60);
    });
});

// Edit-mode toggle dispatched from the Dashboard Livewire page.
window.addEventListener('dashboard-edit-mode', (event) => {
    const el = canvasEl();
    if (!el) return;

    const enabled = Boolean(event.detail?.enabled ?? event.detail);
    el.dataset.editMode = enabled ? '1' : '0';

    if (!grid) initCanvas();
    if (grid) grid.setStatic(!enabled);

    if (enabled) {
        document.querySelectorAll('[wire\\:poll]').forEach((el) => {
            el.removeAttribute('wire:poll');
            el.setAttribute('data-poll-suspended', 'true');
        });
    } else {
        document.querySelectorAll('[data-poll-suspended="true"]').forEach((el) => {
            const interval = el.getAttribute('data-poll-interval') || '60s';
            el.setAttribute('wire:poll.' + interval, 'updateChartData');
            el.removeAttribute('data-poll-suspended');
        });
    }
});
