<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

final class AuthService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function signup(string $fullName, string $email, string $password, string $requestedRole): array
    {
        $allowedRoles = ['student', 'lecturer', 'system_analyst', 'super_admin'];
        if (!in_array($requestedRole, $allowedRoles, true)) {
            return ['ok' => false, 'message' => 'Invalid role selected.'];
        }

        $existing = $this->findUserByEmail($email);
        if ($existing !== null) {
            return ['ok' => false, 'message' => 'Email already registered.'];
        }

        $now = nowUtc();

        $stmt = $this->db->prepare(
            'INSERT INTO users (full_name, email, password_hash, status, created_at, updated_at)
             VALUES (:full_name, :email, :password_hash, :status, :created_at, :updated_at)'
        );

        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => strtolower($email),
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':status' => 'pending_approval',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $userId = (int) $this->db->lastInsertId();

        $requestStmt = $this->db->prepare(
            'INSERT INTO user_role_requests (user_id, requested_role, status, created_at)
             VALUES (:user_id, :requested_role, :status, :created_at)'
        );

        $requestStmt->execute([
            ':user_id' => $userId,
            ':requested_role' => $requestedRole,
            ':status' => 'pending',
            ':created_at' => $now,
        ]);

        $this->logEvent(null, 'signup_requested', $userId, [
            'requested_role' => $requestedRole,
        ]);

        return ['ok' => true, 'message' => 'Signup successful. Await approval before login access.'];
    }

    public function signin(string $email, string $password): array
    {
        $user = $this->findUserByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'status' => 401, 'message' => 'Invalid email or password.'];
        }

        if ($user['status'] !== 'active') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Account is not active yet. Current status: ' . $user['status'],
            ];
        }

        $token = randomToken();
        $hash = tokenHash($token);
        $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $ttlHours = (int) env('TOKEN_TTL_HOURS', '8');
        $expiresAt = $createdAt->modify('+' . max(1, $ttlHours) . ' hours');

        $stmt = $this->db->prepare(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)'
        );

        $stmt->execute([
            ':user_id' => $user['id'],
            ':token_hash' => $hash,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ':created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);

        $roles = $this->rolesForUser((int) $user['id']);

        $this->logEvent((int) $user['id'], 'signin_success', (int) $user['id'], null);

        return [
            'ok' => true,
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'fullName' => $user['full_name'],
                'email' => $user['email'],
                'status' => $user['status'],
                'roles' => $roles,
            ],
        ];
    }

    public function userFromToken(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT u.id, u.full_name, u.email, u.status
             FROM auth_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash
             AND t.expires_at > :now
             LIMIT 1'
        );

        $stmt->execute([
            ':token_hash' => tokenHash($token),
            ':now' => nowUtc(),
        ]);

        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'fullName' => $user['full_name'],
            'email' => $user['email'],
            'status' => $user['status'],
            'roles' => $this->rolesForUser((int) $user['id']),
        ];
    }

    public function pendingRoleRequests(array $actor): array
    {
        $roles = $actor['roles'] ?? [];
        $allowedTargets = [];

        if (in_array('super_admin', $roles, true)) {
            $allowedTargets = ['student', 'lecturer', 'system_analyst', 'super_admin'];
        } elseif (in_array('system_analyst', $roles, true)) {
            $allowedTargets = ['student', 'lecturer'];
        } else {
            return [];
        }

        $inClause = implode(',', array_fill(0, count($allowedTargets), '?'));
        $sql = "SELECT rr.id, rr.requested_role, rr.status, rr.created_at,
                       u.id AS user_id, u.full_name, u.email, u.status AS user_status
                FROM user_role_requests rr
                INNER JOIN users u ON u.id = rr.user_id
                WHERE rr.status = 'pending' AND rr.requested_role IN ($inClause)
                ORDER BY rr.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($allowedTargets);

        return $stmt->fetchAll();
    }

    public function reviewRequest(int $requestId, string $action, array $actor, ?string $note = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT rr.id, rr.user_id, rr.requested_role, rr.status, u.status AS user_status
             FROM user_role_requests rr
             INNER JOIN users u ON u.id = rr.user_id
             WHERE rr.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            return ['ok' => false, 'status' => 404, 'message' => 'Request not found.'];
        }

        if ($request['status'] !== 'pending') {
            return ['ok' => false, 'status' => 400, 'message' => 'Request already reviewed.'];
        }

        $requestedRole = $request['requested_role'];
        if (!$this->canApprove($actor['roles'], $requestedRole)) {
            return ['ok' => false, 'status' => 403, 'message' => 'Not authorized to review this role request.'];
        }

        $normalizedAction = strtolower($action);
        if (!in_array($normalizedAction, ['approve', 'reject'], true)) {
            return ['ok' => false, 'status' => 400, 'message' => 'Action must be approve or reject.'];
        }

        $now = nowUtc();
        $newStatus = $normalizedAction === 'approve' ? 'approved' : 'rejected';

        $updateRequest = $this->db->prepare(
            'UPDATE user_role_requests
             SET status = :status, reviewed_by = :reviewed_by, review_note = :review_note, reviewed_at = :reviewed_at
             WHERE id = :id'
        );

        $updateRequest->execute([
            ':status' => $newStatus,
            ':reviewed_by' => $actor['id'],
            ':review_note' => $note,
            ':reviewed_at' => $now,
            ':id' => $requestId,
        ]);

        if ($normalizedAction === 'approve') {
            $assignStmt = $this->db->prepare(
                'SELECT id FROM user_role_assignments
                 WHERE user_id = :user_id AND role_name = :role_name AND is_active = 1
                 LIMIT 1'
            );
            $assignStmt->execute([
                ':user_id' => $request['user_id'],
                ':role_name' => $requestedRole,
            ]);
            $exists = $assignStmt->fetch();

            if (!$exists) {
                $createAssignment = $this->db->prepare(
                    'INSERT INTO user_role_assignments (user_id, role_name, assigned_by, is_active, created_at)
                     VALUES (:user_id, :role_name, :assigned_by, 1, :created_at)'
                );
                $createAssignment->execute([
                    ':user_id' => $request['user_id'],
                    ':role_name' => $requestedRole,
                    ':assigned_by' => $actor['id'],
                    ':created_at' => $now,
                ]);
            }

            $activateUser = $this->db->prepare(
                'UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id'
            );
            $activateUser->execute([
                ':status' => 'active',
                ':updated_at' => $now,
                ':id' => $request['user_id'],
            ]);

            $this->logEvent((int) $actor['id'], 'appointment_approved', (int) $request['user_id'], [
                'requested_role' => $requestedRole,
                'request_id' => $requestId,
            ]);

            return ['ok' => true, 'message' => 'Request approved.'];
        }

        $rejectUser = $this->db->prepare('UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id');
        $rejectUser->execute([
            ':status' => 'rejected',
            ':updated_at' => $now,
            ':id' => $request['user_id'],
        ]);

        $this->logEvent((int) $actor['id'], 'appointment_rejected', (int) $request['user_id'], [
            'requested_role' => $requestedRole,
            'request_id' => $requestId,
            'note' => $note,
        ]);

        return ['ok' => true, 'message' => 'Request rejected.'];
    }

    private function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower($email)]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function rolesForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT role_name FROM user_role_assignments WHERE user_id = :user_id AND is_active = 1'
        );
        $stmt->execute([':user_id' => $userId]);

        $rows = $stmt->fetchAll();

        return array_map(
            static fn(array $row): string => $row['role_name'],
            $rows
        );
    }

    private function canApprove(array $actorRoles, string $requestedRole): bool
    {
        if (in_array('super_admin', $actorRoles, true)) {
            return true;
        }

        if (in_array('system_analyst', $actorRoles, true) && in_array($requestedRole, ['student', 'lecturer'], true)) {
            return true;
        }

        return false;
    }

    private function logEvent(?int $actorId, string $eventType, ?int $targetUserId, ?array $metadata): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (actor_user_id, event_type, target_user_id, metadata_json, created_at)
             VALUES (:actor_user_id, :event_type, :target_user_id, :metadata_json, :created_at)'
        );

        $stmt->execute([
            ':actor_user_id' => $actorId,
            ':event_type' => $eventType,
            ':target_user_id' => $targetUserId,
            ':metadata_json' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES),
            ':created_at' => nowUtc(),
        ]);
    }
}
