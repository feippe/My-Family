<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\ReminderService;

/**
 * Endpoints meant to be hit by the hosting's scheduled task (cron job).
 * Protected by a shared secret so they can't be triggered by random traffic.
 */
class CronController extends Controller {
    public function reminders(array $p = []): void {
        $this->requireCronKey();

        try {
            $stats = (new ReminderService())->run();
        } catch (\Throwable $e) {
            error_log('[cron/reminders] ' . $e->getMessage());
            $this->json(['error' => 'fallo interno'], 500);
        }

        $this->json(['success' => true] + $stats);
    }

    private function requireCronKey(): void {
        $file = BASE_PATH . '/app/Config/cron.php';
        $cfg  = is_file($file) ? require $file : [];
        $key  = (string)($cfg['key'] ?? '');
        $given = (string)$this->input('key', '');

        if ($key === '' || !hash_equals($key, $given)) {
            $this->json(['error' => 'No autorizado'], 403);
        }
    }
}
