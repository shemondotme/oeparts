/**
 * OeParts — Countup Alpine.js module
 *
 * Animates a number from 0 to `target` when element scrolls into view.
 * Used in the stats section on the homepage.
 *
 * Usage in Blade:
 *   <span x-data="countup(10000)" x-text="display"></span>
 *   <span x-data="countup(500000, { suffix: '+', duration: 2000 })" x-text="display"></span>
 */
export default function countup(target, options = {}) {
    return {
        target,
        display: '0',
        started: false,

        prefix: options.prefix ?? '',
        suffix: options.suffix ?? '',
        duration: options.duration ?? 1500,
        separator: options.separator ?? ',',

        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.started) {
                        this.started = true;
                        this.animate();
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.3 });

            observer.observe(this.$el);
        },

        animate() {
            const start = performance.now();
            const step = (now) => {
                const elapsed = now - start;
                const progress = Math.min(elapsed / this.duration, 1);
                // Ease out cubic
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = Math.floor(eased * this.target);
                this.display = this.prefix + this.format(current) + this.suffix;

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    this.display = this.prefix + this.format(this.target) + this.suffix;
                }
            };
            requestAnimationFrame(step);
        },

        format(num) {
            if (!this.separator) return num.toString();
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, this.separator);
        },
    };
}
