<?php
namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;

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

    public function eventCreated(array $event, array $participants, int $actorId): void {
        $calUrl   = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/') . '/calendar';
        $title    = "Nuevo evento: {$event['title']}";
        $start    = $this->fmtDateTime($event['start_datetime']);
        $body     = "Fuiste agregado/a a '{$event['title']}' el {$start}.";

        foreach ($participants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'event_created', $title, $body, $calUrl,
                ['event_id' => $event['id']]);
            $this->sendPush($u['id'], $title, $body, $calUrl);
            $this->sendMail($u['email'], $u['name'], $title, $event['title'], $start, $body, $calUrl);
        }
    }

    public function eventUpdated(array $event, array $participants, int $actorId): void {
        $calUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/') . '/calendar';
        $title  = "Evento actualizado: {$event['title']}";
        $start  = $this->fmtDateTime($event['start_datetime']);
        $body   = "El evento '{$event['title']}' fue modificado.";

        foreach ($participants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'event_updated', $title, $body, $calUrl,
                ['event_id' => $event['id']]);
            $this->sendPush($u['id'], $title, $body, $calUrl);
        }
    }

    public function eventDeleted(string $eventTitle, array $participants, int $actorId): void {
        $calUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/') . '/calendar';
        $title  = "Evento eliminado: {$eventTitle}";
        $body   = "El evento '{$eventTitle}' fue eliminado.";

        foreach ($participants as $u) {
            if ($u['id'] == $actorId) continue;
            $this->notifModel->createForUser($u['id'], 'event_deleted', $title, $body, $calUrl);
            $this->sendPush($u['id'], $title, $body, $calUrl);
        }
    }

    public function invitation(int $groupId, string $inviteLink, array $inviter): void {
        // Email-only since the invitee might not have an account
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
