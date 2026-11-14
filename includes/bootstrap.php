<?php
/*
 * SHARED BACKEND HELPERS - SA'ADATUL D.I.Y
 * Every file in api/ starts with: require __DIR__ . '/../includes/bootstrap.php';
 * It sets up JSON responses, the login session and common helper functions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $error): void {
    error_log('[SaadatulDIY] ' . $error);

    $message = $error instanceof PDOException
        ? 'Database error. Please make sure MySQL is running in XAMPP and the saadatuldiy2_db database has been imported.'
        : 'Something went wrong on the server. Please try again.';

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo json_encode(['success' => false, 'message' => $message]);
});

// ---------------------------------------------------------
//  SESSION (keeps the customer logged in)
// ---------------------------------------------------------
session_name('SDIYSESSID');
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    // SameSite=None is required when the frontend (e.g. Live Server or a
    // hosted site) calls the API on a DIFFERENT site. It needs Secure,
    // so it is only used over HTTPS. On plain HTTP (local dev) Lax is
    // correct because the Live Server proxy makes requests same-origin.
    'samesite' => $isHttps ? 'None' : 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

// ---------------------------------------------------------
//  JSON RESPONSES
// ---------------------------------------------------------
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(['success' => false, 'message' => $message] + $extra, $status);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function require_method(string ...$methods): string
{
    $method = request_method();

    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_error('Method not allowed.', 405);
    }

    return $method;
}

/** Reads a JSON request body. Only same-site pages can send JSON with the login cookie. */
function read_json(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($type, 'application/json') !== 0) {
        json_error('Requests must be sent as JSON.', 415);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($data)) {
        json_error('Invalid request data.');
    }

    return $data;
}

// ---------------------------------------------------------
//  CORS FOR LOCAL DEVELOPMENT
//  The frontend runs on VS Code Live Server (port 5500) and
//  the PHP backend on php -S (port 8080). Live Server is
//  configured to proxy /api to the PHP server, so requests
//  are normally same-origin. These origins are also allowed
//  directly for convenience. Never add "*" here.
// ---------------------------------------------------------
const DEV_ORIGINS = [
    'http://127.0.0.1:5500',
    'http://localhost:5500',
    'http://127.0.0.1:5501',
    'http://localhost:5501',
];

function request_origin(): string
{
    return (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
}

function origin_is_allowed(string $origin): bool
{
    if ($origin === '') {
        return true; // same-origin requests send no Origin header
    }

    $host = parse_url($origin, PHP_URL_HOST);
    $port = parse_url($origin, PHP_URL_PORT);
    $originHost = $host . ($port ? ':' . $port : '');

    if (strcasecmp($originHost, (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0) {
        return true;
    }

    // Live Server can use ANY local port (5500, 5501, 5502, ...),
    // so every loopback origin is allowed for local development.
    if (in_array(strtolower((string) $host), ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    return in_array($origin, DEV_ORIGINS, true);
}

function apply_cors(): void
{
    $origin = request_origin();

    if ($origin !== '' && origin_is_allowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
}

apply_cors();

// Preflight requests from the browser.
if (request_method() === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    http_response_code(204);
    exit;
}

/** Blocks requests that come from other websites (CSRF protection). */
function check_same_origin(): void
{
    if (request_method() === 'GET') {
        return;
    }

    if (!origin_is_allowed(request_origin())) {
        json_error('Request blocked.', 403);
    }
}

check_same_origin();

// ---------------------------------------------------------
//  INPUT HELPERS
// ---------------------------------------------------------
function clean_text(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';

    return trim($text);
}

function text_length(string $text): int
{
    return (int) preg_match_all('/./us', $text);
}

function positive_int(mixed $value): ?int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $number === false ? null : $number;
}

function money(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

// ---------------------------------------------------------
//  LOGIN HELPERS
// ---------------------------------------------------------
function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;

    return is_int($id) && $id > 0 ? $id : null;
}

function current_user(): ?array
{
    $id = current_user_id();

    if ($id === null) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, full_name, email, phone, address, city, postcode, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return user_payload($user);
}

/** Stops the request with 401 when the customer is not logged in. */
function require_login(): int
{
    $id = current_user_id();

    if ($id === null || current_user() === null) {
        json_error('Please log in or sign up to continue.', 401, ['loginRequired' => true]);
    }

    return $id;
}

function log_in_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function user_payload(array $user): array
{
    return [
        'id'        => (int) $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'phone'     => $user['phone'] ?? null,
        'address'   => $user['address'] ?? null,
        'city'      => $user['city'] ?? null,
        'postcode'  => $user['postcode'] ?? null,
        'createdAt' => $user['created_at'] ?? null,
    ];
}

// ---------------------------------------------------------
//  PRODUCT & CART HELPERS
// ---------------------------------------------------------
const PRODUCT_CATEGORIES = ['keychain', 'figurine', 'plushie', 'accessory'];

function product_payload(array $row, array $favouriteIds = []): array
{
    $id = (int) $row['id'];

    return [
        'id'          => $id,
        'name'        => $row['name'],
        'category'    => $row['category'],
        'price'       => (float) $row['price'],
        'description' => $row['description'],
        'image'       => $row['image'],
        'stock'       => $row['stock'] === null ? null : (int) $row['stock'],
        'isFavourite' => in_array($id, $favouriteIds, true),
    ];
}

function favourite_ids(?int $userId): array
{
    if ($userId === null) {
        return [];
    }

    $stmt = db()->prepare('SELECT product_id FROM favourites WHERE user_id = ?');
    $stmt->execute([$userId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function find_active_product(int $productId): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$productId]);

    return $stmt->fetch() ?: null;
}

function cart_summary(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT ci.id AS cart_item_id, ci.product_id, ci.variant_id, ci.custom_order_id, ci.quantity,
                p.name, p.category, p.price, p.image, p.stock,
                v.name AS variant_name, v.price AS variant_price, v.image_path AS variant_image,
                co.order_type AS custom_type, co.estimated_total AS custom_total, co.id AS custom_id
         FROM cart_items ci
         LEFT JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_variants v ON v.id = ci.variant_id AND v.is_active = 1
         LEFT JOIN custom_orders co ON co.id = ci.custom_order_id
         WHERE ci.user_id = ?
         ORDER BY ci.added_at, ci.id'
    );
    $stmt->execute([$userId]);

    $items = [];
    $subtotal = 0.0;
    $count = 0;

    foreach ($stmt->fetchAll() as $row) {
        $quantity = (int) $row['quantity'];

        if ($row['custom_order_id'] !== null) {
            // Custom made-to-order doll (keychain or figurine).
            // PRODUCT IMAGE: change this placeholder if you add a custom-order photo.
            $price = (float) $row['custom_total'];
            $typeLabel = $row['custom_type'] === 'figurine' ? 'Custom Figurine' : 'Custom Keychain';

            $items[] = [
                'cartItemId'    => (int) $row['cart_item_id'],
                'productId'     => null,
                'variantId'     => null,
                'customOrderId' => (int) $row['custom_order_id'],
                'type'          => 'custom',
                'name'          => $typeLabel . ' Order #' . (int) $row['custom_id'],
                'category'      => $row['custom_type'],
                'design'        => null,
                'price'         => $price,
                'image'         => 'photos/custom-dolls.png',
                'stock'         => null,
                'quantity'      => $quantity,
                'lineTotal'     => round($price * $quantity, 2),
            ];
        } else {
            // Normal product, optionally with a chosen design (variant).
            if ($row['name'] === null) {
                continue; // product was deleted
            }

            $price = $row['variant_price'] !== null ? (float) $row['variant_price'] : (float) $row['price'];
            $image = $row['variant_image'] ?: $row['image'];
            $design = $row['variant_name'];
            $name = $row['name'] . ($design ? ' — ' . $design : '');

            $items[] = [
                'cartItemId'    => (int) $row['cart_item_id'],
                'productId'     => (int) $row['product_id'],
                'variantId'     => $row['variant_id'] !== null ? (int) $row['variant_id'] : null,
                'customOrderId' => null,
                'type'          => 'product',
                'name'          => $name,
                'category'      => $row['category'],
                'design'        => $design,
                'price'         => $price,
                'image'         => $image,
                'stock'         => $row['stock'] === null ? null : (int) $row['stock'],
                'quantity'      => $quantity,
                'lineTotal'     => round($price * $quantity, 2),
            ];
        }

        $subtotal += end($items)['lineTotal'];
        $count += $quantity;
    }

    return [
        'items'    => $items,
        'subtotal' => round($subtotal, 2),
        'count'    => $count,
    ];
}
