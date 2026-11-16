<?php
/*
 * ORDER HISTORY (login required) - GET
 * Returns shop orders (with items) and custom orders for the account page.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/custom-orders.php';

$userId = require_login();
require_method('GET');
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id, fulfilment, shipping_zone, notes, subtotal, delivery_fee, total, status, payment_status, created_at
     FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT order_id, product_id, product_name, design_name, image, unit_price, quantity, line_total
         FROM order_items WHERE order_id IN ($placeholders) ORDER BY id"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $item) {
        $itemsByOrder[(int) $item['order_id']][] = [
            'productId' => $item['product_id'] === null ? null : (int) $item['product_id'],
            'name'      => $item['product_name'],
            'design'    => $item['design_name'],
            'image'     => $item['image'],
            'price'     => (float) $item['unit_price'],
            'quantity'  => (int) $item['quantity'],
            'lineTotal' => (float) $item['line_total'],
        ];
    }
}

$orderList = array_map(fn ($order) => [
    'id'           => (int) $order['id'],
    'fulfilment'   => $order['fulfilment'],
    'shippingZone' => $order['shipping_zone'],
    'notes'        => $order['notes'],
    'subtotal'     => (float) $order['subtotal'],
    'deliveryFee'  => (float) $order['delivery_fee'],
    'total'        => (float) $order['total'],
    'status'       => $order['status'],
    'paymentStatus' => $order['payment_status'],
    'createdAt'    => $order['created_at'],
    'items'        => $itemsByOrder[(int) $order['id']] ?? [],
], $orders);

$stmt = $pdo->prepare(
    'SELECT id, order_type, details, special_notes, estimated_total, final_price, status, order_id, created_at
     FROM custom_orders WHERE user_id = ? ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$userId]);

$user = current_user();
$customList = [];

foreach ($stmt->fetchAll() as $row) {
    $keychains = json_decode($row['details'], true) ?: [];
    $notes = (string) ($row['special_notes'] ?? '');

    $customList[] = [
        'id'             => (int) $row['id'],
        'type'           => $row['order_type'],
        'orderId'        => $row['order_id'] === null ? null : (int) $row['order_id'],
        'keychains'      => $keychains,
        'specialNotes'   => $notes,
        'estimatedTotal' => (float) $row['estimated_total'],
        'finalPrice'     => $row['final_price'] === null ? null : (float) $row['final_price'],
        'status'         => $row['status'],
        'createdAt'      => $row['created_at'],
        'whatsappUrl'    => custom_order_whatsapp_url((int) $row['id'], $user, (string) $row['order_type'], $keychains, $notes, (float) $row['estimated_total']),
    ];
}

json_response([
    'success'      => true,
    'orders'       => $orderList,
    'customOrders' => $customList,
]);
