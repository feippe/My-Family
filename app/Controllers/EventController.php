<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\EventException;
use App\Services\NotificationService;

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

        $pids          = array_map(fn($p) => (int)$p['id'], $ev['participants'] ?? []);
        $isParticipant = in_array($userId, $pids, true);
        $vis           = $ev['visibility'];

        // Private: only participants may read it.
        if ($vis === 'private' && !$isParticipant) $this->json(['error' => 'Sin acceso'], 403);

        // Hybrid seen by a non-participant: read-only "Ocupado" + participant names.
        if ($vis === 'hybrid' && !$isParticipant) {
            $this->json([
                'id'              => $ev['id'],
                'title'           => 'Ocupado',
                'busy'            => true,
                'start_datetime'  => $ev['start_datetime'],
                'end_datetime'    => $ev['end_datetime'],
                'participant_names' => array_map(fn($p) => $p['name'], $ev['participants'] ?? []),
            ]);
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

        $eventId      = $this->events->create($eventData);
        $participants = $data['participants'] ?? [$userId];
        if (!in_array($userId, $participants)) $participants[] = $userId;
        $this->events->setParticipants($eventId, $participants);

        $created = $this->events->withParticipants($eventId);

        // Notify participants
        try {
            $svc = new NotificationService();
            $svc->eventCreated($created, $created['participants'], $userId, $this->auth->user()['name'] ?? '');
        } catch (\Throwable) {}

        $this->json(['success' => true, 'event' => $this->toFC($created, $userId)]);
    }

    public function apiUpdate(array $p = []): void {
        $this->requireAuth();
        $eventId = (int)$p['id'];
        $groupId = $this->auth->groupId();
        $userId  = $this->auth->id();

        $ev = $this->events->findById($eventId);
        if (!$ev || $ev['group_id'] != $groupId) $this->json(['error' => 'No encontrado'], 404);

        // Public events are editable by the whole group; private/hybrid only by participants.
        if ($ev['visibility'] !== 'public' && !$this->events->isParticipant($eventId, $userId)) {
            $this->json(['error' => 'No tenés permiso para editar este evento'], 403);
        }

        $data  = $this->body();
        $scope = $data['scope'] ?? 'all'; // 'this' | 'following' | 'all'

        if ($ev['is_recurring'] && $scope !== 'all') {
            $instanceDate = $data['instance_date'] ?? substr($data['start_datetime'] ?? $ev['start_datetime'], 0, 10);
            $this->handleRecurringUpdate($ev, $data, $scope, $instanceDate, $userId);
            return;
        }

        $this->applyUpdate($eventId, $data, $userId, $ev);
    }

    private function handleRecurringUpdate(array $ev, array $data, string $scope, string $instanceDate, int $userId): void {
        if ($scope === 'this') {
            // Create/update an exception for this specific occurrence
            $exModel = new EventException();
            $overrides = [];
            if (isset($data['title']))       $overrides['new_title']       = $data['title'];
            if (isset($data['description'])) $overrides['new_description'] = $data['description'];
            if (isset($data['location']))    $overrides['new_location']    = $data['location'];
            if (isset($data['category_id'])) $overrides['new_category_id'] = $data['category_id'];
            if (isset($data['visibility']))  $overrides['new_visibility']  = $data['visibility'];
            if (isset($data['color']))       $overrides['new_color']       = $data['color'];
            if (isset($data['start_datetime'])) $overrides['new_start']   = $data['start_datetime'];
            if (isset($data['end_datetime']))    $overrides['new_end']     = $data['end_datetime'];
            $exModel->upsert($ev['id'], $instanceDate, $overrides);
            $this->json(['success' => true, 'reload' => true]);
        }

        if ($scope === 'following') {
            // Trim original: set recurrence_end = instance_date - 1 day
            $trimEnd = (new \DateTime($instanceDate))->modify('-1 day')->format('Y-m-d');
            $this->events->updateById($ev['id'], ['recurrence_end' => $trimEnd, 'updated_at' => date('Y-m-d H:i:s')]);

            // Create new event from instance_date forward
            $rule = null;
            if (!empty($data['recurrence_rule']) && is_array($data['recurrence_rule'])) {
                $rule = json_encode($data['recurrence_rule']);
            } elseif ($ev['recurrence_rule']) {
                $rule = $ev['recurrence_rule'];
            }

            $newData = [
                'group_id'        => $ev['group_id'],
                'creator_id'      => $userId,
                'title'           => $data['title']           ?? $ev['title'],
                'description'     => $data['description']     ?? $ev['description'],
                'location'        => $data['location']        ?? $ev['location'],
                'category_id'     => $data['category_id']     ?? $ev['category_id'],
                'visibility'      => $data['visibility']      ?? $ev['visibility'],
                'color'           => $data['color']           ?? $ev['color'],
                'start_datetime'  => $data['start_datetime']  ?? str_replace(substr($ev['start_datetime'],0,10), $instanceDate, $ev['start_datetime']),
                'end_datetime'    => $data['end_datetime']    ?? str_replace(substr($ev['end_datetime'],0,10),   $instanceDate, $ev['end_datetime']),
                'all_day'         => $ev['all_day'],
                'is_recurring'    => 1,
                'recurrence_type' => $data['recurrence_type'] ?? $ev['recurrence_type'],
                'recurrence_rule' => $rule,
                'recurrence_end'  => $data['recurrence_end']  ?? $ev['recurrence_end'],
            ];
            $newId = $this->events->create($newData);

            // Copy participants
            $parts = $this->events->getParticipantUsers($ev['id']);
            $this->events->setParticipants($newId, array_column($parts, 'id'));

            $this->json(['success' => true, 'reload' => true]);
        }
    }

    private function applyUpdate(int $eventId, array $data, int $userId, array $ev): void {
        // Detect date/time change before applying the update
        $dateChanged = (isset($data['start_datetime']) && $data['start_datetime'] !== $ev['start_datetime'])
                    || (isset($data['end_datetime'])   && $data['end_datetime']   !== $ev['end_datetime']);

        // Snapshot current participants before any participant change
        $oldParticipants = isset($data['participants']) ? $this->events->getParticipantUsers($eventId) : [];

        $allowed = ['title','description','location','category_id','visibility','color',
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

        $newParticipants = null;
        if (isset($data['participants'])) {
            $parts = $data['participants'];
            if (!in_array($userId, $parts)) $parts[] = $userId;
            $this->events->setParticipants($eventId, $parts);
            $newParticipants = $this->events->getParticipantUsers($eventId);
        }

        $updated   = $this->events->withParticipants($eventId);
        $actorName = $this->auth->user()['name'] ?? '';

        try {
            $svc = new NotificationService();

            if ($dateChanged) {
                $svc->eventUpdated($updated, $updated['participants'], $userId, $actorName);
            }

            if ($newParticipants !== null) {
                $oldIds     = array_column($oldParticipants, 'id');
                $newIds     = array_column($newParticipants, 'id');
                $addedIds   = array_diff($newIds, $oldIds);
                $removedIds = array_diff($oldIds, $newIds);

                if ($addedIds) {
                    $added = array_values(array_filter($newParticipants, fn($u) => in_array($u['id'], $addedIds)));
                    $svc->participantsAdded($updated, $added, $userId, $actorName);
                }
                if ($removedIds) {
                    $removed = array_values(array_filter($oldParticipants, fn($u) => in_array($u['id'], $removedIds)));
                    $svc->participantsRemoved($updated['title'], $removed, $userId, $actorName);
                }
            }
        } catch (\Throwable) {}

        $this->json(['success' => true, 'event' => $this->toFC($updated, $userId)]);
    }

    public function apiDelete(array $p = []): void {
        $this->requireAuth();
        $eventId = (int)$p['id'];
        $groupId = $this->auth->groupId();
        $userId  = $this->auth->id();
        $data    = $this->body();
        $scope   = $data['scope'] ?? 'all';

        $ev = $this->events->findById($eventId);
        if (!$ev || $ev['group_id'] != $groupId) $this->json(['error' => 'No encontrado'], 404);

        // Public events are deletable by the whole group; private/hybrid only by participants.
        if ($ev['visibility'] !== 'public' && !$this->events->isParticipant($eventId, $userId)) {
            $this->json(['error' => 'No tenés permiso para eliminar este evento'], 403);
        }

        $participants = $this->events->getParticipantUsers($eventId);
        $evTitle      = $ev['title'];

        if ($ev['is_recurring'] && $scope !== 'all') {
            $instanceDate = $data['instance_date'] ?? '';
            if (!$instanceDate) $this->json(['error' => 'instance_date requerido'], 422);

            if ($scope === 'this') {
                (new EventException())->markDeleted($eventId, $instanceDate);
            } elseif ($scope === 'following') {
                $trimEnd = (new \DateTime($instanceDate))->modify('-1 day')->format('Y-m-d');
                $this->events->updateById($eventId, ['recurrence_end' => $trimEnd, 'updated_at' => date('Y-m-d H:i:s')]);
            }
            $this->json(['success' => true, 'reload' => true]);
            return;
        }

        $this->events->deleteById($eventId);

        try {
            $svc = new NotificationService();
            $svc->eventDeleted($evTitle, $participants, $userId, $this->auth->user()['name'] ?? '');
        } catch (\Throwable) {}

        $this->json(['success' => true]);
    }

    private function toFC(array $ev, int $userId): ?array {
        $participants  = $ev['participants'] ?? [];
        $pids          = array_map(fn($p) => (int)$p['id'], $participants);
        $isParticipant = in_array($userId, $pids, true);
        $vis           = $ev['visibility'];

        // Private events are invisible to everyone except their participants.
        if ($vis === 'private' && !$isParticipant) return null;

        // "Ocupado": a hybrid event seen by someone who is NOT a participant →
        // translucent, no details, just the participant names.
        $isBusy    = ($vis === 'hybrid' && !$isParticipant);
        $canEdit   = ($vis === 'public') || $isParticipant;
        $showTitle = !$isBusy;
        $showNames = ($vis === 'public') || $isBusy;
        $color     = $ev['color'] ?? $ev['category_color'] ?? '#7c3aed';
        $names     = array_map(fn($p) => $p['name'], $participants);

        return [
            'id'              => $ev['id'] . ($ev['instance_date'] ?? ''),
            'event_id'        => $ev['id'],
            'title'           => $isBusy ? 'Ocupado' : $ev['title'],
            'start'           => $ev['start_datetime'],
            'end'             => $ev['end_datetime'],
            'allDay'          => (bool)$ev['all_day'],
            'backgroundColor' => $color,
            'borderColor'     => $color,
            'textColor'       => '#ffffff',
            'classNames'      => $isBusy ? ['fc-busy'] : [],
            'extendedProps'   => [
                'event_id'        => $ev['id'],
                'description'     => $isBusy ? null : ($ev['description'] ?? null),
                'location'        => $isBusy ? null : ($ev['location'] ?? null),
                'category_id'     => $ev['category_id'] ?? null,
                'category_name'   => $ev['category_name'] ?? null,
                'category_color'  => $ev['category_color'] ?? null,
                'visibility'      => $vis,
                'is_recurring'    => (bool)$ev['is_recurring'],
                'recurrence_type' => $ev['recurrence_type'] ?? null,
                'creator_name'    => $ev['creator_name'] ?? null,
                'creator_avatar'  => $ev['creator_avatar'] ?? null,
                'creator_color'   => $ev['creator_color'] ?? null,
                'is_owner'        => $ev['creator_id'] == $userId,
                'is_busy'         => $isBusy,
                'can_edit'        => $canEdit,
                'show_title'      => $showTitle,
                'show_names'      => $showNames,
                'participant_names' => $names,
                'participants'    => $isBusy ? [] : $participants,
                'instance_date'   => $ev['instance_date'] ?? null,
                'has_exception'   => $ev['has_exception'] ?? false,
            ],
        ];
    }
}
