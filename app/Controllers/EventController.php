<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;

class EventController extends Controller {
    private Event $events;

    public function __construct() {
        parent::__construct();
        $this->events = new Event();
    }

    public function apiIndex(array $p = []): void {
        $this->requireAuth();
        $groupId = $this->auth->groupId();
        $userId  = $this->auth->id();
        $start   = $this->input('start', date('Y-m-01 00:00:00'));
        $end     = $this->input('end',   date('Y-m-t 23:59:59'));

        $rows      = $this->events->forCalendar($groupId, $userId, $start, $end);
        $formatted = [];
        foreach ($rows as $row) {
            $fc = $this->toFC($row, $userId);
            if ($fc !== null) $formatted[] = $fc;
        }
        $this->json($formatted);
    }

    public function apiShow(array $p = []): void {
        $this->requireAuth();
        $userId  = $this->auth->id();
        $groupId = $this->auth->groupId();
        $eventId = (int)$p['id'];

        $ev = $this->events->withParticipants($eventId);
        if (!$ev || $ev['group_id'] != $groupId) $this->json(['error' => 'No encontrado'], 404);

        if ($ev['visibility'] === 'private' && $ev['creator_id'] != $userId) {
            $this->json(['error' => 'Sin acceso'], 403);
        }

        if ($ev['visibility'] === 'hybrid' && $ev['creator_id'] != $userId) {
            $ev['title']       = 'Reservado';
            $ev['description'] = null;
            $ev['location']    = null;
        }

        $this->json($ev);
    }

    public function apiCreate(array $p = []): void {
        $this->requireAuth();
        $data    = $this->body();
        $userId  = $this->auth->id();
        $groupId = $this->auth->groupId();

        if (empty($data['title']) || empty($data['start_datetime'])) {
            $this->json(['error' => 'Título y fecha de inicio son requeridos'], 422);
        }

        $rule = null;
        if (!empty($data['recurrence_rule']) && is_array($data['recurrence_rule'])) {
            $rule = json_encode($data['recurrence_rule']);
        }

        $eventData = [
            'group_id'        => $groupId,
            'creator_id'      => $userId,
            'title'           => $data['title'],
            'description'     => $data['description']    ?? null,
            'location'        => $data['location']       ?? null,
            'category_id'     => $data['category_id']    ?? null,
            'visibility'      => $data['visibility']     ?? 'public',
            'color'           => $data['color']          ?? null,
            'start_datetime'  => $data['start_datetime'],
            'end_datetime'    => $data['end_datetime']   ?? $data['start_datetime'],
            'all_day'         => (int)($data['all_day'] ?? 0),
            'is_recurring'    => (int)($data['is_recurring'] ?? 0),
            'recurrence_type' => $data['recurrence_type'] ?? null,
            'recurrence_rule' => $rule,
            'recurrence_end'  => $data['recurrence_end'] ?? null,
        ];

        $eventId = $this->events->create($eventData);
        $participants = $data['participants'] ?? [$userId];
        if (!in_array($userId, $participants)) $participants[] = $userId;
        $this->events->setParticipants($eventId, $participants);

        $created = $this->events->withParticipants($eventId);
        $this->json(['success' => true, 'event' => $this->toFC($created, $userId)]);
    }

    public function apiUpdate(array $p = []): void {
        $this->requireAuth();
        $eventId = (int)$p['id'];
        $groupId = $this->auth->groupId();
        $userId  = $this->auth->id();

        if (!$this->events->belongsToGroup($eventId, $groupId)) {
            $this->json(['error' => 'No encontrado'], 404);
        }

        $data      = $this->body();
        $allowed   = ['title','description','location','category_id','visibility','color',
                      'start_datetime','end_datetime','all_day','is_recurring',
                      'recurrence_type','recurrence_end'];
        $updateData = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) $updateData[$f] = $data[$f];
        }
        if (!empty($data['recurrence_rule']) && is_array($data['recurrence_rule'])) {
            $updateData['recurrence_rule'] = json_encode($data['recurrence_rule']);
        }
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $this->events->updateById($eventId, $updateData);

        if (isset($data['participants'])) {
            $parts = $data['participants'];
            if (!in_array($userId, $parts)) $parts[] = $userId;
            $this->events->setParticipants($eventId, $parts);
        }

        $updated = $this->events->withParticipants($eventId);
        $this->json(['success' => true, 'event' => $this->toFC($updated, $userId)]);
    }

    public function apiDelete(array $p = []): void {
        $this->requireAuth();
        $eventId = (int)$p['id'];
        $groupId = $this->auth->groupId();

        if (!$this->events->belongsToGroup($eventId, $groupId)) {
            $this->json(['error' => 'No encontrado'], 404);
        }

        $this->events->deleteById($eventId);
        $this->json(['success' => true]);
    }

    private function toFC(array $ev, int $userId): ?array {
        if ($ev['visibility'] === 'private' && $ev['creator_id'] != $userId) return null;

        $isOwner  = $ev['creator_id'] == $userId;
        $isHybrid = $ev['visibility'] === 'hybrid' && !$isOwner;
        $color    = $ev['color'] ?? $ev['category_color'] ?? '#7c3aed';

        return [
            'id'              => $ev['id'] . ($ev['instance_date'] ?? ''),
            'event_id'        => $ev['id'],
            'title'           => $isHybrid ? 'Reservado' : $ev['title'],
            'start'           => $ev['start_datetime'],
            'end'             => $ev['end_datetime'],
            'allDay'          => (bool)$ev['all_day'],
            'backgroundColor' => $isHybrid ? '#3a3a5c' : $color,
            'borderColor'     => $isHybrid ? '#4a4a70' : $color,
            'textColor'       => '#ffffff',
            'classNames'      => $isHybrid ? ['fc-hybrid'] : [],
            'extendedProps'   => [
                'event_id'       => $ev['id'],
                'description'    => $isHybrid ? null : ($ev['description'] ?? null),
                'location'       => $isHybrid ? null : ($ev['location'] ?? null),
                'category_id'    => $ev['category_id'] ?? null,
                'category_name'  => $ev['category_name'] ?? null,
                'category_color' => $ev['category_color'] ?? null,
                'visibility'     => $ev['visibility'],
                'is_recurring'   => (bool)$ev['is_recurring'],
                'recurrence_type'=> $ev['recurrence_type'] ?? null,
                'creator_name'   => $ev['creator_name'] ?? null,
                'creator_avatar' => $ev['creator_avatar'] ?? null,
                'creator_color'  => $ev['creator_color'] ?? null,
                'is_owner'       => $isOwner,
                'is_hybrid'      => $isHybrid,
                'participants'   => $ev['participants'] ?? [],
                'instance_date'  => $ev['instance_date'] ?? null,
            ],
        ];
    }
}
