<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

const AUTH_SESSION_KEY = 'auth_user';

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function auth_login(array $user): void
{
    app_session_start();
    session_regenerate_id(true);
    $_SESSION[AUTH_SESSION_KEY] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function auth_logout(): void
{
    app_session_start();
    $_SESSION = [];
    session_regenerate_id(true);
}

function current_user(): ?array
{
    app_session_start();
    $user = $_SESSION[AUTH_SESSION_KEY] ?? null;

    return is_array($user) ? $user : null;
}

function auth_check(): bool
{
    return current_user() !== null;
}

function auth_is_admin(): bool
{
    $user = current_user();

    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function auth_require_login(): void
{
    if (!auth_check()) {
        flash_set('error', 'Debes iniciar sesion para continuar.');
        redirect_to('index.php?page=login');
    }
}

function auth_require_admin(): bool
{
    auth_require_login();

    if (!auth_is_admin()) {
        http_response_code(403);

        return false;
    }

    return true;
}

function auth_require_admin_action(): void
{
    auth_require_login();

    if (!auth_is_admin()) {
        http_response_code(403);
        exit('Acceso denegado. Esta accion requiere rol administrador.');
    }
}
