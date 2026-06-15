<?php
namespace App\Services;

use App\Core\RecurrenceHelper;
use App\Models\Event;
use App\Models\EventException;
use App\Models\EventReminder;

/**
 * Finds event occurrences that are due for a reminder and pushes them.
 *
 * Two reminders per occurrence: 24 hours before and 30 minutes before the
 * scheduled start. A small grace window absorbs delayed/missed cron runs;
 * a dedup table (claimed atomically) guarantees each reminder fires once.
 */
class ReminderService {
    /** Seconds before start at which each reminder fires. */
    private const OFFSETS = ['24h' => 86400, '30m' => 1800];

    /** How late a reminder may still fire after its scheduled moment. */
    private const GRACE = 3600;

    private Event               $events;
    private EventException      $exceptions;
    private EventReminder       $sent;
    private NotificationService $notifier;

    public function __construct() {
        $this->events     = new Event();
        $this->exceptions = new EventException();
        $this->sent       = new EventReminder();
        $this->notifier   = new NotificationService();
    }

    /** @return array{checked:int,sent:int} */
    public function run(?\DateTime $now = null): array {
        $now  = $now ?: new \DateTime();
        $from = $now->format('Y-m-d H:i:s');
        // Widest horizon we ever need is the 24h reminder, +1h of grace.
        $to   = (clone $now)->modify('+25 hours')->format('Y-m-d H:i:s');

        $checked = 0;
        $pushed  = 0;

        foreach ($this->collectOccurrences($from, $to) as $occ) {
            $checked++;
            $start = new \DateTime($occ['start_datetime']);

            foreach (self::OFFSETS as $kind => $offset) {
                $target = (clone $start)->modify("-{$offset} seconds");

                // Not yet due, or the event already started → skip.
                if ($target > $now || $start <= $now) continue;
                // Too stale (cron was down longer than the grace window) → skip.
                if (($now->getTimestamp() - $target->getTimestamp()) > self::GRACE) continue;

                $occDate = $start->format('Y-m-d');
                if (!$this->sent->claim((int)$occ['event_id'], $occDate, $kind)) continue;

                $participants = $this->events->getParticipantUsers((int)$occ['event_id']);
                if (!$participants) continue;

                $this->notifier->eventReminder($occ, $participants);
                $pushed++;
            }
        }

        return ['checked' => $checked, 'sent' => $pushed];
    }

    /** Flatten non-recurring rows + expanded recurring instances into occurrences. */
    private function collectOccurrences(string $from, string $to): array {
        $out = [];

        foreach ($this->events->upcomingNonRecurring($from, $to) as $e) {
            $e['event_id'] = $e['id'];
            $out[] = $e;
        }

        foreach ($this->events->allRecurring() as $e) {
            $ex = $this->exceptions->forEvent((int)$e['id']);
            foreach (RecurrenceHelper::expand($e, $from, $to, $ex) as $inst) {
                // Keep the parent event id for participants + tap-to-open URL.
                $inst['event_id'] = $e['id'];
                $out[] = $inst;
            }
        }

        return $out;
    }
}
