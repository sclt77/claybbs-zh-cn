




SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `admin_name` varchar(120) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `detail` text,
  `ip` varchar(80) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_action` (`action`,`created_at`),
  KEY `idx_admin_audit_target` (`target_type`,`target_id`),
  KEY `idx_admin_audit_admin` (`admin_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `ai_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'openai_compatible',
  `base_url` varchar(255) NOT NULL DEFAULT '',
  `api_key` text,
  `model` varchar(120) NOT NULL DEFAULT '',
  `endpoint_path` varchar(180) DEFAULT NULL,
  `request_template` longtext,
  `response_path` varchar(120) DEFAULT NULL,
  `temperature` decimal(4,2) NOT NULL DEFAULT '0.00',
  `max_tokens` int(10) unsigned NOT NULL DEFAULT '600',
  `timeout_seconds` int(10) unsigned NOT NULL DEFAULT '12',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_providers_enabled` (`enabled`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `ai_review_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `target_type` varchar(40) NOT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `draft_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content_excerpt` text,
  `status` varchar(20) NOT NULL DEFAULT 'error',
  `risk_level` varchar(20) DEFAULT NULL,
  `categories` varchar(255) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `suggestion` varchar(500) DEFAULT NULL,
  `request_payload` longtext,
  `response_raw` longtext,
  `parsed_result` longtext,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_review_logs_status` (`status`),
  KEY `idx_ai_review_logs_target` (`target_type`,`target_id`),
  KEY `idx_ai_review_logs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `announcement_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `visitor_key` varchar(80) DEFAULT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_announcement_read_user` (`announcement_id`,`user_id`),
  KEY `idx_announcement_read_visitor` (`announcement_id`,`visitor_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `popup_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `popup_once` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned DEFAULT NULL,
  `post_id` bigint(20) unsigned DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `mime` varchar(120) NOT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT '0',
  `kind` varchar(20) NOT NULL DEFAULT 'file',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attachments_user_id` (`user_id`),
  KEY `idx_attachments_thread_id` (`thread_id`),
  KEY `idx_attachments_post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `banners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `thread_id` bigint(20) unsigned DEFAULT NULL,
  `placement` varchar(20) NOT NULL DEFAULT 'home',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banners_placement_status` (`placement`,`status`,`sort_order`),
  KEY `idx_banners_thread` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `moderator_note` varchar(500) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `chat_group_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `join_source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invite',
  `last_read_message_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `cleared_message_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `custom_title` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_title_color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  `banned_by` bigint(20) unsigned DEFAULT NULL,
  `ban_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `muted_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_group_member` (`group_id`,`user_id`),
  KEY `idx_chat_group_members_user` (`user_id`),
  KEY `idx_chat_group_members_role` (`group_id`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `chat_group_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `sender_user_id` bigint(20) unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `media_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `review_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_suggestion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_result_json` longtext COLLATE utf8mb4_unicode_ci,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_content` text COLLATE utf8mb4_unicode_ci,
  `revoked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_group_messages_group_id` (`group_id`,`id`),
  KEY `idx_chat_group_messages_sender` (`sender_user_id`),
  KEY `idx_chat_group_messages_status` (`status`),
  KEY `idx_chat_group_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `chat_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notice` text COLLATE utf8mb4_unicode_ci,
  `notice_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `join_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `visibility` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `last_message_id` bigint(20) unsigned DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_groups_public_id` (`public_id`),
  KEY `idx_chat_groups_owner` (`owner_user_id`),
  KEY `idx_chat_groups_last` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `content_likes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `target_type` varchar(20) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_like_user_target` (`user_id`,`target_type`,`target_id`),
  KEY `idx_like_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `content_report_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_logs_report` (`report_id`,`created_at`),
  KEY `idx_report_logs_operator` (`operator_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `content_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `target_type` varchar(20) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL DEFAULT '',
  `category` varchar(50) DEFAULT NULL,
  `evidence` json DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `priority` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `admin_note` varchar(255) DEFAULT NULL,
  `resolution` varchar(50) DEFAULT NULL,
  `target_action` varchar(50) DEFAULT NULL,
  `handled_by` bigint(20) unsigned DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  `reporter_notified_at` datetime DEFAULT NULL,
  `target_author_notified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_report_user_target` (`user_id`,`target_type`,`target_id`),
  KEY `idx_report_status` (`status`),
  KEY `idx_report_target` (`target_type`,`target_id`),
  KEY `idx_report_category_status` (`category`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `currencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(60) NOT NULL,
  `symbol` varchar(12) NOT NULL DEFAULT '',
  `exchange_rate` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `precision` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_currency_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `currencies` (`code`,`name`,`symbol`,`exchange_rate`,`precision`,`status`,`sort_order`,`created_at`) VALUES
('COIN','金币','','100.000000',0,'active',10,NOW()),
('COIN_1','银币','','10.000000',0,'active',20,NOW()),
('COIN_2','铜币','','1.000000',0,'active',30,NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `exchange_rate`=VALUES(`exchange_rate`), `precision`=VALUES(`precision`), `status`='active', `sort_order`=VALUES(`sort_order`);
CREATE TABLE IF NOT EXISTS `levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` int(10) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `min_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `color` varchar(20) NOT NULL DEFAULT '#64748b',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_levels_level` (`level`),
  KEY `idx_levels_min_exp` (`min_exp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `mentions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned NOT NULL,
  `post_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'mention',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mentions_user_id` (`user_id`),
  KEY `idx_mentions_thread_id` (`thread_id`),
  KEY `idx_mentions_post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `moderator_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `moderator_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `target_type` varchar(30) DEFAULT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mod_actions_user` (`moderator_id`,`created_at`),
  KEY `idx_mod_actions_section` (`section_id`,`created_at`),
  KEY `idx_mod_actions_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `moment_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `cover_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `moments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` text,
  `images_json` longtext,
  `visibility` varchar(20) NOT NULL DEFAULT 'friends',
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_moments_user` (`user_id`,`id`),
  KEY `idx_moments_status` (`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `payment_callback_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` varchar(40) NOT NULL,
  `order_no` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `payload` longtext,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_callback_order` (`order_no`),
  KEY `idx_payment_callback_channel` (`channel`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `payment_channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `config_json` longtext,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payment_channel_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL,
  `pay_amount` decimal(18,2) NOT NULL,
  `channel` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `title` varchar(120) DEFAULT NULL,
  `trade_no` varchar(120) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payment_order_no` (`order_no`),
  KEY `idx_payment_orders_user` (`user_id`,`created_at`),
  KEY `idx_payment_orders_status` (`status`,`created_at`),
  KEY `idx_payment_orders_trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `payment_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL,
  `pay_amount` decimal(18,2) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_packages_currency` (`currency_code`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `payment_redeem_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL,
  `max_uses` int(10) unsigned NOT NULL DEFAULT '1',
  `used_count` int(10) unsigned NOT NULL DEFAULT '0',
  `used_by` bigint(20) unsigned DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payment_redeem_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `payment_redeem_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `redeem_code_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_redeem_user` (`redeem_code_id`,`user_id`),
  KEY `idx_redeem_logs_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `group_name` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `plugin_avatar_frames` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(80) NOT NULL,
    `name` VARCHAR(80) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `quality` VARCHAR(40) NOT NULL DEFAULT 'standard',
    `quality_name` VARCHAR(40) NOT NULL DEFAULT '标准',
    `quality_color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
    `grant_type` VARCHAR(20) NOT NULL DEFAULT 'manual',
    `rule_type` VARCHAR(40) NOT NULL DEFAULT 'manual',
    `rule_value` INT NOT NULL DEFAULT 0,
    `obtain_method` VARCHAR(30) NOT NULL DEFAULT 'grant',
    `price_currency` VARCHAR(20) DEFAULT NULL,
    `price_amount` DECIMAL(18,6) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_plugin_avatar_frames_code` (`code`),
    KEY `idx_plugin_avatar_frames_status_sort` (`status`, `sort_order`),
    KEY `idx_plugin_avatar_frames_grant` (`grant_type`, `rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_user_avatar_frames` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `frame_id` BIGINT UNSIGNED NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `grant_source` VARCHAR(30) NOT NULL DEFAULT 'manual',
    `is_equipped` TINYINT(1) NOT NULL DEFAULT 0,
    `notice_sent` TINYINT(1) NOT NULL DEFAULT 0,
    `granted_by` BIGINT UNSIGNED DEFAULT NULL,
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_plugin_user_avatar_frame` (`user_id`, `frame_id`),
    KEY `idx_plugin_user_avatar_frames_user` (`user_id`),
    KEY `idx_plugin_user_avatar_frames_frame` (`frame_id`),
    KEY `idx_plugin_user_avatar_frames_equipped` (`user_id`, `is_equipped`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_avatar_frame_qualities` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(40) NOT NULL,
    `color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_plugin_avatar_frame_qualities_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_avatar_frame_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `frame_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(30) NOT NULL,
    `source` VARCHAR(30) DEFAULT NULL,
    `operator_id` BIGINT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `ip` VARCHAR(64) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plugin_avatar_frame_logs_user` (`user_id`, `created_at`),
    KEY `idx_plugin_avatar_frame_logs_frame` (`frame_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO plugin_avatar_frame_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES
('legend','传奇','#b91c1c',10,'active',NOW(),NOW()),
('epic','史诗','#7c3aed',20,'active',NOW(),NOW()),
('rare','稀有','#2563eb',30,'active',NOW(),NOW()),
('standard','标准','#64748b',40,'active',NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), sort_order=VALUES(sort_order), status=VALUES(status), updated_at=NOW();
CREATE TABLE IF NOT EXISTS `plugin_badge_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `badge_id` bigint(20) unsigned NOT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_badge_logs_user` (`user_id`,`created_at`),
  KEY `idx_plugin_badge_logs_badge` (`badge_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_badge_qualities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_badge_qualities_code` (`code`),
  KEY `idx_plugin_badge_qualities_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_badges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#f59e0b',
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `grant_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `rule_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `rule_value` int(11) NOT NULL DEFAULT '0',
  `max_equipped` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_badges_code` (`code`),
  KEY `idx_plugin_badges_status_sort` (`status`,`sort_order`),
  KEY `idx_plugin_badges_category` (`category`),
  KEY `idx_plugin_badges_grant` (`grant_type`,`rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_slug` varchar(100) NOT NULL,
  `phase` varchar(40) NOT NULL DEFAULT 'boot',
  `message` text,
  `trace` mediumtext,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_error_slug` (`plugin_slug`,`created_at`),
  KEY `idx_plugin_error_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `plugin_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_slug` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_hash` varchar(128) NOT NULL,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_migration` (`plugin_slug`,`file_path`,`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `plugin_nameplate_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `nameplate_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(18,6) DEFAULT NULL,
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_nameplate_logs_user` (`user_id`,`created_at`),
  KEY `idx_plugin_nameplate_logs_plate` (`nameplate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_nameplates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `style_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aurora',
  `frame_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#38bdf8',
  `accent_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#a78bfa',
  `text_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0f172a',
  `custom_css` text COLLATE utf8mb4_unicode_ci,
  `price_currency` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_amount` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `obtain_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shop',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_nameplates_status_sort` (`status`,`sort_order`),
  KEY `idx_plugin_nameplates_method` (`obtain_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_user_avatar_frames` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `frame_id` bigint(20) unsigned NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grant_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `is_equipped` tinyint(1) NOT NULL DEFAULT '0',
  `notice_sent` tinyint(1) NOT NULL DEFAULT '0',
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_user_avatar_frame` (`user_id`,`frame_id`),
  KEY `idx_plugin_user_avatar_frames_user` (`user_id`),
  KEY `idx_plugin_user_avatar_frames_frame` (`frame_id`),
  KEY `idx_plugin_user_avatar_frames_equipped` (`user_id`,`is_equipped`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_user_badges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `badge_id` bigint(20) unsigned NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grant_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `is_equipped` tinyint(1) NOT NULL DEFAULT '1',
  `equip_slot` tinyint(3) unsigned DEFAULT NULL,
  `notice_sent` tinyint(1) NOT NULL DEFAULT '0',
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_user_badge` (`user_id`,`badge_id`),
  KEY `idx_plugin_user_badges_user` (`user_id`),
  KEY `idx_plugin_user_badges_badge` (`badge_id`),
  KEY `idx_plugin_user_badges_equipped` (`user_id`,`is_equipped`,`equip_slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `plugin_user_nameplates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `nameplate_id` bigint(20) unsigned NOT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grant',
  `source_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_equipped` tinyint(1) NOT NULL DEFAULT '0',
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `obtained_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_user_nameplate` (`user_id`,`nameplate_id`),
  KEY `idx_plugin_user_nameplates_user_equipped` (`user_id`,`is_equipped`),
  KEY `idx_plugin_user_nameplates_plate` (`nameplate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `reply_user_id` bigint(20) unsigned DEFAULT NULL,
  `content` longtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `like_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `is_accepted` tinyint(1) NOT NULL DEFAULT '0',
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posts_thread_id` (`thread_id`),
  KEY `idx_posts_user_id` (`user_id`),
  KEY `idx_posts_parent_id` (`parent_id`),
  KEY `idx_posts_accepted` (`thread_id`,`is_accepted`),
  FULLTEXT KEY `ft_posts_content` (`content`),
  CONSTRAINT `fk_posts_thread_id` FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `private_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_low_id` bigint(20) unsigned NOT NULL,
  `user_high_id` bigint(20) unsigned NOT NULL,
  `last_message_id` bigint(20) unsigned DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `hidden_for_low` tinyint(1) NOT NULL DEFAULT '0',
  `hidden_for_high` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_for_low` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_for_high` tinyint(1) NOT NULL DEFAULT '0',
  `muted_for_low` tinyint(1) NOT NULL DEFAULT '0',
  `muted_for_high` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_private_conversations_pair` (`user_low_id`,`user_high_id`),
  KEY `idx_private_conversations_low` (`user_low_id`,`last_message_at`),
  KEY `idx_private_conversations_high` (`user_high_id`,`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `private_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `receiver_id` bigint(20) unsigned NOT NULL,
  `content` text NOT NULL,
  `revoked_content` text DEFAULT NULL,
  `message_type` varchar(20) NOT NULL DEFAULT 'text',
  `media_url` varchar(255) DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `review_reason` varchar(500) DEFAULT NULL,
  `review_suggestion` varchar(500) DEFAULT NULL,
  `ai_result_json` longtext,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_private_messages_conversation` (`conversation_id`,`id`),
  KEY `idx_private_messages_receiver_status` (`receiver_id`,`status`,`read_at`,`id`),
  KEY `idx_private_messages_sender_status` (`sender_id`,`status`,`id`),
  KEY `idx_private_messages_type` (`message_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `question_answer_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `answer_user_id` bigint(20) unsigned NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `reason` varchar(1000) DEFAULT NULL,
  `raw_response` longtext,
  `status` varchar(20) NOT NULL DEFAULT 'scored',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_question_score_post` (`post_id`),
  KEY `idx_question_score_thread` (`thread_id`,`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `question_bounty_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `requester_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `high_score_post_id` bigint(20) unsigned DEFAULT NULL,
  `ai_snapshot` longtext,
  `reviewer_id` bigint(20) unsigned DEFAULT NULL,
  `review_note` varchar(500) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bounty_review_pending` (`thread_id`,`status`),
  KEY `idx_bounty_reviews_status` (`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `recycle_bin` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_type` varchar(30) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `snapshot` mediumtext,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `restored_at` datetime DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recycle_target` (`target_type`,`target_id`),
  KEY `idx_recycle_deleted` (`deleted_at`),
  KEY `idx_recycle_state` (`restored_at`,`purged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `reply_drafts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `content` longtext,
  `is_autosave` tinyint(1) NOT NULL DEFAULT '0',
  `review_status` varchar(30) DEFAULT NULL,
  `review_reason` varchar(500) DEFAULT NULL,
  `review_suggestion` varchar(500) DEFAULT NULL,
  `review_categories` varchar(255) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reply_drafts_user_id` (`user_id`),
  KEY `idx_reply_drafts_thread_id` (`thread_id`),
  KEY `idx_reply_drafts_user_autosave` (`user_id`,`thread_id`,`is_autosave`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `level` int(10) unsigned NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `section_follows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_section_follow` (`user_id`,`section_id`),
  KEY `idx_section_follow_section` (`section_id`),
  KEY `idx_section_follow_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `post_permission` varchar(30) NOT NULL DEFAULT 'login',
  `moderation_mode` varchar(20) NOT NULL DEFAULT 'normal',
  `is_question` tinyint(1) NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `thread_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sections_slug` (`slug`),
  KEY `idx_sections_category_id` (`category_id`),
  CONSTRAINT `fk_sections_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `market_extensions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `extension_type` varchar(20) NOT NULL DEFAULT 'plugin',
  `slug` varchar(100) NOT NULL,
  `name` varchar(160) DEFAULT NULL,
  `version` varchar(60) DEFAULT NULL,
  `license_required` tinyint(1) NOT NULL DEFAULT '0',
  `license_key` varchar(160) DEFAULT NULL,
  `package_hash` varchar(64) DEFAULT NULL,
  `manifest_hash` varchar(64) DEFAULT NULL,
  `manifest_json` mediumtext,
  `installed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_ext_type_slug` (`extension_type`,`slug`),
  KEY `idx_market_ext_license` (`license_required`),
  KEY `idx_market_ext_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `system_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `ref_type` varchar(40) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '优先级: 0=普通, 1=重要, 2=紧急',
  `category` varchar(20) NOT NULL DEFAULT 'system' COMMENT '消息分类: fans=粉丝, reply=回复, like=点赞, favorite=收藏, private=私聊, review=审核, finance=财务, system=系统',
  `target_type` varchar(20) NOT NULL DEFAULT 'all' COMMENT '目标类型: all=全部, role=指定角色, user=指定用户',
  `target_roles` json DEFAULT NULL COMMENT '目标角色ID列表 [1,2,3]',
  `target_users` json DEFAULT NULL COMMENT '目标用户ID列表 [1,2,3]',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT '状态: active=启用, draft=草稿, archived=归档',
  `sent_at` datetime DEFAULT NULL COMMENT '发送时间',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_status` (`status`),
  KEY `idx_messages_priority` (`priority`),
  KEY `idx_messages_category` (`category`),
  KEY `idx_messages_sent_at` (`sent_at`),
  KEY `idx_messages_ref` (`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `system_updates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `description` text,
  `applied_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_updates_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `task_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `cycle_key` varchar(40) NOT NULL DEFAULT 'once',
  `content` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `review_note` varchar(255) DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_sub_user` (`user_id`),
  KEY `idx_task_sub_task` (`task_id`),
  KEY `idx_task_sub_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'daily',
  `action` varchar(60) NOT NULL DEFAULT 'manual',
  `cycle_type` varchar(20) NOT NULL DEFAULT 'once',
  `target_count` int(10) unsigned NOT NULL DEFAULT '1',
  `reward_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `reward_currencies` json DEFAULT NULL,
  `manual_review` tinyint(1) NOT NULL DEFAULT '0',
  `claim_required` tinyint(1) NOT NULL DEFAULT '0',
  `max_claims_per_user` int(10) unsigned NOT NULL DEFAULT '1',
  `once_per_ref` tinyint(1) NOT NULL DEFAULT '0',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_action` (`action`),
  KEY `idx_tasks_category` (`category`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_cycle` (`cycle_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_collection_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `collection_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collection_thread` (`collection_id`,`thread_id`),
  KEY `idx_collection_items_thread` (`thread_id`),
  KEY `idx_collection_items_sort` (`collection_id`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `thread_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `thread_count` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_collections_user` (`user_id`,`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `thread_drafts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` longtext,
  `review_status` varchar(30) DEFAULT NULL,
  `review_reason` varchar(500) DEFAULT NULL,
  `review_suggestion` varchar(500) DEFAULT NULL,
  `review_categories` varchar(255) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `is_autosave` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_drafts_user_id` (`user_id`),
  KEY `idx_thread_drafts_user_autosave_updated` (`user_id`,`is_autosave`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_edit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `editor_id` bigint(20) unsigned NOT NULL,
  `editor_type` varchar(20) NOT NULL DEFAULT 'user',
  `old_title` varchar(200) DEFAULT NULL,
  `new_title` varchar(200) DEFAULT NULL,
  `old_content` longtext,
  `new_content` longtext,
  `old_section_id` bigint(20) unsigned DEFAULT NULL,
  `new_section_id` bigint(20) unsigned DEFAULT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_edit_logs_thread_id` (`thread_id`),
  KEY `idx_thread_edit_logs_editor_id` (`editor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_favorites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fav_user_thread` (`user_id`,`thread_id`),
  KEY `idx_fav_thread` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_paywall_unlocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_thread_paywall_user` (`thread_id`,`user_id`),
  KEY `idx_thread_paywall_thread` (`thread_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_read_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `last_post_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_thread_read_user` (`user_id`,`thread_id`),
  KEY `idx_thread_read_user_updated` (`user_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `content` longtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `review_reason` varchar(500) DEFAULT NULL,
  `review_suggestion` varchar(500) DEFAULT NULL,
  `ai_result_json` longtext,
  `reviewer_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `is_autosave` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_revisions_status` (`status`,`created_at`),
  KEY `idx_thread_revisions_thread` (`thread_id`,`status`),
  KEY `idx_thread_revisions_user` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `thread_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_rewards_thread` (`thread_id`,`created_at`),
  KEY `idx_thread_rewards_user` (`user_id`),
  KEY `idx_thread_rewards_author` (`author_id`),
  KEY `idx_thread_rewards_rank` (`thread_id`,`currency_code`,`amount`),
  CONSTRAINT `fk_thread_rewards_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_thread_rewards_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_thread_rewards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `threads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `content` longtext NOT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `is_top` tinyint(1) NOT NULL DEFAULT '0',
  `top_scope` varchar(20) NOT NULL DEFAULT 'none',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `featured_reason` varchar(255) DEFAULT NULL,
  `is_recommended` tinyint(1) NOT NULL DEFAULT '0',
  `recommended_reason` varchar(255) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `view_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `like_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `favorite_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `report_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `read_complete_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `reply_count` bigint(20) unsigned NOT NULL DEFAULT '0',
  `paid_visible_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `paid_visible_price` decimal(18,6) DEFAULT NULL,
  `paid_visible_currency` varchar(20) DEFAULT NULL,
  `question_status` varchar(20) NOT NULL DEFAULT 'none',
  `bounty_currency` varchar(20) DEFAULT NULL,
  `bounty_amount` decimal(18,6) DEFAULT NULL,
  `accepted_post_id` bigint(20) unsigned DEFAULT NULL,
  `accepted_user_id` bigint(20) unsigned DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `last_reply_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_threads_user_id` (`user_id`),
  KEY `idx_threads_section_id` (`section_id`),
  KEY `idx_threads_top_scope` (`top_scope`,`is_top`,`status`,`created_at`),
  KEY `idx_threads_question` (`question_status`,`accepted_post_id`),
  FULLTEXT KEY `ft_threads_title_content` (`title`,`content`),
  CONSTRAINT `fk_threads_section_id` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_threads_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `update_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `migration_file` varchar(255) NOT NULL,
  `checksum` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `error_message` text,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_update_migration` (`version`,`migration_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blocker_id` bigint(20) unsigned NOT NULL,
  `blocked_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_block_pair` (`blocker_id`,`blocked_id`),
  KEY `idx_blocked` (`blocked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `checkin_date` date NOT NULL,
  `streak_days` int(10) unsigned NOT NULL DEFAULT '1',
  `reward_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `reward_currency_code` varchar(20) DEFAULT NULL,
  `reward_currency_amount` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_checkin_user_date` (`user_id`,`checkin_date`),
  KEY `idx_checkin_user_date` (`user_id`,`checkin_date`),
  KEY `idx_checkin_date` (`checkin_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_credit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score_change` int(11) NOT NULL DEFAULT '0',
  `before_score` int(11) NOT NULL DEFAULT '0',
  `after_score` int(11) NOT NULL DEFAULT '0',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_credit_logs_user` (`user_id`,`created_at`),
  KEY `idx_credit_logs_ref` (`ref_type`,`ref_id`,`action`),
  KEY `idx_credit_logs_action` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_credit_stats` (
  `user_id` bigint(20) unsigned NOT NULL,
  `score` int(11) NOT NULL DEFAULT '100',
  `recovered_at` datetime DEFAULT NULL,
  `valid_reports` int(10) unsigned NOT NULL DEFAULT '0',
  `invalid_reports` int(10) unsigned NOT NULL DEFAULT '0',
  `violations` int(10) unsigned NOT NULL DEFAULT '0',
  `manual_adjustments` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_user_credit_score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_daily_refreshes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `refresh_date` date NOT NULL,
  `source` varchar(40) NOT NULL DEFAULT 'web',
  `touched_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_daily_refresh` (`user_id`,`refresh_date`),
  KEY `idx_user_daily_refresh_date` (`refresh_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_exp_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(60) NOT NULL,
  `exp_change` int(11) NOT NULL DEFAULT '0',
  `before_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `after_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `before_level` int(10) unsigned NOT NULL DEFAULT '1',
  `after_level` int(10) unsigned NOT NULL DEFAULT '1',
  `ref_type` varchar(40) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exp_user` (`user_id`),
  KEY `idx_exp_action` (`action`),
  KEY `idx_exp_created` (`created_at`),
  KEY `idx_exp_ref` (`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_follows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` bigint(20) unsigned NOT NULL,
  `following_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_follow_pair` (`follower_id`,`following_id`),
  KEY `idx_following_id` (`following_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_growth_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `total_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `current_level` int(10) unsigned NOT NULL DEFAULT '1',
  `today_exp` int(10) unsigned NOT NULL DEFAULT '0',
  `today_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_growth_user` (`user_id`),
  KEY `idx_growth_level` (`current_level`),
  KEY `idx_growth_exp` (`total_exp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_login_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_hash` char(64) NOT NULL,
  `device_name` varchar(120) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `login_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_active_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_login_session` (`session_hash`),
  KEY `idx_login_sessions_user` (`user_id`,`last_active_at`),
  KEY `idx_login_sessions_revoked` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_message_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `message_id` bigint(20) unsigned NOT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_message` (`user_id`,`message_id`),
  KEY `idx_reads_user` (`user_id`),
  KEY `idx_reads_message` (`message_id`),
  CONSTRAINT `fk_reads_message` FOREIGN KEY (`message_id`) REFERENCES `system_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_notification_settings` (
  `user_id` bigint(20) unsigned NOT NULL,
  `follow_post` tinyint(1) NOT NULL DEFAULT '1',
  `reply` tinyint(1) NOT NULL DEFAULT '1',
  `mention` tinyint(1) NOT NULL DEFAULT '1',
  `fans` tinyint(1) NOT NULL DEFAULT '1',
  `like` tinyint(1) NOT NULL DEFAULT '1',
  `favorite` tinyint(1) NOT NULL DEFAULT '1',
  `private_chat` tinyint(1) NOT NULL DEFAULT '1',
  `review_notice` tinyint(1) NOT NULL DEFAULT '1',
  `report_notice` tinyint(1) NOT NULL DEFAULT '1',
  `moderation_notice` tinyint(1) NOT NULL DEFAULT '1',
  `system_notice` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_oauth_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(30) NOT NULL,
  `openid` varchar(128) NOT NULL,
  `unionid` varchar(128) DEFAULT NULL,
  `nickname` varchar(120) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `access_token_json` text,
  `bound_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_oauth_provider_openid` (`provider`,`openid`),
  UNIQUE KEY `uniq_oauth_user_provider` (`user_id`,`provider`),
  KEY `idx_oauth_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_privacy_settings` (
  `user_id` bigint(20) unsigned NOT NULL,
  `disallow_follow` tinyint(1) NOT NULL DEFAULT '0',
  `hide_following` tinyint(1) NOT NULL DEFAULT '0',
  `hide_followers` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_privacy_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_reading_list` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `thread_id` bigint(20) unsigned NOT NULL,
  `list_type` varchar(20) NOT NULL DEFAULT 'later',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reading_user_thread_type` (`user_id`,`thread_id`,`list_type`),
  KEY `idx_reading_user_type` (`user_id`,`list_type`,`updated_at`),
  KEY `idx_reading_thread` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `scope` varchar(20) NOT NULL DEFAULT 'global',
  `scope_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role_scope` (`user_id`,`role_id`,`scope`,`scope_id`),
  KEY `idx_ur_user_id` (`user_id`),
  KEY `fk_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_task_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `cycle_key` varchar(40) NOT NULL DEFAULT 'once',
  `progress` int(10) unsigned NOT NULL DEFAULT '0',
  `target_count` int(10) unsigned NOT NULL DEFAULT '1',
  `status` varchar(20) NOT NULL DEFAULT 'doing',
  `completed_at` datetime DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_progress` (`user_id`,`task_id`,`cycle_key`),
  KEY `idx_task_progress_task` (`task_id`),
  KEY `idx_task_progress_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_task_refs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `ref_type` varchar(40) NOT NULL,
  `ref_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_ref` (`user_id`,`task_id`,`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type_id` bigint(20) unsigned NOT NULL,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `display_name` varchar(24) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `verified_at` datetime DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` bigint(20) unsigned DEFAULT NULL,
  `revoke_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_verifications_user` (`user_id`,`status`),
  KEY `idx_user_verifications_type` (`type_id`),
  CONSTRAINT `fk_user_verifications_type` FOREIGN KEY (`type_id`) REFERENCES `verification_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(32) DEFAULT NULL,
  `public_id_style` varchar(64) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `bio` text,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `role` varchar(30) NOT NULL DEFAULT 'user',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verify_token` varchar(64) DEFAULT NULL,
  `email_verify_expires_at` datetime DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_public_id` (`public_id`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `verification_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type_id` bigint(20) unsigned NOT NULL,
  `display_name` varchar(24) DEFAULT NULL,
  `real_name` varchar(80) DEFAULT NULL,
  `material` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `review_note` varchar(255) DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_verification_requests_user` (`user_id`),
  KEY `idx_verification_requests_status` (`status`),
  KEY `idx_verification_requests_type` (`type_id`),
  CONSTRAINT `fk_verification_requests_type` FOREIGN KEY (`type_id`) REFERENCES `verification_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_verification_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `verification_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#2563eb',
  `description` varchar(255) DEFAULT NULL,
  `apply_note` varchar(255) DEFAULT NULL,
  `allow_apply` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `amount` decimal(18,6) NOT NULL,
  `balance_after` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `type` varchar(30) NOT NULL DEFAULT 'adjust',
  `operator_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `remark` varchar(255) DEFAULT NULL,
  `ref_type` varchar(30) DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_tx_user` (`user_id`),
  KEY `idx_wallet_tx_currency` (`currency_code`),
  KEY `idx_wallet_tx_ref` (`ref_type`,`ref_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `currency_code` varchar(20) NOT NULL,
  `balance` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `locked_balance` decimal(18,6) NOT NULL DEFAULT '0.000000',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wallet_user_currency` (`user_id`,`currency_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `chat_group_invites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `inviter_user_id` BIGINT UNSIGNED NOT NULL,
  `invitee_user_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `decided_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_group_invite_token` (`token`),
  KEY `idx_chat_group_invites_group` (`group_id`),
  KEY `idx_chat_group_invites_invitee` (`invitee_user_id`),
  KEY `idx_chat_group_invites_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_group_join_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `message` VARCHAR(255) DEFAULT NULL,
  `handled_by` BIGINT UNSIGNED DEFAULT NULL,
  `handled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_group_join_request` (`group_id`,`user_id`),
  KEY `idx_chat_group_join_requests_user` (`user_id`),
  KEY `idx_chat_group_join_requests_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `reporter_id` bigint(20) unsigned NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','processed','rejected') DEFAULT 'pending',
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `admin_note` text,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_group` (`group_id`),
  KEY `idx_reporter` (`reporter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_report_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `message_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `message_text` text,
  `message_type` varchar(20) DEFAULT 'text',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_report_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `action_type` enum('ban','warn','reject') NOT NULL,
  `target_user_id` bigint(20) unsigned NOT NULL,
  `ban_duration` int(11) DEFAULT NULL COMMENT '封禁天数，0=永久',
  `ban_reason` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_id`),
  KEY `idx_user` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Software store tables
CREATE TABLE IF NOT EXISTS software_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    icon VARCHAR(255) DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY uk_category_slug (slug),
    KEY idx_category_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS softwares (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    icon VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    detail LONGTEXT DEFAULT NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'android',
    type VARCHAR(20) DEFAULT '',
    is_recommended TINYINT(1) NOT NULL DEFAULT 0,
    recommended_at DATETIME DEFAULT NULL,
    category_id BIGINT UNSIGNED DEFAULT NULL,
    uploader_id BIGINT UNSIGNED NOT NULL,
    developer VARCHAR(120) DEFAULT NULL,
    version VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    size VARCHAR(50) DEFAULT NULL,
    download_url VARCHAR(500) NOT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    rating_avg DECIMAL(2,1) NOT NULL DEFAULT 0.0,
    rating_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    admin_note TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY uk_software_slug (slug),
    KEY idx_software_status (status, created_at DESC),
    KEY idx_software_platform (platform, status),
    KEY idx_software_category (category_id, status),
    KEY idx_software_uploader (uploader_id, status),
    KEY idx_software_recommended (is_recommended, recommended_at),
    KEY idx_software_rating (rating_avg DESC, rating_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS software_screenshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_screenshot_software (software_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS software_downloads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY uk_download_software_ip (software_id, ip),
    KEY idx_download_software (software_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS software_ratings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY uk_rating_software_user (software_id, user_id),
    KEY idx_rating_software (software_id, rating DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS software_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    likes INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_review_software (software_id, status, created_at DESC),
    KEY idx_review_user (user_id, created_at DESC),
    KEY idx_review_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS software_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_submission_software (software_id),
    KEY idx_submission_user (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE IF NOT EXISTS software_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    uploader_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(50) NOT NULL,
    size VARCHAR(50) DEFAULT NULL,
    download_url VARCHAR(500) NOT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    changelog LONGTEXT DEFAULT NULL,
    icon VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    published_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_sv_software_status (software_id, status),
    KEY idx_sv_status_created (status, created_at),
    KEY idx_sv_uploader (uploader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS software_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#3cc9a4',
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY uniq_slug (slug),
    KEY idx_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




INSERT INTO software_categories (name, slug, icon, sort_order, status) VALUES
('游戏', 'games', 'fa-gamepad', 1, 'active'),
('工具', 'tools', 'fa-wrench', 2, 'active'),
('社交', 'social', 'fa-users', 3, 'active'),
('娱乐', 'entertainment', 'fa-film', 4, 'active'),
('学习', 'education', 'fa-graduation-cap', 5, 'active'),
('办公', 'productivity', 'fa-briefcase', 6, 'active'),
('系统', 'system', 'fa-cog', 7, 'active'),
('其他', 'others', 'fa-ellipsis-h', 99, 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), status=VALUES(status);




INSERT INTO software_types (name, slug, color, sort_order, status) VALUES
('搬运', 'repost', '#7c72ff', 1, 'active'),
('原创', 'original', '#19be6b', 2, 'active'),
('金标', 'gold', '#ff6600', 3, 'active'),
('官方', 'official', '#2979ff', 4, 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), sort_order=VALUES(sort_order), status=VALUES(status);




INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('site_mode', 'forum', NOW()),
('software_store_enabled', '1', NOW())
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW();



-- Additional full install tables
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'system',
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notif_type` (`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_chat_bubbles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `effect_type` VARCHAR(40) DEFAULT NULL,
  `effect_params` TEXT DEFAULT NULL,
  `quality` VARCHAR(40) NOT NULL DEFAULT 'standard',
  `quality_name` VARCHAR(40) NOT NULL DEFAULT '标准',
  `quality_color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
  `obtain_method` VARCHAR(30) NOT NULL DEFAULT 'grant',
  `price_currency` VARCHAR(20) DEFAULT NULL,
  `price_amount` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `grant_type` VARCHAR(20) NOT NULL DEFAULT 'manual',
  `rule_type` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `rule_value` INT NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_chat_bubbles_code` (`code`),
  KEY `idx_plugin_chat_bubbles_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_user_chat_bubbles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `bubble_id` BIGINT UNSIGNED NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `grant_source` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `is_equipped` TINYINT(1) NOT NULL DEFAULT 0,
  `notice_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `granted_by` BIGINT UNSIGNED DEFAULT NULL,
  `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_user_chat_bubble` (`user_id`, `bubble_id`),
  KEY `idx_plugin_user_chat_bubbles_user` (`user_id`),
  KEY `idx_plugin_user_chat_bubbles_bubble` (`bubble_id`),
  KEY `idx_plugin_user_chat_bubbles_equipped` (`user_id`, `is_equipped`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_bubble_qualities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(40) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_bubble_qualities_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plugin_chat_bubble_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `bubble_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(30) NOT NULL,
  `source` VARCHAR(30) DEFAULT NULL,
  `operator_id` BIGINT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_chat_bubble_logs_user` (`user_id`, `created_at`),
  KEY `idx_plugin_chat_bubble_logs_bubble` (`bubble_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;





INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
('site_name', 'ClayBBS', NOW(), NOW()),
('site_description', '一个现代化的论坛系统', NOW(), NOW()),
('registration_enabled', '1', NOW(), NOW()),
('group_chat_enabled', '1', NOW(), NOW()),
('group_chat_review_enabled', '0', NOW(), NOW())
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = NOW();

INSERT IGNORE INTO `tasks` (`id`,`title`,`description`,`category`,`action`,`cycle_type`,`target_count`,`reward_exp`,`reward_currencies`,`manual_review`,`claim_required`,`max_claims_per_user`,`once_per_ref`,`status`,`sort_order`) VALUES
(1,'每日登录','每日登录即可获得经验奖励。','daily','login_daily','daily',1,5,JSON_ARRAY(),0,0,1,1,'active',10),
(2,'每日发帖','发布 1 篇审核通过的帖子。','daily','thread_publish','daily',1,20,JSON_ARRAY(),0,0,1,1,'active',20),
(3,'每日回复','发布 3 条审核通过的回复。','daily','post_publish','daily',3,15,JSON_ARRAY(),0,0,1,1,'active',30),
(4,'完善资料','上传头像并填写个人简介。','newbie','profile_completed','once',1,30,JSON_ARRAY(),0,0,1,0,'active',40),
(5,'邮箱验证','完成邮箱验证。','newbie','email_verified','once',1,30,JSON_ARRAY(),0,0,1,0,'active',50),
(6,'认证通过','通过任意一种用户认证。','newbie','verification_approved','once',1,100,JSON_ARRAY(),0,0,1,0,'active',60),
(7,'绑定登录方式','绑定 QQ、GitHub、微信或彩虹聚合登录中的任意一种即可完成。','newbie','oauth_bound','once',1,30,JSON_ARRAY(),0,0,1,0,'active',70);
