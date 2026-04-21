import './bootstrap';
import Alpine from 'alpinejs';
import NProgress from 'nprogress';
import Sortable from 'sortablejs';

window.Sortable = Sortable;

// Alpine.js for admin panel interactivity
window.Alpine = Alpine;
Alpine.start();

NProgress.configure({ showSpinner: false });

document.addEventListener('DOMContentLoaded', () => {
    NProgress.done();
});
