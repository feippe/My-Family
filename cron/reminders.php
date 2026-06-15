<?php
/**
 * CLI entry point for the reminder cron job.
 *
 * Cron command (runs every 5-10 minutes in cPanel → Cron Jobs):
 *   /opt/cpanel/ea-php81/root/usr/bin/php -q /home/feippec2/familia.feippe.com/cron/reminders.php
 */

define('BASE_PATH', dirname(__DIR__));

// Fake the HTTP context so app/Config/app.php builds correct production URLs.
$_SERVER['HTTPS']       = 'on';
$_SERVER['HTTP_HOST']   = 'familia.feippe.com';
$_SERVER['SERVER_NAME'] = 'familia.feippe.com';

// Autoloader — identical mapping to public_html/index.php
spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// Apply any pending DB migrations before running (same as boot).
try {
    \App\Core\Migrator::run(\App\Core\Database::getInstance());
} catch (\Throwable $e) {
    fwrite(STDERR, '[Migrator] ' . $e->getMessage() . PHP_EOL);
}

try {
    $stats = (new \App\Services\ReminderService())->run();
    // Only log when something was actually sent (keeps cron mail quiet).
    if ($stats['sent'] > 0) {
        echo date('Y-m-d H:i:s') . "  reminders sent={$stats['sent']} checked={$stats['checked']}" . PHP_EOL;
    }
} catch (\Throwable $e) {
    fwrite(STDERR, '[cron/reminders] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
