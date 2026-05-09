<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/checkout_view.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Reservation.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserMembership.php';

function profile_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrador',
        'user' => 'Usuario',
        default => 'Sin rol',
    };
}

function member_demo_state_for_user_id(int $userId): array
{
    $state = [
        'membership' => null,
        'is_active' => false,
        'load_error' => false,
        'source' => 'db',
    ];

    if ($userId <= 0) {
        return $state;
    }

    try {
        $membership = user_membership_find_current($userId, USER_MEMBERSHIP_PLAN_DEMO);
        $state['membership'] = $membership;
        $state['is_active'] = user_membership_row_is_active($membership);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $state['load_error'] = true;
        $state['source'] = 'session';
        $state['is_active'] = is_member_demo_active();
    }

    return $state;
}

function member_demo_state_for_user(?array $user): array
{
    return member_demo_state_for_user_id((int) ($user['id'] ?? 0));
}

function member_demo_active_for_user_id(int $userId): bool
{
    $state = member_demo_state_for_user_id($userId);

    return (bool) ($state['is_active'] ?? false);
}

function member_demo_status_label(array $state): string
{
    if (($state['is_active'] ?? false) === true) {
        return 'Activa';
    }

    $membership = is_array($state['membership'] ?? null) ? $state['membership'] : null;
    $status = (string) ($membership['status'] ?? '');

    return match ($status) {
        USER_MEMBERSHIP_STATUS_CANCELLED => 'Cancelada',
        USER_MEMBERSHIP_STATUS_EXPIRED => 'Expirada',
        USER_MEMBERSHIP_STATUS_ACTIVE => 'Expirada',
        default => 'Inactiva',
    };
}

function member_demo_status_copy(array $state): string
{
    if (($state['load_error'] ?? false) === true) {
        return 'No se pudo actualizar el estado en este momento.';
    }

    if (($state['is_active'] ?? false) === true) {
        return 'Tu membresia esta activa.';
    }

    $membership = is_array($state['membership'] ?? null) ? $state['membership'] : null;

    if ($membership !== null) {
        return 'Puedes reactivar la membresia desde Socios.';
    }

    return 'Sin membresia activa.';
}

function checkout_membership_confirm_with_payment(int $userId, array $pricing): array
{
    if ($userId <= 0) {
        return [
            'ok' => false,
            'message' => 'La membresia requiere un usuario valido.',
        ];
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $currentMembership = user_membership_find_current_for_update($pdo, $userId, USER_MEMBERSHIP_PLAN_DEMO);

        if (user_membership_row_is_active($currentMembership)) {
            $pdo->commit();

            return [
                'ok' => true,
                'already_active' => true,
                'membership' => $currentMembership,
            ];
        }

        $payment = payment_insert_simulated_paid(
            $pdo,
            $userId,
            'membership',
            null,
            [
                [
                    'item_type' => 'membership',
                    'item_label' => CHECKOUT_MEMBERSHIP_PLAN_LABEL,
                    'quantity' => 1,
                    'unit_amount' => CHECKOUT_MEMBERSHIP_DEMO_TOTAL,
                    'total_amount' => CHECKOUT_MEMBERSHIP_DEMO_TOTAL,
                ],
            ],
            CHECKOUT_MEMBERSHIP_DEMO_TOTAL,
            (float) ($pricing['discount_amount'] ?? 0),
            (float) ($pricing['total_amount'] ?? CHECKOUT_MEMBERSHIP_DEMO_TOTAL)
        );

        $activation = user_membership_activate(
            $pdo,
            $userId,
            (int) ($payment['id'] ?? 0),
            USER_MEMBERSHIP_PLAN_DEMO
        );

        $pdo->commit();

        return [
            'ok' => true,
            'already_active' => ($activation['already_active'] ?? false) === true,
            'payment' => $payment,
            'membership' => $activation['membership'] ?? null,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($exception->getMessage());

        return [
            'ok' => false,
            'message' => 'No se pudo activar la membresia en este momento.',
        ];
    }
}


function render_profile(): void
{
    auth_require_login();

    $user = current_user();
    $profileUser = $user ?? [];
    $userId = (int) ($profileUser['id'] ?? 0);
    $messages = flash_get();
    $reservationSummary = null;
    $latestTicket = null;
    $paymentCount = null;
    $profileUserLoadError = false;
    $reservationSummaryLoadError = false;
    $latestTicketLoadError = false;
    $paymentSummaryLoadError = false;

    try {
        $freshUser = user_find_by_id($userId);

        if ($freshUser !== null) {
            $profileUser = $freshUser;
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $profileUserLoadError = true;
    }

    try {
        $reservationSummary = reservation_user_profile_summary($userId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $reservationSummaryLoadError = true;
    }

    try {
        $latestTicket = reservation_latest_ticket_for_user($userId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $latestTicketLoadError = true;
    }

    try {
        $paymentCount = payment_simulated_count_for_user($userId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $paymentSummaryLoadError = true;
    }

    $memberDemoState = member_demo_state_for_user($profileUser);
    $memberDemoActive = (bool) ($memberDemoState['is_active'] ?? false);
    $memberDemoStatusLabel = member_demo_status_label($memberDemoState);
    $profileRoleLabel = profile_role_label((string) ($profileUser['role'] ?? ''));

    require __DIR__ . '/../views/profile.php';
}


function handle_member_demo_activate(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $user = current_user();
    $userId = (int) ($user['id'] ?? 0);
    $memberDemoState = member_demo_state_for_user_id($userId);

    if (($memberDemoState['is_active'] ?? false) === true) {
        set_member_demo_active(true);
        flash_set('info', 'Tu membresía ya está activa.');
        redirect_to('index.php?page=socios');
    }

    set_member_demo_active(false);
    flash_set('info', 'Continúa para activar tu membresía.');
    redirect_to(checkout_url('membership'));
}

function handle_member_demo_deactivate(): void
{
    auth_require_login();
    csrf_require_valid_post();

    $user = current_user();
    $userId = (int) ($user['id'] ?? 0);
    $memberDemoState = member_demo_state_for_user_id($userId);

    if (($memberDemoState['is_active'] ?? false) === false) {
        set_member_demo_active(false);
        flash_set('info', 'Tu membresía ya está desactivada.');
        redirect_to('index.php?page=socios');
    }

    if (($memberDemoState['load_error'] ?? false) === true) {
        set_member_demo_active(false);
        flash_set('success', 'Membresía desactivada correctamente.');
        redirect_to('index.php?page=socios');
    }

    try {
        user_membership_cancel($userId, USER_MEMBERSHIP_PLAN_DEMO);
        set_member_demo_active(false);
        flash_set('success', 'Membresía desactivada correctamente.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        flash_set('error', 'No se pudo desactivar la membresía en este momento.');
    }

    redirect_to('index.php?page=socios');
}
