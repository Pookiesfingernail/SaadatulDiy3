<?php
/*
 * CHECKOUT (login required) - POST multipart/form-data from checkout.html
 * Fields: email, full_name, phone, country, fulfilment (delivery|pickup),
 *         address, city, postcode, shipping (5|7|9|10), proof (file)
 *
 * Creates ONE order from the customer's saved cart. The cart can
 * contain normal products (with designs) AND custom orders.
 * Afterwards an order notification email is sent to
 * ORDER_NOTIFY_EMAIL (saadatul.art2@gmail.com) via SMTP.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mailer.php';
require __DIR__ . '/../includes/custom-orders.php';

const PROOF_MAX_BYTES = 5 * 1024 * 1024;
const PROOF_DIR = __DIR__ . '/../uploads/payment-proofs';
const CUSTOM_UPLOAD_DIR = __DIR__ . '/../uploads/custom-orders';

// value of the shipping radio button => [zone, fee]
const SHIPPING_ZONES = [
    '5'  => ['Brunei Muara', 5.00],
    '7'  => ['Tutong', 7.00],
    '9'  => ['Belait', 9.00],
    '10' => ['Temburong', 10.00],
];

$userId = require_login();
require_method('POST');

// ---------- Customer details ----------
$fields = [];
foreach (['email', 'full_name', 'phone', 'country', 'address', 'city', 'postcode', 'fulfilment', 'shipping'] as $name) {
    $fields[$name] = clean_text($_POST[$name] ?? '');
}

$fields['email'] = strtolower($fields['email']);
$isDelivery = $fields['fulfilment'] !== 'pickup';

$errors = [];

if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL) || strlen($fields['email']) > 254) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (text_length($fields['full_name']) < 2 || text_length($fields['full_name']) > 100) {
    $errors['fullname'] = 'Please enter your full name.';
}

// Phone must be a Brunei number: +673 followed by exactly 7 digits.
$phoneDigits = preg_replace('/\D/', '', $fields['phone']) ?? '';
if (preg_match('/^673\d{7}$/', $phoneDigits) !== 1) {
    $errors['phone'] = 'Please enter a valid Brunei phone number (+673 followed by 7 digits).';
} else {
    // Store the full international format, e.g. +6737123456
    $fields['phone'] = '+673' . substr($phoneDigits, 3);
}

if ($fields['country'] === '' || text_length($fields['country']) > 100) {
    $errors['country'] = 'Please enter your country.';
}

$zone = null;
$deliveryFee = 0.0;

// Optional order notes written by the customer on the cart page.
$orderNotes = clean_text($_POST['notes'] ?? '');
if (text_length($orderNotes) > 2000) {
    $errors['notes'] = 'Order notes must be 2000 characters or fewer.';
}

if ($isDelivery) {
    if ($fields['address'] === '' || text_length($fields['address']) > 255) {
        $errors['address'] = 'Please enter your delivery address.';
    }
    if ($fields['city'] === '' || text_length($fields['city']) > 100) {
        $errors['city'] = 'Please enter your city.';
    }
    if ($fields['postcode'] === '' || text_length($fields['postcode']) > 20) {
        $errors['postcode'] = 'Please enter your postcode.';
    }
    if (!isset(SHIPPING_ZONES[$fields['shipping']])) {
        $errors['shipping'] = 'Please choose a delivery district.';
    } else {
        [$zone, $deliveryFee] = SHIPPING_ZONES[$fields['shipping']];
    }
}

if ($errors) {
    json_error(reset($errors), 422, ['errors' => $errors]);
}

// ---------- Payment screenshot ----------
$file = $_FILES['proof'] ?? null;

if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_error('Please upload your BIBD bank transfer screenshot before placing your order.', 422);
}
if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > PROOF_MAX_BYTES) {
    json_error('Payment screenshot must be 5 MB or smaller.', 422);
}
if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    json_error('The payment screenshot could not be uploaded. Please try again.', 422);
}

$head = (string) file_get_contents($file['tmp_name'], false, null, 0, 12);
$extension = match (true) {
    str_starts_with($head, "\xFF\xD8\xFF")                           => 'jpg',
    str_starts_with($head, "\x89PNG\r\n\x1A\n")                      => 'png',
    str_starts_with($head, 'GIF8')                                   => 'gif',
    str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
    str_starts_with($head, '%PDF')                                   => 'pdf',
    default                                                        => null,
};

if ($extension === null) {
    json_error('Payment proof must be an image (JPG, PNG, GIF, WEBP) or a PDF file.', 422);
}

// ---------- Create the order ----------
$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'SELECT ci.id AS cart_item_id, ci.product_id, ci.variant_id, ci.custom_order_id, ci.quantity,
                p.id AS pid, p.name, p.category, p.price, p.image, p.stock,
                v.id AS vid, v.name AS variant_name, v.price AS variant_price, v.image_path AS variant_image,
                co.id AS cid, co.order_type AS custom_type, co.estimated_total AS custom_total
         FROM cart_items ci
         LEFT JOIN products p ON p.id = ci.product_id AND p.is_active = 1
         LEFT JOIN product_variants v ON v.id = ci.variant_id AND v.is_active = 1
         LEFT JOIN custom_orders co ON co.id = ci.custom_order_id
         WHERE ci.user_id = ?
         ORDER BY ci.added_at, ci.id
         FOR UPDATE'
    );
    $stmt->execute([$userId]);
    $cartRows = $stmt->fetchAll();

    if (!$cartRows) {
        $pdo->rollBack();
        json_error('Your cart is empty. Please add items from the Shop page.', 422);
    }

    $subtotal = 0.0;
    $orderRows = [];   // rows ready for order_items
    $customIds = [];   // custom orders included in this purchase

    foreach ($cartRows as $row) {
        $quantity = (int) $row['quantity'];

        if ($row['custom_order_id'] !== null) {
            // Custom made-to-order item: price comes from the saved custom order.
            $price = (float) $row['custom_total'];
            $typeLabel = $row['custom_type'] === 'figurine' ? 'Custom Figurine' : 'Custom Keychain';

            $orderRows[] = [
                'item_type'       => 'custom',
                'product_id'      => null,
                'variant_id'      => null,
                'custom_order_id' => (int) $row['custom_order_id'],
                'design_name'     => null,
                'name'            => $typeLabel . ' Order #' . (int) $row['cid'],
                'category'        => $row['custom_type'],
                'image'           => 'photos/custom-dolls.png',
                'price'           => $price,
                'quantity'        => $quantity,
            ];
            $customIds[] = (int) $row['custom_order_id'];
        } else {
            if ($row['pid'] === null) {
                $pdo->rollBack();
                json_error('An item in your cart is no longer available. Please remove it and try again.', 409);
            }

            if ($row['stock'] !== null && $quantity > (int) $row['stock']) {
                $pdo->rollBack();
                json_error('Sorry, only ' . (int) $row['stock'] . ' of "' . $row['name'] . '" left in stock. Please update your cart.', 409);
            }

            $price = $row['variant_price'] !== null ? (float) $row['variant_price'] : (float) $row['price'];
            $design = $row['variant_name'];

            $orderRows[] = [
                'item_type'       => 'product',
                'product_id'      => (int) $row['pid'],
                'variant_id'      => $row['vid'] !== null ? (int) $row['vid'] : null,
                'custom_order_id' => null,
                'design_name'     => $design,
                'name'            => $row['name'] . ($design ? ' — ' . $design : ''),
                'category'        => $row['category'],
                'image'           => $row['variant_image'] ?: $row['image'],
                'price'           => $price,
                'quantity'        => $quantity,
            ];
        }

        $subtotal += round($orderRows[count($orderRows) - 1]['price'] * $quantity, 2);
    }

    $subtotal = round($subtotal, 2);
    $total = round($subtotal + $deliveryFee, 2);

    if (!is_dir(PROOF_DIR) && !mkdir(PROOF_DIR, 0750, true) && !is_dir(PROOF_DIR)) {
        throw new RuntimeException('Cannot create payment proof folder.');
    }

    $proofName = 'order-' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $proofPath = PROOF_DIR . '/' . $proofName;

    if (!move_uploaded_file($file['tmp_name'], $proofPath)) {
        throw new RuntimeException('Cannot save payment proof.');
    }

    $pdo->prepare(
        'INSERT INTO orders
            (user_id, email, full_name, phone, country, fulfilment, address, city, postcode,
             shipping_zone, notes, subtotal, delivery_fee, total, payment_proof, payment_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending_verification")'
    )->execute([
        $userId,
        $fields['email'],
        $fields['full_name'],
        $fields['phone'],
        $fields['country'],
        $isDelivery ? 'delivery' : 'pickup',
        $isDelivery ? $fields['address'] : null,
        $isDelivery ? $fields['city'] : null,
        $isDelivery ? $fields['postcode'] : null,
        $zone,
        $orderNotes === '' ? null : $orderNotes,
        money($subtotal),
        money($deliveryFee),
        money($total),
        $proofName,
    ]);

    $orderId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, item_type, product_id, variant_id, custom_order_id, design_name,
             product_name, image, unit_price, quantity, line_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $reduceStock = $pdo->prepare(
        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL'
    );

    foreach ($orderRows as $item) {
        $insertItem->execute([
            $orderId,
            $item['item_type'],
            $item['product_id'],
            $item['variant_id'],
            $item['custom_order_id'],
            $item['design_name'],
            $item['name'],
            $item['image'],
            money($item['price']),
            $item['quantity'],
            money($item['price'] * $item['quantity']),
        ]);

        if ($item['item_type'] === 'product') {
            $reduceStock->execute([$item['quantity'], $item['product_id']]);
        }
    }

    // Link purchased custom orders to this order.
    if ($customIds) {
        $placeholders = implode(',', array_fill(0, count($customIds), '?'));
        $pdo->prepare(
            "UPDATE custom_orders SET order_id = ?, status = 'ordered' WHERE id IN ($placeholders)"
        )->execute(array_merge([$orderId], $customIds));
    }

    $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);

    // Remember delivery details for next time (only fills empty profile fields).
    $pdo->prepare(
        'UPDATE users SET
            phone    = COALESCE(NULLIF(phone, \'\'), ?),
            address  = COALESCE(NULLIF(address, \'\'), ?),
            city     = COALESCE(NULLIF(city, \'\'), ?),
            postcode = COALESCE(NULLIF(postcode, \'\'), ?)
         WHERE id = ?'
    )->execute([
        $fields['phone'],
        $isDelivery ? $fields['address'] : null,
        $isDelivery ? $fields['city'] : null,
        $isDelivery ? $fields['postcode'] : null,
        $userId,
    ]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($proofPath) && is_file($proofPath)) {
        unlink($proofPath);
    }
    throw $error;
}

// ---------- Order notification email ----------
// Sent to ORDER_NOTIFY_EMAIL (saadatul.art2@gmail.com) via SMTP.
// The order is already saved - a failed email never loses it.
$emailStatus = 'skipped';
$emailError = null;

try {
    $customer = current_user();
    [$subject, $html, $attachments] = build_order_email($orderId, $fields, $orderRows, $subtotal, $deliveryFee, $total, $zone, $isDelivery, $proofPath, $orderNotes);

    $pdo->prepare(
        'INSERT INTO email_notifications (order_id, recipient, subject, status, attempts)
         VALUES (?, ?, ?, ?, 1)'
    )->execute([$orderId, ORDER_NOTIFY_EMAIL, $subject, 'pending']);

    $result = send_smtp_mail(ORDER_NOTIFY_EMAIL, $subject, $html, $attachments);

    if ($result['ok']) {
        $emailStatus = 'sent';
        $pdo->prepare(
            "UPDATE email_notifications SET status = 'sent', sent_at = NOW() WHERE order_id = ?"
        )->execute([$orderId]);
    } else {
        $emailStatus = 'failed';
        $emailError = $result['error'];
        $pdo->prepare(
            "UPDATE email_notifications SET status = 'failed', last_error = ? WHERE order_id = ?"
        )->execute([$emailError, $orderId]);
        error_log('[SaadatulDIY] Order #' . $orderId . ' email failed: ' . $emailError);
    }

    $pdo->prepare('UPDATE orders SET email_status = ? WHERE id = ?')
        ->execute([$emailStatus, $orderId]);
} catch (Throwable $error) {
    $emailStatus = 'failed';
    $emailError = $error->getMessage();
    error_log('[SaadatulDIY] Order #' . $orderId . ' email error: ' . $emailError);

    try {
        $pdo->prepare('UPDATE orders SET email_status = ? WHERE id = ?')
            ->execute(['failed', $orderId]);
    } catch (Throwable $ignored) {
    }
}

$message = 'Thank you for your order! Your order number is #' . $orderId . '.';
if ($emailStatus === 'sent') {
    $message .= ' A confirmation email has been sent.';
}

json_response([
    'success'       => true,
    'message'       => $message,
    'orderId'       => $orderId,
    'orderNumber'   => 'SD-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
    'total'         => $total,
    'status'        => 'pending',
    'paymentStatus' => 'pending_verification',
    'emailStatus'   => $emailStatus,
], 201);

/**
 * Builds the order notification email (HTML) and collects the
 * files to attach: custom reference photos + payment proof.
 * Returns [subject, htmlBody, attachmentPaths].
 */
function build_order_email(int $orderId, array $fields, array $orderRows, float $subtotal, float $deliveryFee, float $total, ?string $zone, bool $isDelivery, string $proofPath, string $orderNotes = ''): array
{
    $e = fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    $orderNumber = 'SD-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
    $subject = "New Sa'adatulDIY Order — {$orderNumber}";

    $productRows = array_filter($orderRows, fn ($i) => $i['item_type'] === 'product');
    $customRows = array_filter($orderRows, fn ($i) => $i['item_type'] === 'custom');

    $html = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto">';
    $html .= '<h2 style="color:#465b8b">Sa\'adatul D.I.Y &mdash; New Order ' . $e($orderNumber) . '</h2>';
    $html .= '<table cellpadding="4" cellspacing="0" style="border-collapse:collapse;margin-bottom:15px">';
    $html .= '<tr><td><strong>Order number:</strong></td><td>' . $e($orderNumber) . '</td></tr>';
    $html .= '<tr><td><strong>Order date:</strong></td><td>' . date('d M Y, h:i A') . '</td></tr>';
    $html .= '<tr><td><strong>Order status:</strong></td><td>Pending (awaiting payment verification)</td></tr>';
    $html .= '<tr><td><strong>Grand total:</strong></td><td><strong>BND ' . money($total) . '</strong></td></tr>';
    $html .= '</table>';
    $html .= '<p><strong>Customer:</strong> ' . $e($fields['full_name']) . '<br>';
    $html .= '<strong>Email:</strong> ' . $e($fields['email']) . '<br>';
    $html .= '<strong>Phone:</strong> ' . $e($fields['phone']) . '<br>';
    $html .= '<strong>Country:</strong> ' . $e($fields['country']) . '<br>';
    $html .= '<strong>Fulfilment:</strong> ' . ($isDelivery ? 'Delivery - ' . $e($zone ?? '') : 'Pickup (Box Of Cravings, Madang)') . '</p>';

    if ($isDelivery) {
        $html .= '<p><strong>Address:</strong> ' . $e($fields['address']) . ', ' . $e($fields['city'])
            . ', ' . $e($fields['postcode']) . ', Brunei Darussalam</p>';
    }

    // ---- Section A: normal products ----
    if ($productRows) {
        $html .= '<h3 style="color:#465b8b">Products Ordered</h3><table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
        $html .= '<tr style="background:#eef2fb"><th align="left">Product</th><th>Category</th><th>Design</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr>';
        foreach ($productRows as $item) {
            $html .= '<tr><td>' . $e($item['name']) . '</td><td>' . $e(ucfirst($item['category'] ?? '-')) . '</td><td>' . $e($item['design_name'] ?? '-') . '</td>'
                . '<td>' . $item['quantity'] . '</td><td>BND ' . money($item['price']) . '</td>'
                . '<td>BND ' . money($item['price'] * $item['quantity']) . '</td></tr>';
        }
        $html .= '</table>';
    }

    // ---- Section B: customised products ----
    $attachments = [];
    if ($customRows) {
        $html .= '<h3 style="color:#465b8b">Customised Products</h3>';

        foreach ($customRows as $item) {
            $customId = (int) $item['custom_order_id'];

            // Load the saved doll details + uploaded photos.
            $dolls = db()->prepare(
                'SELECT * FROM custom_order_items WHERE custom_order_id = ? ORDER BY position'
            );
            $dolls->execute([$customId]);
            $dollRows = $dolls->fetchAll();

            $files = db()->prepare(
                'SELECT f.slot, f.file_path, f.original_name, i.position
                 FROM custom_order_files f
                 JOIN custom_order_items i ON i.id = f.custom_order_item_id
                 WHERE i.custom_order_id = ? ORDER BY i.position, f.id'
            );
            $files->execute([$customId]);
            $fileRows = $files->fetchAll();

            // Order-level customer instructions for this custom order.
            $co = db()->prepare('SELECT order_type, special_notes FROM custom_orders WHERE id = ?');
            $co->execute([$customId]);
            $coRow = $co->fetch() ?: [];

            $typeLabel = $item['name']; // e.g. "Custom Keychain Order #3"
            $html .= '<p><strong>' . $e($typeLabel) . '</strong> (' . $e(ucfirst((string) ($coRow['order_type'] ?? 'custom'))) . ')'
                . ' &mdash; Qty: ' . $item['quantity']
                . ' &mdash; Estimated price: BND ' . money($item['price'] * $item['quantity']) . '</p><ul>';

            foreach ($dollRows as $doll) {
                $acc = json_decode((string) $doll['accessories'], true) ?: [];
                $html .= '<li>Customisation #' . (int) $doll['position'] . ' &mdash; ' . $e(ucfirst($doll['gender'])) . ' doll'
                    . ' &mdash; skin tone: ' . $e($doll['skin_tone'])
                    . ', hair: ' . $e($doll['hair'])
                    . ($doll['hair_note'] ? ' (colour/instructions: ' . $e($doll['hair_note']) . ')' : '')
                    . ', outfit: ' . $e($doll['outfit'])
                    . ($doll['outfit_note'] ? ' (colour/instructions: ' . $e($doll['outfit_note']) . ')' : '')
                    . ($acc ? ', accessories: ' . $e(implode(', ', $acc)) : '')
                    . ($doll['accessory_note'] ? ' (' . $e($doll['accessory_note']) . ')' : '')
                    . ' &mdash; BND ' . money((float) $doll['item_total']) . '</li>';
            }
            $html .= '</ul>';

            if (!empty($coRow['special_notes'])) {
                $html .= '<p><strong>Customer instructions:</strong> ' . $e((string) $coRow['special_notes']) . '</p>';
            }

            // Reference photos: attached to the email AND linked when
            // SITE_URL is configured (local dev has no public URL).
            $siteUrl = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
            foreach ($fileRows as $file) {
                $path = CUSTOM_UPLOAD_DIR . '/' . basename($file['file_path']);
                if (is_file($path)) {
                    $attachments[] = $path;
                    $html .= '<p style="font-size:12px;color:#666">Reference photo: doll #' . (int) $file['position']
                        . ' ' . $e($file['slot'])
                        . ($file['original_name'] ? ' (' . $e($file['original_name']) . ')' : '')
                        . ' &mdash; attached to this email';
                    if ($siteUrl !== '') {
                        $html .= ' | <a href="' . $e($siteUrl . '/uploads/custom-orders/' . basename($file['file_path'])) . '">view photo</a>';
                    }
                    $html .= '</p>';
                }
            }
        }
    }

    // ---- Customer order notes ----
    if ($orderNotes !== '') {
        $html .= '<h3 style="color:#465b8b">Customer Order Notes</h3>';
        $html .= '<p>' . nl2br($e($orderNotes)) . '</p>';
    }

    // ---- Section C: order summary ----
    $html .= '<h3 style="color:#465b8b">Order Summary</h3>';
    $html .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
    $html .= '<tr><td>Subtotal</td><td align="right">BND ' . money($subtotal) . '</td></tr>';
    $html .= '<tr><td>' . ($isDelivery ? 'Delivery (' . $e($zone ?? '') . ')' : 'Pickup') . '</td><td align="right">BND ' . money($deliveryFee) . '</td></tr>';
    $html .= '<tr style="font-weight:bold"><td>Grand Total</td><td align="right">BND ' . money($total) . '</td></tr>';
    $html .= '</table>';
    $html .= '<p style="color:#888;font-size:12px">Payment proof file: ' . $e(basename($proofPath)) . ' (attached)</p>';
    $html .= '<p style="color:#888;font-size:12px">This is an automated order notification. Please confirm payment before dispatching.</p>';
    $html .= '</div>';

    // Attach the payment proof screenshot too.
    $attachments[] = $proofPath;

    return [$subject, $html, $attachments];
}
