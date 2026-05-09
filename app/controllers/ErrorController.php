<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';

function render_error_page(string $heading, string $copy, int $statusCode = 404, array $messages = []): void
{
    http_response_code($statusCode);

    $user = current_user();
    $statusLabel = (string) $statusCode;

    require __DIR__ . '/../views/error.php';
}

function render_not_found_page(string $heading, string $copy, array $messages = []): void
{
    render_error_page($heading, $copy, 404, $messages);
}

function render_admin_denied_page(): void
{
    $user = current_user();
    $messages = [
        [
            'type' => 'error',
            'message' => 'Acceso denegado. Necesitas una cuenta administradora.',
        ],
    ];

    require __DIR__ . '/../views/admin_denied.php';
}
