-- ============================================================================
-- Money Wise 2026 — Schema v4: Behavior columns missing from v2
-- Adds page_visible_time + page_hidden_time so tracker can store them.
-- Idempotent: uses ALTER TABLE ... ADD COLUMN IF NOT EXISTS.
-- ============================================================================

ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_visible_time` INT DEFAULT 0;
ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `page_hidden_time`  INT DEFAULT 0;
