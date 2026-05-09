<?php
declare(strict_types=1);

const MEMBER_DEMO_SESSION_KEY = 'is_member_demo';

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function flash_set(string $type, string $message): void
{
    app_session_start();
    $_SESSION['_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_get(): array
{
    app_session_start();

    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($messages) ? $messages : [];
}

function is_member_demo_active(): bool
{
    app_session_start();

    return ($_SESSION[MEMBER_DEMO_SESSION_KEY] ?? false) === true;
}

function set_member_demo_active(bool $isActive): void
{
    app_session_start();

    if ($isActive === true) {
        $_SESSION[MEMBER_DEMO_SESSION_KEY] = true;
        return;
    }

    unset($_SESSION[MEMBER_DEMO_SESSION_KEY]);
}
