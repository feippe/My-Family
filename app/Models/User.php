<?php
namespace App\Models;

use App\Core\Model;

class User extends Model {
    protected string $table = 'users';

    public function findByEmail(string $email): ?array {
        return $this->qOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public function create(string $name, string $email, string $password): int {
        $colors = ['#7c3aed','#2563eb','#16a34a','#ea580c','#ca8a04','#0891b2','#db2777'];
        $color  = $colors[array_rand($colors)];
        $avatar = mb_strtoupper(mb_substr($name, 0, 1));

        return $this->insert([
            'name'       => $name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'avatar'     => $avatar,
            'color'      => $color,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public function getGroupId(int $userId): ?int {
        $r = $this->qOne('SELECT group_id FROM family_members WHERE user_id = ? LIMIT 1', [$userId]);
        return $r ? (int) $r['group_id'] : null;
    }

    public function getGroupMembers(int $groupId): array {
        return $this->q(
            'SELECT u.id, u.name, u.email, u.avatar, u.color FROM users u
             JOIN family_members fm ON fm.user_id = u.id
             WHERE fm.group_id = ? ORDER BY u.name',
            [$groupId]
        );
    }

    public function updateProfile(int $id, array $data): bool {
        return $this->updateById($id, $data);
    }
}
