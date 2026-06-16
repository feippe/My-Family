<?php
namespace App\Core;

/**
 * Minimal iCal (RFC 5545) fetcher and parser.
 *
 * Handles VEVENT blocks: DTSTART/DTEND (with TZID and VALUE=DATE),
 * RRULE expansion (DAILY/WEEKLY/MONTHLY/YEARLY + BYDAY/COUNT/UNTIL/INTERVAL),
 * EXDATE, and line unfolding. Returns FullCalendar-compatible event arrays.
 *
 * Events are read-only by design: no notifications, no editing.
 */
class ICalParser {

    /** Fetch raw iCal content from a URL (curl with file_get_contents fallback). */
    public static function fetch(string $url): string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Familia-Calendar/1.0',
            ]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $body === '') throw new \RuntimeException("iCal fetch error: $err");
            if ($code >= 400) throw new \RuntimeException("iCal HTTP $code");
            return $body;
        }
        $ctx  = stream_context_create(['http' => [
            'timeout'    => 10,
            'user_agent' => 'Familia-Calendar/1.0',
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) throw new \RuntimeException("iCal fetch failed");
        return $body;
    }

    /**
     * Parse iCal text and return FullCalendar-compatible event objects
     * filtered to [$windowStart, $windowEnd] (ISO or 'Y-m-d H:i:s' strings).
     */
    public static function parse(
        string $ical,
        string $windowStart,
        string $windowEnd,
        string $color,
        string $calName,
        int    $calId
    ): array {
        $winStart = new \DateTime($windowStart);
        $winEnd   = new \DateTime($windowEnd);

        $vevents = self::extractVEvents(self::unfold($ical));
        $result  = [];

        foreach ($vevents as $ev) {
            // Skip cancelled events
            if (($ev['STATUS'] ?? '') === 'CANCELLED') continue;

            if (!empty($ev['RRULE'])) {
                foreach (self::expandRRule($ev, $winStart, $winEnd) as $inst) {
                    $result[] = self::toFC($inst, $color, $calName, $calId);
                }
            } else {
                $dtStart = self::parseDate($ev['DTSTART'] ?? null, $ev['DTSTART_TZID'] ?? null);
                if (!$dtStart) continue;

                $dtEnd = self::parseDate(
                    $ev['DTEND'] ?? ($ev['DTSTART'] ?? null),
                    $ev['DTEND_TZID'] ?? ($ev['DTSTART_TZID'] ?? null)
                );
                if (!$dtEnd) $dtEnd = clone $dtStart;

                // Apply DURATION if no DTEND
                if (empty($ev['DTEND']) && !empty($ev['DURATION'])) {
                    $dtEnd = clone $dtStart;
                    $dtEnd->add(new \DateInterval($ev['DURATION']));
                }

                if ($dtEnd < $winStart || $dtStart > $winEnd) continue;

                $ev['_start'] = $dtStart;
                $ev['_end']   = $dtEnd;
                $result[] = self::toFC($ev, $color, $calName, $calId);
            }
        }

        return $result;
    }

    // ── Private helpers ───────────────────────────────────

    /** Unfold continuation lines (RFC 5545 §3.1) and split into lines. */
    private static function unfold(string $ical): array {
        $ical = str_replace(["\r\n", "\r"], "\n", $ical);
        $ical = preg_replace('/\n[ \t]/', '', $ical);
        return explode("\n", $ical);
    }

    /** Extract all VEVENT blocks as property arrays. */
    private static function extractVEvents(array $lines): array {
        $events  = [];
        $current = null;

        foreach ($lines as $raw) {
            $line = rtrim($raw);
            if ($line === 'BEGIN:VEVENT') { $current = []; continue; }
            if ($line === 'END:VEVENT')   { if ($current !== null) $events[] = $current; $current = null; continue; }
            if ($current === null) continue;

            $colon = strpos($line, ':');
            if ($colon === false) continue;

            $namePart = substr($line, 0, $colon);
            $value    = substr($line, $colon + 1);
            $segments = explode(';', $namePart);
            $name     = strtoupper(array_shift($segments));

            $params = [];
            foreach ($segments as $seg) {
                if (str_contains($seg, '=')) {
                    [$pk, $pv] = explode('=', $seg, 2);
                    $params[strtoupper($pk)] = $pv;
                }
            }

            match ($name) {
                'DTSTART'     => [$current['DTSTART']      = $value,
                                  $current['DTSTART_TZID'] = $params['TZID'] ?? null,
                                  $current['DTSTART_DATE'] = ($params['VALUE'] ?? '') === 'DATE'],
                'DTEND'       => [$current['DTEND']        = $value,
                                  $current['DTEND_TZID']   = $params['TZID'] ?? null],
                'DURATION'    => $current['DURATION']    = $value,
                'SUMMARY'     => $current['SUMMARY']     = self::unescape($value),
                'DESCRIPTION' => $current['DESCRIPTION'] = self::unescape($value),
                'LOCATION'    => $current['LOCATION']    = self::unescape($value),
                'STATUS'      => $current['STATUS']      = strtoupper($value),
                'UID'         => $current['UID']         = $value,
                'RRULE'       => $current['RRULE']       = self::parseRRule($value),
                'EXDATE'      => $current['EXDATE'][]    = $value,
                default       => null,
            };
        }

        return $events;
    }

    /** Parse a date/datetime string with optional TZID into a DateTime object. */
    private static function parseDate(?string $value, ?string $tzid): ?\DateTime {
        if (!$value) return null;
        $value = trim($value);
        try {
            // UTC: ends with Z
            if (str_ends_with($value, 'Z')) {
                return new \DateTime(substr($value, 0, -1), new \DateTimeZone('UTC'));
            }
            // DATE-only (all-day): 8 digits
            if (strlen($value) === 8 && ctype_digit($value)) {
                return \DateTime::createFromFormat('Ymd', $value) ?: null;
            }
            // Datetime with TZID
            $tz = null;
            if ($tzid) {
                try { $tz = new \DateTimeZone($tzid); }
                catch (\Throwable) { $tz = new \DateTimeZone('UTC'); }
            }
            $dt = \DateTime::createFromFormat('Ymd\THis', $value, $tz);
            return $dt ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Parse RRULE value string into an associative array. */
    private static function parseRRule(string $rrule): array {
        $out = [];
        foreach (explode(';', $rrule) as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $out[strtoupper($k)] = $v;
            }
        }
        return $out;
    }

    /** Expand a recurring VEVENT into instances within [winStart, winEnd]. */
    private static function expandRRule(array $ev, \DateTime $winStart, \DateTime $winEnd): array {
        $rrule    = $ev['RRULE'];
        $freq     = strtoupper($rrule['FREQ'] ?? '');
        $interval = max(1, (int)($rrule['INTERVAL'] ?? 1));
        $maxCount = isset($rrule['COUNT']) ? (int)$rrule['COUNT'] : PHP_INT_MAX;

        $dtStart = self::parseDate($ev['DTSTART'], $ev['DTSTART_TZID'] ?? null);
        $dtEnd   = self::parseDate($ev['DTEND'] ?? $ev['DTSTART'], $ev['DTEND_TZID'] ?? ($ev['DTSTART_TZID'] ?? null));
        if (!$dtStart) return [];
        if (!$dtEnd)   $dtEnd = clone $dtStart;

        $duration = $dtEnd->getTimestamp() - $dtStart->getTimestamp();

        $until = null;
        if (!empty($rrule['UNTIL'])) {
            $until = self::parseDate($rrule['UNTIL'], null);
        }

        // Build EXDATE lookup keyed by Ymd
        $exDates = [];
        foreach ($ev['EXDATE'] ?? [] as $exd) {
            foreach (explode(',', $exd) as $single) {
                $exDt = self::parseDate(trim($single), $ev['DTSTART_TZID'] ?? null);
                if ($exDt) $exDates[$exDt->format('Ymd')] = true;
            }
        }

        $result = [];
        $n      = 0;       // total occurrences generated (for COUNT)

        if ($freq === 'WEEKLY' && !empty($rrule['BYDAY'])) {
            // iCal day codes → PHP date('N') values (1=Mon … 7=Sun)
            static $icalToN = ['MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6,'SU'=>7];
            $byDayN = [];
            foreach (explode(',', $rrule['BYDAY']) as $d) {
                $code = strtoupper(preg_replace('/^[-+]?\d+/', '', trim($d)));
                if (isset($icalToN[$code])) $byDayN[] = $icalToN[$code];
            }
            sort($byDayN);

            // Find Monday of the week containing dtStart (PHP N: 1=Mon)
            $dtN     = (int)$dtStart->format('N');
            $weekMon = (clone $dtStart)->modify('-' . ($dtN - 1) . ' days');
            $weekMon->setTime(
                (int)$dtStart->format('H'),
                (int)$dtStart->format('i'),
                (int)$dtStart->format('s')
            );

            $cur    = clone $weekMon;
            $safety = 600;  // max weeks to iterate

            while ($safety-- > 0 && $n < $maxCount) {
                if ($until && $cur > $until) break;
                if ($cur > $winEnd) break;

                foreach ($byDayN as $dayN) {
                    $occ = (clone $cur)->modify('+' . ($dayN - 1) . ' days');
                    if ($occ < $dtStart) continue;
                    if ($until && $occ > $until) { $n = $maxCount; break; }
                    if (isset($exDates[$occ->format('Ymd')])) { $n++; continue; }
                    $n++;
                    if ($n > $maxCount) break;
                    if ($occ >= $winStart && $occ <= $winEnd) {
                        $endOcc = (clone $occ)->setTimestamp($occ->getTimestamp() + $duration);
                        $inst            = $ev;
                        $inst['_start']  = $occ;
                        $inst['_end']    = $endOcc;
                        $result[] = $inst;
                    }
                }

                $cur->modify('+' . ($interval * 7) . ' days');
            }
        } else {
            // Simple: DAILY, WEEKLY (no BYDAY), MONTHLY, YEARLY
            $cur    = clone $dtStart;
            $safety = 5000;

            // Fast-forward to near the window to avoid iterating years of history
            if ($cur < $winStart) {
                $diff = $winStart->getTimestamp() - $cur->getTimestamp();
                $step = match($freq) {
                    'DAILY'  => 86400  * $interval,
                    'WEEKLY' => 604800 * $interval,
                    default  => 0,
                };
                if ($step > 0) {
                    $skip = max(0, (int)floor($diff / $step) - 1);
                    if ($skip > 0 && $n + $skip < $maxCount) {
                        $n += $skip;
                        $cur->modify('+' . ($skip * $interval) . ($freq === 'DAILY' ? ' days' : ' weeks'));
                    }
                }
            }

            while ($safety-- > 0 && $n < $maxCount) {
                if ($until && $cur > $until) break;
                if ($cur > $winEnd) break;

                $ymd = $cur->format('Ymd');
                if (!isset($exDates[$ymd]) && $cur >= $winStart) {
                    $endOcc = (clone $cur)->setTimestamp($cur->getTimestamp() + $duration);
                    $inst            = $ev;
                    $inst['_start']  = clone $cur;
                    $inst['_end']    = $endOcc;
                    $result[] = $inst;
                }
                $n++;

                switch ($freq) {
                    case 'DAILY':   $cur->modify("+{$interval} days");   break;
                    case 'WEEKLY':  $cur->modify("+{$interval} weeks");  break;
                    case 'MONTHLY': $cur->modify("+{$interval} months"); break;
                    case 'YEARLY':  $cur->modify("+{$interval} years");  break;
                    default: $safety = 0;
                }
            }
        }

        return $result;
    }

    /** Format a parsed event as a FullCalendar-compatible array. */
    private static function toFC(array $ev, string $color, string $calName, int $calId): array {
        /** @var \DateTime $start */
        $start  = $ev['_start'];
        /** @var \DateTime $end */
        $end    = $ev['_end'];
        $allDay = $ev['DTSTART_DATE'] ?? false;
        $uid    = $ev['UID'] ?? uniqid('ical_');

        return [
            'id'              => 'ext_' . $calId . '_' . md5($uid . $start->format('YmdHis')),
            'title'           => $ev['SUMMARY'] ?? '(Sin título)',
            'start'           => $allDay ? $start->format('Y-m-d')      : $start->format('Y-m-d\TH:i:s'),
            'end'             => $allDay ? $end->format('Y-m-d')        : $end->format('Y-m-d\TH:i:s'),
            'allDay'          => $allDay,
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'textColor'       => '#ffffff',
            'classNames'      => ['fc-external'],
            'extendedProps'   => [
                'is_external'       => true,
                'can_edit'          => false,
                'is_busy'           => false,
                'show_title'        => true,
                'show_names'        => false,
                'calendar_name'     => $calName,
                'calendar_id'       => $calId,
                'description'       => $ev['DESCRIPTION'] ?? null,
                'location'          => $ev['LOCATION'] ?? null,
                'participant_names'  => [],
                'participant_colors' => [$color],
                'is_recurring'      => isset($ev['RRULE']),
            ],
        ];
    }

    /** Unescape iCal text values. */
    private static function unescape(string $s): string {
        return str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $s);
    }
}
