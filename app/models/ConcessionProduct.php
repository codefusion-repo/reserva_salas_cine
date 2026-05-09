<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

function concession_products_active_all(): array
{
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
    return db_fetch_all(
        'SELECT id, name, description, price_amount, icon, badge, is_active, sort_order, created_at, updated_at
         FROM concession_products
         ORDER BY is_active DESC, sort_order ASC, id ASC'
    );
}

function concession_product_find_by_id(int $productId): ?array
{
    return db_fetch_one(
        'SELECT id, name, description, price_amount, icon, badge, is_active, sort_order, created_at, updated_at
         FROM concession_products
         WHERE id = :id
         LIMIT 1',
        ['id' => $productId]
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
