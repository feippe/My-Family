<?php
namespace App\Models;

use App\Core\Model;

class Notification extends Model {
    protected string $table = 'notifications';

    public function createForUser(int $userId, string $type, string $title, string $body = '', string $url = '', array $data = []): int {
        return $this->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'action_url' => $url,
            'data'       => $data ? json_encode($data) : null,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unreadForUser(int $userId, int $limit = 20): array {
        return $this->q(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public function unreadCount(int $userId): int {
        $r = $this->qOne('SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
        return (int)($r['cnt'] ?? 0);
    }

    public function markRead(int $id, int $userId): void {
        $this->exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function markAllRead(int $userId): void {
        $this->exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }
}
