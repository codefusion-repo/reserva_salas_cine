<?php
declare(strict_types=1);

require_once __DIR__ . '/ConcessionController.php';
require_once __DIR__ . '/ErrorController.php';
require_once __DIR__ . '/MembershipController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/checkout_view.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/reservation_view.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Reservation.php';

function render_checkout_page(): void
{
    auth_require_login();

    $user = current_user();
    $type = checkout_type_from_request($_GET['type'] ?? null);

    if ($type === null) {
        render_not_found_page(
            'Pago no encontrado',
            'La opcion solicitada no existe o no esta disponible.',
            flash_get()
        );
        return;
    }

    $checkout = [
        'type' => $type,
        'active_nav' => 'cartelera',
        'title' => 'Confirmar pago',
        'eyebrow' => 'Pago de prueba',
        'heading' => 'Confirma tu compra',
        'lead' => 'Revisa el resumen antes de continuar. No habra cobro real.',
        'summary_title' => 'Resumen',
        'subtotal_amount' => 0.0,
        'total_label' => checkout_demo_money_label(0),
        'can_confirm' => false,
        'return_url' => 'index.php?page=cartelera',
        'confirm_fields' => ['type' => $type],
    ];

    if ($type === 'reservation') {
        $reservationId = positive_int_from_request($_GET['reservation_id'] ?? null);

        if ($reservationId === null) {
            render_not_found_page(
                'Reserva no encontrada',
                'Selecciona una reserva pendiente valida para abrir el pago.',
                flash_get()
            );
            return;
        }

        try {
            $reservation = reservation_find_for_user($reservationId, (int) ($user['id'] ?? 0));
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            render_error_page(
                'No se pudo cargar el pago',
                'Intenta nuevamente mas tarde.',
                500,
                flash_get()
            );
            return;
        }

        if ($reservation === null) {
            render_not_found_page(
                'Reserva no encontrada',
                'La reserva solicitada no existe o no pertenece a tu cuenta.',
                flash_get()
            );
            return;
        }

        if ((string) ($reservation['status'] ?? '') !== 'pending') {
            render_error_page(
                'Pago no disponible',
                'Solo las reservas pendientes pueden confirmarse.',
                409,
                flash_get()
            );
            return;
        }

        $checkout = array_replace($checkout, [
            'active_nav' => 'my_reservations',
            'title' => 'Confirmar reserva',
            'heading' => 'Confirma tu reserva',
            'lead' => 'Revisa tus entradas antes de confirmar. No habra cobro real.',
            'summary_title' => 'Reserva pendiente',
            'subtotal_amount' => (float) ($reservation['total_amount'] ?? 0),
            'total_label' => checkout_demo_money_label((float) ($reservation['total_amount'] ?? 0)),
            'can_confirm' => true,
            'return_url' => 'index.php?page=my_reservations',
            'confirm_fields' => [
                'type' => 'reservation',
                'reservation_id' => (string) $reservationId,
            ],
            'reservation' => $reservation,
            'showtime_labels' => reservation_showtime_labels($reservation),
        ]);
    } elseif ($type === 'concessions') {
        $cartSummary = [
            'items' => [],
            'total' => 0.0,
            'total_label' => reservation_format_money(0),
            'is_empty' => true,
            'pruned' => false,
        ];
        $cartLoadError = false;
        $catalogSetupRequired = false;

        try {
            if (!concession_products_table_exists()) {
                $catalogSetupRequired = true;
            } else {
                $cartSummary = concession_cart_summary_from_products(concession_products_active_all());

                if (($cartSummary['pruned'] ?? false) === true) {
                    flash_set('info', 'Se quitaron del carrito productos que ya no estan activos.');
                }
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $cartLoadError = true;
        }

        $cartItems = is_array($cartSummary['items'] ?? null) ? $cartSummary['items'] : [];

        $checkout = array_replace($checkout, [
            'active_nav' => 'confiteria',
            'title' => 'Confirmar confiteria',
            'heading' => 'Confirma tu carrito',
            'lead' => 'Revisa tus combos antes de confirmar. No habra cobro real.',
            'summary_title' => 'Tu carrito',
            'subtotal_amount' => (float) ($cartSummary['total'] ?? 0),
            'total_label' => (string) ($cartSummary['total_label'] ?? reservation_format_money(0)),
            'can_confirm' => $cartItems !== [] && !$cartLoadError && !$catalogSetupRequired,
            'return_url' => 'index.php?page=confiteria',
            'cart_summary' => $cartSummary,
            'cart_load_error' => $cartLoadError,
            'catalog_setup_required' => $catalogSetupRequired,
            'last_receipt' => concession_checkout_last_receipt(),
        ]);
    } elseif ($type === 'membership') {
        $memberDemoState = member_demo_state_for_user($user);
        $memberDemoActive = (bool) ($memberDemoState['is_active'] ?? false);

        $checkout = array_replace($checkout, [
            'active_nav' => 'socios',
            'title' => 'Activar membresia',
            'heading' => 'Hazte socio',
            'lead' => $memberDemoActive
                ? 'Tu membresia ya esta activa.'
                : 'Activa tu membresia. No habra cobro real.',
            'summary_title' => CHECKOUT_MEMBERSHIP_PLAN_LABEL,
            'subtotal_amount' => CHECKOUT_MEMBERSHIP_DEMO_TOTAL,
            'total_label' => checkout_demo_money_label(CHECKOUT_MEMBERSHIP_DEMO_TOTAL),
            'can_confirm' => !$memberDemoActive,
            'return_url' => 'index.php?page=socios',
            'member_demo_active' => $memberDemoActive,
            'member_demo_status_label' => member_demo_status_label($memberDemoState),
            'membership_plan' => [
                'name' => CHECKOUT_MEMBERSHIP_PLAN_LABEL,
                'total_label' => reservation_format_money(CHECKOUT_MEMBERSHIP_DEMO_TOTAL),
                'benefits' => [
                    'Estado de socio visible en tu cuenta.',
                    'Beneficios de prueba sin descuentos reales.',
                    'Sin cobro real ni datos bancarios.',
                ],
            ],
        ]);
    }

    $pricing = checkout_pricing_labels($type, (float) ($checkout['subtotal_amount'] ?? 0));
    $checkout['pricing'] = $pricing;
    $checkout['total_label'] = (string) ($pricing['total_label'] ?? checkout_demo_money_label(0));
    $checkout['coupon_fields'] = $checkout['confirm_fields'];
    $messages = flash_get();

    require __DIR__ . '/../views/checkout.php';
}

function handle_coupon_apply(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $user = current_user();
    $type = checkout_type_from_request($_POST['type'] ?? null);
    $reservationId = positive_int_from_request($_POST['reservation_id'] ?? null);

    if ($type === null) {
        flash_set('error', 'La opcion de pago no es valida.');
        redirect_to('index.php?page=dashboard');
    }

    $redirectUrl = checkout_coupon_redirect_url($type, $reservationId);
    $code = checkout_coupon_code_from_value($_POST['coupon_code'] ?? null);

    if ($code === '') {
        flash_set('error', 'Ingresa un codigo de cupon.');
        redirect_to($redirectUrl);
    }

    $coupon = checkout_coupon_find($code);

    if ($coupon === null) {
        flash_set('error', 'Cupon invalido.');
        redirect_to($redirectUrl);
    }

    if (!checkout_coupon_is_active_now($coupon)) {
        flash_set('error', 'Cupon invalido o inactivo.');
        redirect_to($redirectUrl);
    }

    if (!checkout_coupon_applies_to_type($coupon, $type)) {
        flash_set('error', 'Este cupon no aplica para este pago.');
        redirect_to($redirectUrl);
    }

    $context = checkout_coupon_apply_context($type, (int) ($user['id'] ?? 0), $reservationId);
    $contextRedirectUrl = (string) ($context['redirect_url'] ?? $redirectUrl);

    if (($context['ok'] ?? false) !== true) {
        flash_set('error', (string) ($context['message'] ?? 'No se pudo aplicar el cupon.'));
        redirect_to($contextRedirectUrl);
    }

    checkout_coupon_session_set($type, (string) ($coupon['code'] ?? $code));

    $pricing = checkout_coupon_price_summary_for_code(
        $type,
        (string) ($coupon['code'] ?? $code),
        (float) ($context['subtotal_amount'] ?? 0)
    );

    flash_set(
        'success',
        'Cupon aplicado: ' . (string) ($coupon['code'] ?? $code) . ' (-' . checkout_demo_money_label((float) ($pricing['discount_amount'] ?? 0)) . ').'
    );
    redirect_to($contextRedirectUrl);
}

function handle_coupon_remove(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $type = checkout_type_from_request($_POST['type'] ?? null);
    $reservationId = positive_int_from_request($_POST['reservation_id'] ?? null);

    if ($type === null) {
        flash_set('error', 'La opcion de pago no es valida.');
        redirect_to('index.php?page=dashboard');
    }

    checkout_coupon_session_remove($type);
    flash_set('success', 'Cupon quitado.');
    redirect_to(checkout_coupon_redirect_url($type, $reservationId));
}

function handle_checkout_confirm(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $user = current_user();
    $type = checkout_type_from_request($_POST['type'] ?? null);

    if ($type === null) {
        render_not_found_page(
            'Pago no encontrado',
            'La opcion enviada no existe o no esta disponible.'
        );
        return;
    }

    if ($type === 'reservation') {
        $reservationId = positive_int_from_request($_POST['reservation_id'] ?? null);

        if ($reservationId === null) {
            flash_set('error', 'Selecciona una reserva pendiente valida.');
            redirect_to('index.php?page=my_reservations');
        }

        $result = reservation_confirm_pending_for_user(
            $reservationId,
            (int) ($user['id'] ?? 0),
            checkout_coupon_session_code('reservation')
        );
        $isOk = ($result['ok'] ?? false) === true;
        $resultMessage = (string) ($result['message'] ?? 'No se pudo confirmar la reserva.');

        if ($isOk) {
            $resultMessage = 'Reserva confirmada. No hubo cobro real.';
        } elseif (str_contains(strtolower($resultMessage), 'pago simulado')) {
            $resultMessage = 'La reserva ya tiene un pago registrado.';
        }

        flash_set(
            $isOk ? 'success' : 'error',
            $resultMessage
        );

        if ($isOk) {
            checkout_coupon_session_remove('reservation');
            redirect_to('index.php?page=ticket&reservation_id=' . (int) $reservationId);
        }

        redirect_to(checkout_url('reservation', ['reservation_id' => (int) $reservationId]));
    }

    if ($type === 'concessions') {
        $cartSummary = [
            'items' => [],
            'total' => 0.0,
            'total_label' => reservation_format_money(0),
            'is_empty' => true,
            'pruned' => false,
        ];

        try {
            if (concession_products_table_exists()) {
                $cartSummary = concession_cart_summary_from_products(concession_products_active_all());
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            flash_set('error', 'No se pudo validar el carrito en este momento.');
            redirect_to(checkout_url('concessions'));
        }

        if (($cartSummary['items'] ?? []) === []) {
            flash_set('error', 'Agrega productos al carrito antes de confirmar.');
            redirect_to(checkout_url('concessions'));
        }

        $paymentItems = [];

        foreach (($cartSummary['items'] ?? []) as $cartItem) {
            if (!is_array($cartItem)) {
                continue;
            }

            $paymentItems[] = [
                'item_type' => 'concession',
                'item_label' => (string) ($cartItem['name'] ?? 'Producto confiteria'),
                'quantity' => (int) ($cartItem['quantity'] ?? 0),
                'unit_amount' => (float) ($cartItem['unit_price'] ?? 0),
                'total_amount' => (float) ($cartItem['subtotal'] ?? 0),
            ];
        }

        $totalAmount = (float) ($cartSummary['total'] ?? 0);
        $pricing = checkout_pricing_labels('concessions', $totalAmount);
        $paymentResult = payment_create_simulated(
            (int) ($user['id'] ?? 0),
            'concessions',
            null,
            $paymentItems,
            $totalAmount,
            (float) ($pricing['discount_amount'] ?? 0),
            (float) ($pricing['total_amount'] ?? $totalAmount)
        );

        if (($paymentResult['ok'] ?? false) !== true) {
            flash_set('error', (string) ($paymentResult['message'] ?? 'No se pudo confirmar el pago.'));
            redirect_to(checkout_url('concessions'));
        }

        $payment = is_array($paymentResult['payment'] ?? null) ? $paymentResult['payment'] : [];
        concession_checkout_save_receipt($cartSummary, (string) ($payment['reference_code'] ?? ''), $pricing);
        concession_cart_save([]);
        checkout_coupon_session_remove('concessions');
        flash_set('success', 'Tu compra de confiteria esta lista.');
        redirect_to(checkout_url('concessions', ['result' => 'success']));
    }

    if ($type === 'membership') {
        $userId = (int) ($user['id'] ?? 0);

        if (member_demo_active_for_user_id($userId)) {
            set_member_demo_active(true);
            checkout_coupon_session_remove('membership');
            flash_set('info', 'Tu membresia ya esta activa.');
            redirect_to('index.php?page=socios');
        }

        $pricing = checkout_pricing_labels('membership', CHECKOUT_MEMBERSHIP_DEMO_TOTAL);
        $paymentResult = checkout_membership_confirm_with_payment($userId, $pricing);

        if (($paymentResult['ok'] ?? false) !== true) {
            flash_set('error', (string) ($paymentResult['message'] ?? 'No se pudo activar la membresia.'));
            redirect_to(checkout_url('membership'));
        }

        set_member_demo_active(true);
        checkout_coupon_session_remove('membership');

        if (($paymentResult['already_active'] ?? false) === true) {
            flash_set('info', 'Tu membresia ya esta activa.');
            redirect_to('index.php?page=socios');
        }

        flash_set('success', 'Membresia activada.');
        redirect_to('index.php?page=socios');
    }
}
