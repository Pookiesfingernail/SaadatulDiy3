<?php
/*
 * CUSTOMER REVIEWS
 *   GET                                  list published reviews (newest first)
 *   POST multipart/form-data             submit a review
 *     Fields: customer_name, product_name, rating (1-5), comment,
 *             images[] (optional, up to 4 JPG/PNG/WEBP photos)
 * The 4 original reviews stay hard-coded in review.html;
 * only new customer reviews are stored here.
 */

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

const REVIEW_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const REVIEW_MAX_IMAGES = 4;
const REVIEW_UPLOAD_DIR = __DIR__ . '/../uploads/reviews';

$method = require_method('GET', 'POST');
$pdo = db();

if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT r.id, r.customer_name, r.product_name, r.rating, r.comment, r.image_path, r.created_at
         FROM reviews r WHERE r.status = "approved"
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $reviewRows = $stmt->fetchAll();

    // Load all photos for these reviews in one query.
    $imagesByReview = [];
    if ($reviewRows) {
        $ids = array_map('intval', array_column($reviewRows, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $imgStmt = $pdo->prepare(
            "SELECT review_id, image_path FROM review_images
             WHERE review_id IN ($placeholders) ORDER BY sort_order, id"
        );
        $imgStmt->execute($ids);
        foreach ($imgStmt->fetchAll() as $img) {
            $imagesByReview[(int) $img['review_id']][] = 'uploads/reviews/' . $img['image_path'];
        }
    }

    $reviews = array_map(function ($row) use ($imagesByReview) {
        $images = $imagesByReview[(int) $row['id']] ?? [];
        // Backwards compatibility: old reviews only have image_path.
        if (!$images && $row['image_path']) {
            $images = ['uploads/reviews/' . $row['image_path']];
        }

        return [
            'id'           => (int) $row['id'],
            'customerName' => $row['customer_name'],
            'productName'  => $row['product_name'],
            'rating'       => (int) $row['rating'],
            'comment'      => $row['comment'],
            'image'        => $images[0] ?? null,
            'images'       => $images,
            'createdAt'    => $row['created_at'],
        ];
    }, $reviewRows);

    json_response(['success' => true, 'reviews' => $reviews]);
}

// ---------- Submit a review ----------
$name = clean_text($_POST['customer_name'] ?? '');
$product = clean_text($_POST['product_name'] ?? '');
$comment = clean_text($_POST['comment'] ?? '');
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

$errors = [];
if (text_length($name) < 2 || text_length($name) > 100) {
    $errors['name'] = 'Please enter your name.';
}
if (text_length($product) < 2 || text_length($product) > 150) {
    $errors['product'] = 'Please enter the product name.';
}
if ($rating === false || $rating === null) {
    $errors['rating'] = 'Please select a star rating.';
}
if ($comment === '' || text_length($comment) > 2000) {
    $errors['comment'] = 'Please write a comment (2000 characters or fewer).';
}
if ($errors) {
    json_error(reset($errors), 422, ['errors' => $errors]);
}

// ---------- Optional photos (images[] or single image) ----------
$uploads = [];

// Normalise both "images[]" and a single "image" upload into one list.
if (isset($_FILES['images']) && is_array($_FILES['images'])) {
    $names = (array) $_FILES['images']['name'];
    foreach (array_keys($names) as $i) {
        $uploads[] = [
            'name'     => (string) $names[$i],
            'type'     => (string) ($_FILES['images']['type'][$i] ?? ''),
            'tmp_name' => (string) ($_FILES['images']['tmp_name'][$i] ?? ''),
            'error'    => (int) ($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($_FILES['images']['size'][$i] ?? 0),
        ];
    }
} elseif (isset($_FILES['image'])) {
    $f = $_FILES['image'];
    $uploads[] = [
        'name'     => (string) ($f['name'] ?? ''),
        'type'     => (string) ($f['type'] ?? ''),
        'tmp_name' => (string) ($f['tmp_name'] ?? ''),
        'error'    => (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE),
        'size'     => (int) ($f['size'] ?? 0),
    ];
}

// Drop empty slots (browsers may send an empty entry).
$uploads = array_values(array_filter($uploads, fn ($u) => $u['error'] !== UPLOAD_ERR_NO_FILE));

if (count($uploads) > REVIEW_MAX_IMAGES) {
    json_error('You can attach up to ' . REVIEW_MAX_IMAGES . ' photos.', 422);
}

$savedNames = [];

foreach ($uploads as $upload) {
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > REVIEW_IMAGE_MAX_BYTES) {
        json_error('Review photos must be 5 MB or smaller.', 422);
    }

    $head = (string) file_get_contents($upload['tmp_name'], false, null, 0, 12);
    $extension = match (true) {
        str_starts_with($head, "\xFF\xD8\xFF")                           => 'jpg',
        str_starts_with($head, "\x89PNG\r\n\x1A\n")                      => 'png',
        str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
        default                                                        => null,
    };

    if ($extension === null) {
        json_error('Review photos must be JPG, PNG or WEBP images.', 422);
    }

    if (!is_dir(REVIEW_UPLOAD_DIR) && !mkdir(REVIEW_UPLOAD_DIR, 0750, true) && !is_dir(REVIEW_UPLOAD_DIR)) {
        throw new RuntimeException('Cannot create review upload folder.');
    }

    $imageName = 'review-' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;

    if (!move_uploaded_file($upload['tmp_name'], REVIEW_UPLOAD_DIR . '/' . $imageName)) {
        throw new RuntimeException('Cannot save review photo.');
    }

    $savedNames[] = $imageName;
}

$pdo->beginTransaction();

try {
    // image_path keeps the FIRST photo for backwards compatibility;
    // all photos live in review_images.
    $pdo->prepare(
        'INSERT INTO reviews (customer_name, product_name, rating, comment, image_path)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$name, $product, $rating, $comment, $savedNames[0] ?? null]);

    $reviewId = (int) $pdo->lastInsertId();

    $insertImage = $pdo->prepare(
        'INSERT INTO review_images (review_id, image_path, sort_order) VALUES (?, ?, ?)'
    );
    foreach ($savedNames as $i => $imageName) {
        $insertImage->execute([$reviewId, $imageName, $i]);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedNames as $imageName) {
        $path = REVIEW_UPLOAD_DIR . '/' . $imageName;
        if (is_file($path)) unlink($path);
    }
    throw $error;
}

$images = array_map(fn ($n) => 'uploads/reviews/' . $n, $savedNames);

json_response([
    'success' => true,
    'message' => 'Thank you for sharing your review!',
    'review'  => [
        'id'           => $reviewId,
        'customerName' => $name,
        'productName'  => $product,
        'rating'       => $rating,
        'comment'      => $comment,
        'image'        => $images[0] ?? null,
        'images'       => $images,
        'createdAt'    => date('Y-m-d H:i:s'),
    ],
], 201);
