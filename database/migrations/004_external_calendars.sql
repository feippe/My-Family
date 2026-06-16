CREATE TABLE IF NOT EXISTS external_calendars (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id   INT UNSIGNED NOT NULL,
    name       VARCHAR(255) NOT NULL,
    url        TEXT         NOT NULL,
    color      VARCHAR(7)   NOT NULL DEFAULT '#0891b2',
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    KEY idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
