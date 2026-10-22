// cart.js — Full corrected version
// Used by index.php / category.php / menu.php for the overlay "Add to Cart" button
// The main add-to-cart flow goes through customizeModal.php → confirmAddToCart()
// This file handles: cart badge updates + the data-id overlay button (legacy path)

document.addEventListener('DOMContentLoaded', function() {

    // ── Legacy: buttons with data-id attribute (overlay hover button) ──
    document.querySelectorAll('.add-to-cart-btn[data-id]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = this.getAttribute('data-id');
            if (productId) {
                const card    = this.closest('.product-card');
                const qtySpan = card ? card.querySelector('.qty-value, .qty-display') : null;
                const quantity = qtySpan ? parseInt(qtySpan.textContent) || 1 : 1;
                addToCartDirect(productId, quantity);
            }
        });
    });

    function isUserLoggedIn() {
        if (typeof window.isLoggedIn !== 'undefined') return window.isLoggedIn === true;
        const body = document.body;
        if (body.dataset.loggedIn !== undefined)
            return body.dataset.loggedIn === 'true' || body.dataset.loggedIn === '1';
        return !!document.querySelector('.btn-logout');
    }

    // Direct add (no modal) — used only if button has data-id
    function addToCartDirect(productId, quantity) {
        quantity = Math.max(1, parseInt(quantity) || 1);
        if (!isUserLoggedIn()) { showToast('❌ Please login first to add items to cart'); return; }

        let cart = JSON.parse(localStorage.getItem('cart')) || [];

        // Sum ALL entries for this product
        const totalAlreadyInCart = cart
            .filter(item => item.id == productId)
            .reduce((sum, item) => sum + (parseInt(item.quantity) || 1), 0);

        const remaining = Math.max(0, 50 - totalAlreadyInCart);

        if (remaining === 0) {
            showToast('⚠️ Maximum 50 items for this product already in cart!');
            return;
        }

        const actualQty = Math.min(quantity, remaining);
        if (actualQty < quantity) {
            showToast(`⚠️ Only ${remaining} more allowed. Added ${actualQty} instead of ${quantity}.`);
        }

        // Merge with existing plain entry if possible
        const existingPlain = cart.find(item =>
            item.id == productId &&
            !item.spice_level &&
            !item.serve_time &&
            !item.bev_temp &&
            (!item.removes || Object.values(item.removes).every(v => !v)) &&
            (!item.addons  || Object.values(item.addons).every(v => !v))
        );

        if (existingPlain) {
            existingPlain.quantity = (parseInt(existingPlain.quantity) || 1) + actualQty;
        } else {
            cart.push({ id: productId, quantity: actualQty });
        }

        localStorage.setItem('cart', JSON.stringify(cart));
        localStorage.setItem('cartLastModified', Date.now().toString());
        updateCartBadge();

        if (actualQty === quantity) {
            showToast(`✅ Added ${actualQty} item(s) to cart!`);
        }
    }

    function showToast(message) {
        const existing = document.querySelector('.ft-cart-toast');
        if (existing) existing.remove();
        const toast = document.createElement('div');
        toast.className = 'ft-cart-toast';
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:var(--primary-color,#d4650f);color:white;padding:15px 25px;border-radius:5px;box-shadow:0 5px 15px rgba(0,0,0,0.3);z-index:99999;animation:slideUp 0.3s ease-out;max-width:90%;font-weight:600;';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    }
});

// ── Cart badge updater — called globally ──
function updateCartBadge() {
    try {
        const cart  = JSON.parse(localStorage.getItem('cart')) || [];
        const total = cart.reduce((sum, item) => sum + (parseInt(item.quantity) || 1), 0);
        document.querySelectorAll('.cart-badge, .cart-count, [data-cart-count]').forEach(el => {
            el.textContent = total;
            el.style.display = total > 0 ? '' : 'none';
        });
    } catch(e) {}
}

// Run on load
document.addEventListener('DOMContentLoaded', updateCartBadge);

// CSS for toast animation
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .ft-cart-toast {
            position: fixed; bottom: 20px; right: 20px;
            background: var(--primary-color, #d4650f); color: white;
            padding: 15px 25px; border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 99999; animation: ftSlideUp 0.3s ease-out;
            max-width: 90%; font-weight: 600;
        }
        @keyframes ftSlideUp {
            from { transform: translateY(100px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
    `;
    document.head.appendChild(style);
})();