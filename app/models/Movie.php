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
