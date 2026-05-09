<?php
declare(strict_types=1);

require_once __DIR__ . '/ErrorController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/payment_view.php';
require_once __DIR__ . '/../helpers/reservation_view.php';
require_once __DIR__ . '/../models/Payment.php';

function render_my_payments(): void
{
    auth_require_login();

    $user = current_user();
    $messages = flash_get();
    $payments = [];
    $paymentLoadError = false;

    try {
        $payments = payment_user_all((int) ($user['id'] ?? 0));
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        $paymentLoadError = true;
    }

    require __DIR__ . '/../views/my_payments.php';
}


function render_payment_detail(): void
{
    auth_require_login();

    $user = current_user();
    $paymentId = positive_int_from_request($_GET['payment_id'] ?? null);

    if ($paymentId === null) {
        render_not_found_page(
            'Pago no encontrado',
            'El pago solicitado no existe o no pertenece a tu cuenta.'
        );
        return;
    }

    try {
        $payment = payment_find_for_user($paymentId, (int) ($user['id'] ?? 0));

        if ($payment === null) {
            render_not_found_page(
                'Pago no encontrado',
                'El pago solicitado no existe o no pertenece a tu cuenta.'
            );
            return;
        }

        $paymentItems = payment_items_for_payment($paymentId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo cargar el pago',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    $messages = flash_get();

    require __DIR__ . '/../views/payment_detail.php';
}

function render_invoice(): void
{
    auth_require_login();

    $user = current_user();
    $paymentId = positive_int_from_request($_GET['payment_id'] ?? null);

    if ($paymentId === null) {
        render_not_found_page(
            'Comprobante no encontrado',
            'El pago solicitado no existe o no pertenece a tu cuenta.'
        );
        return;
    }

    try {
        $payment = payment_find_for_user($paymentId, (int) ($user['id'] ?? 0));

        if ($payment === null) {
            render_not_found_page(
                'Comprobante no encontrado',
                'El pago solicitado no existe o no pertenece a tu cuenta.'
            );
            return;
        }

        $paymentItems = payment_items_for_payment($paymentId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo cargar el comprobante',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    $messages = flash_get();

    require __DIR__ . '/../views/invoice.php';
}

function handle_invoice_download(): void
{
    auth_require_login();

    $user = current_user();
    $paymentId = positive_int_from_request($_GET['payment_id'] ?? null);

    if ($paymentId === null) {
        render_not_found_page(
            'Comprobante no encontrado',
            'El pago solicitado no existe o no pertenece a tu cuenta.'
        );
        return;
    }

    try {
        $payment = payment_find_for_user($paymentId, (int) ($user['id'] ?? 0));

        if ($payment === null) {
            render_not_found_page(
                'Comprobante no encontrado',
                'El pago solicitado no existe o no pertenece a tu cuenta.'
            );
            return;
        }

        $paymentItems = payment_items_for_payment($paymentId);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        render_error_page(
            'No se pudo descargar el comprobante',
            'Intenta nuevamente mas tarde.',
            500
        );
        return;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . payment_invoice_filename($payment) . '"');
    header('X-Content-Type-Options: nosniff');

    echo payment_invoice_text($payment, $paymentItems, $user ?? []);
}
