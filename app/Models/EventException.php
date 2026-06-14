<?php
namespace App\Models;

use App\Core\Model;

class EventException extends Model {
    protected string $table = 'event_exceptions';

    public function forEvent(int $eventId): array {
        $rows = $this->q('SELECT * FROM event_exceptions WHERE event_id = ?', [$eventId]);
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['exception_date']] = $r;
        }
        return $map;
    }

    public function upsert(int $eventId, string $exceptionDate, array $overrides = [], bool $deleted = false): void {
        $existing = $this->qOne(
            'SELECT id FROM event_exceptions WHERE event_id = ? AND exception_date = ?',
            [$eventId, $exceptionDate]
        );

        $data = array_merge([
            'event_id'        => $eventId,
            'exception_date'  => $exceptionDate,
            'is_deleted'      => $deleted ? 1 : 0,
        ], $overrides);

        if ($existing) {
            unset($data['event_id'], $data['exception_date']);
            $this->updateById($existing['id'], $data);
        } else {
            $this->insert($data);
        }
    }

    public function markDeleted(int $eventId, string $exceptionDate): void {
        $this->upsert($eventId, $exceptionDate, [], true);
    }
}
