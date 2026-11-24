const keychains = [
    {
        gender: "",
        basePrice: 0,
        skin: "",
        hair: "",
        outfit: "",
        accessories: [],
        hairNote: "",
        outfitNote: "",
        accessoryNote: ""
    }
];

const skinTones = [
    { name: "Light", color: "#f5dfc8" },
    { name: "Fair", color: "#eac5a5" },
    { name: "Medium", color: "#c99473" },
    { name: "Tan", color: "#a9795c" }
];

const hairOptions = [
    { name: "Standard Hijab", price: 0, womenOnly: true },
    { name: "Short Hair", price: 0 },
    { name: "Long Hair", price: 0 },
    { name: "Curly / Wavy Hair", price: 5 },
    { name: "Custom Detailed Hair", price: 5 }
];

const outfitOptions = [
    { name: "Casual Outfit", price: 0 },
    { name: "Military / Army", price: 8 },
    { name: "General Uniform", price: 8 },
    { name: "Baju Melayu Traditional", price: 8 },
    { name: "Formal Suits", price: 8 },
    { name: "Dress", price: 8 }
];

const accessoryOptions = [
    { name: "Cap / Helmet or Head Accessories", price: 8 },
    { name: "Eyeglasses", price: 3 },
    { name: "Backpack / Bag", price: 5 },
    { name: "Graduation Robe", price: 8 },
    { name: "Other Details", price: 5 }
];

const keychainForms = document.getElementById("keychainForms");
const summaryItems = document.getElementById("summaryItems");
const totalPrice = document.getElementById("totalPrice");

// CREATE A KEYCHAIN FORM
function createKeychainForm(item, index) {

    const hairChoices = hairOptions.filter(option => {
        if (item.gender === "Male" && option.womenOnly) {
            return false;
        }
        return true;
    });

    return `
        <div class="section-heading">
            <span class="number">#${index + 1}</span>
            <h2>Keychain Customisation</h2>
        </div>

        <section class="form-section keychain-card">
            <label>CHOOSE DOLL GENDER (Fixed Size 12cm - 14cm)</label>
            <div class="gender-options">
                <button type="button" class="choice ${item.gender === "Male" ? "selected" : ""}" data-index="${index}" data-field="gender" data-value="Male">
                    <strong>Male Keychain</strong>
                    <small>Male crochet doll</small>
                    <span class="choice-price">BND $38.00</span>
                </button>
                <button type="button" class="choice ${item.gender === "Female" ? "selected" : ""}" data-index="${index}" data-field="gender" data-value="Female">
                    <strong>Female Keychain</strong>
                    <small>Female crochet doll</small>
                    <span class="choice-price">BND $40.00</span>
                </button>
            </div>

            <p class="small-instruction">SELECT SKIN TONE</p>
            <div class="skin-options">
                ${skinTones.map(tone => `
                    <button type="button" title="${tone.name}" class="skin-choice ${item.skin === tone.name ? "selected" : ""}" style="background:${tone.color}" data-index="${index}" data-field="skin" data-value="${tone.name}"></button>
                `).join("")}
            </div>
        </section>

        <!-- HAIR STYLING -->
        <section class="form-section">
            <h3>Hair & Head Styling</h3>

            <label class="reference-photo-box">
                <i class="fa-regular fa-image"></i>
                <div>
                    <strong>Reference Photo Customisation:</strong><br>
                    Upload your own picture or reference photo to help us understand your preferred hairstyle. <strong>(Required)</strong>
                    <span class="file-upload-status" id="hairFile-${index}"></span>
                </div>
                <input type="file" class="hidden-file" id="hairInput-${index}" accept="image/jpeg,image/png,image/webp" onchange="handleReferenceUpload(this, 'hairFile-${index}', ${index}, 'hairFile')">
            </label>
            <p class="field-error" id="hairErr-${index}" hidden>Please upload the required hairstyle reference photo.</p>

            <label>HAIRSTYLE / LENGTH / HEADWEAR OPTIONS</label>
            <div class="option-grid">
                ${hairChoices.map(option => `
                    <button type="button" class="option-button ${item.hair === option.name ? "selected" : ""}" data-index="${index}" data-field="hair" data-value="${option.name}">
                        ${option.name}
                        ${option.price > 0 ? `<small>+ BND $${option.price.toFixed(2)}</small>` : ""}
                    </button>
                `).join("")}
            </div>
            <p class="small-instruction">*Enter colour code from PDF if your choice of hairstyle or headwear is different.</p>
            
            <!-- ACCORDION BUTTON & DROPDOWN -->
            <button type="button" class="colour-chart-btn" onclick="toggleColourChart('hairChart-${index}')">
                <i class="fa-solid fa-palette"></i> View Colour Code Options
            </button>
            <div class="colour-dropdown" id="hairChart-${index}">
                <img src="photos/yarncolours.jpg" alt="Colour Chart">
            </div>

            <textarea placeholder="Type in the colour number & hairstyle (if any)..." data-index="${index}" data-note="hairNote">${item.hairNote}</textarea>
        </section>

        <!-- OUTFIT -->
        <section class="form-section">
            <h3>Outfit / Apparel</h3>

            <label class="reference-photo-box">
                <i class="fa-regular fa-image"></i>
                <div>
                    <strong>Reference Photo Customisation:</strong><br>
                    Upload your own picture or reference photo to help us understand your preferred outfit. <strong>(Required)</strong>
                    <span class="file-upload-status" id="outfitFile-${index}"></span>
                </div>
                <input type="file" class="hidden-file" id="outfitInput-${index}" accept="image/jpeg,image/png,image/webp" onchange="handleReferenceUpload(this, 'outfitFile-${index}', ${index}, 'outfitFile')">
            </label>
            <p class="field-error" id="outfitErr-${index}" hidden>Please upload the required outfit reference photo.</p>

            <label>SELECT OUTFIT / APPAREL CATEGORY</label>
            <div class="option-grid">
                ${outfitOptions.map(option => `
                    <button type="button" class="option-button ${item.outfit === option.name ? "selected" : ""}" data-index="${index}" data-field="outfit" data-value="${option.name}">
                        ${option.name}
                        ${option.price > 0 ? `<small>+ BND $${option.price.toFixed(2)}</small>` : ""}
                    </button>
                `).join("")}
            </div>
            <p class="small-instruction">*Enter colour code from PDF if your choice of outfit is different.</p>
            
            <!-- ACCORDION BUTTON & DROPDOWN -->
            <button type="button" class="colour-chart-btn" onclick="toggleColourChart('outfitChart-${index}')">
                <i class="fa-solid fa-palette"></i> View Colour Code Options
            </button>
            <div class="colour-dropdown" id="outfitChart-${index}">
                <img src="photos/yarncolours.jpg" alt="Colour Chart">
            </div>

            <textarea placeholder="Type in the colour number & outfit request..." data-index="${index}" data-note="outfitNote">${item.outfitNote}</textarea>
        </section>

        <!-- ACCESSORIES -->
        <section class="form-section">
            <h3>Optional Accessories & Details</h3>
            
            <label class="reference-photo-box">
                <i class="fa-regular fa-image"></i>
                <div>
                    <strong>Reference Photo Customisation:</strong><br>
                    Upload your own picture or reference photo to help us understand your preferred accessories. <small>(Optional)</small>
                    <span class="file-upload-status" id="accFile-${index}"></span>
                </div>
                <input type="file" class="hidden-file" id="accInput-${index}" accept="image/jpeg,image/png,image/webp" onchange="handleReferenceUpload(this, 'accFile-${index}', ${index}, 'accFile')">
            </label>

            <label>SELECT ACCESSORIES</label>
            <div class="accessory-list">
                ${accessoryOptions.map(option => {
                    const selected = item.accessories.includes(option.name);
                    return `
                        <label class="accessory-option ${selected ? "selected" : ""}">
                            <input type="checkbox" data-index="${index}" data-accessory="${option.name}" ${selected ? "checked" : ""}>
                            <span class="accessory-name">${option.name}</span>
                            <span class="accessory-price">BND $${option.price.toFixed(2)}</span>
                        </label>
                    `;
                }).join("")}
            </div>
            <p class="small-instruction">*Type in the colour number & accessories request if needed.</p>
            
            <!-- ACCORDION BUTTON & DROPDOWN -->
            <button type="button" class="colour-chart-btn" onclick="toggleColourChart('accChart-${index}')">
                <i class="fa-solid fa-palette"></i> View Colour Code Options
            </button>
            <div class="colour-dropdown" id="accChart-${index}">
                <img src="photos/yarncolours.jpg" alt="Colour Chart">
            </div>

            <textarea placeholder="Type in the colour number & accessories request..." data-index="${index}" data-note="accessoryNote">${item.accessoryNote}</textarea>
        </section>
    `;
}

// HANDLE FILE UPLOAD DISPLAY
// Stores the chosen File on the doll model so it survives the
// form re-render and is sent to the server on "Add to Cart".
function handleReferenceUpload(input, spanId, index, field) {
    const span = document.getElementById(spanId);
    const file = input.files && input.files.length > 0 ? input.files[0] : null;

    // Remember the file (or clear it) on the doll data.
    if (keychains[index]) keychains[index][field] = file;

    if (file) {
        // Basic client-side check: must be a real image file.
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            span.textContent = "Please choose a JPG, PNG or WEBP image.";
            span.style.color = "#e74c3c";
            if (keychains[index]) keychains[index][field] = null;
            input.value = "";
            return;
        }
        span.textContent = "✓ " + file.name;
        span.style.color = "";
    } else {
        span.textContent = "";
    }
}

// Re-attach stored files to the freshly rendered file inputs
// (the whole form is re-rendered every time an option is clicked).
function restoreFileInputs() {
    const slots = [
        ["hairFile", "hairInput"],
        ["outfitFile", "outfitInput"],
        ["accFile", "accInput"]
    ];

    keychains.forEach((item, index) => {
        slots.forEach(([modelField, inputId]) => {
            const file = item[modelField];
            const input = document.getElementById(inputId + "-" + index);
            const span = document.getElementById(modelField + "-" + index);
            if (!input || !file) return;

            try {
                const data = new DataTransfer();
                data.items.add(file);
                input.files = data.files;
            } catch (error) {
                // Older browsers cannot re-attach files; the customer
                // simply uploads the photo again.
            }

            if (span) {
                span.textContent = "✓ " + file.name;
                span.style.color = "";
            }
        });
    });
}

// DISPLAY KEYCHAIN FORMS
function renderKeychains() {
    keychainForms.innerHTML = keychains.map((item, index) => createKeychainForm(item, index)).join("");
    restoreFileInputs();
    updateSummary();
}

// ADD OR REMOVE SECOND KEYCHAIN
const addButton = document.getElementById("addKeychain");
addButton.addEventListener("change", function () {
    if (addButton.checked) {
        keychains.push({
            gender: "Female",
            basePrice: 40,
            skin: "Light",
            hair: "Standard Hijab",
            outfit: "Casual Outfit",
            accessories: [],
            hairNote: "",
            outfitNote: "",
            accessoryNote: ""
        });
    } else {
        keychains.splice(1, 1);
    }
    renderKeychains();
});

// HANDLE ALL OPTION BUTTONS
keychainForms.addEventListener("click", function(event) {
    const button = event.target.closest("button[data-field]");
    if (!button) return;

    const index = Number(button.dataset.index);
    const field = button.dataset.field;
    const value = button.dataset.value;
    const item = keychains[index];

    item[field] = value;

    if (field === "gender") {
        item.basePrice = value === "Male" ? 38 : 40;
        if (value === "Male" && item.hair === "Standard Hijab") {
            item.hair = "Short Hair";
        }
        if (value === "Female" && !item.hair) {
            item.hair = "Standard Hijab";
        }
        renderKeychains();
        return;
    }
    renderKeychains();
});

// HANDLE ACCESSORY CHECKBOXES
keychainForms.addEventListener("change", function(event) {
    if (!event.target.matches("input[data-accessory]")) return;

    const index = Number(event.target.dataset.index);
    const accessory = event.target.dataset.accessory;
    const selected = event.target.checked;

    if (selected) {
        keychains[index].accessories.push(accessory);
    } else {
        keychains[index].accessories = keychains[index].accessories.filter(item => item !== accessory);
    }
    renderKeychains();
});

// HANDLE TEXT NOTES
keychainForms.addEventListener("input", function(event) {
    const noteField = event.target.dataset.note;
    if (!noteField) return;

    const index = Number(event.target.dataset.index);
    keychains[index][noteField] = event.target.value;
});

// GET PRICE OF SELECTED OPTIONS
function getOptionPrice(options, selectedName) {
    const selected = options.find(option => option.name === selectedName);
    return selected ? selected.price : 0;
}

// UPDATE LIVE ORDER SUMMARY
function updateSummary() {
    let total = 0;

    summaryItems.innerHTML = keychains.map((item, index) => {
        const hairPrice = getOptionPrice(hairOptions, item.hair);
        const outfitPrice = getOptionPrice(outfitOptions, item.outfit);
        const accessoryPrice = item.accessories.reduce((sum, name) => sum + getOptionPrice(accessoryOptions, name), 0);
        const itemTotal = item.basePrice + hairPrice + outfitPrice + accessoryPrice;

        total += itemTotal;

        return `
            <div class="summary-item">
                <h3>Keychain #${index + 1} (${item.gender || "Choose gender"})</h3>
                <div class="summary-line"><span>Skin: ${item.skin || "Not selected"}</span><span></span></div>
                <div class="summary-line"><span>Hair: ${item.hair || "Not selected"}</span><span>${hairPrice ? "+$" + hairPrice.toFixed(2) : ""}</span></div>
                <div class="summary-line"><span>Outfit: ${item.outfit || "Not selected"}</span><span>${outfitPrice ? "+$" + outfitPrice.toFixed(2) : ""}</span></div>
                ${item.accessories.map(name => `
                    <div class="summary-line"><span>Acc: ${name}</span><span>+$${getOptionPrice(accessoryOptions, name).toFixed(2)}</span></div>
                `).join("")}
                <div class="summary-line"><strong>Item Total</strong><strong>BND $${itemTotal.toFixed(2)}</strong></div>
            </div>
        `;
    }).join("");

    totalPrice.textContent = total.toFixed(2);
}

//  MODAL & VALIDATION LOGIC
const DRAFT_KEY = "sdiyCustomDraftKeychain";
const confirmButton = document.getElementById("confirmCustom");
const specialNotes = document.getElementById("specialNotes");

confirmButton.addEventListener("click", function() {
    // 1. Clear previous errors
    document.querySelectorAll('.missing-error').forEach(el => el.classList.remove('missing-error'));
    document.querySelectorAll('.form-section').forEach(el => el.classList.remove('has-error'));

    let hasError = false;
    let firstErrorElement = null;

    // 2. Check every keychain for missing fields
    keychains.forEach((item, index) => {
        if (!item.gender) {
            document.querySelectorAll(`.choice[data-index="${index}"][data-field="gender"]`).forEach(el => el.classList.add('missing-error'));
            if (!firstErrorElement) firstErrorElement = document.querySelector(`.choice[data-index="${index}"][data-field="gender"]`);
            hasError = true;
        }
        if (!item.skin) {
            document.querySelectorAll(`.skin-choice[data-index="${index}"][data-field="skin"]`).forEach(el => el.classList.add('missing-error'));
            if (!firstErrorElement) firstErrorElement = document.querySelector(`.skin-choice[data-index="${index}"][data-field="skin"]`);
            hasError = true;
        }
        if (!item.hair) {
            document.querySelectorAll(`.option-button[data-index="${index}"][data-field="hair"]`).forEach(el => el.classList.add('missing-error'));
            if (!firstErrorElement) firstErrorElement = document.querySelector(`.option-button[data-index="${index}"][data-field="hair"]`);
            hasError = true;
        }
        if (!item.outfit) {
            document.querySelectorAll(`.option-button[data-index="${index}"][data-field="outfit"]`).forEach(el => el.classList.add('missing-error'));
            if (!firstErrorElement) firstErrorElement = document.querySelector(`.option-button[data-index="${index}"][data-field="outfit"]`);
            hasError = true;
        }

        // REQUIRED UPLOADS: hairstyle + outfit reference photos.
        // (Accessories photo stays optional.)
        [["hairFile", "hairErr"], ["outfitFile", "outfitErr"]].forEach(([modelField, errId]) => {
            const file = item[modelField];
            const valid = file instanceof File;

            const errBox = document.getElementById(`${errId}-${index}`);
            if (errBox) errBox.hidden = valid;

            // Highlight the upload box itself in red.
            const input = document.getElementById(
                (modelField === "hairFile" ? "hairInput-" : "outfitInput-") + index
            );
            const box = input ? input.closest(".reference-photo-box") : null;
            if (box) box.classList.toggle("missing-error", !valid);

            if (!valid) {
                if (!firstErrorElement && box) firstErrorElement = box;
                hasError = true;
            }
        });
    });

    // 3. If there's an error, highlight the whole section and scroll to it
    if (hasError) {
        document.querySelectorAll('.missing-error').forEach(el => {
            el.closest('.form-section').classList.add('has-error');
        });

        if (firstErrorElement) {
            firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return; // Stop! Don't show the modal.
    }

    // 4. If everything is perfect, save draft and show modal
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
        keychains: keychains,
        specialNotes: specialNotes.value
    }));

    document.getElementById("customModal").style.display = "flex";
});

function closeCustomModal() {
    document.getElementById("customModal").style.display = "none";
}

let isAddingToCart = false;

async function addToCart() {
    // One click = one custom order. Ignore repeat clicks while
    // the request is still running.
    if (isAddingToCart) return;
    isAddingToCart = true;

    const modal = document.getElementById("customModal");
    const modalAddButton = document.getElementById("modalAddToCart");
    if (modalAddButton) modalAddButton.disabled = true;
    confirmButton.disabled = true;

    const user = await SDIY.requireLogin("Please log in or sign up to submit your custom order.");
    if (!user) {
        isAddingToCart = false;
        if (modalAddButton) modalAddButton.disabled = false;
        confirmButton.disabled = false;
        return;
    }

    try {
        // Send the choices as JSON plus the reference photos as files.
        const formData = new FormData();
        formData.append("data", JSON.stringify({
            orderType: "keychain",
            keychains: keychains,
            specialNotes: specialNotes.value
        }));

        // Attach each doll's photos: files[0][hair], files[0][outfit], ...
        keychains.forEach((item, index) => {
            [["hairFile", "hair"], ["outfitFile", "outfit"], ["accFile", "accessory"]].forEach(([modelField, slot]) => {
                const file = item[modelField];
                if (file instanceof File) {
                    formData.append(`files[${index}][${slot}]`, file, file.name);
                }
            });
        });

        const result = await SDIY.api("custom_order.php", {
            method: "POST",
            form: formData
        });

        SDIY.setCartCount(result.count);
        sessionStorage.removeItem(DRAFT_KEY);

        // Close the popup only after the order was saved, then
        // stay on this page - the customer opens the cart
        // manually through the navigation bag icon.
        modal.style.display = "none";

        SDIY.toast(
            "Custom order added to your cart! Estimated total: BND $" +
            result.estimatedTotal.toFixed(2) +
            " - final price is confirmed on WhatsApp."
        );

    } catch (error) {
        // Keep the customer on the page and let them retry or
        // keep editing. Close the popup so the error toast
        // (which sits under the modal layer) stays visible.
        modal.style.display = "none";
        SDIY.toast(error.message, "error");
    } finally {
        isAddingToCart = false;
        if (modalAddButton) modalAddButton.disabled = false;
        confirmButton.disabled = false;
    }
}

function restoreDraft() {
    try {
        const draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || "null");
        if (!draft || !Array.isArray(draft.keychains) || !draft.keychains.length) return;

        keychains.splice(0, keychains.length, ...draft.keychains.slice(0, 2).map(item => ({
            ...item,
            basePrice: Number(item.basePrice) || 0,
            accessories: Array.isArray(item.accessories) ? item.accessories : []
        })));

        specialNotes.value = draft.specialNotes || "";
        addButton.checked = keychains.length > 1;

    } catch (error) {
        sessionStorage.removeItem(DRAFT_KEY);
    }
}

// --- TOGGLE COLOUR CHART DROPDOWN ---
function toggleColourChart(id) {
    const dropdown = document.getElementById(id);
    if (dropdown) {
        dropdown.classList.toggle("active");
    }
}

// INITIAL DISPLAY
restoreDraft();
renderKeychains();