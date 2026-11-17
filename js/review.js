/* CUSTOMER REVIEWS PAGE - SA'ADATUL D.I.Y
   The 4 original reviews stay hard-coded in review.html.
   New reviews are saved in MySQL through api/reviews.php and
   appear in the scrollable review grid without a page refresh. */

const reviewForm = document.getElementById("reviewForm");
const reviewGrid = document.getElementById("reviewGrid");
const productImage = document.getElementById("productImage");
const imagePreview = document.getElementById("imagePreview");
const formMessage = document.getElementById("reviewMessage");
const MAX_REVIEW_PHOTOS = 4;

// Photos chosen by the customer but not yet submitted.
// Each entry: { file: File, url: string (object URL for preview) }.
let selectedPhotos = [];

// ---------- Star rating highlight ----------
// The form shows 5 labels (values 5..1). A label is "filled"
// when its star value is <= the selected rating, so picking
// 3 stars lights up exactly 3 stars.
function updateRatingHighlight() {
    const checked = reviewForm.querySelector('input[name="rating"]:checked');
    const rating = checked ? Number(checked.value) : 0;

    reviewForm.querySelectorAll('.rating-input label').forEach(label => {
        const input = reviewForm.querySelector('#' + label.getAttribute('for'));
        const value = input ? Number(input.value) : 0;
        label.classList.toggle('filled', rating > 0 && value <= rating);
    });
}

reviewForm.addEventListener('change', event => {
    if (event.target.name === 'rating') updateRatingHighlight();
});

// ---------- Photo previews with X remove button ----------
function renderPhotoPreviews() {
    imagePreview.innerHTML = '';

    selectedPhotos.forEach((photo, index) => {
        const item = document.createElement('div');
        item.className = 'photo-preview-item';

        const img = document.createElement('img');
        img.src = photo.url;
        img.alt = 'Review photo ' + (index + 1);
        item.appendChild(img);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'photo-remove-btn';
        remove.textContent = '×';
        remove.setAttribute('aria-label', 'Remove photo ' + (index + 1));
        // X removes ONLY this photo - text and rating stay untouched.
        remove.addEventListener('click', () => {
            URL.revokeObjectURL(photo.url);
            selectedPhotos.splice(index, 1);
            renderPhotoPreviews();
        });
        item.appendChild(remove);

        imagePreview.appendChild(item);
    });
}

productImage.addEventListener("change", function () {
    const incoming = Array.from(this.files || []);

    for (const file of incoming) {
        if (selectedPhotos.length >= MAX_REVIEW_PHOTOS) {
            alert("You can attach up to " + MAX_REVIEW_PHOTOS + " photos.");
            break;
        }
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            alert("Please select JPG, PNG or WEBP images only.");
            continue;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert("Each photo must be 5 MB or smaller.");
            continue;
        }
        selectedPhotos.push({ file: file, url: URL.createObjectURL(file) });
    }

    // Clear the input so the same file can be picked again after removal.
    this.value = "";
    renderPhotoPreviews();
});

function clearSelectedPhotos() {
    selectedPhotos.forEach(photo => URL.revokeObjectURL(photo.url));
    selectedPhotos = [];
    renderPhotoPreviews();
}

// Build one review card using the SAME design as the original 4.
function buildReviewCard(review) {
    const card = document.createElement("article");
    card.className = "review-card";

    const heading = document.createElement("h3");
    heading.textContent = review.customerName;
    card.appendChild(heading);

    if (review.id) {
        const deleteBtn = document.createElement("button");
        deleteBtn.className = "delete-review-btn";
        deleteBtn.innerHTML = "&times;";
        deleteBtn.title = "Delete this review";
        deleteBtn.addEventListener("click", async () => {
            if (confirm("Are you sure you want to delete this review?")) {
                try {
                    await SDIY.api("reviews.php", {
                        method: "POST",
                        body: { action: "delete", review_id: review.id }
                    });
                    card.remove(); // Removes it from the screen
                } catch (error) {
                    alert("Could not delete review: " + error.message);
                }
            }
        });
        card.appendChild(deleteBtn);
    }

    const stars = document.createElement("div");
    stars.className = "stars";
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement("span");
        star.className = "star" + (i <= review.rating ? " filled" : "");
        stars.appendChild(star);
    }
    card.appendChild(stars);

    const content = document.createElement("div");
    content.className = "review-content";

    const images = Array.isArray(review.images) && review.images.length
        ? review.images
        : (review.image ? [review.image] : []);

       if (images.length === 1) {
        const photo = document.createElement("img");
        photo.className = "review-photo";
        photo.src = images[0];
        photo.alt = "Customer's product";
        photo.setAttribute("data-fallback", "");
        photo.onerror = () => { photo.style.display = "none"; };
        content.appendChild(photo);
    } else if (images.length > 1) {
        const wrap = document.createElement("div");
        wrap.style.cssText = "display:flex;flex-wrap:wrap;gap:6px;flex-shrink:0;";
        images.forEach(src => {
            const photo = document.createElement("img");
            photo.className = "review-photo";
            photo.style.width = "72px";
            photo.style.height = "72px";
            photo.src = src;
            photo.alt = "Customer's product";
            photo.setAttribute("data-fallback", "");
            photo.onerror = () => { photo.style.display = "none"; };
            wrap.appendChild(photo);
        });
        content.appendChild(wrap);
    } else {
        const placeholder = document.createElement("div");
        placeholder.className = "review-photo placeholder-photo";
        placeholder.textContent = "photo";
        content.appendChild(placeholder);
    }

    const comment = document.createElement("p");
    comment.textContent = review.comment;
    content.appendChild(comment);
    card.appendChild(content);

    const productName = document.createElement("span");
    productName.className = "product-name";
    productName.textContent = review.productName;
    card.appendChild(productName);

    return card;
}

// Insert a review card right after the 4 original static reviews,
// so customer reviews appear together newest-first.
function insertReviewCard(review) {
    const card = buildReviewCard(review);
    const firstDbCard = reviewGrid.querySelector(".review-card[data-db]");
    if (firstDbCard) {
        reviewGrid.insertBefore(card, firstDbCard);
    } else {
        const cards = reviewGrid.querySelectorAll(".review-card");
        const anchor = cards[Math.min(3, cards.length - 1)];
        reviewGrid.insertBefore(card, anchor ? anchor.nextSibling : null);
    }
    card.setAttribute("data-db", "1");
}

// Load reviews saved in the database (newest first).
async function loadReviews() {
    try {
        const result = await SDIY.api("reviews.php");
        (result.reviews || []).forEach(review => {
            const card = buildReviewCard(review);
            card.setAttribute("data-db", "1");
            reviewGrid.appendChild(card);
        });
    } catch (error) {
        // Reviews still work if the backend is offline; just log it.
        console.warn("Could not load saved reviews:", error.message);
    }
}

// Submit review
reviewForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const name = document.getElementById("customerName").value.trim();
    const product = document.getElementById("productName").value.trim();
    const comments = document.getElementById("comments").value.trim();
    const rating = reviewForm.querySelector('input[name="rating"]:checked');

    if (!name || !product || !comments) {
        alert("Please fill in your name, product and comments.");
        return;
    }
    if (!rating) {
        alert("Please select a star rating.");
        return;
    }

    const submitButton = reviewForm.querySelector(".submit-button");
    submitButton.disabled = true;
    submitButton.textContent = "Submitting...";

    try {
        const formData = new FormData();
        formData.append("customer_name", name);
        formData.append("product_name", product);
        formData.append("rating", rating.value);
        formData.append("comment", comments);
        selectedPhotos.forEach(photo => {
            formData.append("images[]", photo.file, photo.file.name);
        });

        const result = await SDIY.api("reviews.php", { method: "POST", form: formData });

        // Show the new review immediately (it is also saved in MySQL).
        insertReviewCard(result.review);

        reviewForm.reset();
        clearSelectedPhotos();
        updateRatingHighlight();

        if (formMessage) {
            formMessage.textContent = result.message || "Thank you for sharing your review!";
            formMessage.className = "form-message success";
        }

    } catch (error) {
        if (formMessage) {
            formMessage.textContent = error.message;
            formMessage.className = "form-message error";
        } else {
            alert(error.message);
        }
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = "SUBMIT";
    }
});

loadReviews();
