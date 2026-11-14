/* SHOPPING CART PAGE - SA'ADATUL D.I.Y
   Items and quantities are saved in the database (api/cart.php). Login required.
   The cart can contain normal products (with designs) AND custom orders. */

const cartItemsBox = document.getElementById("cartItems");
const cartLoading = document.getElementById("cartLoading");
const cartEmpty = document.getElementById("cartEmpty");
const cartItemCount = document.getElementById("cartItemCount");
const cartSubtotal = document.getElementById("cartSubtotal");
const cartTotal = document.getElementById("cartTotal");
const checkoutButton = document.getElementById("checkoutButton");

function renderCart(cart) {

    cartLoading.hidden = true;
    cartEmpty.hidden = cart.items.length > 0;

    cartItemCount.textContent = cart.count + (cart.count === 1 ? " item" : " items");
    cartSubtotal.textContent = SDIY.formatPrice(cart.subtotal);
    cartTotal.textContent = SDIY.formatPrice(cart.subtotal);

    checkoutButton.classList.toggle("disabled", cart.items.length === 0);
    checkoutButton.setAttribute("aria-disabled", String(cart.items.length === 0));

    cartItemsBox.innerHTML = cart.items.map(item => {
        const name = SDIY.escapeHtml(item.name);
        const isCustom = item.type === "custom";
        const max = item.stock === null ? 99 : Math.min(99, item.stock);

        // Normal products link to their detail page; custom orders do not.
        const link = isCustom ? "" : `href="product.html?id=${item.productId}"`;
        const image = `
            ${isCustom ? `<img src="${SDIY.escapeHtml(item.image)}" alt="${name}" data-fallback>`
                       : `<a ${link}><img src="${SDIY.escapeHtml(item.image)}" alt="${name}" data-fallback></a>`}
        `;

        const qtyControls = isCustom
            ? `<div class="cart-qty"><span>1</span></div>`
            : `<div class="cart-qty">
                    <button type="button" data-action="minus" aria-label="Decrease quantity" ${item.quantity <= 1 ? "disabled" : ""}>-</button>
                    <span>${item.quantity}</span>
                    <button type="button" data-action="plus" aria-label="Increase quantity" ${item.quantity >= max ? "disabled" : ""}>+</button>
                </div>`;

        return `
            <div class="cart-item" data-cart-item-id="${item.cartItemId}" data-quantity="${item.quantity}">
                ${image}

                <div class="cart-item-info">
                    <h3>${isCustom ? name : `<a ${link}>${name}</a>`}</h3>
                    <p>BND ${SDIY.formatPrice(item.price)} each</p>
                    ${isCustom ? `<p>Made to order - final price confirmed on WhatsApp</p>` : ""}
                    ${item.stock !== null ? `<p>${item.stock} in stock</p>` : ""}
                </div>

                ${qtyControls}

                <div class="cart-item-end">
                    <strong>BND ${SDIY.formatPrice(item.lineTotal)}</strong>
                    <button type="button" class="cart-remove" data-action="remove">
                        <i class="fa-regular fa-trash-can"></i> Remove
                    </button>
                </div>
            </div>
        `;
    }).join("");

    SDIY.setCartCount(cart.count);
}

cartItemsBox.addEventListener("click", async event => {

    const button = event.target.closest("button[data-action]");
    if (!button) return;

    const row = button.closest(".cart-item");
    const cartItemId = Number(row.dataset.cartItemId);
    const quantity = Number(row.dataset.quantity);
    const action = button.dataset.action;

    row.querySelectorAll("button").forEach(item => { item.disabled = true; });

    try {
        let result;

        if (action === "remove") {
            result = await SDIY.api("cart.php", { method: "DELETE", body: { cart_item_id: cartItemId } });
        } else {
            result = await SDIY.api("cart.php", {
                method: "PATCH",
                body: { cart_item_id: cartItemId, quantity: action === "plus" ? quantity + 1 : quantity - 1 }
            });
        }

        renderCart(result);

        if (action === "remove") SDIY.toast(result.message);

    } catch (error) {
        SDIY.toast(error.message, "error");
        loadCart();
    }
});

async function loadCart() {

    const user = await SDIY.requireLogin("Please log in or sign up to view your shopping cart.");
    if (!user) return;

    try {
        renderCart(await SDIY.api("cart.php"));
    } catch (error) {
        cartLoading.textContent = error.message;
    }
}

loadCart();
