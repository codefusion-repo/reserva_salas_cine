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
        'subtotal_amount' => payment_amount_to_db($subtotalAmount),
        'discount_amount' => payment_amount_to_db($discountAmount),
        'total_amount' => payment_amount_to_db($totalAmount),
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
