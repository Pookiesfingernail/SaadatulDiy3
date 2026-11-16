/* SHOP PAGE - SA'ADATUL D.I.Y */

const shopContainer = document.querySelector('.shop-container');
const filterButtons = document.querySelectorAll('.filter-btn');
const categorySections = document.querySelectorAll('.category-section');
const searchTerm = new URLSearchParams(window.location.search).get('search') || '';

// --- PRODUCT CARD ---
function productCard(product) {
    const name = SDIY.escapeHtml(product.name);

    return `
        <div class="product-card" data-product-id="${product.id}">
            <div class="card-image-wrapper">
                <a href="product.html?id=${product.id}">
                    <img src="${SDIY.escapeHtml(product.image)}" alt="${name}" data-fallback>
                </a>
            </div>
            <div class="card-info">
                <h3>${name}</h3>
                <p class="price">BND ${SDIY.formatPrice(product.price)}</p>

                <div class="card-actions">
                    <a href="product.html?id=${product.id}" class="view-detail-btn">View Detail</a>
                    <button class="wishlist-btn ${product.isFavourite ? 'active' : ''}"
                        data-product-id="${product.id}"
                        aria-pressed="${product.isFavourite}"
                        aria-label="${product.isFavourite ? 'Remove from favourites' : 'Add to favourites'}">
                        <i class="${product.isFavourite ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function setHeart(button, isFavourite) {
    const icon = button.querySelector('i');
    button.classList.toggle('active', isFavourite);
    button.setAttribute('aria-pressed', String(isFavourite));
    button.setAttribute('aria-label', isFavourite ? 'Remove from favourites' : 'Add to favourites');
    icon.classList.toggle('fa-solid', isFavourite);
    icon.classList.toggle('fa-regular', !isFavourite);
}

// --- LOAD PRODUCTS ---
async function loadProducts() {
    try {
        const query = searchTerm ? '?search=' + encodeURIComponent(searchTerm) : '';
        const { products } = await SDIY.api('products.php' + query);

        categorySections.forEach(section => {
            const category = section.id.replace('section-', '');
            const grid = section.querySelector('.product-grid');
            const items = products.filter(product => product.category === category);

            grid.innerHTML = items.length
                ? items.map(productCard).join('')
                : '<p class="price" style="grid-column: 1 / -1;">No products in this category yet.</p>';

            section.dataset.empty = String(items.length === 0);
        });

        if (searchTerm) {
            categorySections.forEach(section => {
                section.style.display = section.dataset.empty === 'true' ? 'none' : 'block';
            });

            let note = document.querySelector('.search-result-note');
            if (!note) {
                note = document.createElement('p');
                note.className = 'search-result-note';
                note.style.cssText = 'text-align:center;color:#465b8b;margin:0 0 20px;';
                shopContainer.prepend(note);
            }
            note.innerHTML = products.length
                ? `Showing results for "<strong>${SDIY.escapeHtml(searchTerm)}</strong>" &middot; <a href="shop.html">Show all products</a>`
                : `No products found for "<strong>${SDIY.escapeHtml(searchTerm)}</strong>". <a href="shop.html">Show all products</a>`;
        }

        applyFilter('all');
    } catch (error) {
        SDIY.toast(error.message, 'error');
    }
}

// --- FAVOURITES ---
shopContainer.addEventListener('click', async function (e) {
    const button = e.target.closest('.wishlist-btn');
    if (!button) return;

    e.preventDefault();

    const productId = Number(button.dataset.productId);
    if (!productId) return;

    const makeFavourite = !button.classList.contains('active');
    button.disabled = true;

    try {
        const result = await SDIY.setFavourite(productId, makeFavourite);
        if (result) {
            setHeart(button, makeFavourite);
            SDIY.toast(result.message);
        }
    } catch (error) {
        SDIY.toast(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

// --- CATEGORY FILTERING ---
const MAX_VISIBLE_PRODUCTS = 5;
const expandedCategories = new Set();
let currentFilter = 'all';

function applyFilter(filterValue) {
    currentFilter = filterValue;

    // Update the active state of the top buttons
    filterButtons.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-filter') === filterValue);
    });

    // Show/Hide the sections
    categorySections.forEach(section => {
        const category = section.id.replace('section-', '');
        const show = filterValue === 'all' || category === filterValue;
        section.style.display = show ? 'block' : 'none';
        
        if (!show) return;

        const cards = section.querySelectorAll('.product-card');
        const viewAllBtn = section.querySelector('.view-all');
        
        if (filterValue === 'all' && !searchTerm) {
            const expanded = expandedCategories.has(category);
            let visible = 0;
            
            cards.forEach(card => {
                const showCard = expanded || visible < MAX_VISIBLE_PRODUCTS;
                card.style.display = showCard ? 'flex' : 'none';
                if (visible < MAX_VISIBLE_PRODUCTS || expanded) visible++;
            });
            
            if (viewAllBtn) {
                viewAllBtn.style.display = 'inline-block';
                viewAllBtn.textContent = expanded ? 'Show less' : 'View all';
            }
        } else {

            cards.forEach(card => { card.style.display = 'flex'; });

            if (viewAllBtn) viewAllBtn.style.display = 'none';
        }
    });
}

// Top Filter Buttons Click 
filterButtons.forEach(button => {
    button.addEventListener('click', function () {
        const value = this.getAttribute('data-filter');
        if (value === 'all') expandedCategories.clear();
        applyFilter(value);
    });
});

// "View All"  
categorySections.forEach(section => {
    const viewAll = section.querySelector('.view-all');
    if (!viewAll) return;

    viewAll.addEventListener('click', e => {
        e.preventDefault();
        const category = section.id.replace('section-', '');


        if (currentFilter === 'all') {
            applyFilter(category);
        } else {

            if (expandedCategories.has(category)) {
                expandedCategories.delete(category);
            } else {
                expandedCategories.add(category);
            }
            applyFilter(category);
        }

        window.scrollTo({ top: shopContainer.offsetTop - 20, behavior: 'smooth' });
    });
});

loadProducts();