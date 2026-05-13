-- ============================================================================
-- Money Wise 2026 — Visitor Tracker Database Schema
-- Run this in phpMyAdmin after creating database u373133718_moneywise
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Table: visitors
-- Main visitor data with full fingerprint, IP intelligence, behavior & risk
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `visitors`;
CREATE TABLE `visitors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `visitor_id` VARCHAR(64) NOT NULL,
  `session_id` VARCHAR(64) NOT NULL,

  -- Timestamps
  `visit_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- IP Intelligence (Stage 1)
  `ip_address` VARCHAR(45),
  `ip_type` VARCHAR(50) DEFAULT NULL,
  `country` VARCHAR(100),
  `country_code` VARCHAR(10),
  `continent` VARCHAR(100),
  `region` VARCHAR(100),
  `city` VARCHAR(100),
  `isp` VARCHAR(255),
  `asn` VARCHAR(50),
  `as_name` VARCHAR(255),
  `as_domain` VARCHAR(255),
  `org` VARCHAR(255),
  `is_proxy` TINYINT(1) DEFAULT 0,
  `is_vpn` TINYINT(1) DEFAULT 0,
  `is_tor` TINYINT(1) DEFAULT 0,
  `is_datacenter` TINYINT(1) DEFAULT 0,
  `proxy_type` VARCHAR(50),
  `proxy_risk_score` INT DEFAULT 0,

  -- Browser Fingerprint (Stage 2)
  `user_agent` TEXT,
  `browser_name` VARCHAR(100),
  `browser_version` VARCHAR(50),
  `os_name` VARCHAR(100),
  `os_version` VARCHAR(50),
  `device_type` VARCHAR(50),
  `languages` TEXT,
  `language_primary` VARCHAR(20),
  `timezone` VARCHAR(100),
  `timezone_offset` INT,

  -- Device & Hardware (Stage 3)
  `screen_width` INT,
  `screen_height` INT,
  `screen_avail_width` INT,
  `screen_avail_height` INT,
  `screen_color_depth` INT,
  `pixel_ratio` FLOAT,
  `viewport_width` INT,
  `viewport_height` INT,
  `cpu_cores` INT,
  `device_memory` FLOAT,
  `touch_support` TINYINT(1) DEFAULT 0,
  `max_touch_points` INT,
  `battery_level` FLOAT,
  `battery_charging` TINYINT(1),

  -- Advanced Fingerprint
  `webgl_renderer` VARCHAR(255),
  `webgl_vendor` VARCHAR(255),
  `webgl_version` VARCHAR(100),
  `canvas_fingerprint` VARCHAR(64),
  `audio_fingerprint` VARCHAR(64),
  `fonts_count` INT DEFAULT 0,
  `fonts_list` TEXT,
  `plugins_count` INT DEFAULT 0,

  -- Browser State (Stage 5)
  `cookies_enabled` TINYINT(1) DEFAULT 0,
  `local_storage_enabled` TINYINT(1) DEFAULT 0,
  `session_storage_enabled` TINYINT(1) DEFAULT 0,
  `indexed_db_enabled` TINYINT(1) DEFAULT 0,
  `do_not_track` VARCHAR(10),
  `is_incognito` TINYINT(1) DEFAULT 0,
  `is_bot` TINYINT(1) DEFAULT 0,
  `is_webdriver` TINYINT(1) DEFAULT 0,

  -- Network
  `connection_type` VARCHAR(50),
  `effective_type` VARCHAR(50),
  `downlink` FLOAT,
  `rtt` INT,
  `save_data` TINYINT(1),

  -- Referrer & Source
  `referrer` TEXT,
  `referrer_domain` VARCHAR(255),
  `traffic_source` VARCHAR(100),
  `landing_page` VARCHAR(500),
  `utm_source` VARCHAR(100),
  `utm_medium` VARCHAR(100),
  `utm_campaign` VARCHAR(100),
  `utm_content` VARCHAR(100),
  `utm_term` VARCHAR(100),

  -- Behavior (Stage 6 & 7)
  `page_url` VARCHAR(500),
  `page_title` VARCHAR(500),
  `page_path` VARCHAR(500),
  `session_duration` INT DEFAULT 0,
  `scroll_depth_max` INT DEFAULT 0,
  `mouse_movements` INT DEFAULT 0,
  `clicks_count` INT DEFAULT 0,
  `keystrokes_count` INT DEFAULT 0,
  `tab_switches` INT DEFAULT 0,
  `pages_viewed` INT DEFAULT 1,

  -- Risk Assessment (Stage 11)
  `risk_score` INT DEFAULT 0,
  `risk_level` VARCHAR(20),
  `risk_flags` TEXT,

  -- Raw JSON for full analysis
  `full_data` LONGTEXT,

  INDEX `idx_visitor` (`visitor_id`),
  INDEX `idx_session` (`session_id`),
  INDEX `idx_time` (`visit_time`),
  INDEX `idx_ip` (`ip_address`),
  INDEX `idx_risk` (`risk_score`),
  INDEX `idx_country` (`country_code`),
  INDEX `idx_traffic` (`traffic_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: behavior_events
-- Granular event log (clicks, scroll positions, etc.)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `behavior_events`;
CREATE TABLE `behavior_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `visitor_id` VARCHAR(64),
  `session_id` VARCHAR(64),
  `event_type` VARCHAR(50),
  `event_data` TEXT,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_session_event` (`session_id`, `event_type`),
  INDEX `idx_event_time` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: login_attempts
-- Track failed dashboard login attempts for rate limiting
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempt_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) DEFAULT 0,
  INDEX `idx_ip_time` (`ip_address`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Done. Verify with:
--   SHOW TABLES;
--   DESCRIBE visitors;
-- ----------------------------------------------------------------------------
