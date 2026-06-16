<?php
namespace App\Models;

use App\Core\Model;

class ExternalCalendar extends Model {
    protected string $table = 'external_calendars';

    public function forGroup(int $groupId): array {
        return $this->q(
            'SELECT * FROM external_calendars WHERE group_id = ? ORDER BY name',
            [$groupId]
        );
    }

    public function activeForGroup(int $groupId): array {
        return $this->q(
            'SELECT * FROM external_calendars WHERE group_id = ? AND is_active = 1 ORDER BY name',
            [$groupId]
        );
    }

    public function create(int $groupId, string $name, string $url, string $color): int {
        return $this->insert([
            'group_id'   => $groupId,
            'name'       => $name,
            'url'        => $url,
            'color'      => $color,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function belongsToGroup(int $id, int $groupId): bool {
        return $this->qOne(
            'SELECT id FROM external_calendars WHERE id = ? AND group_id = ?',
            [$id, $groupId]
        ) !== null;
    }
}
