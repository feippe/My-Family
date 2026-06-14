-- FamilyCal Database Schema
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(191) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    avatar      VARCHAR(10)  DEFAULT NULL,
    color       VARCHAR(7)   DEFAULT '#7c3aed',
    created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS family_groups (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS family_members (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('admin','member') DEFAULT 'member',
    joined_at   DATETIME NOT NULL,
    UNIQUE KEY uq_group_user (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES family_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id    INT UNSIGNED NOT NULL,
    email       VARCHAR(191) NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    invited_by  INT UNSIGNED NOT NULL,
    status      ENUM('pending','accepted','expired') DEFAULT 'pending',
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL,
    FOREIGN KEY (group_id)   REFERENCES family_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id    INT UNSIGNED NOT NULL,
    name        VARCHAR(60)  NOT NULL,
    color       VARCHAR(7)   NOT NULL DEFAULT '#7c3aed',
    icon        VARCHAR(10)  DEFAULT '📅',
    created_at  DATETIME     NOT NULL,
    FOREIGN KEY (group_id) REFERENCES family_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id         INT UNSIGNED NOT NULL,
    creator_id       INT UNSIGNED NOT NULL,
    title            VARCHAR(200) NOT NULL,
    description      TEXT         DEFAULT NULL,
    location         VARCHAR(200) DEFAULT NULL,
    category_id      INT UNSIGNED DEFAULT NULL,
    visibility       ENUM('public','private','hybrid') DEFAULT 'public',
    color            VARCHAR(7)   DEFAULT NULL,
    start_datetime   DATETIME     NOT NULL,
    end_datetime     DATETIME     NOT NULL,
    all_day          TINYINT(1)   DEFAULT 0,
    is_recurring     TINYINT(1)   DEFAULT 0,
    recurrence_type  ENUM('weekly','monthly','annual') DEFAULT NULL,
    recurrence_rule  JSON         DEFAULT NULL,
    recurrence_end   DATE         DEFAULT NULL,
    created_at       DATETIME     NOT NULL,
    updated_at       DATETIME     NOT NULL,
    FOREIGN KEY (group_id)    REFERENCES family_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_group_start (group_id, start_datetime),
    INDEX idx_recurring (group_id, is_recurring)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_participants (
    event_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_exceptions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id         INT UNSIGNED NOT NULL,
    exception_date   DATE         NOT NULL,
    is_deleted       TINYINT(1)   DEFAULT 0,
    new_start        DATETIME     DEFAULT NULL,
    new_end          DATETIME     DEFAULT NULL,
    new_title        VARCHAR(200) DEFAULT NULL,
    new_description  TEXT         DEFAULT NULL,
    UNIQUE KEY uq_event_date (event_id, exception_date),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
