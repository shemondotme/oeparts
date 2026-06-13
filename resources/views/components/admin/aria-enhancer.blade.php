@props([])

<div x-data="{
    init() {
        this.$nextTick(() => {
            // Sidebar navigation
            const sidebar = document.querySelector('.fi-sidebar');
            if (sidebar) {
                sidebar.setAttribute('role', 'navigation');
                sidebar.setAttribute('aria-label', 'Admin sidebar navigation');
            }

            // Main content
            const main = document.querySelector('.fi-main-ctn');
            if (main) {
                main.setAttribute('role', 'main');
                main.setAttribute('aria-label', 'Main content');
                main.id = 'main-content';
            }

            // Topbar
            const topbar = document.querySelector('.fi-topbar');
            if (topbar) {
                topbar.setAttribute('role', 'banner');
                topbar.setAttribute('aria-label', 'Admin top bar');
            }

            // Page header
            const header = document.querySelector('.fi-header');
            if (header) {
                header.setAttribute('role', 'heading');
                header.setAttribute('aria-level', '1');
            }

            // Widget grid
            const widgetGrid = document.querySelector('.fi-wi-content');
            if (widgetGrid) {
                widgetGrid.setAttribute('role', 'region');
                widgetGrid.setAttribute('aria-label', 'Dashboard widgets');
            }

            // Tables
            document.querySelectorAll('.fi-table').forEach((table, i) => {
                table.setAttribute('role', 'table');
                table.setAttribute('aria-label', 'Data table ' + (i + 1));
            });

            // Form sections
            document.querySelectorAll('.fi-fo-section').forEach((section, i) => {
                section.setAttribute('role', 'region');
                const heading = section.querySelector('.fi-fo-section-header-heading');
                if (heading) {
                    section.setAttribute('aria-label', heading.textContent.trim());
                }
            });

            // Modal dialogs
            document.querySelectorAll('.fi-modal').forEach(modal => {
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
            });

            // Badges with status
            document.querySelectorAll('.fi-badge').forEach(badge => {
                if (!badge.getAttribute('aria-label')) {
                    badge.setAttribute('aria-label', badge.textContent.trim());
                }
            });

            // Navigation items
            document.querySelectorAll('.fi-sidebar-item-label').forEach(label => {
                const item = label.closest('.fi-sidebar-item');
                if (item && item.querySelector('a')) {
                    item.querySelector('a').setAttribute('role', 'link');
                }
            });

            // Actions dropdown
            document.querySelectorAll('.fi-dropdown').forEach(dropdown => {
                const trigger = dropdown.querySelector('.fi-dropdown-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-haspopup', 'true');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });

            // Notifications
            const notifBell = document.querySelector('[\\@click=\"open = !open\"]');
            if (notifBell) {
                notifBell.setAttribute('aria-label', 'Notifications');
                notifBell.setAttribute('aria-haspopup', 'true');
            }
        });
    }
}" x-init="init()" class="hidden" aria-hidden="true"></div>
