<?php
/*
 * CUSTOM ORDER (login required) - POST multipart/form-data
 * Sent from custom.html (keychain) and custom2.html (figurine).
 * Saves the custom order + dolls + uploaded photos in MySQL,
 * adds it to the customer's cart and returns the cart summary.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/custom-orders.php';

const CUSTOM_UPLOAD_DIR = __DIR__ . '/../uploads/custom-orders';

$userId = require_login();
require_method('POST');

// Read the JSON part of the form 
$raw = clean_text($_POST['data'] ?? '');
$payload = $raw !== '' ? json_decode($raw, true) : null;

if (!is_array($payload)) {
    json_error('Invalid custom order data.', 422);
}

$orderType = $payload['orderType'] ?? null;
$itemsKey = $orderType === 'figurine' ? 'figurines' : 'keychains';
$items = $payload[$itemsKey] ?? null;

[$orderType, $cleanItems, $estimatedTotal] = validate_custom_items($items, $orderType);

$specialNotes = clean_text($payload['specialNotes'] ?? '');
if (text_length($specialNotes) > CUSTOM_SPECIAL_NOTE_LIMIT) {
    json_error('Special notes must be ' . CUSTOM_SPECIAL_NOTE_LIMIT . ' characters or fewer.', 422);
}

// Save order, dolls and photos 
$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare(
        'INSERT INTO custom_orders (user_id, order_type, details, special_notes, estimated_total, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        $orderType,
        json_encode($cleanItems, JSON_UNESCAPED_UNICODE),
        $specialNotes === '' ? null : $specialNotes,
        money($estimatedTotal),
        'in_cart',
    ]);

    $customOrderId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO custom_order_items
            (custom_order_id, position, gender, skin_tone, hair, hair_note, outfit, outfit_note,
             accessories, accessory_note, item_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertFile = $pdo->prepare(
        'INSERT INTO custom_order_files (custom_order_item_id, slot, file_path, original_name)
         VALUES (?, ?, ?, ?)'
    );

    $uploadedFiles = [];

    foreach ($cleanItems as $index => $item) {
        $insertItem->execute([
            $customOrderId,
            $index + 1,
            $item['gender'],
            $item['skin'],
            $item['hair'],
            $item['hairNote'] === '' ? null : $item['hairNote'],
            $item['outfit'],
            $item['outfitNote'] === '' ? null : $item['outfitNote'],
            json_encode($item['accessories'], JSON_UNESCAPED_UNICODE),
            $item['accessoryNote'] === '' ? null : $item['accessoryNote'],
            money($item['itemTotal']),
        ]);

        $itemId = (int) $pdo->lastInsertId();

        // Save the reference photos for this doll (hair + outfit required).
        $saved = store_doll_files(normalize_custom_files($_FILES['files'] ?? []), $index, CUSTOM_UPLOAD_DIR);

        foreach ($saved as $file) {
            $insertFile->execute([$itemId, $file['slot'], $file['file_path'], $file['original_name']]);
            $uploadedFiles[] = CUSTOM_UPLOAD_DIR . '/' . $file['file_path'];
        }
    }

    // Add the custom order to the customer's cart (quantity 1).
    // One cart row per custom order - submitting again creates a new order.
    $pdo->prepare(
        'INSERT INTO cart_items (user_id, custom_order_id, quantity) VALUES (?, ?, 1)'
    )->execute([$userId, $customOrderId]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($uploadedFiles ?? [] as $path) {
        if (is_file($path)) unlink($path);
    }
    throw $error;
}

$typeLabel = CUSTOM_TYPES[$orderType]['label'];

json_response([
    'success'        => true,
    'message'        => $typeLabel . ' added to your cart! We will confirm the final price with you on WhatsApp.',
    'customOrderId'  => $customOrderId,
    'estimatedTotal' => $estimatedTotal,
    'whatsappUrl'    => custom_order_whatsapp_url($customOrderId, current_user(), $orderType, $cleanItems, $specialNotes, $estimatedTotal),
] + cart_summary($userId), 201);
