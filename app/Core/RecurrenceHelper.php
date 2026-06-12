<?php
namespace App\Core;

class RecurrenceHelper {
    public static function expand(array $event, string $rangeStart, string $rangeEnd): array {
        if (!$event['is_recurring'] || empty($event['recurrence_rule'])) return [$event];

        $rule     = is_string($event['recurrence_rule']) ? json_decode($event['recurrence_rule'], true) : $event['recurrence_rule'];
        $type     = $rule['type'] ?? $event['recurrence_type'] ?? 'weekly';
        $origStart = new \DateTime($event['start_datetime']);
        $origEnd   = new \DateTime($event['end_datetime']);
        $duration  = $origStart->diff($origEnd);

        $rStart = new \DateTime($rangeStart);
        $rEnd   = new \DateTime($rangeEnd);
        $recEnd = !empty($event['recurrence_end']) ? new \DateTime($event['recurrence_end']) : clone $rEnd;

        $instances = [];

        switch ($type) {
            case 'weekly':
                $instances = self::weekly($event, $rule, $origStart, $rStart, $rEnd, $recEnd, $duration);
                break;
            case 'monthly':
                $instances = self::monthly($event, $rule, $origStart, $rStart, $rEnd, $recEnd, $duration);
                break;
            case 'annual':
            case 'yearly':
                $instances = self::annual($event, $rule, $origStart, $rStart, $rEnd, $recEnd, $duration);
                break;
        }

        return $instances;
    }

    private static function weekly(array $ev, array $rule, \DateTime $orig, \DateTime $rS, \DateTime $rE, \DateTime $recEnd, \DateInterval $dur): array {
        $days = $rule['days'] ?? [strtolower($orig->format('l'))];
        $dayMap = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $targetDays = array_map(fn($d) => $dayMap[$d] ?? 0, $days);

        $instances = [];
        $cur = clone $rS;
        $cur->setTime((int)$orig->format('H'), (int)$orig->format('i'), 0);

        while ($cur <= $rE && $cur <= $recEnd) {
            if (in_array((int)$cur->format('w'), $targetDays)) {
                if ($cur >= $orig) {
                    $instances[] = self::makeInstance($ev, clone $cur, $dur);
                }
            }
            $cur->modify('+1 day');
        }
        return $instances;
    }

    private static function monthly(array $ev, array $rule, \DateTime $orig, \DateTime $rS, \DateTime $rE, \DateTime $recEnd, \DateInterval $dur): array {
        $mode = $rule['mode'] ?? 'day_of_month';
        $instances = [];

        $cur = new \DateTime($rS->format('Y-m-01'));
        while ($cur <= $rE && $cur <= $recEnd) {
            $year  = (int)$cur->format('Y');
            $month = (int)$cur->format('n');

            if ($mode === 'day_of_month') {
                $day = $rule['day'] ?? (int)$orig->format('j');
                $maxDay = (int)(new \DateTime("$year-$month-01"))->format('t');
                $day = min($day, $maxDay);
                $candidate = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
                $candidate->setTime((int)$orig->format('H'), (int)$orig->format('i'), 0);
            } else {
                $candidate = self::nthWeekday($year, $month, $rule['weekday'] ?? 'friday', (int)($rule['occurrence'] ?? 1));
                if ($candidate) $candidate->setTime((int)$orig->format('H'), (int)$orig->format('i'), 0);
            }

            if ($candidate && $candidate >= $rS && $candidate <= $rE && $candidate <= $recEnd && $candidate >= $orig) {
                $instances[] = self::makeInstance($ev, $candidate, $dur);
            }

            $cur->modify('+1 month');
        }
        return $instances;
    }

    private static function annual(array $ev, array $rule, \DateTime $orig, \DateTime $rS, \DateTime $rE, \DateTime $recEnd, \DateInterval $dur): array {
        $mode  = $rule['mode'] ?? 'fixed_date';
        $month = $rule['month'] ?? (int)$orig->format('n');
        $instances = [];

        for ($year = (int)$rS->format('Y'); $year <= (int)$rE->format('Y'); $year++) {
            if ($mode === 'fixed_date') {
                $day = $rule['day'] ?? (int)$orig->format('j');
                $candidate = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
            } else {
                $candidate = self::nthWeekday($year, $month, $rule['weekday'] ?? 'monday', (int)($rule['occurrence'] ?? 1));
            }
            if ($candidate) {
                $candidate->setTime((int)$orig->format('H'), (int)$orig->format('i'), 0);
                if ($candidate >= $rS && $candidate <= $rE && $candidate <= $recEnd && $candidate >= $orig) {
                    $instances[] = self::makeInstance($ev, $candidate, $dur);
                }
            }
        }
        return $instances;
    }

    private static function nthWeekday(int $year, int $month, string $weekday, int $n): ?\DateTime {
        $dayNames = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $target = $dayNames[$weekday] ?? 1;

        if ($n === -1) {
            $last = new \DateTime("last day of $year-$month");
            while ((int)$last->format('w') !== $target) $last->modify('-1 day');
            return $last;
        }

        $d = new \DateTime("$year-$month-01");
        $count = 0;
        $end = new \DateTime("$year-$month-01");
        $end->modify('+1 month');
        while ($d < $end) {
            if ((int)$d->format('w') === $target) {
                $count++;
                if ($count === $n) return clone $d;
            }
            $d->modify('+1 day');
        }
        return null;
    }

    private static function makeInstance(array $ev, \DateTime $start, \DateInterval $dur): array {
        $end = clone $start;
        $end->add($dur);
        $inst = $ev;
        $inst['start_datetime'] = $start->format('Y-m-d H:i:s');
        $inst['end_datetime']   = $end->format('Y-m-d H:i:s');
        $inst['instance_date']  = $start->format('Y-m-d');
        return $inst;
    }
}
