/**
 * Admin dashboard modular canvas — gridstack integration.
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

let grid = null;
let saveTimer = null;

function canvasEl() {
    return document.getElementById('dashboard-canvas');
}

function livewireComponent(el) {
    const root = el.closest('[wire\\:id]');
    if (!root || !window.Livewire) return null;

    return window.Livewire.find(root.getAttribute('wire:id'));
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

function initCanvas() {
    const el = canvasEl();
    if (!el || el.gridstack) return;

    const editing = el.dataset.editMode === '1';

    grid = GridStack.init(
        {
            column: 12,
            cellHeight: 90,
            margin: 8,
            float: false,
            staticGrid: !editing,
            animate: true,
            columnOpts: {
                breakpoints: [{ w: 768, c: 1 }],
            },
        },
        el,
    );

    grid.on('change', () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persistLayout, 500);
    });
}

function destroyCanvas() {
    if (grid) {
        clearTimeout(saveTimer);
        // false: keep DOM nodes — Livewire owns them.
        grid.destroy(false);
        grid = null;
    }
}

document.addEventListener('DOMContentLoaded', initCanvas);
document.addEventListener('livewire:navigated', () => {
    destroyCanvas();
    initCanvas();
});
document.addEventListener('livewire:navigate', destroyCanvas);

// Edit-mode toggle dispatched from the Dashboard Livewire page.
window.addEventListener('dashboard-edit-mode', (event) => {
    const el = canvasEl();
    if (!el) return;

    const enabled = Boolean(event.detail?.enabled ?? event.detail);
    el.dataset.editMode = enabled ? '1' : '0';

    if (!grid) initCanvas();
    if (grid) grid.setStatic(!enabled);
});
