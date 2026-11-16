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
    notes         TEXT NULL COMMENT 'Order notes written by the customer on the cart page',
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
    user_id        INT UNSIGNED NULL COMMENT 'Logged-in customer who wrote the review (NULL = guest)',
    customer_name  VARCHAR(100) NOT NULL,
    product_name   VARCHAR(150) NOT NULL,
    rating         TINYINT UNSIGNED NOT NULL,
    comment        TEXT NOT NULL,
    image_path     VARCHAR(255) NULL COMMENT 'File name inside uploads/reviews/',
    status         ENUM('approved', 'hidden') NOT NULL DEFAULT 'approved',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reviews_status (status, created_at),
    KEY idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL,
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
--  APPROVED PRODUCTS - exactly 20 (5 per category)
--  Keychains=1 Figurines=2 Plushies=3 Accessories=4
--
--  Step 1 UPDATEs match rows by their ORIGINAL slug so the
--  existing product IDs - and therefore carts, favourites
--  and order history - stay connected. Step 2 INSERT IGNORE
--  creates the same 20 products on a fresh database.
--  No sample products are inserted.
-- =========================================================
UPDATE products SET name='Nailong Flower Keychain', slug='nailong-flower-keychain', category='keychain', price=30.00, stock=NULL, is_active=1,
    description='Inspired by the adorable Nailong character, dressed up as a bright and cheerful sunflower! With its cute little face, vibrant orange petals and sunny yellow body, this little flower is sure to brighten up your day. Perfect for adding a touch of cuteness to your bag or keys! Height +-12cm.',
    image='shopkeychain/Flower-nailong.png' WHERE slug='nailong-flower-keychain';
UPDATE products SET name='Forget Me Not Flower Keychain', slug='forget-me-not-flower-keychain', category='keychain', price=15.00, stock=NULL, is_active=1,
    description='A cluster of bright blue flowers in a pink wrap finished with a yellow bow, Height +-10cm.',
    image='shopkeychain/Forgetmenot.png' WHERE slug='assorted-flowers-keychain';
UPDATE products SET name='BMO Keychain', slug='bmo-keychain', category='keychain', price=20.00, stock=NULL, is_active=1,
    description='A game-console character from Adventure time called BMO handmade crochet.',
    image='shopkeychain/BMOs.png' WHERE slug='bmo-keychain';
UPDATE products SET name='Teddy and Lottie Keychain', slug='teddy-lottie-keychain', category='keychain', price=11.00, stock=NULL, is_active=1,
    description='Teddy and Lottie, the character from Mr.Bean.',
    image='shopkeychain/Teddies.png' WHERE slug='teddy-keychain';
UPDATE products SET name='Whale Keychain', slug='whale-keychain', category='keychain', price=6.00, stock=NULL, is_active=1,
    description='A palm-sized blue whale with a white belly and a sweet little smile.',
    image='shopkeychain/Whales.png' WHERE slug='whale-keychain';
UPDATE products SET name='Teletubbies Figurine', slug='teletubbies-figurine', category='figurine', price=35.00, stock=NULL, is_active=1,
    description='Inspired from children TV series called Teletubbies! Height +-25cm.',
    image='shopfigurine/Teletubbies.png' WHERE slug='teletubbies-figurines';
UPDATE products SET name='Princess Aurora Figurine', slug='princess-aurora-figurine', category='figurine', price=40.00, stock=NULL, is_active=1,
    description='inspired from animated Disney movie called Princess Aurora! Known for her cursed by maleficent to fall into an eternal sleep after pricking her finger on a spinning wheel that can awakened by true love''s kiss! Height +-25cm.',
    image='shopfigurine/PrincessAurora.png' WHERE slug='princess-aurora-figurine';
UPDATE products SET name='Rapunzel Figurine', slug='rapunzel-figurine', category='figurine', price=48.00, stock=NULL, is_active=1,
    description='inspired from animated Disney Movie called Tangled! Known for her long magical hair that gotten kidnapped by Mother Gothel since she was a baby and locks her away in a hidden tower! Height +-25cm.',
    image='shopfigurine/Rapunzel.png' WHERE slug='rapunzel-figurine';
UPDATE products SET name='Princess Ariel Mermaid Figurine', slug='ariel-mermaid-figurine', category='figurine', price=45.00, stock=NULL, is_active=1,
    description='Inspired from an animated Disney movie called The Little Mermaid! A princess, daughter of King Triton and late Queen Athena in underwater kingdom of Atlantica. Height +-25cm.',
    image='shopfigurine/MermaidA.png' WHERE slug='ariel-mermaid-figurine';
UPDATE products SET name='Princess Tiana Figurine', slug='princess-tiana-figurine', category='figurine', price=45.00, stock=NULL, is_active=1,
    description='Inspired from animated Disney movie called The Princess and the Frog. After marrying Prince Naveen, she transforms into a real princess and lift the frog curse!. Height +-25cm.',
    image='shopfigurine/Tiana.png' WHERE slug='princess-tiana-figurine';
UPDATE products SET name='Royal Brunei Armed Plushie', slug='rbaf-plushie', category='plushie', price=180.00, stock=NULL, is_active=1,
    description='Challenging and expensive custom pieces for Bruneian Army lovers that reminds of them their family or army collectors! Height: +-45cm.',
    image='shopplushie/RBA.png' WHERE slug='rbaf-plushie';
UPDATE products SET name='Shaun and Bitzer Plushie', slug='shaun-bitzer-plushie', category='plushie', price=90.00, stock=NULL, is_active=1,
    description='A cartoon inspired from Shaun The Sheep Series! Two bestfriends since childhood growing up on Mossy Bottom Farm. A commission or custom piece for special gift that reminded of their childhood show or their bestfriend. Height +-35cm.',
    image='shopplushie/ShawnTheSheep.png' WHERE slug='shaun-bitzer-plushie';
UPDATE products SET name='Crown Elephant Plushie', slug='crown-elephant-plushie', category='plushie', price=98.00, stock=NULL, is_active=1,
    description='Amigurumi version inspired from a game called It Takes Two! A beloved toy princess character of Rose (main character) belongings. This gift can be perfect for toddlers and a fan of the inspired game! Height +-35cm.',
    image='shopplushie/Cutie.png' WHERE slug='cutie-elephant-plushie';
UPDATE products SET name='Baby Elephant Plushie', slug='baby-elephant-plushie', category='plushie', price=95.00, stock=NULL, is_active=1,
    description='Very popular piece in crafting communities that''s known for its pink overalls and a perfect gift for toddlers & newborns! Height 34cm.',
    image='shopplushie/BabyElephant.png' WHERE slug='pink-elephant-plushie';
UPDATE products SET name='Appa Bison Plushie', slug='appa-bison-plushie', category='plushie', price=50.00, stock=NULL, is_active=1,
    description='A cartoon inspired from Avatar: The Last Airbender! A longlife companion of Avatar Aang, the main protagonist of the series. Those who is a fan of avatar and wants their own companion too would love this! +-35cm.',
    image='shopplushie/Appa.png' WHERE slug='appa-bison-plushie';
UPDATE products SET name='Elephant Car Hanger', slug='elephant-car-hanger', category='accessory', price=13.00, stock=NULL, is_active=1,
    description='A tiny grey elephant crochet sitting on a leaf swing with a cream flower.! +-10',
    image='shopacc/ElephantHang.png' WHERE slug='elephant-car-hanger';
UPDATE products SET name='Patrick Car Hanger', slug='patrick-car-hanger', category='accessory', price=15.00, stock=NULL, is_active=1,
    description='A smiling Patrick hanging from a matching pink cord that can be hanged in your car, +-10.',
    image='shopacc/PatrickHang.png' WHERE slug='patrick-car-hanger';
UPDATE products SET name='Sunflower Pouch', slug='sunflower-pouch', category='accessory', price=12.00, stock=NULL, is_active=1,
    description='A mini Sunflower pouch that you can use to keep your coins, earbuds, charger and more.',
    image='shopacc/Sunflowerpouch.png' WHERE slug='coaster';
UPDATE products SET name='Stitch Pouch', slug='stitch-pouch', category='accessory', price=20.00, stock=NULL, is_active=1,
    description='A cute Stitch pouch that you can use to keep your coins, earbuds, keys, and other small essentials.',
    image='shopacc/Stitch.png' WHERE slug='pouch';
UPDATE products SET name='Bracelet', slug='bracelet', category='accessory', price=7.00, stock=NULL, is_active=1,
    description='A pair of handmade crochet bands with little bows in two shades of pinks.',
    image='shopacc/Bracelets.png' WHERE slug='bracelet';

INSERT IGNORE INTO products (name, slug, category, price, description, image, stock) VALUES
    ('Nailong Flower Keychain',   'nailong-flower-keychain',   'keychain', 30.00, 'Inspired by the adorable Nailong character, dressed up as a bright and cheerful sunflower! With its cute little face, vibrant orange petals and sunny yellow body, this little flower is sure to brighten up your day. Perfect for adding a touch of cuteness to your bag or keys! Height +-12cm.', 'shopkeychain/Flower-nailong.png', NULL),
    ('Forget Me Not Flower Keychain', 'forget-me-not-flower-keychain', 'keychain', 15.00, 'A cluster of bright blue flowers in a pink wrap finished with a yellow bow, Height +-10cm.', 'shopkeychain/Forgetmenot.png', NULL),
    ('BMO Keychain',              'bmo-keychain',              'keychain', 20.00, 'A game-console character from Adventure time called BMO handmade crochet.', 'shopkeychain/BMOs.png', NULL),
    ('Teddy and Lottie Keychain','teddy-lottie-keychain',     'keychain', 11.00, 'Teddy and Lottie, the character from Mr.Bean.', 'shopkeychain/Teddies.png', NULL),
    ('Whale Keychain',            'whale-keychain',            'keychain',  6.00, 'A palm-sized blue whale with a white belly and a sweet little smile.', 'shopkeychain/Whales.png', NULL),
    ('Teletubbies Figurine',      'teletubbies-figurine',      'figurine', 35.00, 'Inspired from children TV series called Teletubbies! Height +-25cm.', 'shopfigurine/Teletubbies.png', NULL),
    ('Princess Aurora Figurine',  'princess-aurora-figurine',  'figurine', 40.00, 'inspired from animated Disney movie called Princess Aurora! Known for her cursed by maleficent to fall into an eternal sleep after pricking her finger on a spinning wheel that can awakened by true love''s kiss! Height +-25cm.', 'shopfigurine/PrincessAurora.png', NULL),
    ('Rapunzel Figurine',         'rapunzel-figurine',         'figurine', 48.00, 'inspired from animated Disney Movie called Tangled! Known for her long magical hair that gotten kidnapped by Mother Gothel since she was a baby and locks her away in a hidden tower! Height +-25cm.', 'shopfigurine/Rapunzel.png', NULL),
    ('Princess Ariel Mermaid Figurine', 'ariel-mermaid-figurine', 'figurine', 45.00, 'Inspired from an animated Disney movie called The Little Mermaid! A princess, daughter of King Triton and late Queen Athena in underwater kingdom of Atlantica. Height +-25cm.', 'shopfigurine/MermaidA.png', NULL),
    ('Princess Tiana Figurine',   'princess-tiana-figurine',   'figurine', 45.00, 'Inspired from animated Disney movie called The Princess and the Frog. After marrying Prince Naveen, she transforms into a real princess and lift the frog curse!. Height +-25cm.', 'shopfigurine/Tiana.png', NULL),
    ('Royal Brunei Armed Plushie','rbaf-plushie',              'plushie', 180.00, 'Challenging and expensive custom pieces for Bruneian Army lovers that reminds of them their family or army collectors! Height: +-45cm.', 'shopplushie/RBA.png', NULL),
    ('Shaun and Bitzer Plushie',  'shaun-bitzer-plushie',      'plushie',  90.00, 'A cartoon inspired from Shaun The Sheep Series! Two bestfriends since childhood growing up on Mossy Bottom Farm. A commission or custom piece for special gift that reminded of their childhood show or their bestfriend. Height +-35cm.', 'shopplushie/ShawnTheSheep.png', NULL),
    ('Crown Elephant Plushie',    'crown-elephant-plushie',    'plushie',  98.00, 'Amigurumi version inspired from a game called It Takes Two! A beloved toy princess character of Rose (main character) belongings. This gift can be perfect for toddlers and a fan of the inspired game! Height +-35cm.', 'shopplushie/Cutie.png', NULL),
    ('Baby Elephant Plushie',     'baby-elephant-plushie',     'plushie',  95.00, 'Very popular piece in crafting communities that''s known for its pink overalls and a perfect gift for toddlers & newborns! Height 34cm.', 'shopplushie/BabyElephant.png', NULL),
    ('Appa Bison Plushie',        'appa-bison-plushie',        'plushie',  50.00, 'A cartoon inspired from Avatar: The Last Airbender! A longlife companion of Avatar Aang, the main protagonist of the series. Those who is a fan of avatar and wants their own companion too would love this! +-35cm.', 'shopplushie/Appa.png', NULL),
    ('Elephant Car Hanger',       'elephant-car-hanger',       'accessory',13.00, 'A tiny grey elephant crochet sitting on a leaf swing with a cream flower.! +-10', 'shopacc/ElephantHang.png', NULL),
    ('Patrick Car Hanger',        'patrick-car-hanger',        'accessory',15.00, 'A smiling Patrick hanging from a matching pink cord that can be hanged in your car, +-10.', 'shopacc/PatrickHang.png', NULL),
    ('Sunflower Pouch',           'sunflower-pouch',           'accessory',12.00, 'A mini Sunflower pouch that you can use to keep your coins, earbuds, charger and more.', 'shopacc/Sunflowerpouch.png', NULL),
    ('Stitch Pouch',              'stitch-pouch',              'accessory',20.00, 'A cute Stitch pouch that you can use to keep your coins, earbuds, keys, and other small essentials.', 'shopacc/Stitch.png', NULL),
    ('Bracelet',                  'bracelet',                  'accessory', 7.00, 'A pair of handmade crochet bands with little bows in two shades of pinks.', 'shopacc/Bracelets.png', NULL);

-- ---------------------------------------------------------
--  PRODUCT DESIGNS (variants)
--  Only FIVE products have multiple designs:
--    BMO Keychain            -> Pink, Blue
--    Teddy and Lottie       -> Teddy, Lottie
--    Whale Keychain         -> Pink, Blue
--    Teletubbies Figurine   -> Po, Laa-Laa, Dipsy, Tinky Winky
--    Shaun and Bitzer       -> Shaun, Bitzer
--  Every other product is single-design: its "Other Design"
--  section hides automatically.
--
--  No separate design photos exist yet, so each variant keeps
--  the product's main image. When real design photos are added,
--  update product_variants.image_path - the product page will
--  then swap the main image automatically.
-- ---------------------------------------------------------
-- 1) Soft-retire every existing variant so stale designs from
--    older imports can never leak onto the shop.
UPDATE product_variants SET is_active = 0;

-- 2) Reactivate + sync the approved designs (matches by slug+name).
UPDATE product_variants pv
JOIN products p ON p.id = pv.product_id
JOIN (
    SELECT 'bmo-keychain' AS slug, 'Pink' AS name, '#ffb6c1' AS color, 'shopkeychain/BMOs.png' AS image_path, NULL AS price, 1 AS sort_order
    UNION ALL SELECT 'bmo-keychain', 'Blue', '#aec6cf', 'shopkeychain/BMOs.png', NULL, 2
    UNION ALL SELECT 'teddy-lottie-keychain', 'Teddy', '#b08968', 'shopkeychain/Teddies.png', NULL, 1
    UNION ALL SELECT 'teddy-lottie-keychain', 'Lottie', '#e6ccb2', 'shopkeychain/Teddies.png', NULL, 2
    UNION ALL SELECT 'whale-keychain', 'Pink', '#ffb6c1', 'shopkeychain/Whales.png', NULL, 1
    UNION ALL SELECT 'whale-keychain', 'Blue', '#aec6cf', 'shopkeychain/Whales.png', NULL, 2
    UNION ALL SELECT 'teletubbies-figurine', 'Po', '#e74c3c', 'shopfigurine/Teletubbies.png', NULL, 1
    UNION ALL SELECT 'teletubbies-figurine', 'Laa-Laa', '#f1c40f', 'shopfigurine/Teletubbies.png', NULL, 2
    UNION ALL SELECT 'teletubbies-figurine', 'Dipsy', '#2ecc71', 'shopfigurine/Teletubbies.png', NULL, 3
    UNION ALL SELECT 'teletubbies-figurine', 'Tinky Winky', '#9b59b6', 'shopfigurine/Teletubbies.png', NULL, 4
    UNION ALL SELECT 'shaun-bitzer-plushie', 'Shaun', '#ecf0f1', 'shopplushie/ShawnTheSheep.png', NULL, 1
    UNION ALL SELECT 'shaun-bitzer-plushie', 'Bitzer', '#8b5a2b', 'shopplushie/ShawnTheSheep.png', NULL, 2
) v ON v.slug = p.slug AND v.name = pv.name
SET pv.is_active = 1, pv.color = v.color, pv.image_path = v.image_path, pv.price = v.price, pv.sort_order = v.sort_order;

-- 3) Insert approved designs that are missing entirely.
INSERT INTO product_variants (product_id, name, color, image_path, price, sort_order, is_active)
SELECT p.id, v.name, v.color, v.image_path, v.price, v.sort_order, 1
FROM products p
JOIN (
    SELECT 'bmo-keychain' AS slug, 'Pink' AS name, '#ffb6c1' AS color, 'shopkeychain/BMOs.png' AS image_path, NULL AS price, 1 AS sort_order
    UNION ALL SELECT 'bmo-keychain', 'Blue', '#aec6cf', 'shopkeychain/BMOs.png', NULL, 2
    UNION ALL SELECT 'teddy-lottie-keychain', 'Teddy', '#b08968', 'shopkeychain/Teddies.png', NULL, 1
    UNION ALL SELECT 'teddy-lottie-keychain', 'Lottie', '#e6ccb2', 'shopkeychain/Teddies.png', NULL, 2
    UNION ALL SELECT 'whale-keychain', 'Pink', '#ffb6c1', 'shopkeychain/Whales.png', NULL, 1
    UNION ALL SELECT 'whale-keychain', 'Blue', '#aec6cf', 'shopkeychain/Whales.png', NULL, 2
    UNION ALL SELECT 'teletubbies-figurine', 'Po', '#e74c3c', 'shopfigurine/Teletubbies.png', NULL, 1
    UNION ALL SELECT 'teletubbies-figurine', 'Laa-Laa', '#f1c40f', 'shopfigurine/Teletubbies.png', NULL, 2
    UNION ALL SELECT 'teletubbies-figurine', 'Dipsy', '#2ecc71', 'shopfigurine/Teletubbies.png', NULL, 3
    UNION ALL SELECT 'teletubbies-figurine', 'Tinky Winky', '#9b59b6', 'shopfigurine/Teletubbies.png', NULL, 4
    UNION ALL SELECT 'shaun-bitzer-plushie', 'Shaun', '#ecf0f1', 'shopplushie/ShawnTheSheep.png', NULL, 1
    UNION ALL SELECT 'shaun-bitzer-plushie', 'Bitzer', '#8b5a2b', 'shopplushie/ShawnTheSheep.png', NULL, 2
) v ON v.slug = p.slug
WHERE NOT EXISTS (
    SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.name = v.name
);

-- ---------------------------------------------------------
--  CLEANUP: remove the old sample keychain product.
--  Only the unwanted sample row is deleted - the five
--  approved keychains and all other products are kept.
--  order_items.product_id is ON DELETE SET NULL so past
--  orders keep their history.
-- ---------------------------------------------------------
DELETE FROM products WHERE slug = 'sample-crochet-keychain';

-- ---------------------------------------------------------
--  UPGRADES FOR DATABASES IMPORTED FROM AN OLDER VERSION
--  (IF NOT EXISTS guards require MariaDB, e.g. XAMPP)
-- ---------------------------------------------------------
ALTER TABLE orders  ADD COLUMN IF NOT EXISTS notes TEXT NULL COMMENT 'Order notes written by the customer on the cart page' AFTER shipping_zone;
ALTER TABLE reviews ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL COMMENT 'Logged-in customer who wrote the review (NULL = guest)' AFTER id;
ALTER TABLE reviews ADD KEY IF NOT EXISTS idx_reviews_user (user_id);
-- NOTE: the fk_reviews_user foreign key is created automatically on fresh
-- installs by CREATE TABLE above. For databases imported from an older
-- version, add it once manually if needed:
--   ALTER TABLE reviews ADD CONSTRAINT fk_reviews_user
--       FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL;
