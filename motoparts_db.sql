USE `if0_41499406_motoparts_db`;
-- ============================================================
--  MotoParts Kenya — Complete Database
--  Import this file in phpMyAdmin: Database > Import > Choose File
--  All tables match the PHP source code exactly.
-- ============================================================


-- ============================================================
-- 1. USERS
--    Used by: login.php, register.php, checkout.php, admin/users.php
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id     INT          NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    phone       VARCHAR(20)  DEFAULT NULL,
    address     TEXT         DEFAULT NULL,
    role        ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. CATEGORIES
--    Used by: admin/categories.php, catalog.php, parts.php
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    category_id   INT          NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description   TEXT         DEFAULT NULL,
    icon          VARCHAR(50)  NOT NULL DEFAULT 'fa-cog',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. SPARE_PARTS  (also aliased as "parts" in some queries)
--    Used by: admin/parts.php, admin/stock.php, catalog.php,
--             part-detail.php, cart.php, checkout.php
-- ============================================================
CREATE TABLE IF NOT EXISTS spare_parts (
    part_id     INT            NOT NULL AUTO_INCREMENT,
    part_name   VARCHAR(200)   NOT NULL,
    category_id INT            DEFAULT NULL,
    price       DECIMAL(10,2)  NOT NULL,
    stock       INT            NOT NULL DEFAULT 0,
    description TEXT           DEFAULT NULL,
    image       VARCHAR(255)   NOT NULL DEFAULT 'default-part.jpg',
    brand       VARCHAR(100)   DEFAULT NULL,
    sku         VARCHAR(50)    DEFAULT NULL,
    is_active   TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (part_id),
    UNIQUE KEY uq_sku (sku),
    KEY idx_category (category_id),
    CONSTRAINT fk_part_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

;

-- ============================================================
-- 4. CART
--    Used by: cart.php, cart-action.php, checkout.php
-- ============================================================
CREATE TABLE IF NOT EXISTS cart (
    cart_id    INT       NOT NULL AUTO_INCREMENT,
    user_id    INT       NOT NULL,
    part_id    INT       NOT NULL,
    quantity   INT       NOT NULL DEFAULT 1,
    added_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cart_id),
    UNIQUE KEY uq_user_part (user_id, part_id),
    KEY idx_cart_user (user_id),
    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cart_part
        FOREIGN KEY (part_id) REFERENCES spare_parts(part_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. ORDERS
--    Used by: checkout.php, orders.php, admin/orders.php,
--             order-detail.php, order-success.php
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    order_id         INT           NOT NULL AUTO_INCREMENT,
    user_id          INT           NOT NULL,
    order_date       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount     DECIMAL(10,2) NOT NULL,
    status           ENUM('pending','confirmed','processing','shipped','delivered','cancelled')
                                   NOT NULL DEFAULT 'pending',
    shipping_address TEXT          DEFAULT NULL,
    notes            TEXT          DEFAULT NULL,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    KEY idx_order_user (user_id),
    CONSTRAINT fk_order_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. ORDER_DETAILS
--    Used by: checkout.php, order-detail.php, admin/orders.php
--    NOTE: checkout.php inserts column "price" (not unit_price)
-- ============================================================
CREATE TABLE IF NOT EXISTS order_details (
    order_detail_id INT           NOT NULL AUTO_INCREMENT,
    order_id        INT           NOT NULL,
    part_id         INT           NOT NULL,
    quantity        INT           NOT NULL,
    price           DECIMAL(10,2) NOT NULL,   -- matches checkout.php INSERT
    subtotal        DECIMAL(10,2) GENERATED ALWAYS AS (quantity * price) STORED,
    PRIMARY KEY (order_detail_id),
    KEY idx_od_order (order_id),
    KEY idx_od_part  (part_id),
    CONSTRAINT fk_od_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_od_part
        FOREIGN KEY (part_id) REFERENCES spare_parts(part_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. PAYMENTS
--    Used by: checkout.php, admin/payments.php, stk_push.php
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id     INT           NOT NULL AUTO_INCREMENT,
    order_id       INT           NOT NULL,
    payment_date   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount         DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','card','mobile_money','bank_transfer','mpesa')
                                  NOT NULL DEFAULT 'cash',
    transaction_id VARCHAR(100)  DEFAULT NULL,
    status         ENUM('pending','completed','failed','refunded')
                                  NOT NULL DEFAULT 'pending',
    PRIMARY KEY (payment_id),
    UNIQUE KEY uq_order_payment (order_id),
    CONSTRAINT fk_payment_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. SETTINGS
--    Used by: admin/settings.php, admin/dashboard.php
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    `key`      VARCHAR(100) NOT NULL,
    `value`    TEXT         DEFAULT NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ---- Admin user (login: admin@motoparts.com / password) ----
INSERT IGNORE INTO users (name, email, password, role) VALUES
('System Admin', 'admin@motoparts.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin');

-- ---- Demo customer (login: john@example.com / password) ----
INSERT IGNORE INTO users (name, email, password, phone, address, role) VALUES
('John Kamau', 'john@example.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+254712345678', 'Nairobi, Kenya', 'customer');

-- ---- Categories ----
INSERT IGNORE INTO categories (category_id, category_name, description, icon) VALUES
(1, 'Engine Parts',    'Pistons, valves, gaskets and engine internals',    'fa-circle-notch'),
(2, 'Brakes',          'Brake pads, discs, calipers and brake lines',      'fa-circle'),
(3, 'Electrical',      'Batteries, spark plugs, starters and wiring',      'fa-bolt'),
(4, 'Suspension',      'Forks, shocks, springs and linkages',              'fa-arrows-alt-v'),
(5, 'Transmission',    'Clutch plates, gear sets and chains',              'fa-cogs'),
(6, 'Body Parts',      'Fairings, fenders, mirrors and lights',            'fa-car'),
(7, 'Tyres & Wheels',  'Tyres, rims, spokes and tubes',                    'fa-circle'),
(8, 'Filters',         'Oil, air and fuel filters',                        'fa-filter');

-- ---- Spare Parts ----
INSERT IGNORE INTO spare_parts (part_name, category_id, price, stock, description, brand, sku) VALUES
('Piston Kit 100cc',      1,  2500.00, 45, 'Complete piston kit for 100cc engines. Includes piston, rings and pin.',           'Honda OEM',   'ENG-PIK-100'),
('Cylinder Head Gasket',  1,   850.00, 78, 'High temperature cylinder head gasket. Universal fit for most Japanese bikes.',    'Athena',      'ENG-CHG-001'),
('Valve Set (8pcs)',       1,  3200.00, 23, 'Complete valve set for 4-stroke engines. Inlet and exhaust valves included.',     'Mikuni',      'ENG-VAL-008'),
('Brake Pad Set Front',   2,  1200.00, 92, 'Semi-metallic front brake pads. Excellent stopping power in all conditions.',      'Brembo',      'BRK-FPD-001'),
('Brake Disc 220mm',      2,  4500.00, 34, 'Floating stainless steel brake disc. 220mm diameter with anti-corrosion coating.','EBC',         'BRK-DSC-220'),
('Brake Caliper Rear',    2,  6800.00, 12, 'Single-piston rear brake caliper. Direct bolt-on replacement.',                   'Nissin',      'BRK-CLR-R01'),
('Spark Plug NGK',        3,   450.00,200, 'NGK standard spark plug. Fits most 100-150cc motorcycles.',                       'NGK',         'ELC-SPK-NGK'),
('Battery 12V 7Ah',       3,  3800.00, 38, 'Sealed maintenance-free motorcycle battery. 12V 7Ah capacity.',                   'Yuasa',       'ELC-BAT-12V7'),
('Starter Motor',         3,  8500.00, 15, 'Electric starter motor. Universal fit with bracket mounting.',                    'Denso',       'ELC-STR-001'),
('Front Fork Set',        4, 18000.00,  8, 'Complete telescopic front fork assembly. 35mm tubes with progressive spring.',    'KYB',         'SUS-FRK-F35'),
('Rear Shock Absorber',   4,  7500.00, 22, 'Twin rear shock absorber set. Adjustable preload, 320mm length.',                 'Öhlins',      'SUS-SHK-R320'),
('Clutch Plate Kit',      5,  4200.00, 56, 'Complete clutch plate set. Includes friction and steel plates.',                  'Barnett',     'TRN-CLT-KIT'),
('Drive Chain 428',       5,  1800.00, 64, 'Heavy duty 428 drive chain. 120 links with master link included.',                'DID',         'TRN-CHN-428'),
('Sprocket Set',          5,  2800.00, 41, 'Front and rear sprocket kit. Heat treated steel for durability.',                 'JT Sprockets','TRN-SPK-SET'),
('Headlight Assembly',    6,  5500.00, 19, 'LED projector headlight assembly with DRL. Universal H4 socket.',                 'Moto Lamps',  'BDY-HLT-LED'),
('Side Mirror Pair',      6,  1400.00, 73, 'Foldable chrome side mirrors. Universal M10 thread mount.',                       'Generic',     'BDY-MRR-PR1'),
('Tyre 2.75-17 Front',    7,  4200.00, 31, 'Front tyre 2.75-17. All-terrain pattern suitable for paved and light off-road.', 'Maxxis',      'TYR-FRT-27517'),
('Tyre 3.00-17 Rear',     7,  4800.00, 28, 'Rear tyre 3.00-17. Reinforced sidewall for load carrying.',                     'Maxxis',      'TYR-RR-30017'),
('Oil Filter',            8,   380.00,150, 'Engine oil filter. Compatible with most 4-stroke motorcycle engines.',            'K&N',         'FLT-OIL-001'),
('Air Filter Element',    8,   650.00,110, 'Foam air filter element. Reusable and washable. Improves airflow.',              'K&N',         'FLT-AIR-001');

-- ---- Settings ----
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('store_name',          'MotoParts Kenya'),
('store_email',         'info@motoparts.com'),
('store_phone',         '+254700000000'),
('store_address',       'Nairobi, Kenya'),
('store_currency',      'KSh'),
('low_stock_threshold', '10'),
('tax_rate',            '16'),
('nairobi_fee',         '200'),
('county_fee',          '400'),
('free_threshold',      '5000'),
('delivery_note',       'Nairobi same-day delivery. Nationwide 1-3 business days.'),
('maintenance_mode',    '0'),
('items_per_page',      '15'),
('orders_notify_email', '');

-- ============================================================
-- Done! Default password for both seed accounts: password
-- To change admin password generate a new hash with:
--   php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
-- then run:
--   UPDATE users SET password='<hash>' WHERE email='admin@motoparts.com';
-- ============================================================



