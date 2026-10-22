// Render products
function renderProducts() {
    const productsGrid = document.getElementById('productsGrid');
    const t = translations[currentLanguage];
    
    productsGrid.innerHTML = products.map(product => `
        <div class="product-card fade-in">
            <div class="product-image">
                ${product.emoji}
                <div class="product-overlay">
                    <button onclick="orderProduct(${product.id}, '${product.title}')">
                        <i class="fas fa-shopping-bag"></i> ${t['order-btn']}
                    </button>
                </div>
            </div>
            <div class="product-info">
                <div class="product-title">${product.title}</div>
                <div class="product-category">${product.category}</div>
                <div class="product-rating">
                    ${'<i class="fas fa-star"></i>'.repeat(Math.floor(product.rating))}
                    <span style="color: #999; font-size: 12px; margin-left: 5px;">${product.rating}</span>
                </div>
                <div class="product-price">
                    From <span class="price-tag">RM${product.price}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// Render special offers
function renderSpecialOffers() {
    const specialGrid = document.getElementById('specialGrid');
    const t = translations[currentLanguage];
    
    specialGrid.innerHTML = specialOffers.map(offer => `
        <div class="product-card fade-in">
            <div class="product-image" style="position: relative;">
                ${offer.emoji}
                <div style="position: absolute; top: 10px; right: 10px; background: var(--primary-color); padding: 5px 15px; border-radius: 5px; font-size: 12px; font-weight: bold;">${t['special-badge']}</div>
                <div class="product-overlay">
                    <button onclick="orderProduct(${offer.id}, '${offer.title}')">
                        <i class="fas fa-shopping-bag"></i> ${t['order-btn']}
                    </button>
                </div>
            </div>
            <div class="product-info">
                <div class="product-title">${offer.title}</div>
                <div class="product-category">${offer.category}</div>
                <div style="color: #999; font-size: 12px; margin: 10px 0;">Limited Time Offer</div>
                <div class="product-price">
                    From <span class="price-tag">RM${offer.price}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// Order product function
function orderProduct(productId, productTitle) {
    showToast(`🛒 Adding ${productTitle} to cart...`);
    setTimeout(() => {
        window.location.href = `checkout.php?productId=${productId}`;
    }, 1500);
}

// Show toast notification
function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', () => {
    // Initialize theme (light mode by default)
    const savedTheme = localStorage.getItem('foodTheme') || 'light';
    currentTheme = savedTheme;
    applyTheme(savedTheme);
    
    // Initialize language
    const savedLanguage = localStorage.getItem('foodLanguage') || 'en';
    currentLanguage = savedLanguage;
    setLanguage(savedLanguage);
    updateActiveLanguage(savedLanguage);
    renderProducts();
    renderSpecialOffers();
});