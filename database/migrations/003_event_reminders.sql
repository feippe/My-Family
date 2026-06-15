-- Migration 003: Event reminder dedup log
-- Records which scheduled reminders ('24h' / '30m') have already been sent
-- for a given event occurrence, so the cron runner never double-sends.

CREATE TABLE IF NOT EXISTS event_reminders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id        INT UNSIGNED NOT NULL,
    occurrence_date DATE         NOT NULL,
    kind            VARCHAR(8)   NOT NULL,   -- '24h' | '30m'
    sent_at         DATETIME     NOT NULL,
    UNIQUE KEY uq_event_occ_kind (event_id, occurrence_date, kind),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
