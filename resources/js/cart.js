export default function cartData(initialCart, initialSummary, locale, routeUpdate, routeRemove, routePreview, routeCouponApply, routeCouponRemove) {
    function mapItem(item) {
        let itemName = item.product?.name;
        if (typeof itemName === 'object' && itemName !== null) {
            itemName = itemName[locale] || itemName['en'] || Object.values(itemName)[0];
        }
        return {
            id:             item.id,
            quantity:       item.quantity,
            price:          parseFloat(item.price_at_add),
            oldPrice:       item.old_price ? parseFloat(item.old_price) : null,
            priceChanged:   !!(item.old_price && Math.abs(parseFloat(item.old_price) - parseFloat(item.price_at_add)) > 0.01),
            priceBlocked:   item.block_checkout || false,
            oem_number:     item.product?.oem_number || item.oem_number,
            name:           itemName,
            condition_slug: item.condition_slug || item.product?.condition?.slug || 'new',
            condition_name: item.condition_name || item.product?.condition?.name || 'New',
            condition_bg:   item.condition_bg || item.product?.condition?.bg_color || '#DCFCE7',
            condition_text: item.condition_text || item.product?.condition?.text_color || '#16A34A',
            in_stock:       !!item.product?.is_in_stock,
            removing:       false,
        };
    }

    // Money values arrive from the server as DECIMAL strings ("297.00") because
    // prices are bcmath-computed. The Blade template calls .toFixed() and does
    // arithmetic on them, so coerce the numeric fields to real numbers here —
    // otherwise cartData() throws ("subtotal.toFixed is not a function") and the
    // whole cart component fails to initialise (blank cart page).
    function normalizeSummary(summary) {
        const out = { ...(summary || {}) };
        const numeric = [
            'subtotal', 'subtotal_excl_vat', 'vat_amount', 'grand_total',
            'coupon_discount', 'shipping', 'shipping_needed', 'free_shipping_threshold',
        ];
        for (const field of numeric) {
            if (out[field] !== undefined && out[field] !== null && out[field] !== '') {
                out[field] = parseFloat(out[field]) || 0;
            }
        }
        return out;
    }

    function firePriceChangeToast(summary) {
        if (!summary.price_changes?.length) return;
        const count = summary.price_changes.length;
        const oems = summary.price_changes
            .map(c => c.item?.oem_number || c.item?.product?.oem_number)
            .filter(Boolean)
            .join(', ');
        window.dispatchEvent(new CustomEvent('toast', {
            detail: {
                message: count > 1
                    ? `${count} items have changed in price: ${oems}`
                    : `1 item has changed in price: ${oems}`,
                type: 'warning',
                title: '§ PRICE · UPDATED',
                duration: 8000,
            }
        }));
    }

    return {
        cart:         { items: (initialCart?.items || []).map(mapItem) },
        summary:      normalizeSummary(initialSummary),
        loading:      false,
        errorMessage: '',
        couponCode:   '',
        couponMessage:'',
        couponError:  false,
        confirmOpen:  false,

        init() {
            if (this.summary.price_changes?.length > 0) {
                setTimeout(() => firePriceChangeToast(this.summary), 600);
            }
        },

        async applyCoupon() {
            if (!this.couponCode) return;
            this.couponError = false;
            this.couponMessage = 'Applying…';
            try {
                const res = await fetch(routeCouponApply, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ coupon_code: this.couponCode })
                });
                const data = await res.json();
                if (data.success) {
                    this.couponMessage = '';
                    this.couponCode = '';
                    this.summary = normalizeSummary(data.cart_summary);
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Promo code applied!', type: 'success' } }));
                } else {
                    this.couponError = true;
                    this.couponMessage = data.message || 'Invalid promo code';
                }
            } catch (e) {
                this.couponError = true;
                this.couponMessage = 'Connection error';
            }
            if (this.couponMessage) setTimeout(() => this.couponMessage = '', 4000);
        },

        async removeCoupon() {
            try {
                const res = await fetch(routeCouponRemove, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                const data = await res.json();
                if (data.success) {
                    this.summary = normalizeSummary(data.cart_summary);
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Coupon removed.', type: 'info' } }));
                }
            } catch (e) {
                this.showError('Error removing coupon');
            }
        },

        async incrementItem(itemId) {
            const item = this.cart.items.find(i => i.id === itemId);
            if (item && item.quantity < 99) await this.updateItem(itemId, item.quantity + 1);
        },

        async decrementItem(itemId) {
            const item = this.cart.items.find(i => i.id === itemId);
            if (item && item.quantity > 1) await this.updateItem(itemId, item.quantity - 1);
        },

        async updateItemQuantity(itemId, quantity) {
            const qty = Math.max(1, Math.min(99, parseInt(quantity) || 1));
            await this.updateItem(itemId, qty);
        },

        async updateItem(itemId, quantity) {
            try {
                const res = await fetch(`${routeUpdate}/${itemId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ quantity })
                });
                if (res.ok) {
                    await this.loadCart(false);
                } else {
                    this.showError('Error updating cart');
                }
            } catch (e) {
                this.showError('Connection error');
            }
        },

        async removeItem(itemId) {
            const item = this.cart.items.find(i => i.id === itemId);
            if (!item) return;
            item.removing = true;
            await new Promise(r => setTimeout(r, 300));
            try {
                const res = await fetch(`${routeRemove}/${itemId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                if (res.ok) {
                    await this.loadCart(false);
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Item removed from cart.', type: 'info' } }));
                } else {
                    item.removing = false;
                    this.showError('Error removing item');
                }
            } catch (e) {
                item.removing = false;
                this.showError('Connection error');
            }
        },

        async confirmClearCart() {
            this.confirmOpen = false;
            const ids = [...this.cart.items.map(i => i.id)];
            for (const id of ids) {
                await fetch(`${routeRemove}/${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
            }
            await this.loadCart(false);
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Cart cleared.', type: 'info' } }));
        },

        async loadCart(showToast = true) {
            this.loading = true;
            try {
                const res  = await fetch(routePreview);
                const data = await res.json();
                if (data.success) {
                    this.cart.items = (data.items || []).map(mapItem);
                    this.summary    = normalizeSummary(data.summary);
                    if (showToast && data.summary.price_changes?.length > 0) {
                        firePriceChangeToast(data.summary);
                    }
                    window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { count: data.summary.item_count } }));
                }
            } catch (e) {
                this.showError('Error loading cart');
            } finally {
                this.loading = false;
            }
        },

        showError(msg) {
            this.errorMessage = msg;
            setTimeout(() => { this.errorMessage = ''; }, 5000);
        },

        hasBlockedItems() {
            return this.cart.items.some(i => i.priceBlocked);
        }
    };
}
