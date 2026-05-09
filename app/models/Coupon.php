<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

const COUPON_ALLOWED_CHECKOUT_TYPES = ['reservation', 'concessions', 'membership', 'all'];
const COUPON_ALLOWED_DISCOUNT_TYPES = ['percent', 'fixed'];

function coupons_table_exists(): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    try {
        $row = db_fetch_one(
            'SELECT COUNT(*) AS table_exists
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1',
            ['table_name' => 'coupons']
        );

        $exists = (int) ($row['table_exists'] ?? 0) > 0;
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $exists = false;
    }

    return $exists;
}

function coupons_all(): array
{
    if (!coupons_table_exists()) {
        return [];
    }

    return db_fetch_all(
        'SELECT id, code, description, checkout_type, discount_type, discount_value, is_active, starts_at, ends_at, created_at, updated_at
         FROM coupons
         ORDER BY is_active DESC, code ASC, id ASC'
    );
}

function coupon_find_by_id(int $couponId): ?array
{
    if (!coupons_table_exists()) {
        return null;
    }

    return db_fetch_one(
        'SELECT id, code, description, checkout_type, discount_type, discount_value, is_active, starts_at, ends_at, created_at, updated_at
         FROM coupons
         WHERE id = :id
         LIMIT 1',
        ['id' => $couponId]
    );
}

function coupon_find_by_code(string $code): ?array
{
    if (!coupons_table_exists()) {
        return null;
    }

    return db_fetch_one(
        'SELECT id, code, description, checkout_type, discount_type, discount_value, is_active, starts_at, ends_at, created_at, updated_at
         FROM coupons
         WHERE code = :code
         LIMIT 1',
        ['code' => $code]
    );
}

function coupon_code_exists(string $code, ?int $excludeCouponId = null): bool
{
    if (!coupons_table_exists()) {
        return false;
    }

    $params = ['code' => $code];
    $sql = 'SELECT id
            FROM coupons
            WHERE code = :code';

    if ($excludeCouponId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeCouponId;
    }

    $sql .= ' LIMIT 1';

    return db_fetch_one($sql, $params) !== null;
}

function coupon_create(
    string $code,
    string $description,
    string $checkoutType,
    string $discountType,
    float $discountValue,
    bool $isActive,
    ?string $startsAt,
    ?string $endsAt
): int {
    if (!coupons_table_exists()) {
        return 0;
    }

    db_execute(
        'INSERT INTO coupons (code, description, checkout_type, discount_type, discount_value, is_active, starts_at, ends_at)
         VALUES (:code, :description, :checkout_type, :discount_type, :discount_value, :is_active, :starts_at, :ends_at)',
        [
            'code' => $code,
            'description' => $description,
            'checkout_type' => $checkoutType,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'is_active' => $isActive ? 1 : 0,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]
    );

    return (int) db()->lastInsertId();
}

function coupon_update(
    int $couponId,
    string $code,
    string $description,
    string $checkoutType,
    string $discountType,
    float $discountValue,
    bool $isActive,
    ?string $startsAt,
    ?string $endsAt
): bool {
    if (!coupons_table_exists()) {
        return false;
    }

    return db_execute(
        'UPDATE coupons
         SET code = :code,
             description = :description,
             checkout_type = :checkout_type,
             discount_type = :discount_type,
             discount_value = :discount_value,
             is_active = :is_active,
             starts_at = :starts_at,
             ends_at = :ends_at,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id',
        [
            'id' => $couponId,
            'code' => $code,
            'description' => $description,
            'checkout_type' => $checkoutType,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'is_active' => $isActive ? 1 : 0,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]
    );
}

function coupon_set_active(int $couponId, bool $isActive): bool
{
    if (!coupons_table_exists()) {
        return false;
    }

    return db_execute(
        'UPDATE coupons
         SET is_active = :is_active,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id',
        [
            'id' => $couponId,
            'is_active' => $isActive ? 1 : 0,
        ]
    );
}

function coupon_checkout_type_label(string $checkoutType): string
{
    return match ($checkoutType) {
        'reservation' => 'Reserva',
        'concessions' => 'Confiteria',
        'membership' => 'Membresia',
        'all' => 'Todos',
        default => 'No informado',
    };
}

function coupon_discount_type_label(string $discountType): string
{
    return match ($discountType) {
        'percent' => 'Porcentaje',
        'fixed' => 'Monto fijo',
        default => 'No informado',
    };
}

function coupon_schedule_label(array $coupon): string
{
    $startsAt = trim((string) ($coupon['starts_at'] ?? ''));
    $endsAt = trim((string) ($coupon['ends_at'] ?? ''));

    if ($startsAt === '' && $endsAt === '') {
        return 'Sin fechas';
    }

    $startLabel = $startsAt !== '' ? coupon_datetime_label($startsAt) : 'sin inicio';
    $endLabel = $endsAt !== '' ? coupon_datetime_label($endsAt) : 'sin termino';

    return $startLabel . ' - ' . $endLabel;
}

function coupon_datetime_label(string $value): string
{
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return '';
    }

    return $date->format('Y-m-d H:i');
}
