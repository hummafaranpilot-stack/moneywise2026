-- ============================================================================
-- Money Wise 2026 — Tracker Schema v2 Migration
-- Adds 50+ new columns for enhanced 150-field tracker.
-- IDEMPOTENT: safe to run multiple times (uses IF NOT EXISTS).
-- Requires MariaDB 10.0.2+ or MySQL 8.0.29+.
-- Run this in phpMyAdmin SQL tab on database `u373133718_moneywise`.
-- ============================================================================

-- ---------- Browser Detail ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `app_version` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `app_name` VARCHAR(100) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `vendor` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `vendor_sub` VARCHAR(100) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `product` VARCHAR(100) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `product_sub` VARCHAR(100) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `platform` VARCHAR(100) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `language` VARCHAR(20) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `online` TINYINT(1) DEFAULT 1;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `cookie_enabled` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webdriver` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `pdf_viewer_enabled` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `java_enabled` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `is_brave` TINYINT(1) DEFAULT 0;

-- ---------- Screen / Window ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `screen_pixel_depth` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `window_inner_width` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `window_inner_height` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `window_outer_width` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `window_outer_height` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `device_pixel_ratio` FLOAT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `screen_orientation_type` VARCHAR(50) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `screen_orientation_angle` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `prefers_color_scheme` VARCHAR(20) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `prefers_reduced_motion` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `color_gamut_p3` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `color_gamut_srgb` TINYINT(1) DEFAULT 0;

-- ---------- Hardware ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `hardware_concurrency` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `battery_charging_time` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `battery_discharging_time` INT NULL;

-- ---------- WebGL Detail ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_unmasked_vendor` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_unmasked_renderer` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_shading_language` VARCHAR(100) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_extensions` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_max_texture_size` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webgl_max_renderbuffer_size` INT NULL;

-- ---------- Canvas / Audio ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `canvas_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `canvas_hash` VARCHAR(64) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `audio_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `audio_sample_rate` INT NULL;

-- ---------- Fonts ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `fonts_detected` TEXT NULL;

-- ---------- Plugins / MIME ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `mime_types_list` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `mime_types_count` INT DEFAULT 0;

-- ---------- Storage ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `cookies_string` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `cookies_count` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `localstorage_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `localstorage_keys` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `localstorage_size` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `sessionstorage_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `sessionstorage_keys` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `indexeddb_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `service_worker_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `cache_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `storage_quota` BIGINT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `storage_usage` BIGINT NULL;

-- ---------- Permissions ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `permissions_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `permissions_state` TEXT NULL;

-- ---------- Speech / Media ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `speech_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `speech_voices_count` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `speech_voices_list` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `media_devices_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `media_devices_list` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `audio_inputs` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `audio_outputs` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `video_inputs` INT DEFAULT 0;

-- ---------- WebRTC ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webrtc_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webrtc_ips` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `webrtc_ip_count` INT DEFAULT 0;

-- ---------- Network Detail ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `connection_supported` TINYINT(1) DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `connection_effective_type` VARCHAR(20) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `connection_downlink` FLOAT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `connection_rtt` INT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `connection_save_data` TINYINT(1) DEFAULT 0;

-- ---------- Tracking Click IDs ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `fbclid` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `gclid` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `msclkid` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `ttclid` VARCHAR(255) NULL;

-- ---------- Codecs / Features ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `codec_support` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `feature_support` TEXT NULL;

-- ---------- Behavior Detail ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `behavior_full_data` LONGTEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `mouse_movements_count` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `mouse_clicks_count` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `scroll_events_count` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `key_events_count` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `total_scroll_distance` INT DEFAULT 0;

-- ---------- Page Detail ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_protocol` VARCHAR(20) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_host` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_hostname` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_port` VARCHAR(10) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_pathname` VARCHAR(500) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_search` TEXT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_hash` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_origin` VARCHAR(255) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_charset` VARCHAR(50) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_visibility_state` VARCHAR(20) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_has_focus` TINYINT(1) DEFAULT 1;

-- ---------- Misc ----------
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `locale` VARCHAR(50) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `calendar` VARCHAR(50) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `numbering_system` VARCHAR(50) NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `timezone_offset_hours` FLOAT NULL;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `is_final_beacon` TINYINT(1) DEFAULT 0;

-- ---------- Indexes (idempotent — wrapped to ignore errors) ----------
-- Note: MariaDB doesn't support CREATE INDEX IF NOT EXISTS until 10.5.
-- If your version is older, run these manually one at a time and ignore "already exists" errors.
-- ALTER TABLE `visitors` ADD INDEX `idx_canvas_hash` (`canvas_hash`);
-- ALTER TABLE `visitors` ADD INDEX `idx_traffic_source` (`traffic_source`);
-- ALTER TABLE `visitors` ADD INDEX `idx_visitor` (`visitor_id`);
-- ALTER TABLE `visitors` ADD INDEX `idx_session` (`session_id`);

-- ============================================================================
-- Verification: SHOW COLUMNS FROM `visitors`;
-- Should show ~120 columns total after migration.
-- ============================================================================
