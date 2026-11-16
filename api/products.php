<?php
/*
 * PRODUCTS - GET
 *   api/products.php                 all products (optional ?category=keychain, ?search=word)
 *   api/products.php?id=2            one product + designs (variants) + gallery + other designs
 *   api/products.php?slug=rbaf-plushie   same, looked up by slug
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

require_method('GET');

$pdo = db();
$favouriteIds = favourite_ids(current_user_id());

// Load the design options (variants) of a product.
function product_variants(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT id, name, color, image_path, price
         FROM product_variants
         WHERE product_id = ? AND is_active = 1
         ORDER BY sort_order, id'
    );
    $stmt->execute([$productId]);

    return array_map(fn ($v) => [
        'id'    => (int) $v['id'],
        'name'  => $v['name'],
        'color' => $v['color'],
        'image' => $v['image_path'],
        'price' => $v['price'] === null ? null : (float) $v['price'],
    ], $stmt->fetchAll());
}

// ---------- Single product ----------
$slug = clean_text($_GET['slug'] ?? '');
if (isset($_GET['id']) || $slug !== '') {
    $productId = positive_int($_GET['id'] ?? 0);
    if ($slug !== '') {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch() ?: null;
    } else {
        $row = $productId ? find_active_product($productId) : null;
    }
    if ($row) {
        $productId = (int) $row['id'];
    }

    if (!$row) {
        json_error('Product not found.', 404);
    }

    $stmt = $pdo->prepare(
        'SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id'
    );
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [$row['image']];

    // "Other designs" = other products in the same category.
    $stmt = $pdo->prepare(
        'SELECT * FROM products
         WHERE is_active = 1 AND id <> ? AND category = ?
         ORDER BY id LIMIT 6'
    );
    $stmt->execute([$productId, $row['category']]);
    $otherDesigns = array_map(fn ($p) => product_payload($p, $favouriteIds), $stmt->fetchAll());

    $product = product_payload($row, $favouriteIds);
    $product['images'] = $images;

    json_response([
        'success'      => true,
        'product'      => $product,
        // EDIT PRODUCT DESIGNS: rows in the product_variants table.
        // If this list has 0 or 1 items the "Other Designs" section hides.
        'variants'     => product_variants($productId),
        'otherDesigns' => $otherDesigns,
    ]);
}

// ---------- Product list ----------
$where = ['is_active = 1'];
$params = [];

$category = clean_text($_GET['category'] ?? '');
if ($category !== '' && in_array($category, PRODUCT_CATEGORIES, true)) {
    $where[] = 'category = ?';
    $params[] = $category;
}

$search = clean_text($_GET['search'] ?? '');
if ($search !== '') {
    // Search matches the product name only.
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = 'name LIKE ?';
    $params[] = $like;
}

$stmt = $pdo->prepare(
    'SELECT * FROM products WHERE ' . implode(' AND ', $where) . ' ORDER BY category, id'
);
$stmt->execute($params);

json_response([
    'success'  => true,
    'products' => array_map(fn ($p) => product_payload($p, $favouriteIds), $stmt->fetchAll()),
]);
