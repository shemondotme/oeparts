/**
 * OeParts — Clipboard Alpine.js module
 *
 * Copies text to clipboard with visual feedback.
 * Used on bank transfer payment page (IBAN, BIC, reference).
 *
 * Usage in Blade:
 *   <div x-data="clipboard()">
 *     <span>LT12 3456 7890 1234 5678</span>
 *     <button @click="copy('LT12 3456 7890 1234 5678')" x-text="label"></button>
 *   </div>
 */
export default function clipboard(resetDelay = 2000) {
    return {
        copied: false,
        resetDelay,

        get label() {
            return this.copied ? '✓ Copied' : 'Copy';
        },

        async copy(text) {
            if (this.copied) return;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback for HTTP (local dev)
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }

                this.copied = true;
                this.$dispatch('clipboard-copied', { text });

                setTimeout(() => {
                    this.copied = false;
                }, this.resetDelay);
            } catch (err) {
                console.error('Clipboard copy failed:', err);
            }
        },
    };
}
