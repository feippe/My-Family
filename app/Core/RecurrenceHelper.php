<?php
namespace App\Core;

class RecurrenceHelper {
    public static function expand(array $event, string $rangeStart, string $rangeEnd, array $exceptions = []): array {
        if (!$event['is_recurring'] || empty($event['recurrence_rule'])) return [$event];

        $rule     = is_string($event['recurrence_rule']) ? json_decode($event['recurrence_rule'], true) : $event['recurrence_rule'];
        $type     = $rule['type'] ?? $event['recurrence_type'] ?? 'weekly';
        $origStart= new \DateTime($event['start_datetime']);
        $origEnd  = new \DateTime($event['end_datetime']);
        $duration = $origStart->diff($origEnd);

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

        // Apply exceptions
        $result = [];
        foreach ($instances as $inst) {
            $date = substr($inst['start_datetime'], 0, 10);
            if (isset($exceptions[$date])) {
                $ex = $exceptions[$date];
                if ($ex['is_deleted']) continue; // this occurrence deleted
                // Apply overrides
                foreach (['new_title','new_description','new_start','new_end','new_location','new_category_id','new_visibility','new_color'] as $field) {
                    if ($ex[$field] !== null) {
                        $target = str_replace('new_', '', $field);
                        if ($field === 'new_start') $inst['start_datetime'] = $ex[$field];
                        elseif ($field === 'new_end')  $inst['end_datetime']   = $ex[$field];
                        else $inst[$target] = $ex[$field];
                    }
                }
                $inst['has_exception'] = true;
            }
            $result[] = $inst;
        }

        return $result;
    }

    private static function weekly(array $ev, array $rule, \DateTime $orig, \DateTime $rS, \DateTime $rE, \DateTime $recEnd, \DateInterval $dur): array {
        $days    = $rule['days'] ?? [strtolower($orig->format('l'))];
        $dayMap  = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $targets = array_map(fn($d) => $dayMap[$d] ?? 0, $days);

        $instances = [];
        $cur = clone $rS;
        $cur->setTime((int)$orig->format('H'), (int)$orig->format('i'), 0);

        while ($cur <= $rE && $cur <= $recEnd) {
            if (in_array((int)$cur->format('w'), $targets) && $cur >= $orig) {
                $instances[] = self::makeInstance($ev, clone $cur, $dur);
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
                $day = min($day, (int)(new \DateTime("$year-$month-01"))->format('t'));
                $candidate = new \DateTime(sprintf('%04d-%02d-%02d %s', $year, $month, $day, $orig->format('H:i:s')));
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
                $candidate = new \DateTime(sprintf('%04d-%02d-%02d %s', $year, $month, $day, $orig->format('H:i:s')));
            } else {
                $candidate = self::nthWeekday($year, $month, $rule['weekday'] ?? 'monday', (int)($rule['occurrence'] ?? 1));
                if ($candidate) $candidate->setTime((int)$orig->format('H'), (int)$orig->format('i'), 0);
            }
            if ($candidate && $candidate >= $rS && $candidate <= $rE && $candidate <= $recEnd && $candidate >= $orig) {
                $instances[] = self::makeInstance($ev, $candidate, $dur);
            }
        }
        return $instances;
    }

    private static function nthWeekday(int $year, int $month, string $weekday, int $n): ?\DateTime {
        $dayNames = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $target   = $dayNames[$weekday] ?? 1;

        if ($n === -1) {
            $last = new \DateTime("last day of $year-$month");
            while ((int)$last->format('w') !== $target) $last->modify('-1 day');
            return $last;
        }

        $d = new \DateTime("$year-$month-01");
        $end = new \DateTime("$year-$month-01");
        $end->modify('+1 month');
        $count = 0;
        while ($d < $end) {
            if ((int)$d->format('w') === $target && ++$count === $n) return clone $d;
            $d->modify('+1 day');
        }
        return null;
    }

    private static function makeInstance(array $ev, \DateTime $start, \DateInterval $dur): array {
        $end  = clone $start;
        $end->add($dur);
        $inst = $ev;
        $inst['start_datetime'] = $start->format('Y-m-d H:i:s');
        $inst['end_datetime']   = $end->format('Y-m-d H:i:s');
        $inst['instance_date']  = $start->format('Y-m-d');
        return $inst;
    }
}
