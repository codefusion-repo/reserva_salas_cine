-- Upgrade local para bases XAMPP existentes creadas antes de #85.
-- Las instalaciones nuevas pueden usar database/schema.sql y database/seed.sql.
-- Este script es idempotente, demo-only, y solo instala productos de confiteria.
-- No modifica reservas, usuarios, peliculas, salas, funciones, pagos, cupones ni socios.

SET NAMES utf8mb4;

USE reserva_salas_cine;

CREATE TABLE IF NOT EXISTS concession_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL,
    price_amount DECIMAL(10, 2) NOT NULL,
    icon VARCHAR(20) DEFAULT NULL,
    badge VARCHAR(40) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_concession_products_active_sort (is_active, sort_order, id),
    KEY idx_concession_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO concession_products (id, name, description, price_amount, icon, badge, is_active, sort_order)
VALUES
    (1, 'Combo Clasico', 'Cabritas medianas + bebida.', 4500.00, '🍿🥤', 'Popular', 1, 10),
    (2, 'Combo Doble', 'Cabritas grandes + 2 bebidas.', 7900.00, '🍿🥤🥤', 'Para compartir', 1, 20),
    (3, 'Nachos Cine', 'Nachos + salsa.', 3800.00, '🌭', 'Snack', 1, 30),
    (4, 'Dulce Mix', 'Chocolates + gomitas.', 3200.00, '🍫', 'Dulce', 1, 40),
    (5, 'Bebida individual', 'Bebida mediana.', 1500.00, '🥤', 'Bebida', 1, 50),
    (6, 'Cabritas grandes', 'Cabritas grandes.', 3000.00, '🍿', 'Cabritas', 1, 60)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    price_amount = VALUES(price_amount),
    icon = VALUES(icon),
    badge = VALUES(badge),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);
