<?php
namespace App\Models;

use App\Core\Model;

class Category extends Model {
    protected string $table = 'categories';

    public function forGroup(int $groupId): array {
        return $this->findAll(['group_id' => $groupId], 'name ASC');
    }

    public function create(int $groupId, string $name, string $color, string $icon = '📅'): int {
        return $this->insert([
            'group_id'   => $groupId,
            'name'       => $name,
            'color'      => $color,
            'icon'       => $icon,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function defaults(): array {
        return [
            ['name' => 'Personal',   'color' => '#7c3aed', 'icon' => '👤'],
            ['name' => 'Trabajo',    'color' => '#2563eb', 'icon' => '💼'],
            ['name' => 'Salud',      'color' => '#16a34a', 'icon' => '❤️'],
            ['name' => 'Familia',    'color' => '#ea580c', 'icon' => '🏠'],
            ['name' => 'Social',     'color' => '#ca8a04', 'icon' => '🎉'],
            ['name' => 'Educación',  'color' => '#0891b2', 'icon' => '📚'],
        ];
    }
}
