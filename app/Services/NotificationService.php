<?php
namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;

class NotificationService {
    private Notification     $notifModel;
    private PushSubscription $pushModel;
    private ?WebPushService  $push = null;
    private ?MailService     $mail = null;

    public function __construct() {
        $this->notifModel = new Notification();
        $this->pushModel  = new PushSubscription();

        $pushCfg = require BASE_PATH . '/app/Config/push.php';
        if (!empty($pushCfg['enabled'])) {
            try { $this->push = new WebPushService(); } catch (\Throwable) {}
        }

        $mailCfg = require BASE_PATH . '/app/Config/mail.php';
        if (!empty($mailCfg['enabled'])) {
            $this->mail = new MailService();
        }
    }

    public function eventCreated(array $event, array $participants, int $actorId, string $actorName = ''): void {
        $eventUrl = $this->eventUrl($event['id']);
        $start    = $this->fmtDateTime($event['start_datetime']);
        $title    = "Nuevo evento: {$event['title']}";
        $by       = $actorName ? " por {$actorName}" : '';
        $body     = "Fuiste agregado/a al evento '{$event['title']}' el {$start}{$by}.";

        foreach ($participants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'event_created', $title, $body, $eventUrl,
                ['event_id' => $event['id']]);
            $this->sendPush($u['id'], $title, $body, $eventUrl);
            $this->sendMail($u['email'], $u['name'], $title, $event['title'], $start, $body, $eventUrl);
        }
    }

    public function eventUpdated(array $event, array $participants, int $actorId, string $actorName = ''): void {
        $eventUrl = $this->eventUrl($event['id']);
        $start    = $this->fmtDateTime($event['start_datetime']);
        $title    = "Fecha/hora cambiada: {$event['title']}";
        $by       = $actorName ? " por {$actorName}" : '';
        $body     = "El evento '{$event['title']}' fue movido al {$start}{$by}.";

        foreach ($participants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'event_updated', $title, $body, $eventUrl,
                ['event_id' => $event['id']]);
            $this->sendPush($u['id'], $title, $body, $eventUrl);
        }
    }

    public function participantsAdded(array $event, array $newParticipants, int $actorId, string $actorName = ''): void {
        $eventUrl = $this->eventUrl($event['id']);
        $start    = $this->fmtDateTime($event['start_datetime']);
        $title    = "Te agregaron a: {$event['title']}";
        $by       = $actorName ? $actorName : 'Alguien';
        $body     = "{$by} te agregó al evento '{$event['title']}' el {$start}.";

        foreach ($newParticipants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'participant_added', $title, $body, $eventUrl,
                ['event_id' => $event['id']]);
            $this->sendPush($u['id'], $title, $body, $eventUrl);
        }
    }

    public function participantsRemoved(string $eventTitle, array $removedParticipants, int $actorId, string $actorName = ''): void {
        $appUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
        $calUrl = $appUrl . '/calendar';
        $title  = "Ya no participás en: {$eventTitle}";
        $by     = $actorName ? $actorName : 'Alguien';
        $body   = "{$by} te quitó del evento '{$eventTitle}'.";

        foreach ($removedParticipants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'participant_removed', $title, $body, $calUrl);
            $this->sendPush($u['id'], $title, $body, $calUrl);
        }
    }

    public function eventDeleted(string $eventTitle, array $participants, int $actorId, string $actorName = ''): void {
        $appUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
        $calUrl = $appUrl . '/calendar';
        $title  = "Evento eliminado: {$eventTitle}";
        $by     = $actorName ? " por {$actorName}" : '';
        $body   = "El evento '{$eventTitle}' fue eliminado{$by}.";

        foreach ($participants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'event_deleted', $title, $body, $calUrl);
            $this->sendPush($u['id'], $title, $body, $calUrl);
        }
    }

    /**
     * Scheduled reminder, sent to every participant (no exclusions). The same
     * notification is used for both the 24h-before and 30m-before runs.
     */
    public function eventReminder(array $event, array $participants): void {
        $eventUrl = $this->eventUrl($event['id']);
        $start    = $this->fmtDateTime($event['start_datetime']);
        $title    = "Recordatorio: {$event['title']}";
        $body     = "{$event['title']} — {$start}.";

        foreach ($participants as $u) {
            $this->notifModel->createForUser($u['id'], 'event_reminder', $title, $body, $eventUrl,
                ['event_id' => $event['id']]);
            $this->sendPush($u['id'], $title, $body, $eventUrl);
        }
    }

    public function invitation(int $groupId, string $inviteLink, array $inviter): void {
        // Email-only since the invitee might not have an account
    }

    private function eventUrl(int $eventId): string {
        $appUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
        return $appUrl . '/?open_event=' . $eventId;
    }

    private function sendPush(int $userId, string $title, string $body, string $url): void {
        if (!$this->push) return;
        $subs = $this->pushModel->forUser($userId);
        foreach ($subs as $sub) {
            try {
                $this->push->sendToSubscription($sub, $title, $body, $url);
            } catch (\Throwable) {}
        }
    }

    private function sendMail(string $email, string $name, string $subject, string $evTitle, string $evStart, string $message, string $url): void {
        if (!$this->mail) return;
        try {
            $html = $this->mail->buildEventNotificationEmail($name, $evTitle, $evStart, $message, $url);
            $this->mail->send($email, $subject, $html);
        } catch (\Throwable) {}
    }

    private function fmtDateTime(string $dt): string {
        $months = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $d = new \DateTime($dt);
        return $d->format('j') . ' de ' . $months[(int)$d->format('n')] . ' de ' . $d->format('Y') . ' a las ' . $d->format('H:i');
    }
}
