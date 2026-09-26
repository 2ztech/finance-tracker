<?php

declare(strict_types=1);

final class Auth
{
    private const MAX_ATTEMPTS   = 5;
    private const WINDOW_MINUTES = 15;

    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public static function attemptLogin(string $username, string $password): bool
    {
        $ip = self::clientIp();
        if (self::isRateLimited($ip)) {
            return false;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            self::clearLoginAttempts($ip);
            self::establishSession((int) $user['id'], $username);
            return true;
        }

        self::recordFailedLogin($ip);
        return false;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function hasUsers(): bool
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn() > 0;
    }

    public static function setupFirstUser(string $username, string $password): bool
    {
        if (self::hasUsers()) {
            return false;
        }
        $db = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        if ($stmt->execute([$username, $hash])) {
            self::establishSession((int) $db->lastInsertId(), $username);
            return true;
        }
        return false;
    }

    public static function updateCredentials(int $userId, string $newUsername, string $oldPassword, ?string $newPassword): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($oldPassword, $hash)) {
            return false;
        }

        $dup = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $dup->execute([$newUsername, $userId]);
        if ($dup->fetchColumn()) {
            return false;
        }

        try {
            if ($newPassword !== null && $newPassword !== '') {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdate = $db->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
                $success = $stmtUpdate->execute([$newUsername, $newHash, $userId]);
            } else {
                $stmtUpdate = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
                $success = $stmtUpdate->execute([$newUsername, $userId]);
            }
        } catch (PDOException) {
            return false;
        }

        if ($success) {
            $_SESSION['username'] = $newUsername;
            return true;
        }
        return false;
    }

    // --- Session & throttling helpers ---

    private static function establishSession(int $userId, string $username): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
    }

    public static function isRateLimited(string $ip): bool
    {
        $db = Database::getConnection();
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?");
        $stmt->execute([$ip, $cutoff]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function recordFailedLogin(string $ip): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)");
        $stmt->execute([$ip, date('Y-m-d H:i:s')]);

        $cleanup = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $cleanup->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }

    public static function clearLoginAttempts(string $ip): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->execute([$ip]);
    }
}
