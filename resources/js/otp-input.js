/**
 * OEMHub — OTP Input Alpine.js module
 *
 * Usage in Blade:
 *   <div x-data="otpInput(6)" @otp-verify.window="handleVerify($event.detail)">
 *     <template x-for="(digit, i) in digits" :key="i">
 *       <input type="tel" inputmode="numeric" maxlength="1"
 *              x-model="digits[i]"
 *              @input="onInput($event, i)"
 *              @keydown.backspace="onBackspace($event, i)"
 *              @paste.prevent="onPaste($event)"
 *              :id="'otp-' + i"
 *              class="..." />
 *     </template>
 *   </div>
 */
export default function otpInput(length = 6) {
    return {
        digits: Array(length).fill(''),
        length,

        get code() {
            return this.digits.join('');
        },

        get isComplete() {
            return this.code.length === this.length && this.digits.every(d => d !== '');
        },

        onInput(event, index) {
            const val = event.target.value.replace(/\D/g, '');
            this.digits[index] = val ? val.slice(-1) : '';

            if (val && index < this.length - 1) {
                this.$nextTick(() => {
                    document.getElementById('otp-' + (index + 1))?.focus();
                });
            }

            if (this.isComplete) {
                this.$dispatch('otp-complete', { code: this.code });
            }
        },

        onBackspace(event, index) {
            if (!this.digits[index] && index > 0) {
                this.digits[index - 1] = '';
                this.$nextTick(() => {
                    document.getElementById('otp-' + (index - 1))?.focus();
                });
            }
        },

        onPaste(event) {
            const text = (event.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, this.length);

            if (!text) return;

            text.split('').forEach((char, i) => {
                if (i < this.length) this.digits[i] = char;
            });

            this.$nextTick(() => {
                const focusIndex = Math.min(text.length, this.length - 1);
                document.getElementById('otp-' + focusIndex)?.focus();
            });

            if (text.length === this.length) {
                this.$dispatch('otp-complete', { code: text });
            }
        },

        reset() {
            this.digits = Array(this.length).fill('');
            this.$nextTick(() => {
                document.getElementById('otp-0')?.focus();
            });
        },
    };
}
