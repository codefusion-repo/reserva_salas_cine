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
    return db_fetch_all(
        'SELECT
            s.id,
            s.movie_id,
            s.room_id,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label,
            r.name AS room_name
         FROM showtimes s
         INNER JOIN rooms r ON r.id = s.room_id
         WHERE s.movie_id = :movie_id
           AND s.is_active = :is_active
           AND r.is_active = :room_is_active
         ORDER BY s.starts_at ASC, s.id ASC',
        [
            'movie_id' => $movieId,
            'is_active' => 1,
            'room_is_active' => 1,
        ]
    );
}
