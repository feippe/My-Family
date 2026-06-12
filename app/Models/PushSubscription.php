<?php
namespace App\Models;

use App\Core\Model;

class PushSubscription extends Model {
    protected string $table = 'push_subscriptions';

    public function save(int $userId, string $endpoint, string $p256dh, string $auth, string $ua = ''): void {
        $existing = $this->qOne(
            'SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?',
            [$userId, $endpoint]
        );
        if ($existing) return;

        $this->insert([
            'user_id'    => $userId,
            'endpoint'   => $endpoint,
            'p256dh'     => $p256dh,
            'auth'       => $auth,
            'user_agent' => substr($ua, 0, 200),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function remove(int $userId, string $endpoint): void {
        $this->exec('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?', [$userId, $endpoint]);
    }

    public function forUser(int $userId): array {
        return $this->findAll(['user_id' => $userId]);
    }

    public function forUsers(array $userIds): array {
        if (empty($userIds)) return [];
        $in = implode(',', array_fill(0, count($userIds), '?'));
        return $this->q("SELECT * FROM push_subscriptions WHERE user_id IN ($in)", $userIds);
    }
}
