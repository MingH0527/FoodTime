// ============================================
// TRANSLATIONS.JS - COMPLETE TRANSLATION STRINGS
// ============================================
// Load this FIRST on every page!

const translations = {
    en: {
        // Navigation
        'nav-home': 'Home',
        'nav-menu': 'Menu',
        'nav-special': 'Special Offers',
        'nav-contact': 'Contact',
        'nav-category': 'Category',
        'nav-about': 'About',

        // Welcome & Hero
        'welcome-title': '🍔 Welcome to FOODTIME',
        'welcome-subtitle': 'Order delicious food and beverages online instantly',

        // Buttons
        'order-now': 'Order Now',
        'order-btn': 'Order Now',
        'learn-more': 'Learn More',
        'login': 'Login',
        'sign-up': 'Sign Up',
        'btn-add-cart': 'Add to Cart',
        'btn-logout': 'Logout',
        'btn-orders': 'Orders',
        'btn-cart': 'Cart',
        'view': 'View',

        // Order History Page
        'order-history': 'Order History',
        'total-orders': 'Total Orders',
        'no-orders': 'No orders yet',
        'start-ordering': 'Start Ordering',
        'order-id': 'Order #',
        'date': 'Date',
        'order-type': 'Type',
        'status': 'Status',
        'total': 'Total',
        'action': 'Action',

        // Order Type
        'dine-in': 'Dine In',
        'takeaway': 'Takeaway',

        // Order Status
        'pending': 'Pending',
        'paid': 'Paid',
        'preparing': 'Preparing',
        'completed': 'Completed',
        'cancelled': 'Cancelled',

        // Sections
        'featured-menu': 'Featured Menu',
        'featured': '⭐ Featured Products',
        'special-deals': 'Special Deals & Combos',
        'why-choose': 'Why Choose FOODTIME?',

        // Features
        'fast-delivery': 'Fast Delivery',
        'fast-delivery-desc': 'Get your food in 30 minutes or less. Quick and reliable service.',
        'secure-payment': 'Secure Payment',
        'secure-payment-desc': 'All transactions are encrypted and secure. Your data is always protected.',
        'easy-ordering': 'Easy Ordering',
        'easy-ordering-desc': 'Order anytime, anywhere. Our app works perfectly on all devices.',
        '24-7-support': '24/7 Support',
        '24-7-support-desc': 'Our customer support team is always ready to help you.',
        'fresh-quality': 'Fresh & Quality',
        'fresh-quality-desc': 'All our food is prepared with the freshest ingredients daily.',
        'loyalty-rewards': 'Loyalty Rewards',
        'loyalty-rewards-desc': 'Get loyalty points on every order and enjoy exclusive discounts.',

        // Footer
        'about': 'About FOODTIME',
        'support': 'Support',
        'legal': 'Legal',
        'follow-us': 'Follow Us',
        'about-us': 'About Us',
        'careers': 'Careers',
        'press': 'Press',
        'blog': 'Blog',
        'faq': 'FAQ',
        'help-center': 'Help Center',
        'contact-us': 'Contact Us',
        'feedback': 'Feedback',
        'privacy-policy': 'Privacy Policy',
        'terms-conditions': 'Terms & Conditions',
        'refund-policy': 'Refund Policy',
        'copyright': '© 2026 FOODTIME. All rights reserved.',
        'special-badge': 'SPECIAL',

        // ── CART PAGE ─────────────────────────────────────────────
        'cart-title': 'Shopping Cart',
        'cart-your-items': 'Your Items',
        'cart-order-summary': 'Order Summary',
        'cart-subtotal': 'Subtotal:',
        'cart-service-tax': 'Service Tax (6%):',
        'cart-total': 'Total:',
        'cart-discount': 'Discount:',
        'cart-proceed-checkout': 'Proceed to Checkout',
        'cart-continue-shopping': 'Continue Shopping',
        'cart-empty-msg': 'Your cart is empty',
        'cart-stock-warning': 'Some items in your cart are out of stock or exceed available quantity. Please remove them before checking out.',
        'cart-voucher-title': 'Have a voucher code?',
        'cart-voucher-placeholder': 'e.g. FTAB12CD',
        'cart-voucher-apply-btn': 'Apply',
        'cart-voucher-applied': 'Voucher applied!',
        'cart-voucher-remove': 'Remove',
        'cart-voucher-get-link': 'Get vouchers from My Points →',
        'cart-checkout-stock-error': 'Cannot Checkout — Fix Stock Issues',

        // Cart stock badges (used in JS)
        'cart-stock-in': 'In Stock',
        'cart-stock-low': 'Only {n} left',
        'cart-stock-out': 'Out of Stock',
        'cart-stock-exceed': 'Only {n} left (cart has {q})',

        // Cart base price label
        'cart-base-price': 'Base:',

        // Cart toast / status messages (used in JS)
        'cart-toast-removed': 'Item removed from cart',
        'cart-toast-voucher-remove': 'Voucher removed',
        'cart-toast-empty': 'Please add items to cart first',
        'cart-toast-stock-error': 'Cannot checkout: remove or reduce out-of-stock items first',

        // Custom option tags (used in JS)
        'tag-before-meal': '🍽 Before Meal',
        'tag-after-meal': '🍽 After Meal',
        'tag-original': 'Original',
        'tag-mild': '🌶 Mild',
        'tag-spicy': '🌶🌶 Spicy',
        'tag-no-prefix': '🚫 No ',
        'tag-ice': '🧊 Ice',
        'tag-less-ice': '🧊 Less Ice',
        'tag-no-ice': '🚫 No Ice',
        'tag-hot': '🔥 Hot',
    },

    ms: {
        // Navigation
        'nav-home': 'Utama',
        'nav-menu': 'Menu',
        'nav-special': 'Tawaran Khas',
        'nav-contact': 'Hubungi',
        'nav-category': 'Kategori',
        'nav-about': 'Tentang',

        // Welcome & Hero
        'welcome-title': '🍔 Selamat Datang ke FOODTIME',
        'welcome-subtitle': 'Pesan makanan dan minuman lezat secara dalam talian',

        // Buttons
        'order-now': 'Pesan Sekarang',
        'order-btn': 'Pesan',
        'learn-more': 'Maklumat',
        'login': 'Log Masuk',
        'sign-up': 'Daftar',
        'btn-add-cart': 'Tambah ke Keranjang',
        'btn-logout': 'Log Keluar',
        'btn-orders': 'Pesanan',
        'btn-cart': 'Keranjang',
        'view': 'Lihat',

        // Order History Page
        'order-history': 'Sejarah Pesanan',
        'total-orders': 'Jumlah Pesanan',
        'no-orders': 'Belum ada pesanan',
        'start-ordering': 'Mulai Memesan',
        'order-id': 'Pesanan #',
        'date': 'Tarikh',
        'order-type': 'Jenis',
        'status': 'Status',
        'total': 'Jumlah',
        'action': 'Tindakan',

        // Order Type
        'dine-in': 'Makan di Tempat',
        'takeaway': 'Bawa Pulang',

        // Order Status
        'pending': 'Menunggu',
        'paid': 'Dibayar',
        'preparing': 'Menyediakan',
        'completed': 'Selesai',
        'cancelled': 'Dibatalkan',

        // Sections
        'featured-menu': 'Menu Pilihan',
        'featured': '⭐ Produk Unggulan',
        'special-deals': 'Tawaran Khas & Paket',
        'why-choose': 'Mengapa Pilih FOODTIME?',

        // Features
        'fast-delivery': 'Penghantaran Pantas',
        'fast-delivery-desc': 'Dapatkan makanan anda dalam 30 minit atau kurang. Perkhidmatan pantas dan boleh dipercayai.',
        'secure-payment': 'Pembayaran Selamat',
        'secure-payment-desc': 'Semua transaksi disulitkan dan selamat. Data anda sentiasa dilindungi.',
        'easy-ordering': 'Pesanan Mudah',
        'easy-ordering-desc': 'Pesan bila saja, di mana saja. Aplikasi kami berfungsi di semua peranti.',
        '24-7-support': 'Sokongan 24/7',
        '24-7-support-desc': 'Pasukan sokongan kami sedia membantu anda.',
        'fresh-quality': 'Segar & Berkualiti',
        'fresh-quality-desc': 'Semua makanan kami disediakan dengan bahan segar setiap hari.',
        'loyalty-rewards': 'Ganjaran Kesetiaan',
        'loyalty-rewards-desc': 'Dapatkan poin kesetiaan pada setiap pesanan dan nikmati diskaun eksklusif.',

        // Footer
        'about': 'Tentang FOODTIME',
        'support': 'Sokongan',
        'legal': 'Undang-undang',
        'follow-us': 'Ikuti Kami',
        'about-us': 'Tentang Kami',
        'careers': 'Kerjaya',
        'press': 'Akhbar',
        'blog': 'Blog',
        'faq': 'Soalan Lazim',
        'help-center': 'Pusat Bantuan',
        'contact-us': 'Hubungi Kami',
        'feedback': 'Maklum Balas',
        'privacy-policy': 'Dasar Privasi',
        'terms-conditions': 'Terma & Syarat',
        'refund-policy': 'Dasar Bayaran Balik',
        'copyright': '© 2026 FOODTIME. Hak cipta terpelihara.',
        'special-badge': 'KHAS',

        // ── CART PAGE ─────────────────────────────────────────────
        'cart-title': 'Troli Belanja',
        'cart-your-items': 'Item Anda',
        'cart-order-summary': 'Ringkasan Pesanan',
        'cart-subtotal': 'Subtotal:',
        'cart-service-tax': 'Cukai Perkhidmatan (6%):',
        'cart-total': 'Jumlah:',
        'cart-discount': 'Diskaun:',
        'cart-proceed-checkout': 'Teruskan ke Pembayaran',
        'cart-continue-shopping': 'Teruskan Membeli-belah',
        'cart-empty-msg': 'Troli anda kosong',
        'cart-stock-warning': 'Beberapa item dalam troli anda kehabisan stok atau melebihi kuantiti yang tersedia. Sila buang sebelum membayar.',
        'cart-voucher-title': 'Ada kod baucar?',
        'cart-voucher-placeholder': 'cth. FTAB12CD',
        'cart-voucher-apply-btn': 'Guna',
        'cart-voucher-applied': 'Baucar digunakan!',
        'cart-voucher-remove': 'Buang',
        'cart-voucher-get-link': 'Dapatkan baucar dari Mata Saya →',
        'cart-checkout-stock-error': 'Tidak Boleh Bayar — Betulkan Masalah Stok',

        // Cart stock badges
        'cart-stock-in': 'Ada Stok',
        'cart-stock-low': 'Tinggal {n} sahaja',
        'cart-stock-out': 'Tiada Stok',
        'cart-stock-exceed': 'Tinggal {n} (troli ada {q})',

        // Cart base price label
        'cart-base-price': 'Asas:',

        // Cart toast / status messages
        'cart-toast-removed': 'Item dibuang dari troli',
        'cart-toast-voucher-remove': 'Baucar dibuang',
        'cart-toast-empty': 'Sila tambah item ke troli dahulu',
        'cart-toast-stock-error': 'Tidak boleh bayar: buang atau kurangkan item yang tiada stok',

        // Custom option tags
        'tag-before-meal': '🍽 Sebelum Makan',
        'tag-after-meal': '🍽 Selepas Makan',
        'tag-original': 'Asal',
        'tag-mild': '🌶 Sederhana',
        'tag-spicy': '🌶🌶 Pedas',
        'tag-no-prefix': '🚫 Tanpa ',
        'tag-ice': '🧊 Ais',
        'tag-less-ice': '🧊 Kurang Ais',
        'tag-no-ice': '🚫 Tiada Ais',
        'tag-hot': '🔥 Panas',
    },

    zh: {
        // Navigation
        'nav-home': '主页',
        'nav-menu': '菜单',
        'nav-special': '特别优惠',
        'nav-contact': '联系',
        'nav-category': '类别',
        'nav-about': '关于',

        // Welcome & Hero
        'welcome-title': '🍔 欢迎来到FOODTIME',
        'welcome-subtitle': '在线即时订购美食和饮料',

        // Buttons
        'order-now': '立即订购',
        'order-btn': '订购',
        'learn-more': '了解更多',
        'login': '登录',
        'sign-up': '注册',
        'btn-add-cart': '加入购物车',
        'btn-logout': '登出',
        'btn-orders': '订单',
        'btn-cart': '购物车',
        'view': '查看',

        // Order History Page
        'order-history': '订单历史',
        'total-orders': '总订单数',
        'no-orders': '还没有订单',
        'start-ordering': '开始订购',
        'order-id': '订单 #',
        'date': '日期',
        'order-type': '类型',
        'status': '状态',
        'total': '总计',
        'action': '操作',

        // Order Type
        'dine-in': '堂食',
        'takeaway': '外卖',

        // Order Status
        'pending': '待处理',
        'paid': '已支付',
        'preparing': '准备中',
        'completed': '已完成',
        'cancelled': '已取消',

        // Sections
        'featured-menu': '精选菜单',
        'featured': '⭐ 特色产品',
        'special-deals': '特别优惠和套餐',
        'why-choose': '为什么选择FOODTIME?',

        // Features
        'fast-delivery': '快速配送',
        'fast-delivery-desc': '30分钟内送达。快速可靠的服务。',
        'secure-payment': '安全支付',
        'secure-payment-desc': '所有交易加密保护。您的数据始终安全。',
        'easy-ordering': '轻松订购',
        'easy-ordering-desc': '随时随地订购。在所有设备上完美运行。',
        '24-7-support': '24/7 支持',
        '24-7-support-desc': '客户支持团队随时为您服务。',
        'fresh-quality': '新鲜优质',
        'fresh-quality-desc': '我们所有的食物都用最新鲜的食材每天准备。',
        'loyalty-rewards': '忠诚度奖励',
        'loyalty-rewards-desc': '每次订购都能获得忠诚度积分并享受独家折扣。',

        // Footer
        'about': '关于FOODTIME',
        'support': '支持',
        'legal': '法律',
        'follow-us': '关注我们',
        'about-us': '关于我们',
        'careers': '招聘',
        'press': '新闻',
        'blog': '博客',
        'faq': '常见问题',
        'help-center': '帮助中心',
        'contact-us': '联系我们',
        'feedback': '反馈',
        'privacy-policy': '隐私政策',
        'terms-conditions': '条款和条件',
        'refund-policy': '退款政策',
        'copyright': '© 2026 FOODTIME。版权所有。',
        'special-badge': '特别',

        // ── CART PAGE ─────────────────────────────────────────────
        'cart-title': '购物车',
        'cart-your-items': '您的商品',
        'cart-order-summary': '订单摘要',
        'cart-subtotal': '小计：',
        'cart-service-tax': '服务税 (6%)：',
        'cart-total': '总计：',
        'cart-discount': '折扣：',
        'cart-proceed-checkout': '继续结账',
        'cart-continue-shopping': '继续购物',
        'cart-empty-msg': '您的购物车是空的',
        'cart-stock-warning': '购物车中的部分商品缺货或超出可用数量，请在结账前移除。',
        'cart-voucher-title': '有优惠券吗？',
        'cart-voucher-placeholder': '例如 FTAB12CD',
        'cart-voucher-apply-btn': '使用',
        'cart-voucher-applied': '优惠券已使用！',
        'cart-voucher-remove': '移除',
        'cart-voucher-get-link': '从我的积分获取优惠券 →',
        'cart-checkout-stock-error': '无法结账 — 请解决库存问题',

        // Cart stock badges
        'cart-stock-in': '有货',
        'cart-stock-low': '仅剩 {n} 件',
        'cart-stock-out': '缺货',
        'cart-stock-exceed': '仅剩 {n} 件（购物车有 {q} 件）',

        // Cart base price label
        'cart-base-price': '原价：',

        // Cart toast / status messages
        'cart-toast-removed': '商品已从购物车移除',
        'cart-toast-voucher-remove': '优惠券已移除',
        'cart-toast-empty': '请先将商品加入购物车',
        'cart-toast-stock-error': '无法结账：请移除或减少缺货商品',

        // Custom option tags
        'tag-before-meal': '🍽 餐前',
        'tag-after-meal': '🍽 餐后',
        'tag-original': '原味',
        'tag-mild': '🌶 微辣',
        'tag-spicy': '🌶🌶 辣',
        'tag-no-prefix': '🚫 不要',
        'tag-ice': '🧊 加冰',
        'tag-less-ice': '🧊 少冰',
        'tag-no-ice': '🚫 不加冰',
        'tag-hot': '🔥 热',
    }
};

console.log('✅ Translations loaded! Available languages:', Object.keys(translations));