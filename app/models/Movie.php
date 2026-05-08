<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

function movie_active_all(array $filters = []): array
{
    $sql = 'SELECT id, title, genre, release_year, classification, poster_path
         FROM movies
         WHERE is_active = :is_active';
    $params = ['is_active' => 1];

    $title = trim((string) ($filters['q'] ?? ''));

    if ($title !== '') {
        $sql .= " AND title LIKE :title ESCAPE '\\\\'";
        $params['title'] = '%' . movie_like_pattern($title) . '%';
    }

    $genre = trim((string) ($filters['genre'] ?? ''));

    if ($genre !== '') {
        $sql .= ' AND genre = :genre';
        $params['genre'] = $genre;
    }

    $classification = trim((string) ($filters['classification'] ?? ''));

    if ($classification !== '') {
        $sql .= ' AND classification = :classification';
        $params['classification'] = $classification;
    }

    $sql .= ' ORDER BY id ASC';

    return db_fetch_all(
        $sql,
        $params
    );
}

function movie_like_pattern(string $value): string
{
    return strtr($value, [
        '\\' => '\\\\',
        '%' => '\\%',
        '_' => '\\_',
    ]);
}

function movie_filter_options(): array
{
    $genres = db_fetch_all(
        'SELECT DISTINCT genre
         FROM movies
         WHERE is_active = :is_active
           AND genre IS NOT NULL
           AND genre <> \'\'
         ORDER BY genre ASC',
        ['is_active' => 1]
    );

    $classifications = db_fetch_all(
        'SELECT DISTINCT classification
         FROM movies
         WHERE is_active = :is_active
           AND classification IS NOT NULL
           AND classification <> \'\'
         ORDER BY classification ASC',
        ['is_active' => 1]
    );

    return [
        'genres' => array_map(
            static fn (array $row): string => (string) ($row['genre'] ?? ''),
            $genres
        ),
        'classifications' => array_map(
            static fn (array $row): string => (string) ($row['classification'] ?? ''),
            $classifications
        ),
    ];
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
