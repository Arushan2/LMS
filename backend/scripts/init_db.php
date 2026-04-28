<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/env.php';
loadEnv(dirname(__DIR__) . '/.env');

require_once dirname(__DIR__) . '/src/Database.php';

$db = Database::connection();
$schemaPath = dirname(__DIR__) . '/sql/schema.sql';
$schemaSql = file_get_contents($schemaPath);

if ($schemaSql === false) {
    fwrite(STDERR, "Unable to read schema file.\n");
    exit(1);
}

$db->exec($schemaSql);

$bootstrapEmail = strtolower((string) env('BOOTSTRAP_SUPERADMIN_EMAIL', 'rockarush2@gmail.com'));
$bootstrapPassword = (string) env('BOOTSTRAP_SUPERADMIN_PASSWORD', '1234abcd');
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$existingStmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$existingStmt->execute([':email' => $bootstrapEmail]);
$existingUser = $existingStmt->fetch();

if (!$existingUser) {
    $createUser = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, status, created_at, updated_at)
         VALUES (:full_name, :email, :password_hash, :status, :created_at, :updated_at)'
    );
    $createUser->execute([
        ':full_name' => 'Bootstrap Super Admin',
        ':email' => $bootstrapEmail,
        ':password_hash' => password_hash($bootstrapPassword, PASSWORD_DEFAULT),
        ':status' => 'active',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $userId = (int) $db->lastInsertId();
} else {
    $userId = (int) $existingUser['id'];

    $updateUser = $db->prepare(
        'UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id'
    );
    $updateUser->execute([
        ':status' => 'active',
        ':updated_at' => $now,
        ':id' => $userId,
    ]);
}

$roleCheck = $db->prepare(
    'SELECT id FROM user_role_assignments WHERE user_id = :user_id AND role_name = :role_name AND is_active = 1 LIMIT 1'
);
$roleCheck->execute([
    ':user_id' => $userId,
    ':role_name' => 'super_admin',
]);

if (!$roleCheck->fetch()) {
    $assignRole = $db->prepare(
        'INSERT INTO user_role_assignments (user_id, role_name, assigned_by, is_active, created_at)
         VALUES (:user_id, :role_name, :assigned_by, 1, :created_at)'
    );
    $assignRole->execute([
        ':user_id' => $userId,
        ':role_name' => 'super_admin',
        ':assigned_by' => null,
        ':created_at' => $now,
    ]);
}

fwrite(STDOUT, "Database initialized and bootstrap super admin ready.\n");
