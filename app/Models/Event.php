<?php
namespace App\Models;

use App\Core\Model;
use App\Core\RecurrenceHelper;

class Event extends Model {
    protected string $table = 'events';

    public function forCalendar(int $groupId, int $userId, string $start, string $end): array {
        // Private events are only visible to their participants; everything
        // else (public / hybrid "ocupado") is visible to the whole group.
        $sql = 'SELECT e.*, c.name AS category_name, c.color AS category_color,
                       u.name AS creator_name, u.avatar AS creator_avatar, u.color AS creator_color
                FROM events e
                LEFT JOIN categories c ON c.id = e.category_id
                LEFT JOIN users u ON u.id = e.creator_id
                WHERE e.group_id = ?
                  AND (
                    e.visibility <> \'private\'
                    OR EXISTS (SELECT 1 FROM event_participants ep
                               WHERE ep.event_id = e.id AND ep.user_id = ?)
                  )
                  AND (
                    (e.start_datetime BETWEEN ? AND ?)
                    OR (e.end_datetime  BETWEEN ? AND ?)
                    OR e.is_recurring = 1
                  )
                ORDER BY e.start_datetime';

        $rows   = $this->q($sql, [$groupId, $userId, $start, $end, $start, $end]);
        $this->attachParticipants($rows);

        $exModel = new EventException();
        $result  = [];

        foreach ($rows as $row) {
            if ($row['is_recurring']) {
                $exceptions = $exModel->forEvent($row['id']);
                $instances  = RecurrenceHelper::expand($row, $start, $end, $exceptions);
                foreach ($instances as $inst) $result[] = $inst; // inherits participants
            } else {
                $result[] = $row;
            }
        }

        return $result;
    }

    /** Attach a `participants` array (id, name, avatar, color) to each row in one query. */
    private function attachParticipants(array &$rows): void {
        if (!$rows) return;
        $ids = array_column($rows, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $parts = $this->q(
            "SELECT ep.event_id, u.id, u.name, u.avatar, u.color
             FROM event_participants ep
             JOIN users u ON u.id = ep.user_id
             WHERE ep.event_id IN ($in)
             ORDER BY u.name",
            $ids
        );
        $byEvent = [];
        foreach ($parts as $p) {
            $byEvent[$p['event_id']][] = [
                'id'     => (int)$p['id'],
                'name'   => $p['name'],
                'avatar' => $p['avatar'],
                'color'  => $p['color'],
            ];
        }
        foreach ($rows as &$r) {
            $r['participants'] = $byEvent[$r['id']] ?? [];
        }
    }

    /** True if the user is a participant of the event. */
    public function isParticipant(int $eventId, int $userId): bool {
        return $this->qOne(
            'SELECT 1 FROM event_participants WHERE event_id = ? AND user_id = ?',
            [$eventId, $userId]
        ) !== null;
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

    /** Non-recurring events starting within a window (for the reminder runner). */
    public function upcomingNonRecurring(string $from, string $to): array {
        return $this->q(
            'SELECT * FROM events
             WHERE is_recurring = 0 AND start_datetime BETWEEN ? AND ?
             ORDER BY start_datetime',
            [$from, $to]
        );
    }

    /** All recurring events; the runner expands their upcoming occurrences. */
    public function allRecurring(): array {
        return $this->findAll(['is_recurring' => 1]);
    }
}
