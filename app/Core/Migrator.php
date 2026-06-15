<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Tiny forward-only migration runner.
 *
 * Applies every database/migrations/*.sql that has not been recorded yet,
 * tracking applied files in `schema_migrations`. Statements that fail because
 * the object already exists (table/column/key already there) are ignored, so
 * the runner is safe to introduce on a database where some migrations were
 * already applied out-of-band (e.g. run by hand before this runner existed).
 */
class Migrator {
    // MySQL error codes that mean "already applied" → safe to skip.
    // 1050 table exists · 1060 dup column · 1061 dup key name
    // 1062 dup entry · 1022 dup key · 1091 cannot drop (missing)
    private const IGNORABLE = [1050, 1060, 1061, 1062, 1022, 1091];

    public static function run(PDO $db): void {
        $dir = BASE_PATH . '/database/migrations';
        if (!is_dir($dir)) return;

        // Steady state is a single cheap SELECT; only create the table the
        // first time (when the SELECT fails because it doesn't exist yet).
        try {
            $done = $db->query('SELECT filename FROM schema_migrations')
                       ->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                filename   VARCHAR(191) NOT NULL PRIMARY KEY,
                applied_at DATETIME     NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $done = [];
        }
        $done = array_flip($done);

        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        if (!$files) return;

        $insert = $db->prepare(
            'INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)'
        );

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) continue;

            $sql = (string) file_get_contents($file);
            foreach (self::splitStatements($sql) as $stmt) {
                try {
                    $db->exec($stmt);
                } catch (PDOException $e) {
                    $code = (int) ($e->errorInfo[1] ?? 0);
                    if (!in_array($code, self::IGNORABLE, true)) throw $e;
                }
            }
            $insert->execute([$name, date('Y-m-d H:i:s')]);
        }
    }

    /** Split a .sql file into individual statements, dropping -- comment lines. */
    private static function splitStatements(string $sql): array {
        $out = [];
        foreach (explode(';', $sql) as $part) {
            $lines = array_filter(
                explode("\n", $part),
                fn($l) => !str_starts_with(trim($l), '--')
            );
            $clean = trim(implode("\n", $lines));
            if ($clean !== '') $out[] = $clean;
        }
        return $out;
    }
}
