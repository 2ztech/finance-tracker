<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $formToken = $_POST['csrf_token'] ?? '';

        if ($sessionToken === null) {
            http_response_code(403);
            die('Session expired. Please refresh the page and try again.');
        }

        if (!hash_equals($sessionToken, $formToken)) {
            http_response_code(403);
            die('Invalid CSRF token. Please refresh the page and try again.');
        }
    }
}
