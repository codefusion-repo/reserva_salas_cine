<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/assets.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../models/Movie.php';
require_once __DIR__ . '/../models/User.php';

const AUTH_MIN_PASSWORD_LENGTH = 8;

function auth_mode_from_page(?string $page): string
{
    return $page === 'register' ? 'register' : 'login';
}

function render_auth_page(string $mode, array $errors = [], array $old = []): void
{
    $mode = auth_mode_from_page($mode);
    $messages = flash_get();

    require __DIR__ . '/../views/auth.php';
}

function render_dashboard(): void
{
    auth_require_login();
    $user = current_user();
    $messages = flash_get();
    $movies = [];
    $movieLoadError = false;

    try {
        $movies = movie_active_all();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $movieLoadError = true;
    }

    require __DIR__ . '/../views/dashboard.php';
}

function render_movie_detail(): void
{
    auth_require_login();

    $user = current_user();
    $messages = flash_get();
    $movie = null;
    $showtimeDays = [];
    $movieLoadError = false;
    $movieNotFound = false;
    $movieId = movie_id_from_request($_GET['id'] ?? null);

    if ($movieId === null) {
        http_response_code(404);
        $movieNotFound = true;

        require __DIR__ . '/../views/movie_detail.php';
        return;
    }

    try {
        $movie = movie_find_active_by_id($movieId);

        if ($movie === null) {
            http_response_code(404);
            $movieNotFound = true;
        } else {
            $showtimeDays = movie_showtimes_by_day(movie_active_showtimes($movieId));
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $movieLoadError = true;
    }

    require __DIR__ . '/../views/movie_detail.php';
}

function movie_id_from_request(mixed $value): ?int
{
    if (!is_scalar($value)) {
        return null;
    }

    $value = trim((string) $value);

    if ($value === '' || ctype_digit($value) === false) {
        return null;
    }

    $movieId = (int) $value;

    return $movieId > 0 ? $movieId : null;
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
        ];
    }

    return array_values($days);
}

function movie_spanish_weekday(DateTimeImmutable $date): string
{
    $days = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    return $days[(int) $date->format('N')] ?? '';
}

function movie_spanish_month(DateTimeImmutable $date): string
{
    $months = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    return $months[(int) $date->format('n')] ?? '';
}

function render_admin_panel(): void
{
    if (!auth_require_admin()) {
        $user = current_user();
        $messages = [
            [
                'type' => 'error',
                'message' => 'Acceso denegado. Esta vista requiere rol administrador.',
            ],
        ];

        require __DIR__ . '/../views/admin_denied.php';
        return;
    }

    $user = current_user();
    $messages = flash_get();

    require __DIR__ . '/../views/admin.php';
}

function handle_login(): void
{
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $errors = [];

    if ($email === '') {
        $errors[] = 'Ingresa tu correo electronico.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Ingresa un correo electronico valido.';
    }

    if ($password === '') {
        $errors[] = 'Ingresa tu contrasena.';
    }

    if ($errors !== []) {
        render_auth_page('login', $errors, ['email' => $email]);
        return;
    }

    try {
        $user = user_find_by_email($email);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        render_auth_page('login', ['No se pudo iniciar sesion en este momento.'], ['email' => $email]);
        return;
    }

    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        render_auth_page('login', ['Correo o contrasena incorrectos.'], ['email' => $email]);
        return;
    }

    auth_login(user_public_payload($user));
    flash_set('success', 'Sesion iniciada correctamente.');
    redirect_to('index.php?page=dashboard');
}

function handle_register(): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $errors = [];

    if ($name === '') {
        $errors[] = 'Ingresa tu nombre.';
    }

    if ($email === '') {
        $errors[] = 'Ingresa tu correo electronico.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Ingresa un correo electronico valido.';
    }

    if ($password === '') {
        $errors[] = 'Ingresa una contrasena.';
    } elseif (mb_strlen($password) < AUTH_MIN_PASSWORD_LENGTH) {
        $errors[] = 'La contrasena debe tener al menos ' . AUTH_MIN_PASSWORD_LENGTH . ' caracteres.';
    }

    if ($errors === []) {
        try {
            if (user_email_exists($email)) {
                $errors[] = 'Ya existe una cuenta con ese correo.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'No se pudo validar el correo en este momento.';
        }
    }

    if ($errors !== []) {
        render_auth_page('register', $errors, ['name' => $name, 'email' => $email]);
        return;
    }

    try {
        $userId = user_create($name, $email, $password);
        $user = user_find_by_id($userId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        render_auth_page('register', ['No se pudo crear la cuenta en este momento.'], ['name' => $name, 'email' => $email]);
        return;
    }

    if ($user === null) {
        render_auth_page('register', ['No se pudo iniciar la sesion nueva.'], ['name' => $name, 'email' => $email]);
        return;
    }

    auth_login($user);
    flash_set('success', 'Cuenta creada correctamente.');
    redirect_to('index.php?page=dashboard');
}

function handle_logout(): void
{
    auth_logout();
    flash_set('success', 'Sesion cerrada correctamente.');
    redirect_to('index.php?page=login');
}
