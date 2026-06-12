<?php
namespace App\Core;

class Auth {
    public function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    public function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public function groupId(): ?int {
        return $_SESSION['group_id'] ?? null;
    }

    public function user(): ?array {
        if (!$this->check()) return null;
        static $cache = null;
        if ($cache === null) {
            $db = Database::getInstance();
            $st = $db->prepare('SELECT id, name, email, avatar, color, created_at FROM users WHERE id = ?');
            $st->execute([$_SESSION['user_id']]);
            $cache = $st->fetch() ?: null;
        }
        return $cache;
    }

    public function login(int $userId, int $groupId = null): void {
        $_SESSION['user_id']  = $userId;
        if ($groupId !== null) $_SESSION['group_id'] = $groupId;
        session_regenerate_id(true);
    }

    public function setGroup(int $groupId): void {
        $_SESSION['group_id'] = $groupId;
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
