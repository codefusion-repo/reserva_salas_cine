<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

function movie_active_all(): array
{
    return db_fetch_all(
        'SELECT id, title, genre, release_year, classification, poster_path
         FROM movies
         WHERE is_active = :is_active
         ORDER BY id ASC',
        ['is_active' => 1]
    );
}

function movie_find_active_by_id(int $movieId): ?array
{
    return db_fetch_one(
        'SELECT id, title, synopsis, genre, release_year, classification, poster_path
         FROM movies
         WHERE id = :id
           AND is_active = :is_active
         LIMIT 1',
        [
            'id' => $movieId,
            'is_active' => 1,
        ]
    );
}

function movie_active_showtimes(int $movieId): array
{
    $showtimes = db_fetch_all(
        'SELECT
            s.id,
            s.movie_id,
            s.room_id,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label,
            r.name AS room_name,
            r.capacity AS room_capacity,
            COALESCE(occupied.occupied_active_seats, 0) AS occupied_active_seats
         FROM showtimes s
         INNER JOIN rooms r ON r.id = s.room_id
         LEFT JOIN (
            SELECT rs.showtime_id, COUNT(rs.id) AS occupied_active_seats
            FROM reservation_seats rs
            INNER JOIN reservations rv
                ON rv.id = rs.reservation_id
               AND rv.showtime_id = rs.showtime_id
               AND rv.status IN (:status_pending, :status_confirmed)
            GROUP BY rs.showtime_id
         ) occupied ON occupied.showtime_id = s.id
         WHERE s.movie_id = :movie_id
           AND s.is_active = :is_active
           AND r.is_active = :room_is_active
         ORDER BY s.starts_at ASC, s.id ASC',
        [
            'movie_id' => $movieId,
            'is_active' => 1,
            'room_is_active' => 1,
            'status_pending' => 'pending',
            'status_confirmed' => 'confirmed',
        ]
    );

    foreach ($showtimes as $index => $showtime) {
        $capacity = max(0, (int) ($showtime['room_capacity'] ?? 0));
        $occupiedSeats = max(0, (int) ($showtime['occupied_active_seats'] ?? 0));

        $showtimes[$index]['room_capacity'] = $capacity;
        $showtimes[$index]['occupied_active_seats'] = $occupiedSeats;
        $showtimes[$index]['available_seats'] = max(0, $capacity - $occupiedSeats);
    }

    return $showtimes;
}
