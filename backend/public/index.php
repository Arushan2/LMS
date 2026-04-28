<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/env.php';
loadEnv(dirname(__DIR__) . '/.env');

require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/AuthService.php';

$frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
header('Access-Control-Allow-Origin: ' . $frontendUrl);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$auth = new AuthService();
$method = $_SERVER['REQUEST_METHOD'];
$uri = strtok($_SERVER['REQUEST_URI'], '?') ?: '/';

if ($method === 'GET' && $uri === '/api/health') {
    jsonResponse(['ok' => true, 'service' => 'lms-auth-api']);
    exit;
}

if ($method === 'POST' && $uri === '/api/auth/signup') {
    $input = requestJson();
    $fullName = trim((string) ($input['fullName'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $requestedRole = trim((string) ($input['requestedRole'] ?? ''));

    if ($fullName === '' || $email === '' || $password === '' || $requestedRole === '') {
        jsonResponse(['ok' => false, 'message' => 'Missing required fields.'], 422);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'message' => 'Invalid email format.'], 422);
        exit;
    }

    if (strlen($password) < 8) {
        jsonResponse(['ok' => false, 'message' => 'Password must be at least 8 characters.'], 422);
        exit;
    }

    $result = $auth->signup($fullName, $email, $password, $requestedRole);
    if (!$result['ok']) {
        jsonResponse($result, 409);
        exit;
    }

    jsonResponse($result, 201);
    exit;
}

if ($method === 'POST' && $uri === '/api/auth/signin') {
    $input = requestJson();
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(['ok' => false, 'message' => 'Email and password are required.'], 422);
        exit;
    }

    $result = $auth->signin($email, $password);
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'message' => $result['message']], (int) ($result['status'] ?? 401));
        exit;
    }

    jsonResponse($result);
    exit;
}

$tokenUser = $auth->userFromToken(getBearerToken());

if ($method === 'GET' && $uri === '/api/auth/me') {
    if ($tokenUser === null) {
        jsonResponse(['ok' => false, 'message' => 'Unauthorized.'], 401);
        exit;
    }

    jsonResponse(['ok' => true, 'user' => $tokenUser]);
    exit;
}

if ($method === 'GET' && $uri === '/api/approvals/pending') {
    if ($tokenUser === null) {
        jsonResponse(['ok' => false, 'message' => 'Unauthorized.'], 401);
        exit;
    }

    $requests = $auth->pendingRoleRequests($tokenUser);
    jsonResponse(['ok' => true, 'items' => $requests]);
    exit;
}

if ($method === 'POST' && preg_match('#^/api/approvals/(\d+)/review$#', $uri, $matches) === 1) {
    if ($tokenUser === null) {
        jsonResponse(['ok' => false, 'message' => 'Unauthorized.'], 401);
        exit;
    }

    $requestId = (int) $matches[1];
    $input = requestJson();
    $action = (string) ($input['action'] ?? '');
    $note = isset($input['note']) ? trim((string) $input['note']) : null;

    $result = $auth->reviewRequest($requestId, $action, $tokenUser, $note);
    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'message' => $result['message']], (int) ($result['status'] ?? 400));
        exit;
    }

    jsonResponse($result);
    exit;
}

jsonResponse(['ok' => false, 'message' => 'Route not found.'], 404);
