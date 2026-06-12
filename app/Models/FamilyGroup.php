<?php
namespace App\Models;

use App\Core\Model;

class FamilyGroup extends Model {
    protected string $table = 'family_groups';

    public function createWithAdmin(string $name, int $userId): int {
        $groupId = $this->insert([
            'name'       => $name,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->exec(
            "INSERT INTO family_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'admin', NOW())",
            [$groupId, $userId]
        );
        return $groupId;
    }

    public function getByUser(int $userId): ?array {
        return $this->qOne(
            'SELECT fg.* FROM family_groups fg
             JOIN family_members fm ON fm.group_id = fg.id
             WHERE fm.user_id = ? LIMIT 1',
            [$userId]
        );
    }

    public function addMember(int $groupId, int $userId, string $role = 'member'): void {
        $this->exec(
            "INSERT IGNORE INTO family_members (group_id, user_id, role, joined_at) VALUES (?, ?, ?, NOW())",
            [$groupId, $userId, $role]
        );
    }

    public function removeMember(int $groupId, int $userId): void {
        $this->exec('DELETE FROM family_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
    }
}
