(function () {
    document.querySelectorAll('[data-scroll-top]').forEach((button) => {
        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
})();

(function () {
    const storageKey = 'dinemate_menu_cart';
    const cartToggle = document.getElementById('menuCartToggle');
    const cartBody = document.getElementById('menuCartBody');
    const cartItemsList = document.getElementById('menuCartItems');
    const cartCount = document.getElementById('menuCartCount');
    const cartTotal = document.getElementById('menuCartTotal');
    const addButtons = document.querySelectorAll('.menu-card-add-btn');

    if (!cartToggle || !cartItemsList || !cartCount || !cartTotal) {
        return;
    }

    const loadCart = () => {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch (error) {
            return {};
        }
    };

    const saveCart = (cart) => {
        const hasItems = Object.keys(cart).length > 0;
        if (hasItems) {
            localStorage.setItem(storageKey, JSON.stringify(cart));
        } else {
            localStorage.removeItem(storageKey);
        }
    };

    const formatPrice = (value) => {
        return '$' + Number(value).toFixed(2);
    };

    const buildCartItems = (cart) => {
        const entries = Object.values(cart);
        if (!entries.length) {
            cartItemsList.innerHTML = '<li class="menu-cart-empty">No items yet.</li>';
            return;
        }

        cartItemsList.innerHTML = entries.map((item) => {
            return `
                <li>
                    <div>
                        <div class="menu-cart-item-name">${item.name}</div>
                        <div class="menu-cart-item-meta">${item.qty} × ${formatPrice(item.price)}</div>
                    </div>
                    <button type="button" class="menu-cart-item-remove" data-remove-item-id="${item.id}">Remove</button>
                </li>
            `;
        }).join('');
    };

    const updateCart = (cart) => {
        const items = Object.values(cart);
        const totalQuantity = items.reduce((sum, item) => sum + item.qty, 0);
        const totalPrice = items.reduce((sum, item) => sum + item.qty * Number(item.price), 0);

        cartCount.textContent = totalQuantity.toString();
        cartTotal.textContent = formatPrice(totalPrice);
        buildCartItems(cart);
    };

    const cart = loadCart();
    updateCart(cart);

    cartToggle.addEventListener('click', () => {
        const expanded = cartToggle.getAttribute('aria-expanded') === 'true';
        cartToggle.setAttribute('aria-expanded', String(!expanded));
        cartBody.classList.toggle('hidden');
    });

    addButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const itemId = button.dataset.menuItemId;
            const itemName = button.dataset.menuItemName;
            const itemPrice = Number(button.dataset.menuItemPrice || 0);
            if (!itemId || !itemName) {
                return;
            }

            const currentCart = loadCart();
            const entry = currentCart[itemId] || { id: itemId, name: itemName, price: itemPrice, qty: 0 };
            entry.qty += 1;
            currentCart[itemId] = entry;
            saveCart(currentCart);
            updateCart(currentCart);
            if (cartBody.classList.contains('hidden')) {
                cartBody.classList.remove('hidden');
                cartToggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    cartItemsList.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-item-id]');
        if (!removeButton) {
            return;
        }

        const itemId = removeButton.dataset.removeItemId;
        const currentCart = loadCart();
        if (!currentCart[itemId]) {
            return;
        }

        delete currentCart[itemId];
        saveCart(currentCart);
        updateCart(currentCart);
    });
})();
