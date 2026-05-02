CREATE DATABASE IF NOT EXISTS reserva_salas_cine
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE reserva_salas_cine;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(120) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_rooms_name (name),
    KEY idx_rooms_active (is_active),
    KEY idx_rooms_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    synopsis TEXT NOT NULL,
    genre VARCHAR(80) NOT NULL,
    release_year SMALLINT UNSIGNED NOT NULL,
    classification VARCHAR(20) NOT NULL,
    poster_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_movies_title (title),
    KEY idx_movies_genre (genre),
    KEY idx_movies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS showtimes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movie_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    format_label VARCHAR(40) NOT NULL DEFAULT '2D',
    language_label VARCHAR(40) NOT NULL DEFAULT 'Subtitulada',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_showtimes_movie
        FOREIGN KEY (movie_id) REFERENCES movies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_showtimes_room
        FOREIGN KEY (room_id) REFERENCES rooms (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_showtimes_valid_range CHECK (ends_at > starts_at),
    KEY idx_showtimes_movie_starts (movie_id, starts_at),
    KEY idx_showtimes_room_starts (room_id, starts_at),
    KEY idx_showtimes_active_starts (is_active, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    showtime_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_showtime
        FOREIGN KEY (showtime_id) REFERENCES showtimes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    UNIQUE KEY uq_reservations_id_showtime (id, showtime_id),
    KEY idx_reservations_user_status_created (user_id, status, created_at),
    KEY idx_reservations_showtime_status (showtime_id, status),
    KEY idx_reservations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_seats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    showtime_id INT UNSIGNED NOT NULL,
    seat_row VARCHAR(3) NOT NULL,
    seat_number SMALLINT UNSIGNED NOT NULL,
    seat_type ENUM('standard', 'vip', 'accessibility') NOT NULL DEFAULT 'standard',
    CONSTRAINT fk_reservation_seats_reservation_showtime
        FOREIGN KEY (reservation_id, showtime_id) REFERENCES reservations (id, showtime_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    UNIQUE KEY uq_reservation_seats_reservation_seat (reservation_id, seat_row, seat_number),
    KEY idx_reservation_seats_showtime_seat (showtime_id, seat_row, seat_number),
    KEY idx_reservation_seats_type (seat_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
