<?php

declare(strict_types=1);

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
        $cookieToken = $_COOKIE['lms_token'] ?? null;
        return is_string($cookieToken) && $cookieToken !== '' ? $cookieToken : null;
    }

    return trim(substr($header, 7));
}

function setAuthCookie(?string $token, ?int $expiresAt, bool $secure): void
{
    $options = [
        'expires' => $expiresAt ?? (time() - 3600),
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ];

    setcookie('lms_token', $token ?? '', $options);
}

function requestJson(): array
{
    $body = file_get_contents('php://input');
    if (!is_string($body) || trim($body) === '') {
        return [];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function nowUtc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function tokenHash(string $token): string
{
    return hash('sha256', $token);
}

function randomToken(): string
{
    return bin2hex(random_bytes(32));
}
