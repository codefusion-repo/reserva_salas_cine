<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

function concession_products_table_exists(): bool
{
    try {
        $row = db_fetch_one(
            'SELECT COUNT(*) AS table_exists
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1',
            ['table_name' => 'concession_products']
        );

        return (int) ($row['table_exists'] ?? 0) > 0;
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        return false;
    }
}

function concession_products_active_all(): array
{
    if (!concession_products_table_exists()) {
        return [];
    }

    return db_fetch_all(
        'SELECT id, name, description, price_amount, icon, badge, is_active, sort_order
         FROM concession_products
         WHERE is_active = :is_active
         ORDER BY sort_order ASC, id ASC',
        ['is_active' => 1]
    );
}

function concession_products_all(): array
{
    if (!concession_products_table_exists()) {
        return [];
    }

    return db_fetch_all(
        'SELECT id, name, description, price_amount, icon, badge, is_active, sort_order, created_at, updated_at
         FROM concession_products
         ORDER BY is_active DESC, sort_order ASC, id ASC'
    );
}

function concession_product_find_by_id(int $productId): ?array
{
    if (!concession_products_table_exists()) {
        return null;
    }

    return db_fetch_one(
        'SELECT id, name, description, price_amount, icon, badge, is_active, sort_order, created_at, updated_at
         FROM concession_products
         WHERE id = :id
         LIMIT 1',
        ['id' => $productId]
    );
}

function concession_product_find_active_by_id(int $productId): ?array
{
    if (!concession_products_table_exists()) {
        return null;
    }

    return db_fetch_one(
        'SELECT id, name, description, price_amount, icon, badge, is_active, sort_order
         FROM concession_products
         WHERE id = :id
           AND is_active = :is_active
         LIMIT 1',
        [
            'id' => $productId,
            'is_active' => 1,
        ]
    );
}

function concession_product_create(
    string $name,
    string $description,
    float $priceAmount,
    ?string $icon,
    ?string $badge,
    bool $isActive,
    int $sortOrder
): int {
    if (!concession_products_table_exists()) {
        return 0;
    }

    db_execute(
        'INSERT INTO concession_products (name, description, price_amount, icon, badge, is_active, sort_order)
         VALUES (:name, :description, :price_amount, :icon, :badge, :is_active, :sort_order)',
        [
            'name' => $name,
            'description' => $description,
            'price_amount' => $priceAmount,
            'icon' => $icon,
            'badge' => $badge,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]
    );

    return (int) db()->lastInsertId();
}

function concession_product_update(
    int $productId,
    string $name,
    string $description,
    float $priceAmount,
    ?string $icon,
    ?string $badge,
    bool $isActive,
    int $sortOrder
): bool {
    if (!concession_products_table_exists()) {
        return false;
    }

    return db_execute(
        'UPDATE concession_products
         SET name = :name,
             description = :description,
             price_amount = :price_amount,
             icon = :icon,
             badge = :badge,
             is_active = :is_active,
             sort_order = :sort_order
         WHERE id = :id',
        [
            'id' => $productId,
            'name' => $name,
            'description' => $description,
            'price_amount' => $priceAmount,
            'icon' => $icon,
            'badge' => $badge,
            'is_active' => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
        ]
    );
}

function concession_product_set_active(int $productId, bool $isActive): bool
{
    if (!concession_products_table_exists()) {
        return false;
    }

    return db_execute(
        'UPDATE concession_products
         SET is_active = :is_active
         WHERE id = :id',
        [
            'id' => $productId,
            'is_active' => $isActive ? 1 : 0,
        ]
    );
}

function concession_product_delete(int $productId): bool
{
    if (!concession_products_table_exists()) {
        return false;
    }

    return db_execute(
        'DELETE FROM concession_products
         WHERE id = :id',
        ['id' => $productId]
    );
}
