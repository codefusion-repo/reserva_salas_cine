<?php
declare(strict_types=1);

require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/../models/ConcessionProduct.php';
require_once __DIR__ . '/../models/Reservation.php';
require_once __DIR__ . '/../models/UserMembership.php';

const CHECKOUT_ALLOWED_TYPES = ['reservation', 'concessions', 'membership'];
const CHECKOUT_MEMBERSHIP_PLAN_LABEL = 'Socio Cine';
const CHECKOUT_MEMBERSHIP_DEMO_TOTAL = 5990.0;

function checkout_type_from_request(mixed $value): ?string
{
    if (!is_scalar($value)) {
        return null;
    }

    $type = strtolower(trim((string) $value));

    return in_array($type, CHECKOUT_ALLOWED_TYPES, true) ? $type : null;
}

function checkout_url(string $type, array $params = []): string
{
    $query = array_merge(['page' => 'checkout', 'type' => $type], $params);

    return 'index.php?' . http_build_query($query);
}

function checkout_demo_money_label(float $amount): string
{
    return reservation_format_money($amount);
}

function checkout_coupon_percent_label(float $percent): string
{
    if (fmod($percent, 1.0) === 0.0) {
        return (string) (int) $percent . '%';
    }

    return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',') . '%';
}

function checkout_coupon_discount_label(array $pricing): string
{
    $discountType = (string) ($pricing['discount_type'] ?? 'percent');
    $discountValue = (float) ($pricing['discount_value'] ?? 0.0);

    if ($discountType === 'fixed') {
        return 'Fijo ' . checkout_demo_money_label($discountValue);
    }

    return checkout_coupon_percent_label($discountValue);
}

function checkout_pricing_labels(string $type, float $subtotalAmount): array
{
    $pricing = checkout_coupon_price_summary($type, $subtotalAmount);
    $pricing['subtotal_label'] = checkout_demo_money_label((float) ($pricing['subtotal_amount'] ?? 0));
    $pricing['discount_label'] = checkout_demo_money_label((float) ($pricing['discount_amount'] ?? 0));
    $pricing['total_label'] = checkout_demo_money_label((float) ($pricing['total_amount'] ?? 0));
    $pricing['percent_label'] = checkout_coupon_discount_label($pricing);

    return $pricing;
}

function checkout_coupon_redirect_url(string $type, ?int $reservationId = null): string
{
    if ($type === 'reservation') {
        if ($reservationId === null) {
            return 'index.php?page=my_reservations';
        }

        return checkout_url('reservation', ['reservation_id' => $reservationId]);
    }

    return checkout_url($type);
}

function checkout_coupon_apply_context(string $type, int $userId, ?int $reservationId): array
{
    if ($type === 'reservation') {
        if ($reservationId === null) {
            return [
                'ok' => false,
                'message' => 'Selecciona una reserva pendiente valida para aplicar cupon.',
                'redirect_url' => 'index.php?page=my_reservations',
            ];
        }

        try {
            $reservation = reservation_find_for_user($reservationId, $userId);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            return [
                'ok' => false,
                'message' => 'No se pudo validar la reserva en este momento.',
                'redirect_url' => checkout_coupon_redirect_url('reservation', $reservationId),
            ];
        }

        if ($reservation === null) {
            return [
                'ok' => false,
                'message' => 'La reserva no existe o no pertenece a tu cuenta.',
                'redirect_url' => 'index.php?page=my_reservations',
            ];
        }

        if ((string) ($reservation['status'] ?? '') !== 'pending') {
            return [
                'ok' => false,
                'message' => 'Solo las reservas pendientes pueden usar cupon en este pago.',
                'redirect_url' => checkout_coupon_redirect_url('reservation', $reservationId),
            ];
        }

        return [
            'ok' => true,
            'subtotal_amount' => (float) ($reservation['total_amount'] ?? 0),
            'redirect_url' => checkout_coupon_redirect_url('reservation', $reservationId),
        ];
    }

    if ($type === 'concessions') {
        try {
            if (!concession_products_table_exists()) {
                return [
                    'ok' => false,
                    'message' => 'La confiteria no esta disponible en este momento.',
                    'redirect_url' => checkout_coupon_redirect_url('concessions'),
                ];
            }

            $cartSummary = concession_cart_summary_from_products(concession_products_active_all());
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            return [
                'ok' => false,
                'message' => 'No se pudo validar el carrito en este momento.',
                'redirect_url' => checkout_coupon_redirect_url('concessions'),
            ];
        }

        if (($cartSummary['items'] ?? []) === []) {
            return [
                'ok' => false,
                'message' => 'Agrega productos al carrito antes de aplicar cupon.',
                'redirect_url' => checkout_coupon_redirect_url('concessions'),
            ];
        }

        return [
            'ok' => true,
            'subtotal_amount' => (float) ($cartSummary['total'] ?? 0),
            'redirect_url' => checkout_coupon_redirect_url('concessions'),
        ];
    }

    if ($type === 'membership') {
        if (member_demo_active_for_user_id($userId)) {
            return [
                'ok' => false,
                'message' => 'Tu membresia ya esta activa; no se puede aplicar cupon.',
                'redirect_url' => checkout_coupon_redirect_url('membership'),
            ];
        }

        return [
            'ok' => true,
            'subtotal_amount' => CHECKOUT_MEMBERSHIP_DEMO_TOTAL,
            'redirect_url' => checkout_coupon_redirect_url('membership'),
        ];
    }

    return [
        'ok' => false,
        'message' => 'La opcion de pago no es valida.',
        'redirect_url' => 'index.php?page=dashboard',
    ];
}
