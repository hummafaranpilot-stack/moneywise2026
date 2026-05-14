-- ============================================================================
-- Money Wise 2026 — Schema v3: ad_clicks table
-- Adds tracking for clickable ad interactions.
-- Idempotent: uses CREATE TABLE IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ad_clicks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` VARCHAR(64),
    `session_id` VARCHAR(64),
    `ad_id` VARCHAR(20),
    `ad_format` VARCHAR(50),
    `ad_position` VARCHAR(50),
    `click_x` INT,
    `click_y` INT,
    `time_to_click` INT,
    `target_url` VARCHAR(500),
    `page_url` VARCHAR(500),
    `user_agent` TEXT,
    `ip_address` VARCHAR(45),
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_visitor` (`visitor_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_ad` (`ad_id`),
    INDEX `idx_clicked` (`clicked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
