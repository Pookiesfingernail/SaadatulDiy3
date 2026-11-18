<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

const MAX_QUANTITY = 99;

$userId = require_login();
$method = require_method('GET', 'POST', 'PATCH', 'DELETE');
$pdo = db();

if ($method === 'GET') {
    json_response(['success' => true] + cart_summary($userId));
}

$data = read_json();

// ---------- Find which cart row to change ----------
$cartItemId = positive_int($data['cart_item_id'] ?? null);
$productId = positive_int($data['product_id'] ?? null);
$variantId = positive_int($data['variant_id'] ?? null);

$stmt = $pdo->prepare('SELECT id FROM cart_items WHERE user_id = ?');
$stmt->execute([$userId]);
$ownedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if ($method === 'DELETE') {
    if ($cartItemId !== null) {
        $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')
            ->execute([$cartItemId, $userId]);
    } elseif ($productId !== null) {
        // Remove a specific design, or the plain product when no design given.
        $pdo->prepare(
            'DELETE FROM cart_items
             WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)'
        )->execute([$userId, $productId, $variantId]);
    } else {
        json_error('Please choose an item to remove.', 422);
    }

    json_response(['success' => true, 'message' => 'Item removed from your cart.'] + cart_summary($userId));
}

//  PATCH / POST 
if ($method === 'PATCH') {
    if ($cartItemId === null || !in_array($cartItemId, $ownedIds, true)) {
        json_error('Cart item not found.', 404);
    }

    $quantity = positive_int($data['quantity'] ?? null);
    if ($quantity === null || $quantity > MAX_QUANTITY) {
        json_error('Please choose a quantity between 1 and ' . MAX_QUANTITY . '.', 422);
    }

    $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
        ->execute([$quantity, $cartItemId]);

    json_response(['success' => true, 'message' => 'Cart updated.'] + cart_summary($userId));
}

// POST - add a normal product (optionally with a chosen design).
if ($productId === null) {
    json_error('Please choose a product.', 422);
}

$quantity = positive_int($data['quantity'] ?? 1);
if ($quantity === null || $quantity > MAX_QUANTITY) {
    json_error('Please choose a quantity between 1 and ' . MAX_QUANTITY . '.', 422);
}

$product = find_active_product($productId);
if (!$product) {
    json_error('This product is no longer available.', 404);
}

// Validate the chosen design belongs to this product.
$variant = null;
if ($variantId !== null) {
    $stmt = $pdo->prepare(
        'SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1'
    );
    $stmt->execute([$variantId, $productId]);
    $variant = $stmt->fetch() ?: null;

    if (!$variant) {
        json_error('This design is no longer available.', 404);
    }
}

// Same product + same design merges into one cart row;
// different designs stay as separate rows.
$stmt = $pdo->prepare(
    'SELECT id, quantity FROM cart_items
     WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)'
);
$stmt->execute([$userId, $productId, $variantId]);
$existing = $stmt->fetch();

$newQuantity = $existing ? (int) $existing['quantity'] + $quantity : $quantity;

if ($newQuantity > MAX_QUANTITY) {
    json_error('You can only have up to ' . MAX_QUANTITY . ' of this item in your cart.', 422);
}

$stock = $product['stock'] === null ? null : (int) $product['stock'];
if ($stock !== null && $newQuantity > $stock) {
    json_error($stock === 0
        ? 'Sorry, this item is out of stock.'
        : 'Sorry, only ' . $stock . ' left in stock.', 409);
}

if ($existing) {
    $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
        ->execute([$newQuantity, $existing['id']]);
} else {
    $pdo->prepare(
        'INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)'
    )->execute([$userId, $productId, $variantId, $quantity]);
}

$designName = $variant['name'] ?? null;

json_response([
    'success' => true,
    'message' => $product['name'] . ($designName ? ' (' . $designName . ')' : '') . ' added to your cart.',
] + cart_summary($userId));
