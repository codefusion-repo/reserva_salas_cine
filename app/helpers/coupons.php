<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../models/Coupon.php';

const CHECKOUT_COUPONS_SESSION_KEY = 'checkout_coupons';
const CHECKOUT_COUPON_TYPES = ['reservation', 'concessions', 'membership'];

function checkout_coupon_definitions(): array
{
    return [
        'CINE10' => [
            'code' => 'CINE10',
            'label' => 'Cine 10',
            'percent' => 10.0,
            'checkout_type' => 'reservation',
            'checkout_types' => ['reservation'],
            'discount_type' => 'percent',
            'discount_value' => 10.0,
            'is_active' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ],
        'COMBO15' => [
            'code' => 'COMBO15',
            'label' => 'Combo 15',
            'percent' => 15.0,
            'checkout_type' => 'concessions',
            'checkout_types' => ['concessions'],
            'discount_type' => 'percent',
            'discount_value' => 15.0,
            'is_active' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ],
        'SOCIO20' => [
            'code' => 'SOCIO20',
            'label' => 'Socio 20',
            'percent' => 20.0,
            'checkout_type' => 'membership',
            'checkout_types' => ['membership'],
            'discount_type' => 'percent',
            'discount_value' => 20.0,
            'is_active' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ],
        'FUSION5' => [
            'code' => 'FUSION5',
            'label' => 'Fusion 5',
            'percent' => 5.0,
            'checkout_type' => 'all',
            'checkout_types' => CHECKOUT_COUPON_TYPES,
            'discount_type' => 'percent',
            'discount_value' => 5.0,
            'is_active' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ],
    ];
}

function checkout_coupon_code_from_value(mixed $value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $code = preg_replace('/\s+/', '', trim((string) $value));
    $code = $code === null ? '' : $code;

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($code, 'UTF-8');
    }

    return strtoupper($code);
}

function checkout_coupon_find(string $code): ?array
{
    $normalizedCode = checkout_coupon_code_from_value($code);

    if ($normalizedCode === '') {
        return null;
    }

    try {
        if (coupons_table_exists()) {
            $coupon = coupon_find_by_code($normalizedCode);

            return is_array($coupon) ? checkout_coupon_from_database_row($coupon) : null;
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        return null;
    }

    $coupons = checkout_coupon_definitions();

    return is_array($coupons[$normalizedCode] ?? null) ? $coupons[$normalizedCode] : null;
}

function checkout_coupon_from_database_row(array $coupon): array
{
    $checkoutType = (string) ($coupon['checkout_type'] ?? '');
    $checkoutTypes = $checkoutType === 'all' ? CHECKOUT_COUPON_TYPES : [$checkoutType];
    $discountType = (string) ($coupon['discount_type'] ?? 'percent');
    $discountValue = (float) ($coupon['discount_value'] ?? 0);
    $description = trim((string) ($coupon['description'] ?? ''));

    return [
        'id' => (int) ($coupon['id'] ?? 0),
        'code' => (string) ($coupon['code'] ?? ''),
        'label' => $description !== '' ? $description : (string) ($coupon['code'] ?? ''),
        'checkout_type' => $checkoutType,
        'checkout_types' => $checkoutTypes,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'percent' => $discountType === 'percent' ? $discountValue : 0.0,
        'is_active' => (int) ($coupon['is_active'] ?? 0),
        'starts_at' => $coupon['starts_at'] ?? null,
        'ends_at' => $coupon['ends_at'] ?? null,
    ];
}

function checkout_coupon_applies_to_type(array $coupon, string $checkoutType): bool
{
    $checkoutTypes = $coupon['checkout_types'] ?? [];

    return is_array($checkoutTypes) && in_array($checkoutType, $checkoutTypes, true);
}

function checkout_coupon_is_active_now(array $coupon): bool
{
    if ((int) ($coupon['is_active'] ?? 0) !== 1) {
        return false;
    }

    $now = new DateTimeImmutable('now');
    $startsAt = trim((string) ($coupon['starts_at'] ?? ''));
    $endsAt = trim((string) ($coupon['ends_at'] ?? ''));

    if ($startsAt !== '') {
        try {
            if (new DateTimeImmutable($startsAt) > $now) {
                return false;
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            return false;
        }
    }

    if ($endsAt !== '') {
        try {
            if (new DateTimeImmutable($endsAt) < $now) {
                return false;
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            return false;
        }
    }

    return true;
}

function checkout_coupon_can_apply(array $coupon, string $checkoutType): bool
{
    return checkout_coupon_is_active_now($coupon) && checkout_coupon_applies_to_type($coupon, $checkoutType);
}

function checkout_coupon_session_code(string $checkoutType): ?string
{
    if (!in_array($checkoutType, CHECKOUT_COUPON_TYPES, true)) {
        return null;
    }

    app_session_start();

    $storedCoupons = $_SESSION[CHECKOUT_COUPONS_SESSION_KEY] ?? [];

    if (!is_array($storedCoupons)) {
        unset($_SESSION[CHECKOUT_COUPONS_SESSION_KEY]);
        return null;
    }

    $code = checkout_coupon_code_from_value($storedCoupons[$checkoutType] ?? null);

    $coupon = checkout_coupon_find($code);

    if ($code === '' || $coupon === null || !checkout_coupon_can_apply($coupon, $checkoutType)) {
        unset($storedCoupons[$checkoutType]);
        $_SESSION[CHECKOUT_COUPONS_SESSION_KEY] = $storedCoupons;
        return null;
    }

    return $code;
}

function checkout_coupon_session_set(string $checkoutType, string $code): void
{
    if (!in_array($checkoutType, CHECKOUT_COUPON_TYPES, true)) {
        return;
    }

    $coupon = checkout_coupon_find($code);

    if ($coupon === null || !checkout_coupon_can_apply($coupon, $checkoutType)) {
        return;
    }

    app_session_start();

    $storedCoupons = $_SESSION[CHECKOUT_COUPONS_SESSION_KEY] ?? [];

    if (!is_array($storedCoupons)) {
        $storedCoupons = [];
    }

    $storedCoupons[$checkoutType] = (string) $coupon['code'];
    $_SESSION[CHECKOUT_COUPONS_SESSION_KEY] = $storedCoupons;
}

function checkout_coupon_session_remove(string $checkoutType): void
{
    app_session_start();

    $storedCoupons = $_SESSION[CHECKOUT_COUPONS_SESSION_KEY] ?? [];

    if (!is_array($storedCoupons)) {
        unset($_SESSION[CHECKOUT_COUPONS_SESSION_KEY]);
        return;
    }

    unset($storedCoupons[$checkoutType]);

    if ($storedCoupons === []) {
        unset($_SESSION[CHECKOUT_COUPONS_SESSION_KEY]);
        return;
    }

    $_SESSION[CHECKOUT_COUPONS_SESSION_KEY] = $storedCoupons;
}

function checkout_coupon_price_summary(string $checkoutType, float $subtotalAmount): array
{
    return checkout_coupon_price_summary_for_code(
        $checkoutType,
        checkout_coupon_session_code($checkoutType),
        $subtotalAmount
    );
}

function checkout_coupon_price_summary_for_code(string $checkoutType, ?string $code, float $subtotalAmount): array
{
    $subtotalAmount = checkout_coupon_normalize_amount($subtotalAmount);
    $coupon = $code !== null ? checkout_coupon_find($code) : null;

    if ($coupon === null || !checkout_coupon_can_apply($coupon, $checkoutType)) {
        return checkout_coupon_empty_price_summary($subtotalAmount);
    }

    $discountAmount = checkout_coupon_discount_amount_for_coupon($subtotalAmount, $coupon);
    $totalAmount = checkout_coupon_normalize_amount($subtotalAmount - $discountAmount);

    return [
        'applied' => true,
        'code' => (string) ($coupon['code'] ?? ''),
        'label' => (string) ($coupon['label'] ?? ''),
        'percent' => (float) ($coupon['percent'] ?? 0.0),
        'discount_type' => (string) ($coupon['discount_type'] ?? 'percent'),
        'discount_value' => (float) ($coupon['discount_value'] ?? 0.0),
        'subtotal_amount' => $subtotalAmount,
        'discount_amount' => $discountAmount,
        'total_amount' => $totalAmount,
    ];
}

function checkout_coupon_empty_price_summary(float $subtotalAmount): array
{
    $subtotalAmount = checkout_coupon_normalize_amount($subtotalAmount);

    return [
        'applied' => false,
        'code' => '',
        'label' => '',
        'percent' => 0.0,
        'discount_type' => 'percent',
        'discount_value' => 0.0,
        'subtotal_amount' => $subtotalAmount,
        'discount_amount' => 0.0,
        'total_amount' => $subtotalAmount,
    ];
}

function checkout_coupon_discount_amount(float $subtotalAmount, float $percent): float
{
    $subtotalAmount = checkout_coupon_normalize_amount($subtotalAmount);

    if ($subtotalAmount <= 0.0 || $percent <= 0.0) {
        return 0.0;
    }

    $discountAmount = round($subtotalAmount * ($percent / 100), 0);

    return max(0.0, min($subtotalAmount, $discountAmount));
}

function checkout_coupon_discount_amount_for_coupon(float $subtotalAmount, array $coupon): float
{
    $subtotalAmount = checkout_coupon_normalize_amount($subtotalAmount);

    if ($subtotalAmount <= 0.0) {
        return 0.0;
    }

    $discountType = (string) ($coupon['discount_type'] ?? 'percent');
    $discountValue = (float) ($coupon['discount_value'] ?? 0.0);

    if ($discountValue <= 0.0) {
        return 0.0;
    }

    if ($discountType === 'fixed') {
        return max(0.0, min($subtotalAmount, checkout_coupon_normalize_amount($discountValue)));
    }

    return checkout_coupon_discount_amount($subtotalAmount, $discountValue);
}

function checkout_coupon_normalize_amount(float $amount): float
{
    if ($amount <= 0.0) {
        return 0.0;
    }

    return round($amount, 2);
}
