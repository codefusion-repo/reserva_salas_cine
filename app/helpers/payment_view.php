<?php
declare(strict_types=1);

require_once __DIR__ . '/checkout_view.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Reservation.php';

function payment_checkout_type_label(string $type): string
{
    return match ($type) {
        'reservation' => 'Reserva',
        'concessions' => 'Confiteria',
        'membership' => 'Membresia',
        default => 'Pago',
    };
}

function payment_status_label(string $status): string
{
    return match ($status) {
        PAYMENT_STATUS_SIMULATED_PAID => 'Pagado simulado',
        default => 'Sin estado',
    };
}

function payment_status_css_class(string $status): string
{
    return $status === PAYMENT_STATUS_SIMULATED_PAID ? 'confirmed' : 'unknown';
}

function payment_method_label(string $method): string
{
    return match ($method) {
        PAYMENT_METHOD_SIMULATED => 'Simulado',
        default => 'No informado',
    };
}

function payment_paid_at_label(array $payment): string
{
    $paidAtLabel = reservation_datetime_label($payment['paid_at'] ?? '');

    if ($paidAtLabel !== '') {
        return $paidAtLabel;
    }

    return reservation_datetime_label($payment['created_at'] ?? '');
}

function payment_summary_label(array $payment): string
{
    $checkoutType = (string) ($payment['checkout_type'] ?? '');
    $movieTitle = trim((string) ($payment['movie_title'] ?? ''));

    if ($checkoutType === 'reservation' && $movieTitle !== '') {
        return $movieTitle;
    }

    if ($checkoutType === 'membership') {
        return CHECKOUT_MEMBERSHIP_PLAN_LABEL;
    }

    return payment_checkout_type_label($checkoutType);
}

function payment_reservation_code(array $payment): string
{
    if ((string) ($payment['checkout_type'] ?? '') !== 'reservation' || ($payment['reservation_status'] ?? null) === null) {
        return '';
    }

    $reservationId = (int) ($payment['reservation_id'] ?? 0);

    return $reservationId > 0 ? reservation_visual_code($reservationId) : '';
}


function payment_invoice_filename(array $payment): string
{
    $referenceCode = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($payment['reference_code'] ?? ''));
    $referenceCode = trim((string) $referenceCode, '-_');

    if ($referenceCode === '') {
        $referenceCode = 'pago-' . (int) ($payment['id'] ?? 0);
    }

    return 'comprobante-demo-' . $referenceCode . '.txt';
}

function payment_plain_text_value(mixed $value): string
{
    $normalized = preg_replace('/[ \t\r\n]+/', ' ', trim((string) $value));

    return $normalized === null ? '' : $normalized;
}

function payment_invoice_text(array $payment, array $items, array $user): string
{
    $accountLabel = payment_plain_text_value($user['name'] ?? '');

    if ($accountLabel === '') {
        $accountLabel = 'Usuario';
    }

    $accountEmail = payment_plain_text_value($user['email'] ?? '');

    if ($accountEmail === '') {
        $accountEmail = 'No informado';
    }

    $reservationCode = payment_reservation_code($payment);
    $movieTitle = payment_plain_text_value($payment['movie_title'] ?? '');
    $roomLabel = trim(
        payment_plain_text_value($payment['room_name'] ?? '') . ' - ' . payment_plain_text_value($payment['room_location'] ?? ''),
        ' -'
    );
    $formatLabel = trim(
        payment_plain_text_value($payment['format_label'] ?? '') . ' - ' . payment_plain_text_value($payment['language_label'] ?? ''),
        ' -'
    );
    $showtimeLabel = reservation_datetime_label($payment['starts_at'] ?? '');

    $lines = [
        'ES Cine - Comprobante simulado',
        '',
        'Comprobante simulado.',
        'No válido como factura/boleta legal.',
        'No hubo cobro real.',
        '',
        'Referencia: ' . payment_plain_text_value($payment['reference_code'] ?? ''),
        'Tipo: ' . payment_checkout_type_label((string) ($payment['checkout_type'] ?? '')),
        'Estado: ' . payment_status_label((string) ($payment['status'] ?? '')),
        'Metodo: ' . payment_method_label((string) ($payment['payment_method'] ?? '')),
        'Fecha: ' . payment_paid_at_label($payment),
        'Usuario: ' . $accountLabel,
        'Email: ' . $accountEmail,
    ];

    if ($reservationCode !== '') {
        $lines[] = 'Reserva: ' . $reservationCode;
    }

    if ($movieTitle !== '') {
        $lines[] = 'Pelicula: ' . $movieTitle;
    }

    if ($showtimeLabel !== '') {
        $lines[] = 'Funcion: ' . $showtimeLabel;
    }

    if ($roomLabel !== '') {
        $lines[] = 'Sala: ' . $roomLabel;
    }

    if ($formatLabel !== '') {
        $lines[] = 'Formato: ' . $formatLabel;
    }

    $lines[] = '';
    $lines[] = 'Items:';

    foreach ($items as $item) {
        $quantity = max(0, (int) ($item['quantity'] ?? 0));
        $label = payment_plain_text_value($item['item_label'] ?? 'Item');
        $unitAmount = reservation_format_money((float) ($item['unit_amount'] ?? 0));
        $totalAmount = reservation_format_money((float) ($item['total_amount'] ?? 0));

        $lines[] = '- ' . $quantity . ' x ' . $label . ' | Unitario ' . $unitAmount . ' | Total ' . $totalAmount;
    }

    $lines[] = '';
    $lines[] = 'Subtotal: ' . reservation_format_money((float) ($payment['subtotal_amount'] ?? 0));
    $lines[] = 'Descuento: ' . reservation_format_money((float) ($payment['discount_amount'] ?? 0));
    $lines[] = 'Total demo: ' . reservation_format_money((float) ($payment['total_amount'] ?? 0));

    return implode(PHP_EOL, $lines) . PHP_EOL;
}
