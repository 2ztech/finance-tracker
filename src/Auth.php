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

    public static function hasUsers(): bool {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn() > 0;
    }

    public static function setupFirstUser(string $username, string $password): bool {
        if (self::hasUsers()) {
            return false;
        }
        $db = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        if ($stmt->execute([$username, $hash])) {
            $userId = $db->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            return true;
        }
        return false;
    }

    public static function updateCredentials(int $userId, string $newUsername, string $oldPassword, ?string $newPassword): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($oldPassword, $hash)) {
            if ($newPassword) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdate = $db->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
                $success = $stmtUpdate->execute([$newUsername, $newHash, $userId]);
            } else {
                $stmtUpdate = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
                $success = $stmtUpdate->execute([$newUsername, $userId]);
            }
            if ($success) {
                $_SESSION['username'] = $newUsername;
                return true;
            }
        }
        return false;
    }
}
