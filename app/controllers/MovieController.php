<?php
declare(strict_types=1);

require_once __DIR__ . '/ErrorController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/assets.php';
require_once __DIR__ . '/../helpers/reservation_view.php';
require_once __DIR__ . '/../models/Movie.php';

function movie_filter_value_from_request(mixed $value, int $maxLength = 80): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

    if ($normalized === null) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($normalized, 0, $maxLength, 'UTF-8');
    }

    return substr($normalized, 0, $maxLength);
}

function movie_active_filters_from_request(array $request): array
{
    return [
        'q' => movie_filter_value_from_request($request['q'] ?? ''),
        'genre' => movie_filter_value_from_request($request['genre'] ?? ''),
        'classification' => movie_filter_value_from_request($request['classification'] ?? ''),
    ];
}

function render_dashboard(): void
{
    auth_require_login();
    $user = current_user();
    $messages = flash_get();
    $movieFilters = movie_active_filters_from_request($_GET);
    $movieFilterOptions = [
        'genres' => [],
        'classifications' => [],
    ];
    $hasActiveMovieFilters = $movieFilters['q'] !== ''
        || $movieFilters['genre'] !== ''
        || $movieFilters['classification'] !== '';
    $movies = [];
    $upcomingMovies = [];
    $upcomingSource = 'inactive_movies';
    $movieLoadError = false;
    $upcomingLoadError = false;

    try {
        $movieFilterOptions = movie_filter_options();
        $movies = movie_active_all($movieFilters);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $movieLoadError = true;
    }

    try {
        $upcomingMovies = movie_upcoming_all();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $upcomingLoadError = true;
    }

    if (!$upcomingLoadError && $upcomingMovies === []) {
        $upcomingMovies = movie_upcoming_demo_all();
        $upcomingSource = 'demo';
    }

    require __DIR__ . '/../views/dashboard.php';
}

function render_movie_detail(): void
{
    auth_require_login();

    $movie = null;
    $showtimeDays = [];
    $movieLoadError = false;
    $movieNotFound = false;
    $movieId = movie_id_from_request($_GET['id'] ?? null);

    if ($movieId === null) {
        render_not_found_page(
            'Pelicula no encontrada',
            'La pelicula solicitada no existe o no esta activa.'
        );
        return;
    }

    try {
        $movie = movie_find_active_by_id($movieId);

        if ($movie === null) {
            render_not_found_page(
                'Pelicula no encontrada',
                'La pelicula solicitada no existe o no esta activa.'
            );
            return;
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        $user = current_user();
        $messages = flash_get();
        http_response_code(500);
        $movieLoadError = true;

        require __DIR__ . '/../views/movie_detail.php';
        return;
    }

    $user = current_user();
    $messages = flash_get();

    try {
        $showtimeDays = movie_showtimes_by_day(movie_active_showtimes($movieId));
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $movieLoadError = true;
    }

    require __DIR__ . '/../views/movie_detail.php';
}


function movie_id_from_request(mixed $value): ?int
{
    return positive_int_from_request($value);
}


function movie_showtimes_by_day(array $showtimes): array
{
    $days = [];
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    foreach ($showtimes as $showtime) {
        try {
            $startsAt = new DateTimeImmutable((string) ($showtime['starts_at'] ?? ''));
        } catch (Throwable $exception) {
            continue;
        }

        $dateKey = $startsAt->format('Y-m-d');

        if (!isset($days[$dateKey])) {
            $days[$dateKey] = [
                'date_key' => $dateKey,
                'day_label' => $dateKey === $today ? 'Hoy' : movie_spanish_weekday($startsAt),
                'date_label' => $startsAt->format('j') . '/' . movie_spanish_month($startsAt),
                'showtimes' => [],
            ];
        }

        $days[$dateKey]['showtimes'][] = [
            'id' => (int) ($showtime['id'] ?? 0),
            'time_label' => $startsAt->format('H:i') . ' HRS',
            'format_label' => (string) ($showtime['format_label'] ?? ''),
            'language_label' => (string) ($showtime['language_label'] ?? ''),
            'room_name' => (string) ($showtime['room_name'] ?? ''),
            'available_seats' => max(0, (int) ($showtime['available_seats'] ?? 0)),
            'is_sold_out' => max(0, (int) ($showtime['available_seats'] ?? 0)) <= 0,
            'availability_label' => movie_showtime_availability_label((int) ($showtime['available_seats'] ?? 0)),
            'availability_state' => movie_showtime_availability_state((int) ($showtime['available_seats'] ?? 0)),
        ];
    }

    return array_values($days);
}

function movie_showtime_availability_label(int $availableSeats): string
{
    $availableSeats = max(0, $availableSeats);

    if ($availableSeats === 0) {
        return 'Agotada';
    }

    if ($availableSeats <= 5) {
        return 'Ultimas butacas: ' . $availableSeats . ' disponibles';
    }

    return $availableSeats . ' disponibles';
}

function movie_showtime_availability_state(int $availableSeats): string
{
    $availableSeats = max(0, $availableSeats);

    if ($availableSeats === 0) {
        return 'none';
    }

    if ($availableSeats <= 5) {
        return 'low';
    }

    return 'available';
}
