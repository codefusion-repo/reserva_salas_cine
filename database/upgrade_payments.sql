-- Upgrade local para bases XAMPP existentes creadas antes de #41.
-- Las instalaciones nuevas pueden usar database/schema.sql y database/seed.sql.
-- Este script es idempotente, demo-only, y solo instala pagos simulados.
-- No modifica usuarios, peliculas, salas, funciones, reservas existentes, productos,
-- membresias, pasarelas, datos de tarjeta, facturas, historial de usuario ni UI admin.
-- Ejecutar contra la base activa, por ejemplo:
-- mysql -u root reserva_salas_cine < database/upgrade_payments.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkout_type ENUM('reservation', 'concessions', 'membership') NOT NULL,
    reservation_id INT UNSIGNED DEFAULT NULL,
    reference_code VARCHAR(40) NOT NULL,
    status ENUM('simulated_paid') NOT NULL DEFAULT 'simulated_paid',
    subtotal_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'CLP',
    payment_method ENUM('simulated') NOT NULL DEFAULT 'simulated',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_payments_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_payments_amounts_non_negative
        CHECK (subtotal_amount >= 0 AND discount_amount >= 0 AND total_amount >= 0),
    UNIQUE KEY uq_payments_reference_code (reference_code),
    UNIQUE KEY uq_payments_reservation (reservation_id),
    KEY idx_payments_user_created (user_id, created_at),
    KEY idx_payments_checkout_type (checkout_type),
    KEY idx_payments_status_paid_at (status, paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNSIGNED NOT NULL,
    item_type ENUM('ticket', 'concession', 'membership') NOT NULL,
    item_label VARCHAR(180) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_items_payment
        FOREIGN KEY (payment_id) REFERENCES payments (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_payment_items_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_payment_items_amounts_non_negative
        CHECK (unit_amount >= 0 AND total_amount >= 0),
    KEY idx_payment_items_payment (payment_id),
    KEY idx_payment_items_type (item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
