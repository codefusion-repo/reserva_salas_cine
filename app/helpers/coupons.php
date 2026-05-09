<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

const CHECKOUT_COUPONS_SESSION_KEY = 'checkout_coupons';
const CHECKOUT_COUPON_TYPES = ['reservation', 'concessions', 'membership'];

function checkout_coupon_definitions(): array
{
    return [
        'CINE10' => [
            'code' => 'CINE10',
            'label' => 'Cine 10',
            'percent' => 10.0,
            'checkout_types' => ['reservation'],
        ],
        'COMBO15' => [
            'code' => 'COMBO15',
            'label' => 'Combo 15',
            'percent' => 15.0,
            'checkout_types' => ['concessions'],
        ],
        'SOCIO20' => [
            'code' => 'SOCIO20',
            'label' => 'Socio 20',
            'percent' => 20.0,
            'checkout_types' => ['membership'],
        ],
        'FUSION5' => [
            'code' => 'FUSION5',
            'label' => 'Fusion 5',
            'percent' => 5.0,
            'checkout_types' => CHECKOUT_COUPON_TYPES,
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

    $coupons = checkout_coupon_definitions();

    return is_array($coupons[$normalizedCode] ?? null) ? $coupons[$normalizedCode] : null;
}

function checkout_coupon_applies_to_type(array $coupon, string $checkoutType): bool
{
    $checkoutTypes = $coupon['checkout_types'] ?? [];

    return is_array($checkoutTypes) && in_array($checkoutType, $checkoutTypes, true);
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

    if ($code === '' || $coupon === null || !checkout_coupon_applies_to_type($coupon, $checkoutType)) {
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

    if ($coupon === null || !checkout_coupon_applies_to_type($coupon, $checkoutType)) {
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

    if ($coupon === null || !checkout_coupon_applies_to_type($coupon, $checkoutType)) {
        return checkout_coupon_empty_price_summary($subtotalAmount);
    }

    $discountAmount = checkout_coupon_discount_amount($subtotalAmount, (float) ($coupon['percent'] ?? 0.0));
    $totalAmount = checkout_coupon_normalize_amount($subtotalAmount - $discountAmount);

    return [
        'applied' => true,
        'code' => (string) ($coupon['code'] ?? ''),
        'label' => (string) ($coupon['label'] ?? ''),
        'percent' => (float) ($coupon['percent'] ?? 0.0),
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

function checkout_coupon_normalize_amount(float $amount): float
{
    if ($amount <= 0.0) {
        return 0.0;
    }

    return round($amount, 2);
}
