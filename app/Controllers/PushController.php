<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\PushSubscription;

class PushController extends Controller {
    public function subscribe(array $p = []): void {
        $this->requireAuth();
        $data = $this->body();

        if (empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            $this->json(['error' => 'Suscripción inválida'], 422);
        }

        $model = new PushSubscription();
        $model->save(
            $this->auth->id(),
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        $this->json(['success' => true]);
    }

    public function unsubscribe(array $p = []): void {
        $this->requireAuth();
        $data = $this->body();

        if (empty($data['endpoint'])) { $this->json(['error' => 'Endpoint requerido'], 422); }
        (new PushSubscription())->remove($this->auth->id(), $data['endpoint']);
        $this->json(['success' => true]);
    }

    public function vapidKey(array $p = []): void {
        $cfg = require BASE_PATH . '/app/Config/push.php';
        $this->json([
            'enabled'    => !empty($cfg['enabled']),
            'public_key' => $cfg['vapid_public_b64'] ?? '',
        ]);
    }
}
