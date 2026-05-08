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

if ($action === 'create_reservation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_reservation_create();
    exit;
}

if ($action === 'cancel_reservation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_reservation_cancel();
    exit;
}

if ($action === 'create_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_room_create();
    exit;
}

if ($action === 'update_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_room_update();
    exit;
}

if ($action === 'deactivate_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_room_deactivate();
    exit;
}

if ($action === 'create_showtime' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_showtime_create();
    exit;
}

if ($action === 'update_showtime' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_showtime_update();
    exit;
}

if ($action === 'deactivate_showtime' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_showtime_deactivate();
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
    case 'cartelera':
        render_dashboard();
        break;

    case 'movie':
        render_movie_detail();
        break;

    case 'seats':
        render_seat_selection();
        break;

    case 'my_reservations':
        render_my_reservations();
        break;

    case 'ticket':
        render_reservation_ticket();
        break;

    case 'confiteria':
    case 'socios':
    case 'pago':
        render_coming_soon_page($page);
        break;

    case 'admin':
        render_admin_panel();
        break;

    default:
        render_not_found_page(
            'Pagina no encontrada',
            'La ruta solicitada no existe o ya no esta disponible.'
        );
        break;
}
