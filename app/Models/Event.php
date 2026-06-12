<?php
namespace App\Models;

use App\Core\Model;
use App\Core\RecurrenceHelper;

class Event extends Model {
    protected string $table = 'events';

    public function forCalendar(int $groupId, int $userId, string $start, string $end): array {
        $sql = 'SELECT e.*, c.name AS category_name, c.color AS category_color,
                       u.name AS creator_name, u.avatar AS creator_avatar, u.color AS creator_color
                FROM events e
                LEFT JOIN categories c ON c.id = e.category_id
                LEFT JOIN users u ON u.id = e.creator_id
                WHERE e.group_id = ?
                  AND (e.visibility != \'private\' OR e.creator_id = ?)
                  AND (
                    (e.start_datetime BETWEEN ? AND ?)
                    OR (e.end_datetime  BETWEEN ? AND ?)
                    OR e.is_recurring = 1
                  )
                ORDER BY e.start_datetime';

        $rows   = $this->q($sql, [$groupId, $userId, $start, $end, $start, $end]);
        $exModel = new EventException();
        $result  = [];

        foreach ($rows as $row) {
            if ($row['is_recurring']) {
                $exceptions = $exModel->forEvent($row['id']);
                $instances  = RecurrenceHelper::expand($row, $start, $end, $exceptions);
                foreach ($instances as $inst) $result[] = $inst;
            } else {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function withParticipants(int $id): ?array {
        $ev = $this->findById($id);
        if (!$ev) return null;
        $ev['participants'] = $this->q(
            'SELECT u.id, u.name, u.avatar, u.color, u.email FROM users u
             JOIN event_participants ep ON ep.user_id = u.id
             WHERE ep.event_id = ?',
            [$id]
        );
        return $ev;
    }

    public function create(array $data): int {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->insert($data);
    }

    public function setParticipants(int $eventId, array $userIds): void {
        $this->exec('DELETE FROM event_participants WHERE event_id = ?', [$eventId]);
        foreach (array_unique($userIds) as $uid) {
            $this->exec('INSERT IGNORE INTO event_participants (event_id, user_id) VALUES (?, ?)', [$eventId, (int)$uid]);
        }
    }

    public function getParticipantUsers(int $eventId): array {
        return $this->q(
            'SELECT u.id, u.name, u.email, u.avatar, u.color FROM users u
             JOIN event_participants ep ON ep.user_id = u.id
             WHERE ep.event_id = ?',
            [$eventId]
        );
    }

    public function belongsToGroup(int $eventId, int $groupId): bool {
        return $this->qOne('SELECT id FROM events WHERE id = ? AND group_id = ?', [$eventId, $groupId]) !== null;
    }
}
