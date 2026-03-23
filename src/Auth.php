<?php

class Auth {
    public static function attemptLogin(string $username, string $password): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            return true;
        }

        return false;
    }

    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    public static function logout(): void {
        session_destroy();
    }

    public static function updatePassword(int $userId, string $oldPassword, string $newPassword): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($oldPassword, $hash)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            return $stmtUpdate->execute([$newHash, $userId]);
        }
        return false;
    }
}
