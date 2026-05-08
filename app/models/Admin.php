<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

function admin_summary_stats(): array
{
    $summary = [
        'users_registered' => 0,
        'rooms' => [
            'total' => 0,
            'active' => 0,
        ],
        'movies' => [
            'total' => 0,
            'active' => 0,
        ],
        'showtimes' => [
            'total' => 0,
            'active' => 0,
        ],
        'reservations' => [
            'pending' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
        ],
        'next_showtime' => null,
    ];

    $counts = db_fetch_one(
        'SELECT
            (SELECT COUNT(*) FROM users) AS users_registered,
            (SELECT COUNT(*) FROM rooms) AS total_rooms,
            (SELECT COUNT(*) FROM rooms WHERE is_active = 1) AS active_rooms,
            (SELECT COUNT(*) FROM movies) AS total_movies,
            (SELECT COUNT(*) FROM movies WHERE is_active = 1) AS active_movies,
            (SELECT COUNT(*) FROM showtimes) AS total_showtimes,
            (SELECT COUNT(*) FROM showtimes WHERE is_active = 1) AS active_showtimes'
    );

    if ($counts !== null) {
        $summary['users_registered'] = max(0, (int) ($counts['users_registered'] ?? 0));
        $summary['rooms']['total'] = max(0, (int) ($counts['total_rooms'] ?? 0));
        $summary['rooms']['active'] = max(0, (int) ($counts['active_rooms'] ?? 0));
        $summary['movies']['total'] = max(0, (int) ($counts['total_movies'] ?? 0));
        $summary['movies']['active'] = max(0, (int) ($counts['active_movies'] ?? 0));
        $summary['showtimes']['total'] = max(0, (int) ($counts['total_showtimes'] ?? 0));
        $summary['showtimes']['active'] = max(0, (int) ($counts['active_showtimes'] ?? 0));
    }

    $reservationRows = db_fetch_all(
        'SELECT status, COUNT(*) AS total
         FROM reservations
         GROUP BY status'
    );

    foreach ($reservationRows as $row) {
        $status = (string) ($row['status'] ?? '');

        if (array_key_exists($status, $summary['reservations'])) {
            $summary['reservations'][$status] = max(0, (int) ($row['total'] ?? 0));
        }
    }

    $summary['next_showtime'] = db_fetch_one(
        'SELECT
            s.id,
            s.starts_at,
            s.format_label,
            s.language_label,
            m.title AS movie_title,
            r.name AS room_name,
            r.location AS room_location
         FROM showtimes s
         INNER JOIN movies m ON m.id = s.movie_id
         INNER JOIN rooms r ON r.id = s.room_id
         WHERE s.is_active = 1
           AND m.is_active = 1
           AND r.is_active = 1
           AND s.starts_at >= NOW()
         ORDER BY s.starts_at ASC
         LIMIT 1'
    );

    return $summary;
}

function admin_rooms_all(): array
{
    return db_fetch_all(
        'SELECT id, name, location, capacity, is_active
         FROM rooms
         ORDER BY is_active DESC, name ASC, id ASC'
    );
}

function admin_rooms_active_all(): array
{
    return db_fetch_all(
        'SELECT id, name, location, capacity, is_active
         FROM rooms
         WHERE is_active = :is_active
         ORDER BY name ASC, id ASC',
        ['is_active' => 1]
    );
}

function admin_room_find_by_id(int $roomId): ?array
{
    return db_fetch_one(
        'SELECT id, name, location, capacity, is_active
         FROM rooms
         WHERE id = :id
         LIMIT 1',
        ['id' => $roomId]
    );
}

function admin_room_name_exists(string $name, ?int $excludeRoomId = null): bool
{
    $params = ['name' => $name];
    $sql = 'SELECT id
            FROM rooms
            WHERE name = :name';

    if ($excludeRoomId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeRoomId;
    }

    $sql .= ' LIMIT 1';

    return db_fetch_one($sql, $params) !== null;
}

function admin_room_create(string $name, string $location, int $capacity): int
{
    db_execute(
        'INSERT INTO rooms (name, location, capacity, is_active)
         VALUES (:name, :location, :capacity, :is_active)',
        [
            'name' => $name,
            'location' => $location,
            'capacity' => $capacity,
            'is_active' => 1,
        ]
    );

    return (int) db()->lastInsertId();
}

function admin_room_update(int $roomId, string $name, string $location, int $capacity): bool
{
    return db_execute(
        'UPDATE rooms
         SET name = :name,
             location = :location,
             capacity = :capacity
         WHERE id = :id',
        [
            'id' => $roomId,
            'name' => $name,
            'location' => $location,
            'capacity' => $capacity,
        ]
    );
}

function admin_room_deactivate(int $roomId): bool
{
    return db_execute(
        'UPDATE rooms
         SET is_active = :is_active
         WHERE id = :id',
        [
            'id' => $roomId,
            'is_active' => 0,
        ]
    );
}

function admin_movies_all(): array
{
    return db_fetch_all(
        'SELECT id, title, synopsis, genre, release_year, classification, poster_path, is_active
         FROM movies
         ORDER BY is_active DESC, title ASC, id ASC'
    );
}

function admin_movies_active_all(): array
{
    return db_fetch_all(
        'SELECT id, title, genre, release_year, classification, is_active
         FROM movies
         WHERE is_active = :is_active
         ORDER BY title ASC, id ASC',
        ['is_active' => 1]
    );
}

function admin_movie_find_by_id(int $movieId): ?array
{
    return db_fetch_one(
        'SELECT id, title, synopsis, genre, release_year, classification, poster_path, is_active
         FROM movies
         WHERE id = :id
         LIMIT 1',
        ['id' => $movieId]
    );
}

function admin_movie_active_find_by_id(int $movieId): ?array
{
    return db_fetch_one(
        'SELECT id, title, is_active
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

function admin_movie_create(
    string $title,
    string $synopsis,
    string $genre,
    int $releaseYear,
    string $classification,
    ?string $posterPath,
    bool $isActive
): int {
    db_execute(
        'INSERT INTO movies (title, synopsis, genre, release_year, classification, poster_path, is_active)
         VALUES (:title, :synopsis, :genre, :release_year, :classification, :poster_path, :is_active)',
        [
            'title' => $title,
            'synopsis' => $synopsis,
            'genre' => $genre,
            'release_year' => $releaseYear,
            'classification' => $classification,
            'poster_path' => $posterPath,
            'is_active' => $isActive ? 1 : 0,
        ]
    );

    return (int) db()->lastInsertId();
}

function admin_movie_update(
    int $movieId,
    string $title,
    string $synopsis,
    string $genre,
    int $releaseYear,
    string $classification,
    ?string $posterPath,
    bool $isActive
): bool {
    return db_execute(
        'UPDATE movies
         SET title = :title,
             synopsis = :synopsis,
             genre = :genre,
             release_year = :release_year,
             classification = :classification,
             poster_path = :poster_path,
             is_active = :is_active
         WHERE id = :id',
        [
            'id' => $movieId,
            'title' => $title,
            'synopsis' => $synopsis,
            'genre' => $genre,
            'release_year' => $releaseYear,
            'classification' => $classification,
            'poster_path' => $posterPath,
            'is_active' => $isActive ? 1 : 0,
        ]
    );
}

function admin_movie_set_active(int $movieId, bool $isActive): bool
{
    return db_execute(
        'UPDATE movies
         SET is_active = :is_active
         WHERE id = :id',
        [
            'id' => $movieId,
            'is_active' => $isActive ? 1 : 0,
        ]
    );
}

function admin_room_active_find_by_id(int $roomId): ?array
{
    return db_fetch_one(
        'SELECT id, name, location, capacity, is_active
         FROM rooms
         WHERE id = :id
           AND is_active = :is_active
         LIMIT 1',
        [
            'id' => $roomId,
            'is_active' => 1,
        ]
    );
}

function admin_showtimes_all(): array
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
            s.is_active,
            m.title AS movie_title,
            m.is_active AS movie_is_active,
            r.name AS room_name,
            r.location AS room_location,
            r.is_active AS room_is_active
         FROM showtimes s
         INNER JOIN movies m ON m.id = s.movie_id
         INNER JOIN rooms r ON r.id = s.room_id
         ORDER BY s.starts_at DESC, s.id DESC'
    );
}

function admin_showtime_find_by_id(int $showtimeId): ?array
{
    return db_fetch_one(
        'SELECT
            s.id,
            s.movie_id,
            s.room_id,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label,
            s.is_active,
            m.title AS movie_title,
            m.is_active AS movie_is_active,
            r.name AS room_name,
            r.location AS room_location,
            r.is_active AS room_is_active
         FROM showtimes s
         INNER JOIN movies m ON m.id = s.movie_id
         INNER JOIN rooms r ON r.id = s.room_id
         WHERE s.id = :id
         LIMIT 1',
        ['id' => $showtimeId]
    );
}

function admin_showtime_has_overlap(int $roomId, string $startsAt, string $endsAt, ?int $excludeShowtimeId = null): bool
{
    $params = [
        'room_id' => $roomId,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'is_active' => 1,
    ];
    $sql = 'SELECT id
            FROM showtimes
            WHERE room_id = :room_id
              AND is_active = :is_active
              AND starts_at < :ends_at
              AND ends_at > :starts_at';

    if ($excludeShowtimeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeShowtimeId;
    }

    $sql .= ' LIMIT 1';

    return db_fetch_one($sql, $params) !== null;
}

function admin_showtime_create(
    int $movieId,
    int $roomId,
    string $startsAt,
    string $endsAt,
    string $formatLabel,
    string $languageLabel
): int {
    db_execute(
        'INSERT INTO showtimes (movie_id, room_id, starts_at, ends_at, format_label, language_label, is_active)
         VALUES (:movie_id, :room_id, :starts_at, :ends_at, :format_label, :language_label, :is_active)',
        [
            'movie_id' => $movieId,
            'room_id' => $roomId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'format_label' => $formatLabel,
            'language_label' => $languageLabel,
            'is_active' => 1,
        ]
    );

    return (int) db()->lastInsertId();
}

function admin_showtime_update(
    int $showtimeId,
    int $movieId,
    int $roomId,
    string $startsAt,
    string $endsAt,
    string $formatLabel,
    string $languageLabel,
    bool $isActive
): bool {
    return db_execute(
        'UPDATE showtimes
         SET movie_id = :movie_id,
             room_id = :room_id,
             starts_at = :starts_at,
             ends_at = :ends_at,
             format_label = :format_label,
             language_label = :language_label,
             is_active = :is_active
         WHERE id = :id',
        [
            'id' => $showtimeId,
            'movie_id' => $movieId,
            'room_id' => $roomId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'format_label' => $formatLabel,
            'language_label' => $languageLabel,
            'is_active' => $isActive ? 1 : 0,
        ]
    );
}

function admin_showtime_deactivate(int $showtimeId): bool
{
    return admin_showtime_set_active($showtimeId, false);
}

function admin_showtime_set_active(int $showtimeId, bool $isActive): bool
{
    return db_execute(
        'UPDATE showtimes
         SET is_active = :is_active
         WHERE id = :id',
        [
            'id' => $showtimeId,
            'is_active' => $isActive ? 1 : 0,
        ]
    );
}

function admin_datetime_input_value(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    try {
        $date = new DateTimeImmutable((string) $value);
    } catch (Throwable $exception) {
        return '';
    }

    return $date->format('Y-m-d\TH:i');
}

function admin_status_label(mixed $isActive): string
{
    return (int) $isActive === 1 ? 'Activa' : 'Inactiva';
}

function admin_status_css_class(mixed $isActive): string
{
    return (int) $isActive === 1 ? 'active' : 'inactive';
}
