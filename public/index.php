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

if ($action === 'member_demo_activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_member_demo_activate();
    exit;
}

if ($action === 'member_demo_deactivate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_member_demo_deactivate();
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

if ($action === 'set_room_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_room_set_active();
    exit;
}

if ($action === 'delete_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_room_delete();
    exit;
}

if ($action === 'deactivate_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_room_deactivate();
    exit;
}

if ($action === 'create_movie' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_movie_create();
    exit;
}

if ($action === 'update_movie' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_movie_update();
    exit;
}

if ($action === 'set_movie_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_movie_set_active();
    exit;
}

if ($action === 'delete_movie' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_movie_delete();
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

if ($action === 'set_showtime_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_showtime_set_active();
    exit;
}

if ($action === 'delete_showtime' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_showtime_delete();
    exit;
}

if ($action === 'deactivate_showtime' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_showtime_deactivate();
    exit;
}

if ($action === 'create_concession_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_product_create();
    exit;
}

if ($action === 'update_concession_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_product_update();
    exit;
}

if ($action === 'set_concession_product_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_product_set_active();
    exit;
}

if ($action === 'delete_concession_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_product_delete();
    exit;
}

if ($action === 'concession_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_add();
    exit;
}

if ($action === 'concession_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_update();
    exit;
}

if ($action === 'concession_remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_remove();
    exit;
}

if ($action === 'concession_clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_concession_clear();
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
