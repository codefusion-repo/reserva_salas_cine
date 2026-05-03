<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';

const CSRF_SESSION_KEY = '_csrf_token';
const CSRF_FIELD_NAME = 'csrf_token';

function csrf_token(): string
{
    app_session_start();

    $token = $_SESSION[CSRF_SESSION_KEY] ?? null;

    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION[CSRF_SESSION_KEY] = $token;
    }

    return $token;
}

function csrf_token_field(): string
{
    return '<input type="hidden" name="' . CSRF_FIELD_NAME . '" value="' . e(csrf_token()) . '">';
}

function csrf_validate_token(mixed $token): bool
{
    app_session_start();

    $sessionToken = $_SESSION[CSRF_SESSION_KEY] ?? null;

    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    if (!is_scalar($token)) {
        return false;
    }

    $submittedToken = (string) $token;

    return $submittedToken !== '' && hash_equals($sessionToken, $submittedToken);
}

function csrf_require_valid_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate_token($_POST[CSRF_FIELD_NAME] ?? null)) {
        return;
    }

    http_response_code(403);
    exit('Solicitud no valida.');
}
