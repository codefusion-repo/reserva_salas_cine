<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/controllers/AuthController.php';

app_session_start();

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? null;

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_login();
    exit;
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_register();
    exit;
}

if ($action === 'logout') {
    handle_logout();
    exit;
}

if ($page === null) {
    $page = auth_check() ? 'dashboard' : 'login';
}

switch ($page) {
    case 'login':
    case 'register':
        if (auth_check()) {
            redirect_to('index.php?page=dashboard');
        }

        render_auth_page($page);
        break;

    case 'dashboard':
        render_dashboard();
        break;

    case 'admin':
        render_admin_panel();
        break;

    default:
        http_response_code(404);
        render_auth_page('login', ['Pagina no encontrada.']);
        break;
}
