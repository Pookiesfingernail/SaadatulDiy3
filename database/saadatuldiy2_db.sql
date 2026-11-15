-- =========================================================
--  Sa'adatul D.I.Y - MySQL / MariaDB database
--  Database name: saadatuldiy2_db  (matches config/database.php)
--
--  HOW TO IMPORT (phpMyAdmin):
--    1. Open http://localhost/phpmyadmin
--    2. Click the "Import" tab
--    3. Choose this file: database/saadatuldiy2_db.sql
--    4. Click "Go" at the bottom
--
--  OR using the XAMPP command line:
--    C:\xampp\mysql\bin\mysql.exe -u root < database\saadatuldiy2_db.sql
--
--  Safe to re-import: tables are only created when missing and
--  products are only inserted when they do not exist yet.
--  Existing customers, orders and reviews are NOT deleted.
-- =========================================================

CREATE DATABASE IF NOT EXISTS saadatuldiy2_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE saadatuldiy2_db;

-- ---------------------------------------------------------
--  CUSTOMERS
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(30)  NULL,
    address       VARCHAR(255) NULL,
    city          VARCHAR(100) NULL,
    postcode      VARCHAR(20)  NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed logins, used to slow down password guessing.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(254) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_email (email, attempted_at),
    KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  PRODUCTS
--  price is always in BND (Brunei dollars).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    slug        VARCHAR(160) NOT NULL,
    category    ENUM('keychain', 'figurine', 'plushie', 'accessory') NOT NULL,
    price       DECIMAL(10, 2) NOT NULL COMMENT 'Price in BND',
    description TEXT NULL,
    image       VARCHAR(255) NOT NULL COMMENT 'Image path inside the project, e.g. photos/flower.jpg',
    stock       INT UNSIGNED NULL COMMENT 'NULL = stock is not tracked',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category (category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  PRODUCT DESIGNS / VARIANTS
--  One product can have many designs (e.g. Pink and Blue).
--  If a product has NO rows here it is a single-design product
--  and the "Other Designs" section is hidden automatically.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_variants (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL COMMENT 'Design name shown to customers, e.g. Pink',
    color       VARCHAR(50)  NULL  COMMENT 'Optional colour label, e.g. #ffb6c1',
    image_path  VARCHAR(255) NOT NULL COMMENT 'PRODUCT PHOTO: replace with your own image path',
    price       DECIMAL(10, 2) NULL COMMENT 'BND. NULL = same price as the product',
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_variants_product (product_id, is_active),
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra gallery photos shown on the product page.
CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_image (product_id, image_path),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  CUSTOM ORDERS (keychain / figurine made-to-order dolls)
--  Created before cart_items because cart rows can point
--  to a custom order.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS custom_orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    order_type      ENUM('keychain', 'figurine') NOT NULL DEFAULT 'keychain',
    details         LONGTEXT NOT NULL COMMENT 'JSON summary of the whole order',
    special_notes   TEXT NULL,
    estimated_total DECIMAL(10, 2) NOT NULL COMMENT 'Price in BND',
    final_price     DECIMAL(10, 2) NULL COMMENT 'Filled in after the WhatsApp discussion',
    status          ENUM('in_cart', 'awaiting_whatsapp', 'in_discussion', 'ordered', 'completed', 'cancelled') NOT NULL DEFAULT 'in_cart',
    order_id        INT UNSIGNED NULL COMMENT 'Set when the order is placed at checkout',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_custom_user (user_id, created_at),
    CONSTRAINT fk_custom_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per doll inside a custom order (max 2: couple order).
CREATE TABLE IF NOT EXISTS custom_order_items (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    custom_order_id   INT UNSIGNED NOT NULL,
    position          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = first doll, 2 = second doll',
    gender            VARCHAR(10)  NOT NULL,
    skin_tone         VARCHAR(20)  NOT NULL,
    hair              VARCHAR(50)  NOT NULL,
    hair_note         TEXT NULL,
    outfit            VARCHAR(50)  NOT NULL,
    outfit_note       TEXT NULL,
    accessories       TEXT NULL COMMENT 'JSON list of chosen accessories',
    accessory_note    TEXT NULL,
    item_total        DECIMAL(10, 2) NOT NULL COMMENT 'Price in BND',
    PRIMARY KEY (id),
    KEY idx_custom_items_order (custom_order_id),
    CONSTRAINT fk_custom_items_order FOREIGN KEY (custom_order_id)
        REFERENCES custom_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded reference photos for each doll (hair / outfit / accessory).
CREATE TABLE IF NOT EXISTS custom_order_files (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    custom_order_item_id INT UNSIGNED NOT NULL,
    slot                 ENUM('hair', 'outfit', 'accessory') NOT NULL,
    file_path            VARCHAR(255) NOT NULL COMMENT 'File name inside uploads/custom-orders/',
    original_name        VARCHAR(255) NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_custom_files_item (custom_order_item_id),
    CONSTRAINT fk_custom_files_item FOREIGN KEY (custom_order_item_id)
        REFERENCES custom_order_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  SHOPPING CART & FAVOURITES
--  cart_items can hold a normal product (product_id +
--  variant_id) OR a custom order (custom_order_id).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NULL,
    variant_id       INT UNSIGNED NULL,
    custom_order_id  INT UNSIGNED NULL,
    quantity         INT UNSIGNED NOT NULL DEFAULT 1,
    added_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cart_user (user_id),
    KEY idx_cart_custom (custom_order_id),
    CONSTRAINT chk_cart_quantity CHECK (quantity > 0),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_variant FOREIGN KEY (variant_id)
        REFERENCES product_variants (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_custom FOREIGN KEY (custom_order_id)
        REFERENCES custom_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favourites (
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  ORDERS (checkout)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    email         VARCHAR(254) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    phone         VARCHAR(30)  NOT NULL COMMENT 'Full international format, e.g. +6737123456',
    country       VARCHAR(100) NOT NULL,
    fulfilment    ENUM('delivery', 'pickup') NOT NULL,
    address       VARCHAR(255) NULL,
    city          VARCHAR(100) NULL,
    postcode      VARCHAR(20)  NULL,
    shipping_zone VARCHAR(50)  NULL,
    subtotal      DECIMAL(10, 2) NOT NULL,
    delivery_fee  DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total         DECIMAL(10, 2) NOT NULL,
    payment_proof VARCHAR(255) NOT NULL COMMENT 'File name inside uploads/payment-proofs/',
    status        ENUM('pending', 'confirmed', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_status ENUM('pending_verification', 'verified', 'rejected') NOT NULL DEFAULT 'pending_verification' COMMENT 'Manual payment-proof verification state',
    email_status  ENUM('pending', 'sent', 'failed', 'skipped') NOT NULL DEFAULT 'pending' COMMENT 'Order email notification status',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id, created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
        REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id         INT UNSIGNED NOT NULL,
    item_type        ENUM('product', 'custom') NOT NULL DEFAULT 'product',
    product_id       INT UNSIGNED NULL,
    variant_id       INT UNSIGNED NULL,
    custom_order_id  INT UNSIGNED NULL,
    design_name      VARCHAR(100) NULL COMMENT 'Snapshot of the chosen design, e.g. Pink',
    product_name     VARCHAR(190) NOT NULL,
    image            VARCHAR(255) NOT NULL,
    unit_price       DECIMAL(10, 2) NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    line_total       DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_items_order (order_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_items_variant FOREIGN KEY (variant_id)
        REFERENCES product_variants (id) ON DELETE SET NULL,
    CONSTRAINT fk_items_custom FOREIGN KEY (custom_order_id)
        REFERENCES custom_orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  CUSTOMER REVIEWS
--  The 4 original reviews on review.html stay hard-coded in
--  the page. Only NEW customer reviews are stored here.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name  VARCHAR(100) NOT NULL,
    product_name   VARCHAR(150) NOT NULL,
    rating         TINYINT UNSIGNED NOT NULL,
    comment        TEXT NOT NULL,
    image_path     VARCHAR(255) NULL COMMENT 'File name inside uploads/reviews/',
    status         ENUM('approved', 'hidden') NOT NULL DEFAULT 'approved',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reviews_status (status, created_at),
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos attached to a review (up to 4 per review).
-- reviews.image_path keeps the first photo for backwards compatibility.
CREATE TABLE IF NOT EXISTS review_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    review_id   INT UNSIGNED NOT NULL,
    image_path  VARCHAR(255) NOT NULL COMMENT 'File name inside uploads/reviews/',
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_review_images_review (review_id),
    CONSTRAINT fk_review_images_review FOREIGN KEY (review_id)
        REFERENCES reviews (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  CONTACT US MESSAGES
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(254) NOT NULL,
    phone      VARCHAR(30)  NOT NULL COMMENT 'Full international format, e.g. +6737123456',
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
--  ORDER EMAIL NOTIFICATIONS
--  One row per order email attempt so failed emails can be
--  retried safely without creating duplicate orders.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_notifications (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED NOT NULL,
    recipient    VARCHAR(254) NOT NULL,
    subject      VARCHAR(255) NOT NULL,
    status       ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts     INT UNSIGNED NOT NULL DEFAULT 0,
    last_error   TEXT NULL,
    sent_at      DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_order (order_id),
    CONSTRAINT fk_email_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
--  SAMPLE PRODUCTS (from shop.html)
--  PRODUCT PHOTO: image paths point inside the project
--  "photos" folder. Replace them with your own photos and
--  add new products by copying these INSERT rows.
-- =========================================================
INSERT IGNORE INTO products (name, slug, category, price, description, image, stock) VALUES
    ('Nailong Flower Keychain',   'nailong-flower-keychain',   'keychain', 30.00, 'Handmade crochet flower keychain.', 'shopkeychain/Product-nailong', NULL),
    ('Assorted Flowers Keychain', 'assorted-flowers-keychain', 'keychain', 15.00, 'A flower that lasts forever.', 'photos/designs.png', 5),
    ('BMO Keychain',              'bmo-keychain',              'keychain', 20.00, 'Handmade crochet BMO keychain.', 'photos/BMO.JPEG', NULL),
    ('Teddy Keychain',            'teddy-keychain',            'keychain', 12.00, 'Cute handmade crochet teddy keychain.', 'photos/Teddy.JPEG', NULL),
    ('Whale Keychain',            'whale-keychain',            'keychain',  6.00, 'Handmade crochet whale keychain.', 'photos/Whale.JPEG', NULL),
    ('Teletubbies Figurines',     'teletubbies-figurines',     'figurine', 30.00, 'Handmade crochet Teletubbies figurines.', 'photos/Teletubbies.JPEG', NULL),
    ('Princess Aurora Figurine',  'princess-aurora-figurine',  'figurine', 20.00, 'Handmade crochet Princess Aurora figurine.', 'photos/Princess Aurora.JPEG', NULL),
    ('Rapunzel Figurine',         'rapunzel-figurine',         'figurine', 20.00, 'Handmade crochet Rapunzel figurine.', 'photos/Rapunzel.JPEG', NULL),
    ('Princess Ariel Mermaid Figurine', 'ariel-mermaid-figurine', 'figurine', 20.00, 'Handmade crochet Ariel mermaid figurine.', 'photos/Ariel Mermaid.JPEG', NULL),
    ('Princess Tiana Figurine',   'princess-tiana-figurine',   'figurine', 20.00, 'Handmade crochet Princess Tiana figurine.', 'photos/Princess Tiana.JPEG', NULL),
    ('Royal Brunei Armed Plushie','rbaf-plushie',              'plushie', 180.00, 'Signature handmade RBAF plushie, about 45cm tall.', 'photos/RBAF_plushie.png', NULL),
    ('Shaun & Bitzer Plushie',    'shaun-bitzer-plushie',      'plushie',  20.00, 'Handmade crochet Shaun & Bitzer plushie.', 'photos/Shaun_Bitzer.JPEG', NULL),
    ('Cutie The Elephant Plushie','cutie-elephant-plushie',   'plushie',  20.00, 'Handmade crochet elephant plushie.', 'photos/Cutie_the_Elephant.JPEG', NULL),
    ('Pink Overall Elephant Plushie','pink-elephant-plushie', 'plushie',  20.00, 'Handmade crochet elephant plushie in pink overalls.', 'photos/Overall_Elephant.JPEG', NULL),
    ('Appa Bison Plushie',        'appa-bison-plushie',        'plushie',  20.00, 'Handmade crochet Appa bison plushie.', 'photos/Appa_Bison.JPEG', NULL),
    ('Elephant Car Hanger',       'elephant-car-hanger',       'accessory',30.00, 'Handmade crochet elephant car hanger.', 'photos/Elephant Hanger.JPEG', NULL),
    ('Patrick Car Hanger',        'patrick-car-hanger',        'accessory',20.00, 'Handmade crochet Patrick car hanger.', 'photos/Patrick Hanger.JPEG', NULL),
    ('Coaster',                   'coaster',                   'accessory',20.00, 'Handmade crochet coaster.', 'photos/Coaster.JPEG', NULL),
    ('Pouch',                     'pouch',                     'accessory',20.00, 'Handmade crochet pouch.', 'photos/Pouch.JPEG', NULL),
    ('Bracelet',                  'bracelet',                  'accessory',20.00, 'Handmade crochet bracelet.', 'photos/Bracelet.JPEG', NULL);

-- ---------------------------------------------------------
--  SAMPLE PRODUCT DESIGNS
--  Assorted Flowers Keychain has TWO designs (Pink & Blue).
--  PRODUCT PHOTO: replace the image_path values with your own
--  design photos stored inside the project "photos" folder.
--  To add a design to any product, copy a row and change
--  product_id, name and image_path.
-- ---------------------------------------------------------
INSERT INTO product_variants (product_id, name, color, image_path, price, sort_order)
SELECT p.id, v.name, v.color, v.image_path, v.price, v.sort_order
FROM products p
JOIN (
    SELECT 'assorted-flowers-keychain' AS slug, 'Pink'  AS name, '#ffb6c1' AS color, 'photos/designs.png'    AS image_path, NULL AS price, 1 AS sort_order
    UNION ALL
    SELECT 'assorted-flowers-keychain', 'Blue', '#aec6cf', 'photos/blue-yarn.png', NULL, 2
) v ON v.slug = p.slug
WHERE NOT EXISTS (
    SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id
);
