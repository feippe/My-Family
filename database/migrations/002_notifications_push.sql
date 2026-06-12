-- Migration 002: Notifications & Push subscriptions
-- Run AFTER schema.sql

-- Extend event_exceptions with full override fields
ALTER TABLE event_exceptions
  ADD COLUMN new_location    VARCHAR(200) DEFAULT NULL AFTER new_description,
  ADD COLUMN new_category_id INT UNSIGNED DEFAULT NULL AFTER new_location,
  ADD COLUMN new_visibility  ENUM('public','private','hybrid') DEFAULT NULL AFTER new_category_id,
  ADD COLUMN new_color       VARCHAR(7)   DEFAULT NULL AFTER new_visibility;

-- In-app notifications
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(50)  NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT         DEFAULT NULL,
    action_url  VARCHAR(255) DEFAULT NULL,
    data        JSON         DEFAULT NULL,
    is_read     TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     NOT NULL,
    INDEX idx_user_read (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Push subscriptions (Web Push VAPID)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    endpoint    TEXT         NOT NULL,
    p256dh      TEXT         NOT NULL,
    auth        VARCHAR(255) NOT NULL,
    user_agent  VARCHAR(200) DEFAULT NULL,
    created_at  DATETIME     NOT NULL,
    UNIQUE KEY uq_user_endpoint (user_id, endpoint(191)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email log (optional, for debugging)
CREATE TABLE IF NOT EXISTS email_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email    VARCHAR(191) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    status      ENUM('sent','failed') DEFAULT 'sent',
    error       TEXT         DEFAULT NULL,
    created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
