<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

const PAYMENT_ALLOWED_CHECKOUT_TYPES = ['reservation', 'concessions', 'membership'];
const PAYMENT_ALLOWED_ITEM_TYPES = ['ticket', 'concession', 'membership'];
const PAYMENT_STATUS_SIMULATED_PAID = 'simulated_paid';
const PAYMENT_METHOD_SIMULATED = 'simulated';
const PAYMENT_CURRENCY_CLP = 'CLP';

function payment_create_simulated(
    int $userId,
    string $checkoutType,
    ?int $reservationId,
    array $items,
    float $subtotalAmount,
    float $discountAmount,
    float $totalAmount
): array {
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $payment = payment_insert_simulated_paid(
            $pdo,
            $userId,
            $checkoutType,
            $reservationId,
            $items,
            $subtotalAmount,
            $discountAmount,
            $totalAmount
        );

        $pdo->commit();

        return [
            'ok' => true,
            'payment' => $payment,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($exception->getMessage());

        return [
            'ok' => false,
            'message' => 'No se pudo registrar el pago simulado en este momento.',
        ];
    }
}

function payment_insert_simulated_paid(
    PDO $pdo,
    int $userId,
    string $checkoutType,
    ?int $reservationId,
    array $items,
    float $subtotalAmount,
    float $discountAmount,
    float $totalAmount
): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('El pago requiere un usuario valido.');
    }

    if (!in_array($checkoutType, PAYMENT_ALLOWED_CHECKOUT_TYPES, true)) {
        throw new InvalidArgumentException('Tipo de checkout de pago no permitido.');
    }

    if ($checkoutType === 'reservation' && ($reservationId === null || $reservationId <= 0)) {
        throw new InvalidArgumentException('El pago de reserva requiere una reserva valida.');
    }

    if ($checkoutType !== 'reservation') {
        $reservationId = null;
    }

    $normalizedItems = payment_normalize_items($items);

    if ($normalizedItems === []) {
        throw new InvalidArgumentException('El pago requiere al menos un item.');
    }

    $normalizedSubtotalAmount = payment_amount_from_value($subtotalAmount);
    $normalizedDiscountAmount = payment_amount_from_value($discountAmount);
    $normalizedTotalAmount = payment_amount_from_value($totalAmount);
    $itemsTotalCents = 0;

    foreach ($normalizedItems as $item) {
        $itemsTotalCents += payment_amount_to_cents((float) $item['total_amount']);
    }

    $subtotalCents = payment_amount_to_cents($normalizedSubtotalAmount);
    $discountCents = payment_amount_to_cents($normalizedDiscountAmount);
    $totalCents = payment_amount_to_cents($normalizedTotalAmount);

    if ($itemsTotalCents !== $subtotalCents) {
        throw new InvalidArgumentException('El subtotal del pago no coincide con sus items.');
    }

    if ($discountCents > $subtotalCents) {
        throw new InvalidArgumentException('El descuento no puede superar el subtotal.');
    }

    if (($subtotalCents - $discountCents) !== $totalCents) {
        throw new InvalidArgumentException('El total del pago no coincide con subtotal menos descuento.');
    }

    $referenceCode = payment_reference_code($checkoutType);

    $paymentStatement = $pdo->prepare(
        'INSERT INTO payments (
            user_id,
            checkout_type,
            reservation_id,
            reference_code,
            status,
            subtotal_amount,
            discount_amount,
            total_amount,
            currency,
            payment_method,
            paid_at
         )
         VALUES (
            :user_id,
            :checkout_type,
            :reservation_id,
            :reference_code,
            :status,
            :subtotal_amount,
            :discount_amount,
            :total_amount,
            :currency,
            :payment_method,
            NOW()
         )'
    );
    $paymentStatement->execute([
        'user_id' => $userId,
        'checkout_type' => $checkoutType,
        'reservation_id' => $reservationId,
        'reference_code' => $referenceCode,
        'status' => PAYMENT_STATUS_SIMULATED_PAID,
        'subtotal_amount' => payment_amount_to_db($normalizedSubtotalAmount),
        'discount_amount' => payment_amount_to_db($normalizedDiscountAmount),
        'total_amount' => payment_amount_to_db($normalizedTotalAmount),
        'currency' => PAYMENT_CURRENCY_CLP,
        'payment_method' => PAYMENT_METHOD_SIMULATED,
    ]);

    $paymentId = (int) $pdo->lastInsertId();
    $itemStatement = $pdo->prepare(
        'INSERT INTO payment_items (
            payment_id,
            item_type,
            item_label,
            quantity,
            unit_amount,
            total_amount
         )
         VALUES (
            :payment_id,
            :item_type,
            :item_label,
            :quantity,
            :unit_amount,
            :total_amount
         )'
    );

    foreach ($normalizedItems as $item) {
        $itemStatement->execute([
            'payment_id' => $paymentId,
            'item_type' => $item['item_type'],
            'item_label' => $item['item_label'],
            'quantity' => $item['quantity'],
            'unit_amount' => payment_amount_to_db((float) $item['unit_amount']),
            'total_amount' => payment_amount_to_db((float) $item['total_amount']),
        ]);
    }

    return [
        'id' => $paymentId,
        'reference_code' => $referenceCode,
        'checkout_type' => $checkoutType,
        'reservation_id' => $reservationId,
        'status' => PAYMENT_STATUS_SIMULATED_PAID,
    ];
}

function payment_user_all(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    return db_fetch_all(
        "SELECT
            p.id,
            p.checkout_type,
            p.reservation_id,
            p.reference_code,
            p.status,
            p.subtotal_amount,
            p.discount_amount,
            p.total_amount,
            p.currency,
            p.payment_method,
            p.paid_at,
            p.created_at,
            r.status AS reservation_status,
            m.title AS movie_title,
            rm.name AS room_name,
            rm.location AS room_location,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label
         FROM payments p
         LEFT JOIN reservations r
            ON r.id = p.reservation_id
           AND r.user_id = p.user_id
         LEFT JOIN showtimes s ON s.id = r.showtime_id
         LEFT JOIN movies m ON m.id = s.movie_id
         LEFT JOIN rooms rm ON rm.id = s.room_id
         WHERE p.user_id = :user_id
         ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC",
        ['user_id' => $userId]
    );
}

function payment_find_for_user(int $paymentId, int $userId): ?array
{
    if ($paymentId <= 0 || $userId <= 0) {
        return null;
    }

    return db_fetch_one(
        "SELECT
            p.id,
            p.checkout_type,
            p.reservation_id,
            p.reference_code,
            p.status,
            p.subtotal_amount,
            p.discount_amount,
            p.total_amount,
            p.currency,
            p.payment_method,
            p.paid_at,
            p.created_at,
            r.status AS reservation_status,
            m.title AS movie_title,
            rm.name AS room_name,
            rm.location AS room_location,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label
         FROM payments p
         LEFT JOIN reservations r
            ON r.id = p.reservation_id
           AND r.user_id = p.user_id
         LEFT JOIN showtimes s ON s.id = r.showtime_id
         LEFT JOIN movies m ON m.id = s.movie_id
         LEFT JOIN rooms rm ON rm.id = s.room_id
         WHERE p.id = :id
           AND p.user_id = :user_id
         LIMIT 1",
        [
            'id' => $paymentId,
            'user_id' => $userId,
        ]
    );
}

function payment_items_for_payment(int $paymentId): array
{
    if ($paymentId <= 0) {
        return [];
    }

    return db_fetch_all(
        'SELECT
            id,
            payment_id,
            item_type,
            item_label,
            quantity,
            unit_amount,
            total_amount,
            created_at
         FROM payment_items
         WHERE payment_id = :payment_id
         ORDER BY id ASC',
        ['payment_id' => $paymentId]
    );
}

function payment_admin_all(array $filters = []): array
{
    [$whereSql, $params] = payment_admin_filter_sql($filters);
    $sql = payment_admin_select_sql();

    if ($whereSql !== '') {
        $sql .= ' WHERE ' . $whereSql;
    }

    $sql .= ' ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC';

    return db_fetch_all($sql, $params);
}

function payment_find_for_admin(int $paymentId): ?array
{
    if ($paymentId <= 0) {
        return null;
    }

    return db_fetch_one(
        payment_admin_select_sql() . ' WHERE p.id = :id LIMIT 1',
        ['id' => $paymentId]
    );
}

function payment_admin_select_sql(): string
{
    return "SELECT
            p.id,
            p.user_id,
            u.name AS user_name,
            u.email AS user_email,
            p.checkout_type,
            p.reservation_id,
            p.reference_code,
            p.status,
            p.subtotal_amount,
            p.discount_amount,
            p.total_amount,
            p.currency,
            p.payment_method,
            p.paid_at,
            p.created_at,
            r.status AS reservation_status,
            m.title AS movie_title,
            rm.name AS room_name,
            rm.location AS room_location,
            s.starts_at,
            s.ends_at,
            s.format_label,
            s.language_label
         FROM payments p
         INNER JOIN users u ON u.id = p.user_id
         LEFT JOIN reservations r
            ON r.id = p.reservation_id
           AND r.user_id = p.user_id
         LEFT JOIN showtimes s ON s.id = r.showtime_id
         LEFT JOIN movies m ON m.id = s.movie_id
         LEFT JOIN rooms rm ON rm.id = s.room_id";
}

function payment_admin_filter_sql(array $filters): array
{
    $conditions = [];
    $params = [];
    $checkoutType = (string) ($filters['checkout_type'] ?? '');

    if (in_array($checkoutType, PAYMENT_ALLOWED_CHECKOUT_TYPES, true)) {
        $conditions[] = 'p.checkout_type = :checkout_type';
        $params['checkout_type'] = $checkoutType;
    }

    $status = (string) ($filters['status'] ?? '');

    if ($status === PAYMENT_STATUS_SIMULATED_PAID) {
        $conditions[] = 'p.status = :status';
        $params['status'] = $status;
    }

    $dateFrom = (string) ($filters['date_from'] ?? '');

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
        $conditions[] = 'COALESCE(p.paid_at, p.created_at) >= :date_from_start';
        $params['date_from_start'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = (string) ($filters['date_to'] ?? '');

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
        try {
            $dateToNext = (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d');
            $conditions[] = 'COALESCE(p.paid_at, p.created_at) < :date_to_next_day';
            $params['date_to_next_day'] = $dateToNext . ' 00:00:00';
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }

    $search = trim((string) ($filters['q'] ?? ''));

    if ($search !== '') {
        $likeSearch = '%' . $search . '%';
        $conditions[] = '(u.name LIKE :q_user_name
            OR u.email LIKE :q_user_email
            OR p.reference_code LIKE :q_reference
            OR CAST(p.id AS CHAR) LIKE :q_payment_id
            OR CAST(p.user_id AS CHAR) LIKE :q_user_id
            OR CONCAT(\'RSC-\', LPAD(COALESCE(p.reservation_id, 0), 6, \'0\')) LIKE :q_reservation_code)';
        $params['q_user_name'] = $likeSearch;
        $params['q_user_email'] = $likeSearch;
        $params['q_reference'] = $likeSearch;
        $params['q_payment_id'] = $likeSearch;
        $params['q_user_id'] = $likeSearch;
        $params['q_reservation_code'] = $likeSearch;
    }

    return [
        implode(' AND ', $conditions),
        $params,
    ];
}

function payment_normalize_items(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item de pago invalido.');
        }

        $itemType = (string) ($item['item_type'] ?? '');

        if (!in_array($itemType, PAYMENT_ALLOWED_ITEM_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de item de pago no permitido.');
        }

        $itemLabel = payment_item_label((string) ($item['item_label'] ?? ''));
        $quantity = (int) ($item['quantity'] ?? 0);
        $unitAmount = payment_amount_from_value($item['unit_amount'] ?? null);
        $totalAmount = payment_amount_from_value($item['total_amount'] ?? null);

        if ($itemLabel === '' || $quantity <= 0) {
            throw new InvalidArgumentException('Item de pago incompleto.');
        }

        $normalized[] = [
            'item_type' => $itemType,
            'item_label' => $itemLabel,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'total_amount' => $totalAmount,
        ];
    }

    return $normalized;
}

function payment_reference_code(string $checkoutType): string
{
    $prefix = match ($checkoutType) {
        'reservation' => 'RSV',
        'concessions' => 'CONF',
        'membership' => 'SOC',
        default => 'PAY',
    };

    return $prefix . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function payment_item_label(string $label): string
{
    $label = preg_replace('/\s+/', ' ', trim($label));
    $label = $label === null ? '' : $label;

    if (function_exists('mb_substr')) {
        return mb_substr($label, 0, 180, 'UTF-8');
    }

    return substr($label, 0, 180);
}

function payment_amount_from_value(mixed $value): float
{
    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
        throw new InvalidArgumentException('Monto de pago invalido.');
    }

    $amount = (float) $value;

    if ($amount < 0) {
        throw new InvalidArgumentException('Monto de pago negativo no permitido.');
    }

    return $amount;
}

function payment_amount_to_db(float $amount): string
{
    if ($amount < 0) {
        throw new InvalidArgumentException('Monto de pago negativo no permitido.');
    }

    return number_format($amount, 2, '.', '');
}

function payment_amount_to_cents(float $amount): int
{
    if ($amount < 0) {
        throw new InvalidArgumentException('Monto de pago negativo no permitido.');
    }

    return (int) round($amount * 100);
}
