/* CHECKOUT PAGE - SA'ADATUL D.I.Y
   Shows the customer's saved cart (normal products + custom orders)
   and sends the order + payment screenshot to api/checkout.php.
   Login required. Prices are always recalculated on the server. */

// Product subtotal (loaded from the cart)
let subtotal = 0;
let cartItems = [];
let fulfilment = "delivery";

// ---------- ELEMENTS ----------
const checkoutForm = document.getElementById("checkoutForm");
const placeOrderButtons = document.querySelectorAll(".place-order");

const shippingRadios = document.querySelectorAll('input[name="shipping"]');
const subtotalText = document.getElementById("subtotal");
const deliveryFeeText = document.getElementById("deliveryFee");
const totalText = document.getElementById("total");
const orderItems = document.getElementById("orderItems");
const emptyOrder = document.getElementById("emptyOrder");

const addressFields = document.getElementById("addressFields");
const shippingOptions = document.getElementById("shippingOptions");
const addressInputs = addressFields.querySelectorAll("input");

// ---------- TOTALS ----------
function updateTotals() {
    const selected = document.querySelector('input[name="shipping"]:checked');
    const fee = fulfilment === "delivery" && selected ? Number(selected.value) : 0;

    subtotalText.textContent = subtotal.toFixed(2);
    deliveryFeeText.textContent = fee.toFixed(2);
    totalText.textContent = (subtotal + fee).toFixed(2);
}

shippingRadios.forEach(radio => {
    radio.addEventListener("change", updateTotals);
});

// ---------- DELIVERY / PICKUP TOGGLE ----------
document.querySelectorAll(".switch-option").forEach(button => {
    button.addEventListener("click", () => {
        document.querySelectorAll(".switch-option").forEach(item => {
            item.classList.remove("active");
        });

        button.classList.add("active");
        const isDelivery = button.dataset.method === "delivery";
        fulfilment = isDelivery ? "delivery" : "pickup";

        addressFields.style.display = isDelivery ? "block" : "none";
        shippingOptions.style.display = isDelivery ? "block" : "none";
        document.getElementById("pickupAddressBox").style.display = isDelivery ? "none" : "block";

        addressInputs.forEach(input => { input.required = isDelivery; });

        updateTotals();
    });
});

// ---------- BRUNEI PHONE (+673 fixed prefix, 7 digits only) ----------
// Called from the input's oninput attribute in checkout.html.
function enforceBruneiPhone(input) {
    // Keep digits only, maximum 7 local digits after the +673 prefix.
    input.value = input.value.replace(/\D/g, "").slice(0, 7);
}

// ---------- PAYMENT PROOF ----------
document.getElementById("proof").addEventListener("change", event => {
    const file = event.target.files[0];
    document.getElementById("fileName").textContent = file ? file.name : "No file selected";

    // If a file is selected, remove the red error
    if (file) {
        document.getElementById("fileError").style.display = "none";
        document.querySelector(".upload-button").classList.remove("error");
    }
});

// ---------- RENDER ORDER ITEMS ----------
function renderOrder() {
    emptyOrder.hidden = cartItems.length > 0;

    orderItems.innerHTML = cartItems.map(item => `
        <div class="product">
            <div class="product-image">
                <img src="${SDIY.escapeHtml(item.image)}" alt="${SDIY.escapeHtml(item.name)}" data-fallback>
            </div>
            <span class="quantity">${item.quantity}</span>
            <div class="product-info">
                <p>${SDIY.escapeHtml(item.name)}</p>
                <span>BND ${SDIY.formatPrice(item.lineTotal)}</span>
            </div>
        </div>
    `).join("");

    placeOrderButtons.forEach(button => {
        button.disabled = cartItems.length === 0;
    });

    updateTotals();
}

// ---------- PREFILL FROM PROFILE ----------
function prefill(user) {
    const values = {
        email: user.email,
        fullname: user.full_name,
        phone: user.phone,
        address: user.address,
        city: user.city,
        postcode: user.postcode
    };

    Object.entries(values).forEach(([id, value]) => {
        const input = document.getElementById(id);
        if (!input || !value) return;

        if (id === "phone") {
            // Keep only the 7 local digits inside the +673 field.
            const digits = String(value).replace(/\D/g, "");
            input.value = digits.length === 10 && digits.startsWith("673")
                ? digits.slice(3)
                : digits.slice(-7);
        } else if (!input.value) {
            input.value = value;
        }
    });
}

// ---------- SUBMIT ORDER ----------
checkoutForm.addEventListener("submit", async event => {
    event.preventDefault();
    const form = event.currentTarget;

    if (cartItems.length === 0) {
        SDIY.toast("Your cart is empty. Please add items from the Shop page.", "error");
        return;
    }

    if (!form.reportValidity()) return;

    // Check payment screenshot
    const proof = document.getElementById("proof").files[0];
    const fileError = document.getElementById("fileError");
    const uploadBtn = document.querySelector(".upload-button");

    if (!proof) {
        fileError.style.display = "block";
        uploadBtn.classList.add("error");
        document.querySelector(".payment-box").scrollIntoView({ behavior: "smooth" });
        return;
    }

    if (proof.size > 5 * 1024 * 1024) {
        SDIY.toast("Payment screenshot must be 5 MB or smaller.", "error");
        return;
    }

    // Brunei phone check before sending (server checks again).
    const phoneDigits = document.getElementById("phone").value.replace(/\D/g, "");
    if (phoneDigits.length !== 7) {
        SDIY.toast("Please enter a valid Brunei phone number (7 digits after +673).", "error");
        document.getElementById("phone").focus();
        return;
    }

    const selected = document.querySelector('input[name="shipping"]:checked');
    const data = new FormData();

    data.append("email", document.getElementById("email").value.trim());
    data.append("full_name", document.getElementById("fullname").value.trim());
    // Send the full international number: +673 + 7 digits.
    data.append("phone", "+673" + phoneDigits);
    data.append("country", document.getElementById("country").value.trim());
    data.append("fulfilment", fulfilment);
    data.append("address", document.getElementById("address").value.trim());
    data.append("city", document.getElementById("city").value.trim());
    data.append("postcode", document.getElementById("postcode").value.trim());
    data.append("shipping", selected ? selected.value : "");
    data.append("proof", proof);

    // Optional order notes written on the cart page.
    try {
        const notes = (localStorage.getItem("sdiyOrderNotes") || "").trim();
        if (notes) data.append("notes", notes);
    } catch (error) { /* storage unavailable - order goes without notes */ }

    placeOrderButtons.forEach(button => { button.disabled = true; });

    try {
        const result = await SDIY.api("checkout.php", { method: "POST", form: data });

        SDIY.setCartCount(0);
        try { localStorage.removeItem("sdiyOrderNotes"); } catch (error) { /* ignore */ }

        // Show the real order number returned by the server.
        // The confirmation stays on screen until the customer
        // clicks "Back To Home" - no timer, no auto-redirect.
        const orderNumber = result.orderNumber || ("SD-" + String(result.orderId).padStart(5, "0"));
        document.getElementById("randomId").textContent = orderNumber.replace(/^SD-/, "");
        document.getElementById("confirmOrderNumber").textContent = orderNumber;
        document.getElementById("confirmOrderStatus").textContent =
            (result.status || "pending").replaceAll("_", " ");
        document.getElementById("confirmPaymentStatus").textContent =
            (result.paymentStatus === "verified" ? "Verified" : "Pending Verification");

        // Hide the form so the same order cannot be submitted again.
        document.getElementById("checkoutForm").style.display = "none";
        document.getElementById("successModal").style.display = "flex";
        // Keep the Place Order buttons disabled - the order is done.

    } catch (error) {
        SDIY.toast(error.message, "error");
        placeOrderButtons.forEach(button => { button.disabled = cartItems.length === 0; });
    }
});

// ---------- LOAD CART (login required) ----------
async function loadCheckout() {

    placeOrderButtons.forEach(button => { button.disabled = true; });

    const user = await SDIY.requireLogin("Please log in or sign up to check out.");
    if (!user) return;

    prefill(user);

    try {
        const cart = await SDIY.api("cart.php");
        cartItems = cart.items;
        subtotal = cart.subtotal;
    } catch (error) {
        SDIY.toast(error.message, "error");
    }

    renderOrder();
}

// Calculate initial total
updateTotals();
loadCheckout();
