<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;

class NotificationController extends Controller {
    private Notification $model;

    public function __construct() {
        parent::__construct();
        $this->model = new Notification();
    }

    public function apiIndex(array $p = []): void {
        $this->requireAuth();
        $userId    = $this->auth->id();
        $notifs    = $this->model->unreadForUser($userId, 30);
        $unread    = $this->model->unreadCount($userId);
        $this->json(['notifications' => $notifs, 'unread_count' => $unread]);
    }

    public function apiRead(array $p = []): void {
        $this->requireAuth();
        $this->model->markRead((int)$p['id'], $this->auth->id());
        $this->json(['success' => true, 'unread_count' => $this->model->unreadCount($this->auth->id())]);
    }

    public function apiReadAll(array $p = []): void {
        $this->requireAuth();
        $this->model->markAllRead($this->auth->id());
        $this->json(['success' => true]);
    }

    public function apiUnreadCount(array $p = []): void {
        $this->requireAuth();
        $this->json(['unread_count' => $this->model->unreadCount($this->auth->id())]);
    }
}
