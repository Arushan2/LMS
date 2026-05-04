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

        // Prevent requesting super_admin if one already exists
        if ($requestedRole === 'super_admin' && $this->superAdminExists()) {
            return ['ok' => false, 'message' => 'The system can have only one super admin. That role is unavailable.'];
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

    public function signin(string $email, string $password, string $ipAddress): array
    {
        $normalizedEmail = strtolower($email);

        if ($this->isLoginLocked($normalizedEmail, $ipAddress)) {
            return ['ok' => false, 'status' => 429, 'message' => 'Too many failed attempts. Try again later.'];
        }

        $user = $this->findUserByEmail($normalizedEmail);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedLogin($normalizedEmail, $ipAddress);
            return ['ok' => false, 'status' => 401, 'message' => 'Invalid email or password.'];
        }

        if ($user['status'] !== 'active') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Account is not active yet. Current status: ' . $user['status'],
            ];
        }

        $this->clearLoginAttempts($normalizedEmail, $ipAddress);

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
            'expiresAt' => $expiresAt->getTimestamp(),
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

    public function canReviewRequests(array $actor): bool
    {
        $roles = $actor['roles'] ?? [];

        return in_array('super_admin', $roles, true)
            || in_array('system_analyst', $roles, true);
    }

    public function revokeToken(string $token): void
    {
        $stmt = $this->db->prepare('DELETE FROM auth_tokens WHERE token_hash = :token_hash');
        $stmt->execute([':token_hash' => tokenHash($token)]);
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
            // Only allow approving super_admin if no super_admin exists yet
            if ($requestedRole === 'super_admin' && $this->superAdminExists()) {
                return false;
            }
            return true;
        }

        if (in_array('system_analyst', $actorRoles, true) && in_array($requestedRole, ['student', 'lecturer'], true)) {
            return true;
        }

        return false;
    }

    private function superAdminExists(): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM user_role_assignments WHERE role_name = :role_name AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':role_name' => 'super_admin']);

        return $stmt->fetch() !== false;
    }

    public function hasSuperAdmin(): bool
    {
        return $this->superAdminExists();
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

    private function isLoginLocked(string $email, string $ipAddress): bool
    {
        $stmt = $this->db->prepare(
            'SELECT first_attempt_at, locked_until FROM login_attempts WHERE email = :email AND ip_address = :ip LIMIT 1'
        );
        $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (!empty($row['locked_until'])) {
            $lockedUntil = new DateTimeImmutable($row['locked_until'], new DateTimeZone('UTC'));
            if ($lockedUntil > $now) {
                return true;
            }
        }

        $windowMinutes = (int) env('LOGIN_WINDOW_MINUTES', '15');
        $firstAttemptAt = new DateTimeImmutable($row['first_attempt_at'], new DateTimeZone('UTC'));
        if ($firstAttemptAt->modify('+' . max(1, $windowMinutes) . ' minutes') < $now) {
            $this->clearLoginAttempts($email, $ipAddress);
        }

        return false;
    }

    private function recordFailedLogin(string $email, string $ipAddress): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $maxAttempts = (int) env('LOGIN_MAX_ATTEMPTS', '5');
        $windowMinutes = (int) env('LOGIN_WINDOW_MINUTES', '15');
        $lockMinutes = (int) env('LOGIN_LOCK_MINUTES', '15');

        $stmt = $this->db->prepare(
            'SELECT attempt_count, first_attempt_at, locked_until
             FROM login_attempts
             WHERE email = :email AND ip_address = :ip
             LIMIT 1'
        );
        $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            $insert = $this->db->prepare(
                'INSERT INTO login_attempts (email, ip_address, attempt_count, first_attempt_at, last_attempt_at, locked_until)
                 VALUES (:email, :ip, :attempt_count, :first_attempt_at, :last_attempt_at, :locked_until)'
            );
            $insert->execute([
                ':email' => $email,
                ':ip' => $ipAddress,
                ':attempt_count' => 1,
                ':first_attempt_at' => $now->format('Y-m-d H:i:s'),
                ':last_attempt_at' => $now->format('Y-m-d H:i:s'),
                ':locked_until' => null,
            ]);
            return;
        }

        if (!empty($row['locked_until'])) {
            $lockedUntil = new DateTimeImmutable($row['locked_until'], new DateTimeZone('UTC'));
            if ($lockedUntil > $now) {
                return;
            }
        }

        $firstAttemptAt = new DateTimeImmutable($row['first_attempt_at'], new DateTimeZone('UTC'));
        if ($firstAttemptAt->modify('+' . max(1, $windowMinutes) . ' minutes') < $now) {
            $attemptCount = 1;
            $firstAttemptAt = $now;
        } else {
            $attemptCount = (int) $row['attempt_count'] + 1;
        }

        $lockedUntil = null;
        if ($attemptCount >= max(1, $maxAttempts)) {
            $lockedUntil = $now->modify('+' . max(1, $lockMinutes) . ' minutes');
        }

        $update = $this->db->prepare(
            'UPDATE login_attempts
             SET attempt_count = :attempt_count,
                 first_attempt_at = :first_attempt_at,
                 last_attempt_at = :last_attempt_at,
                 locked_until = :locked_until
             WHERE email = :email AND ip_address = :ip'
        );
        $update->execute([
            ':attempt_count' => $attemptCount,
            ':first_attempt_at' => $firstAttemptAt->format('Y-m-d H:i:s'),
            ':last_attempt_at' => $now->format('Y-m-d H:i:s'),
            ':locked_until' => $lockedUntil ? $lockedUntil->format('Y-m-d H:i:s') : null,
            ':email' => $email,
            ':ip' => $ipAddress,
        ]);
    }

    private function clearLoginAttempts(string $email, string $ipAddress): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip');
        $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress,
        ]);
    }
}
