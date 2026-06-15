<?php
namespace App\Models;

use App\Core\Model;

class EventReminder extends Model {
    protected string $table = 'event_reminders';

    /**
     * Atomically reserve a reminder for an event occurrence + kind.
     * Returns true only the first time (INSERT IGNORE → rowCount 1), so two
     * overlapping cron runs can never send the same reminder twice.
     */
    public function claim(int $eventId, string $occurrenceDate, string $kind): bool {
        $st = $this->db->prepare(
            'INSERT IGNORE INTO event_reminders (event_id, occurrence_date, kind, sent_at)
             VALUES (?, ?, ?, ?)'
        );
        $st->execute([$eventId, $occurrenceDate, $kind, date('Y-m-d H:i:s')]);
        return $st->rowCount() === 1;
    }
}
