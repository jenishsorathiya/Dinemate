<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../config/db.php';

$resolveMenuImageUrl = static function ($imagePath): string {
    $path = trim((string) $imagePath);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $path) || stripos($path, 'data:') === 0) {
        return $path;
    }

    if ($path[0] === '/') {
        return $path;
    }

    $normalizedPath = preg_replace('#^(?:\.\.?/)+#', '', $path);
    return appPath($normalizedPath ?: $path);
};

// Fetch menu items grouped by category
$categories = ['Small Plates', 'Large Plates', 'House Specials', 'Burgers', 'Sides', 'Kiddies', 'Desserts'];
$menuItems = [];

foreach ($categories as $category) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE category = ? AND is_available = 1 ORDER BY name");
    $stmt->execute([$category]);
    $menuItems[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
.menu-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 10px;
    background-color: transparent;
}

.menu-header {
    text-align: center;
    margin-bottom: 50px;
    padding: 30px 0;
    border-bottom: 3px solid var(--dm-accent-dark);
}

.menu-header h1 {
    font-size: 3em;
    color: var(--dm-text);
    margin: 0 0 10px 0;
    font-weight: 700;
    letter-spacing: 2px;
}

.menu-header p {
    font-size: 1.2em;
    color: var(--dm-text-muted);
    margin: 0;
    font-style: italic;
}

.menu-section {
    margin-bottom: 60px;
}

.section-title {
    font-size: 2.2em;
    color: var(--dm-accent-dark);
    text-align: center;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    padding-bottom: 15px;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 2px;
    background-color: var(--dm-accent-dark);
}

.section-subtitle {
    text-align: center;
    color: var(--dm-text-muted);
    font-style: italic;
    margin: -5px 0 25px 0;
}

.menu-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.menu-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 10px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
    overflow: hidden;
}

.menu-card.featured {
    border-left: 3px solid var(--dm-accent-dark);
    background: var(--dm-surface-muted);
}

.card-image {
    height: 200px;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-content {
    padding: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.card-header h3 {
    margin: 0;
    color: var(--dm-text);
    font-size: 1.3em;
    flex: 1;
    font-weight: 600;
}

.price {
    color: var(--dm-accent-dark);
    font-size: 1.4em;
    font-weight: 700;
    white-space: nowrap;
    margin-left: 15px;
}

.description {
    color: var(--dm-text-muted);
    font-size: 0.95em;
    margin: 8px 0 10px 0;
    line-height: 1.4;
}

.badge {
    display: inline-block;
    background-color: var(--dm-border);
    color: var(--dm-accent-dark);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 8px;
}

.sauces-note {
    text-align: center;
    color: var(--dm-text-muted);
    font-size: 0.95em;
    margin-top: 15px;
}

.menu-legend {
    text-align: center;
    padding: 20px;
    color: var(--dm-text-muted);
    font-size: 0.95em;
    border-top: 1px solid var(--dm-border);
    margin-top: 40px;
}

.badge-info {
    font-weight: 600;
    color: var(--dm-accent-dark);
}

.cart-close {
    display: none;
    background: none;
    border: none;
    font-size: 24px;
    color: #666;
    cursor: pointer;
    margin-left: 10px;
}

@media (max-width: 768px) {
    .menu-header h1 {
        font-size: 2em;
    }

    .section-title {
        font-size: 1.6em;
    }

    .menu-cards {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="menu-container">
    <div class="menu-header">
        <h1>Our Menu</h1>
        <p>Experience fine dining at The Old Canberra Inn</p>
    </div>

    <div class="menu-layout">
        <main class="menu-list">
    <?php foreach ($menuItems as $category => $items): ?>
        <?php if (!empty($items)): ?>
            <section class="menu-section">
                <h2 class="section-title"><?php echo $category; ?></h2>
                <?php if ($category === 'Burgers'): ?>
                    <p class="section-subtitle">Served on a milk bun with a choice of chips or salad</p>
                <?php elseif ($category === 'Sides'): ?>
                    <p class="section-subtitle">All $11 as Small Plates</p>
                <?php elseif ($category === 'Kiddies'): ?>
                    <p class="section-subtitle">Served with your choice of chips or salad</p>
                <?php endif; ?>

                <?php if ($category === 'Sides'): ?>
                    <div class="menu-cards">
                        <?php foreach ($items as $item): ?>
                            <div class="menu-card">
                                <?php if (!empty($item['image'])): ?>
                                    <div class="card-image">
                                        <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <span class="price">$<?php echo number_format($item['price'] ?: 11, 2); ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['dietary_info'])): ?>
                                        <span class="badge"><?php echo htmlspecialchars($item['dietary_info']); ?></span>
                                    <?php endif; ?>

                                    <button type="button" class="btn-add-cart" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" data-price="<?php echo number_format($item['price'] ?: 11, 2); ?>" data-image="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="sauces-note"><strong>Sauces:</strong> Mushroom, Peppercorn, Gravy, Chimichurri, Café De Paris Butter</p>
                <?php else: ?>
                    <div class="menu-cards">
                        <?php foreach ($items as $item): ?>
                            <div class="menu-card <?php echo ($category === 'House Specials') ? 'featured' : ''; ?>">
                                <?php if (!empty($item['image'])): ?>
                                    <div class="card-image">
                                        <img src="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <span class="price">$<?php echo number_format($item['price'], 2); ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['dietary_info'])): ?>
                                        <span class="badge"><?php echo htmlspecialchars($item['dietary_info']); ?></span>
                                    <?php endif; ?>

                                    <button type="button" class="btn-add-cart" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" data-price="<?php echo number_format($item['price'], 2); ?>" data-image="<?php echo htmlspecialchars($resolveMenuImageUrl($item['image']), ENT_QUOTES, 'UTF-8'); ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- LEGEND -->
    <div class="menu-legend">
        <p><span class="badge-info">V</span> Vegan | <span class="badge-info">GF</span> Gluten Free</p>
    </div>
        </main>

        <aside class="cart-sidebar">
            <div class="cart-panel">
                <div class="cart-header">
                    <div>
                        <span class="eyebrow">Order cart</span>
                        <h2>Your Basket</h2>
                    </div>
                    <div>
                        <span class="cart-status">Estimated pickup: <strong id="delivery-time">10-15 mins</strong></span>
                        <button type="button" class="cart-close">×</button>
                    </div>
                </div>

                <div class="cart-items-container">
                    <div class="cart-empty-state">
                        <p>Your basket is empty. Add a delicious dish to get started.</p>
                    </div>
                    <div class="cart-items"></div>
                </div>

                <div class="cart-footer">
                    <div class="promo-row">
                        <input id="promo-code" type="text" placeholder="Promo code" aria-label="Promo code">
                        <button type="button" id="apply-promo">Apply</button>
                    </div>
                    <div class="cart-summary">
                        <div><span>Subtotal</span><span id="cart-subtotal">$0.00</span></div>
                        <div><span>Discount</span><span id="cart-discount">-$0.00</span></div>
                        <div><span>Tax (12%)</span><span id="cart-tax">$0.00</span></div>
                        <div class="cart-total"><span>Total</span><span id="cart-total">$0.00</span></div>
                    </div>
                    <button type="button" class="btn-place-order" id="scroll-checkout">Proceed to checkout</button>
                </div>
            </div>
        </aside>
    </div>

    <button id="mobile-cart-toggle" class="mobile-cart-btn">🛒 View Cart</button>

    <div class="checkout-modal hidden" id="checkout-modal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="checkout-modal-card">
            <div class="checkout-modal-header">
                <div>
                    <span class="eyebrow">Secure checkout</span>
                    <h2>Complete Your Order</h2>
                </div>
                <button type="button" id="close-checkout" class="modal-close" aria-label="Close checkout">×</button>
            </div>

            <form class="checkout-form" id="checkout-form" novalidate>
                <div class="checkout-grid">
                    <div class="checkout-section">
                        <label>Full Name<input type="text" id="customer-name" placeholder="Jane Doe" required></label>
                        <label>Phone Number<input type="tel" id="customer-phone" placeholder="0412 345 678" required></label>
                        <label>Email Address<input type="email" id="customer-email" placeholder="jane@example.com" required></label>
                        <label>Order Notes<textarea id="order-notes" rows="3" placeholder="Leave a note for the chef..."></textarea></label>
                    </div>

                    <aside class="order-summary">
                        <div class="summary-card">
                            <div class="summary-header">
                                <span class="eyebrow">Order Summary</span>
                                <span id="summary-count">0 items</span>
                            </div>
                            <div class="summary-items"></div>
                            <div class="summary-totals">
                                <div><span>Subtotal</span><span id="summary-subtotal">$0.00</span></div>
                                <div><span>Tax</span><span id="summary-tax">$0.00</span></div>
                                <div class="summary-total"><span>Total</span><span id="summary-total">$0.00</span></div>
                            </div>
                        </div>

                        <div class="payment-card-details" id="card-details">
                            <label>Cardholder Name<input type="text" id="card-name" placeholder="Jane Doe"></label>
                            <label>Card Number<input type="text" id="card-number" placeholder="1234 5678 9012 3456" maxlength="19"></label>
                            <div class="card-row">
                                <label>Expiry Date<input type="text" id="card-expiry" placeholder="MM/YY" maxlength="5"></label>
                                <label>CVV<input type="text" id="card-cvv" placeholder="123" maxlength="4"></label>
                            </div>
                        </div>
                    </aside>
                </div>

                <button type="button" id="place-order" class="btn-submit-order">Place Order</button>
            </form>
        </div>
    </div>
</div>

<div class="toast-container" aria-live="polite"></div>
<div class="modal-overlay" id="order-modal" aria-hidden="true">
    <div class="modal-card">
        <h3>Order Placed!</h3>
        <p>Your delicious meal will be ready for pickup in approximately <strong id="modal-delivery-time">15 minutes</strong>.</p>
        <button type="button" id="close-modal">Continue browsing</button>
    </div>
</div>

<script>
(function () {
    const TAX_RATE = 0.12;
    const PROMOS = {
        'DINEMATE10': 0.10,
        'DINE20': 0.20,
        'CHEF15': 0.15
    };
    const cart = {};
    const cartItemsEl = document.querySelector('.cart-items');
    const cartEmptyStateEl = document.querySelector('.cart-empty-state');
    const subtotalEl = document.getElementById('cart-subtotal');
    const discountEl = document.getElementById('cart-discount');
    const taxEl = document.getElementById('cart-tax');
    const totalEl = document.getElementById('cart-total');
    const summaryItemsEl = document.querySelector('.summary-items');
    const summaryCountEl = document.getElementById('summary-count');
    const summarySubtotalEl = document.getElementById('summary-subtotal');
    const summaryTaxEl = document.getElementById('summary-tax');
    const summaryTotalEl = document.getElementById('summary-total');
    const deliveryTimeEl = document.getElementById('delivery-time');
    const modalDeliveryTimeEl = document.getElementById('modal-delivery-time');
    const promoInputEl = document.getElementById('promo-code');
    const applyPromoBtn = document.getElementById('apply-promo');
    const placeOrderBtn = document.getElementById('place-order');
    const proceedCheckoutBtn = document.getElementById('scroll-checkout');
    const checkoutModal = document.getElementById('checkout-modal');
    const closeCheckoutBtn = document.getElementById('close-checkout');
    const orderModal = document.getElementById('order-modal');
    const toastContainer = document.querySelector('.toast-container');
    let appliedPromo = null;

    function formatMoney(value) {
        return '$' + value.toFixed(2);
    }

    function computeTotals() {
        const subtotal = Object.values(cart).reduce((sum, item) => sum + item.price * item.quantity, 0);
        const discountValue = appliedPromo ? subtotal * PROMOS[appliedPromo] : 0;
        const taxedAmount = Math.max(subtotal - discountValue, 0) * TAX_RATE;
        const total = Math.max(subtotal - discountValue, 0) + taxedAmount;
        return { subtotal, discountValue, taxedAmount, total };
    }

    function updateCartDisplay() {
        const items = Object.values(cart);
        if (!items.length) {
            cartItemsEl.innerHTML = '';
            cartEmptyStateEl.style.display = 'block';
        } else {
            cartEmptyStateEl.style.display = 'none';
            cartItemsEl.innerHTML = items.map(item => {
                return `
                    <div class="cart-item" data-id="${item.id}">
                        <div class="item-meta">
                            <img src="${item.image}" alt="${item.name}">
                            <div>
                                <strong>${item.name}</strong>
                                <span>${formatMoney(item.price)} each</span>
                            </div>
                        </div>
                        <div class="item-actions">
                            <div class="quantity-control">
                                <button type="button" class="qty-btn" data-action="decrease">−</button>
                                <span>${item.quantity}</span>
                                <button type="button" class="qty-btn" data-action="increase">+</button>
                            </div>
                            <button type="button" class="remove-item" data-action="remove">Remove</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        const totals = computeTotals();
        subtotalEl.textContent = formatMoney(totals.subtotal);
        discountEl.textContent = '-' + formatMoney(totals.discountValue);
        taxEl.textContent = formatMoney(totals.taxedAmount);
        totalEl.textContent = formatMoney(totals.total);
        summarySubtotalEl.textContent = formatMoney(totals.subtotal);
        summaryTaxEl.textContent = formatMoney(totals.taxedAmount);
        summaryTotalEl.textContent = formatMoney(totals.total);
        summaryCountEl.textContent = `${items.length} item${items.length === 1 ? '' : 's'}`;
        summaryItemsEl.innerHTML = items.map(item => {
            return `<div class="summary-line"><span>${item.quantity} × ${item.name}</span><span>${formatMoney(item.price * item.quantity)}</span></div>`;
        }).join('');
    }

    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.textContent = message;
        toastContainer.appendChild(toast);
        window.setTimeout(() => {
            toast.classList.add('visible');
        }, 10);
        window.setTimeout(() => {
            toast.classList.remove('visible');
            window.setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function addItemToCart(item) {
        if (cart[item.id]) {
            cart[item.id].quantity += 1;
        } else {
            cart[item.id] = { ...item, quantity: 1 };
        }
        updateCartDisplay();
        showToast(`${item.name} added to cart`);
    }

    function validateCheckout() {
        const name = document.getElementById('customer-name').value.trim();
        const phone = document.getElementById('customer-phone').value.trim();
        const email = document.getElementById('customer-email').value.trim();
        if (!name || !phone || !email) {
            showToast('Please complete all required checkout fields.');
            return false;
        }
        if (!Object.keys(cart).length) {
            showToast('Add at least one item to the cart before placing an order.');
            return false;
        }
        return true;
    }

    function openCheckout() {
        if (!Object.keys(cart).length) {
            showToast('Add at least one item before checkout.');
            return;
        }
        checkoutModal.classList.add('open');
        checkoutModal.setAttribute('aria-hidden', 'false');
    }

    function closeCheckout() {
        checkoutModal.classList.remove('open');
        checkoutModal.setAttribute('aria-hidden', 'true');
    }

    function openModal() {
        const estimated = 10 + Math.floor(Math.random() * 11);
        deliveryTimeEl.textContent = `Ready in ${estimated}-${estimated + 5} mins`;
        modalDeliveryTimeEl.textContent = `Ready in ${estimated}-${estimated + 5} mins`;
        orderModal.setAttribute('aria-hidden', 'false');
        orderModal.classList.add('open');
    }

    function closeModal() {
        orderModal.setAttribute('aria-hidden', 'true');
        orderModal.classList.remove('open');
    }

    document.querySelectorAll('.btn-add-cart').forEach(button => {
        button.addEventListener('click', () => {
            addItemToCart({
                id: button.dataset.id,
                name: button.dataset.name,
                price: parseFloat(button.dataset.price) || 0,
                image: button.dataset.image || ''
            });
        });
    });

    cartItemsEl.addEventListener('click', event => {
        const action = event.target.dataset.action;
        const itemElement = event.target.closest('.cart-item');
        if (!itemElement || !action) return;
        const id = itemElement.dataset.id;
        if (!cart[id]) return;

        if (action === 'increase') {
            cart[id].quantity += 1;
        }
        if (action === 'decrease') {
            cart[id].quantity = Math.max(1, cart[id].quantity - 1);
        }
        if (action === 'remove') {
            delete cart[id];
        }
        updateCartDisplay();
    });

    applyPromoBtn.addEventListener('click', () => {
        const code = promoInputEl.value.trim().toUpperCase();
        if (!code) {
            showToast('Enter a promo code to apply.');
            return;
        }
        if (!PROMOS[code]) {
            showToast('Promo code is not valid.');
            appliedPromo = null;
        } else {
            appliedPromo = code;
            showToast(`Promo applied: ${Math.round(PROMOS[code] * 100)}% off`);
        }
        updateCartDisplay();
    });

    placeOrderBtn.addEventListener('click', async () => {
        if (!validateCheckout()) return;

        const customerName = document.getElementById('customer-name').value.trim();
        const customerPhone = document.getElementById('customer-phone').value.trim();
        const customerEmail = document.getElementById('customer-email').value.trim();
        const orderNotes = document.getElementById('order-notes').value.trim();

        const payload = {
            customerName,
            customerPhone,
            customerEmail,
            orderNotes,
            paymentMethod: 'cash',
            promoCode: promoInputEl.value.trim().toUpperCase(),
            items: Object.values(cart).map(item => ({
                id: item.id,
                name: item.name,
                price: item.price,
                quantity: item.quantity
            }))
        };

        placeOrderBtn.disabled = true;
        placeOrderBtn.textContent = 'Processing...';

        try {
            const response = await fetch('process-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                showToast(result.error || 'Unable to place order. Please try again.');
                return;
            }

            showToast('Order placed successfully!');
            closeCheckout();
            openModal();
            Object.keys(cart).forEach(key => delete cart[key]);
            updateCartDisplay();
            document.getElementById('checkout-form').reset();
            promoInputEl.value = '';
            appliedPromo = null;
        } catch (error) {
            console.error(error);
            showToast('Order successfully');
        } finally {
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'Place Order';
        }
    });

    proceedCheckoutBtn.addEventListener('click', openCheckout);
    closeCheckoutBtn.addEventListener('click', closeCheckout);
    checkoutModal.addEventListener('click', event => {
        if (event.target === checkoutModal) {
            closeCheckout();
        }
    });

    const mobileCartToggle = document.getElementById('mobile-cart-toggle');
    if (mobileCartToggle) {
        mobileCartToggle.addEventListener('click', () => {
            const cartSidebar = document.querySelector('.cart-sidebar');
            cartSidebar.classList.toggle('open');
        });
    }

    const cartCloseBtn = document.querySelector('.cart-close');
    if (cartCloseBtn) {
        cartCloseBtn.addEventListener('click', () => {
            const cartSidebar = document.querySelector('.cart-sidebar');
            cartSidebar.classList.remove('open');
        });
    }
})();
</script>

<style>
.menu-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(200px, 280px);
    gap: 15px;
    margin-top: 50px;
}

.menu-list {
    display: grid;
    gap: 36px;
}

.cart-sidebar {
    position: sticky;
    top: 24px;
    align-self: flex-start;
    max-width: 340px;
    width: 100%;
}

.cart-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
    padding: 20px;
    display: flex;
    flex-direction: column;
    min-height: 420px;
}

.checkout-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.72);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
    z-index: 1000;
}

.checkout-modal.open {
    opacity: 1;
    visibility: visible;
}

.checkout-modal-card {
    width: min(100%, 980px);
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    border-radius: 28px;
    background: #ffffff;
    padding: 32px;
    box-shadow: 0 40px 80px rgba(15, 23, 42, 0.14);
    position: relative;
}

.checkout-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
}

.modal-close {
    border: none;
    background: transparent;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    color: #475569;
}

.payment-card-details {
    display: none;
}

.checkout-form {
    margin-top: 24px;
}

.cart-panel .cart-footer {
    margin-top: auto;
}

@media (max-width: 1150px) {
    .menu-layout {
        grid-template-columns: 1fr;
    }

    .cart-sidebar {
        position: relative;
        top: auto;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .mobile-cart-btn {
        display: block;
    }

    .cart-sidebar {
        display: none;
    }

    .cart-sidebar.open {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: white;
        z-index: 1001;
        overflow-y: auto;
        padding: 20px;
    }

    .cart-close {
        display: block;
    }

    .menu-header h1 {
        font-size: 2em;
    }

    .section-title {
        font-size: 1.6em;
    }

    .menu-cards {
        grid-template-columns: 1fr;
    }

    .checkout-modal-card {
        padding: 24px;
    }
}

.cart-header,
.checkout-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
}

.eyebrow {
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.72rem;
    color: #2f855a;
    margin-bottom: 8px;
}

.cart-header h2,
.checkout-header h2 {
    margin: 0;
    font-size: 1.8rem;
    color: #111827;
}

.cart-status {
    font-size: 0.95rem;
    color: #475569;
}

.cart-items-container {
    min-height: 220px;
}

.cart-empty-state {
    padding: 30px;
    border: 2px dashed #cbd5e1;
    border-radius: 18px;
    color: #64748b;
    text-align: center;
    display: none;
}

.cart-item,
.summary-line {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 16px;
    align-items: center;
    padding: 18px 0;
    border-bottom: 1px solid #e2e8f0;
}

.cart-item:last-child,
.summary-line:last-child {
    border-bottom: none;
}

.item-meta {
    display: flex;
    align-items: center;
    gap: 14px;
}

.item-meta img {
    width: 68px;
    height: 68px;
    object-fit: cover;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.item-meta strong {
    display: block;
    font-size: 1rem;
    color: #111827;
}

.item-meta span {
    display: block;
    color: #64748b;
    font-size: 0.92rem;
}

.item-actions {
    text-align: right;
}

.quantity-control {
    display: inline-flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    overflow: hidden;
}

.qty-btn {
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    color: #2d3748;
    font-size: 1.2rem;
    cursor: pointer;
}

.quantity-control span {
    display: inline-flex;
    min-width: 32px;
    justify-content: center;
    color: #1f2937;
    font-weight: 700;
}

.remove-item {
    display: block;
    margin-top: 10px;
    border: none;
    background: transparent;
    color: #c53030;
    cursor: pointer;
    font-weight: 600;
}

.promo-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    margin-bottom: 20px;
}

.promo-row input,
.checkout-section input,
.checkout-section textarea,
.payment-card-details input {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    padding: 14px 16px;
    font-size: 0.96rem;
    color: #1f2937;
    background: #f8fafc;
    outline: none;
}

.promo-row button,
.btn-add-cart,
.btn-place-order,
.btn-submit-order,
.payment-tab {
    border: none;
    border-radius: 14px;
    cursor: pointer;
    font-weight: 700;
    transition: transform 0.22s ease, box-shadow 0.22s ease, background-color 0.22s ease;
}

.promo-row button,
.btn-place-order,
.btn-submit-order {
    background: #2f855a;
    color: white;
    padding: 14px 20px;
}

.promo-row button:hover,
.btn-add-cart:hover,
.btn-place-order:hover,
.btn-submit-order:hover,
.payment-tab:hover {
    transform: translateY(-2px);
}

.cart-summary,
.summary-totals {
    background: #f8fafc;
    border-radius: 18px;
    padding: 20px;
    display: grid;
    gap: 14px;
    margin-bottom: 18px;
}

.cart-summary div,
.summary-totals div {
    display: flex;
    justify-content: space-between;
    color: #475569;
}

.cart-total,
.summary-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
}

.btn-add-cart {
    width: 100%;
    margin-top: 18px;
    padding: 14px 16px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #2f855a, #68d391);
    color: white;
}

.btn-add-cart:hover {
    box-shadow: 0 12px 30px rgba(46, 125, 81, 0.25);
}

.btn-place-order,
.btn-submit-order {
    width: 100%;
    padding: 16px 18px;
    letter-spacing: 0.02em;
}

.checkout-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 28px;
}

.checkout-section {
    display: grid;
    gap: 18px;
}

.order-summary {
    display: grid;
    gap: 22px;
}

.summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 22px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 18px;
}

.payment-method-card-details {
    display: none;
}

.card-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.payment-card-details {
    display: none;
    gap: 14px;
}

.payment-card-details label,
.checkout-section label {
    display: grid;
    gap: 8px;
    color: #1f2937;
    font-size: 0.95rem;
}

.payment-card-details input {
    background: #ffffff;
}

.order-summary .summary-line {
    color: #475569;
    font-size: 0.95rem;
}

.order-summary .summary-total {
    font-size: 1.1rem;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.66);
    z-index: 9999;
}

.modal-overlay.open {
    display: flex;
}

.modal-card {
    max-width: 420px;
    width: 100%;
    background: #ffffff;
    border-radius: 26px;
    padding: 32px;
    text-align: center;
    box-shadow: 0 32px 80px rgba(15, 23, 42, 0.16);
}

.modal-card h3 {
    margin: 0 0 14px;
    font-size: 1.8rem;
    color: #111827;
}

.modal-card p {
    color: #475569;
    margin-bottom: 24px;
}

.modal-card button {
    width: 100%;
    padding: 16px 20px;
    background: #2f855a;
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 700;
}

.toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    display: grid;
    gap: 10px;
    z-index: 10000;
}

.toast-message {
    opacity: 0;
    transform: translateY(12px);
    padding: 14px 18px;
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.92);
    color: #f8fafc;
    font-size: 0.95rem;
    box-shadow: 0 15px 36px rgba(15, 23, 42, 0.18);
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.toast-message.visible {
    opacity: 1;
    transform: translateY(0);
}

.btn-add-cart {
    background: linear-gradient(135deg, #111827, #1f2937);
}

.payment-tab.active {
    box-shadow: 0 16px 35px rgba(15, 23, 42, 0.12);
}

@media (max-width: 1080px) {
    .menu-cart-shell {
        grid-template-columns: 1fr;
    }

    .checkout-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .menu-container {
        padding: 24px 16px;
    }

    .menu-header {
        padding: 20px 0;
    }

    .menu-header h1 {
        font-size: 2.4rem;
    }

    .cart-header,
    .checkout-header {
        flex-direction: column;
        align-items: stretch;
    }

    .cart-status {
        text-align: left;
    }
}
</style>

