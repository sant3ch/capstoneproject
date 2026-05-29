-- =========================================================
-- 011_add_is_read_by_user_to_claimed_rewards.sql
-- Adds the `is_read_by_user` flag that user-profile-data.php,
-- user/user-profile.php and user/acknowledge_reward.php already use
-- to track whether the customer has acknowledged a rejected reward.
-- =========================================================

ALTER TABLE `claimed_rewards`
  ADD COLUMN `is_read_by_user` TINYINT(1) NOT NULL DEFAULT 0 AFTER `approved_at`;
