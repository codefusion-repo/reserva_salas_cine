<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

const USER_MEMBERSHIP_PLAN_DEMO = 'socio_cine_demo';
const USER_MEMBERSHIP_STATUS_ACTIVE = 'active';
const USER_MEMBERSHIP_STATUS_CANCELLED = 'cancelled';
const USER_MEMBERSHIP_STATUS_EXPIRED = 'expired';

function user_membership_find_current(int $userId, string $planCode = USER_MEMBERSHIP_PLAN_DEMO): ?array
{
    if ($userId <= 0) {
        return null;
    }

    return db_fetch_one(
        'SELECT
            id,
            user_id,
            plan_code,
            status,
            payment_id,
            activated_at,
            expires_at,
            created_at,
            updated_at
         FROM user_memberships
         WHERE user_id = :user_id
           AND plan_code = :plan_code
         LIMIT 1',
        [
            'user_id' => $userId,
            'plan_code' => user_membership_plan_code($planCode),
        ]
    );
}

function user_membership_find_active(int $userId, string $planCode = USER_MEMBERSHIP_PLAN_DEMO): ?array
{
    if ($userId <= 0) {
        return null;
    }

    return db_fetch_one(
        'SELECT
            id,
            user_id,
            plan_code,
            status,
            payment_id,
            activated_at,
            expires_at,
            created_at,
            updated_at
         FROM user_memberships
         WHERE user_id = :user_id
           AND plan_code = :plan_code
           AND status = :status
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1',
        [
            'user_id' => $userId,
            'plan_code' => user_membership_plan_code($planCode),
            'status' => USER_MEMBERSHIP_STATUS_ACTIVE,
        ]
    );
}

function user_membership_is_active(int $userId, string $planCode = USER_MEMBERSHIP_PLAN_DEMO): bool
{
    return user_membership_find_active($userId, $planCode) !== null;
}

function user_membership_find_current_for_update(
    PDO $pdo,
    int $userId,
    string $planCode = USER_MEMBERSHIP_PLAN_DEMO
): ?array {
    if ($userId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT
            id,
            user_id,
            plan_code,
            status,
            payment_id,
            activated_at,
            expires_at,
            created_at,
            updated_at
         FROM user_memberships
         WHERE user_id = :user_id
           AND plan_code = :plan_code
         LIMIT 1
         FOR UPDATE'
    );
    $statement->execute([
        'user_id' => $userId,
        'plan_code' => user_membership_plan_code($planCode),
    ]);

    $row = $statement->fetch();

    return $row === false ? null : $row;
}

function user_membership_activate(
    PDO $pdo,
    int $userId,
    ?int $paymentId,
    string $planCode = USER_MEMBERSHIP_PLAN_DEMO,
    ?string $expiresAt = null
): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('La membresia requiere un usuario valido.');
    }

    $planCode = user_membership_plan_code($planCode);
    $paymentId = $paymentId !== null && $paymentId > 0 ? $paymentId : null;
    $currentMembership = user_membership_find_current_for_update($pdo, $userId, $planCode);

    if (user_membership_row_is_active($currentMembership)) {
        return [
            'already_active' => true,
            'created' => false,
            'membership' => $currentMembership,
        ];
    }

    if ($currentMembership !== null) {
        $statement = $pdo->prepare(
            'UPDATE user_memberships
             SET status = :status,
                 payment_id = :payment_id,
                 activated_at = NOW(),
                 expires_at = :expires_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'status' => USER_MEMBERSHIP_STATUS_ACTIVE,
            'payment_id' => $paymentId,
            'expires_at' => $expiresAt,
            'id' => (int) $currentMembership['id'],
        ]);

        return [
            'already_active' => false,
            'created' => false,
            'membership' => user_membership_find_current_for_update($pdo, $userId, $planCode),
        ];
    }

    $statement = $pdo->prepare(
        'INSERT INTO user_memberships (
            user_id,
            plan_code,
            status,
            payment_id,
            activated_at,
            expires_at
         )
         VALUES (
            :user_id,
            :plan_code,
            :status,
            :payment_id,
            NOW(),
            :expires_at
         )'
    );
    $statement->execute([
        'user_id' => $userId,
        'plan_code' => $planCode,
        'status' => USER_MEMBERSHIP_STATUS_ACTIVE,
        'payment_id' => $paymentId,
        'expires_at' => $expiresAt,
    ]);

    return [
        'already_active' => false,
        'created' => true,
        'membership' => user_membership_find_current_for_update($pdo, $userId, $planCode),
    ];
}

function user_membership_cancel(int $userId, string $planCode = USER_MEMBERSHIP_PLAN_DEMO): bool
{
    if ($userId <= 0) {
        return false;
    }

    $statement = db()->prepare(
        'UPDATE user_memberships
         SET status = :status,
             updated_at = NOW()
         WHERE user_id = :user_id
           AND plan_code = :plan_code
           AND status = :active_status'
    );
    $statement->execute([
        'status' => USER_MEMBERSHIP_STATUS_CANCELLED,
        'user_id' => $userId,
        'plan_code' => user_membership_plan_code($planCode),
        'active_status' => USER_MEMBERSHIP_STATUS_ACTIVE,
    ]);

    return $statement->rowCount() > 0;
}

function user_membership_row_is_active(?array $membership): bool
{
    if ($membership === null || (string) ($membership['status'] ?? '') !== USER_MEMBERSHIP_STATUS_ACTIVE) {
        return false;
    }

    $expiresAt = trim((string) ($membership['expires_at'] ?? ''));

    if ($expiresAt === '') {
        return true;
    }

    try {
        return new DateTimeImmutable($expiresAt) > new DateTimeImmutable('now');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        return false;
    }
}

function user_membership_plan_code(string $planCode): string
{
    $normalizedPlanCode = strtolower(trim($planCode));

    if (preg_match('/^[a-z0-9_-]{1,40}$/', $normalizedPlanCode) !== 1) {
        throw new InvalidArgumentException('Codigo de plan de membresia invalido.');
    }

    return $normalizedPlanCode;
}
