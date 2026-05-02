<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

function user_find_by_email(string $email): ?array
{
    return db_fetch_one(
        'SELECT id, name, email, password_hash, role
         FROM users
         WHERE email = :email
         LIMIT 1',
        ['email' => $email]
    );
}

function user_find_by_id(int $id): ?array
{
    return db_fetch_one(
        'SELECT id, name, email, role
         FROM users
         WHERE id = :id
         LIMIT 1',
        ['id' => $id]
    );
}

function user_email_exists(string $email): bool
{
    return user_find_by_email($email) !== null;
}

function user_create(string $name, string $email, string $password): int
{
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    db_execute(
        'INSERT INTO users (name, email, password_hash, role)
         VALUES (:name, :email, :password_hash, :role)',
        [
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'user',
        ]
    );

    return (int) db()->lastInsertId();
}

function user_public_payload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}
