@props([])

<div x-data="ariaEnhancer()" x-init="boot()" class="hidden" aria-hidden="true"></div>

<script>
function ariaEnhancer() {
    return {
        boot() {
            this.$nextTick(() => {
                const sidebar = document.querySelector('.fi-sidebar');
                if (sidebar) {
                    sidebar.setAttribute('role', 'navigation');
                    sidebar.setAttribute('aria-label', 'Admin sidebar navigation');
                }

                const main = document.querySelector('.fi-main-ctn');
                if (main) {
                    main.setAttribute('role', 'main');
                    main.setAttribute('aria-label', 'Main content');
                    main.id = 'main-content';
                }

                const topbar = document.querySelector('.fi-topbar');
                if (topbar) {
                    topbar.setAttribute('role', 'banner');
                    topbar.setAttribute('aria-label', 'Admin top bar');
                }

                const header = document.querySelector('.fi-header');
                if (header) {
                    header.setAttribute('role', 'heading');
                    header.setAttribute('aria-level', '1');
                }

                const widgetGrid = document.querySelector('.fi-wi-content');
                if (widgetGrid) {
                    widgetGrid.setAttribute('role', 'region');
                    widgetGrid.setAttribute('aria-label', 'Dashboard widgets');
                }

                document.querySelectorAll('.fi-table').forEach((table, i) => {
                    table.setAttribute('role', 'table');
                    table.setAttribute('aria-label', 'Data table ' + (i + 1));
                });

                document.querySelectorAll('.fi-fo-section').forEach((section) => {
                    section.setAttribute('role', 'region');
                    const heading = section.querySelector('.fi-fo-section-header-heading');
                    if (heading) {
                        section.setAttribute('aria-label', heading.textContent.trim());
                    }
                });

                document.querySelectorAll('.fi-modal').forEach(modal => {
                    modal.setAttribute('role', 'dialog');
                    modal.setAttribute('aria-modal', 'true');
                });

                document.querySelectorAll('.fi-badge').forEach(badge => {
                    if (!badge.getAttribute('aria-label')) {
                        badge.setAttribute('aria-label', badge.textContent.trim());
                    }
                });

                document.querySelectorAll('.fi-sidebar-item-label').forEach(label => {
                    const item = label.closest('.fi-sidebar-item');
                    if (item && item.querySelector('a')) {
                        item.querySelector('a').setAttribute('role', 'link');
                    }
                });

                document.querySelectorAll('.fi-dropdown').forEach(dropdown => {
                    const trigger = dropdown.querySelector('.fi-dropdown-trigger');
                    if (trigger) {
                        trigger.setAttribute('aria-haspopup', 'true');
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                });

                const notifBell = document.querySelector('[x-on\\:click]');
                if (notifBell && notifBell.textContent.trim() === '') {
                    if (!notifBell.getAttribute('aria-label')) {
                        notifBell.setAttribute('aria-label', 'Notifications');
                        notifBell.setAttribute('aria-haspopup', 'true');
                    }
                }
            });
        }
    };
}
</script>
