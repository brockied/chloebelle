-- Chloe Belle Website Database Schema
-- This file contains the complete database structure

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','moderator','user','chloe') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','banned','pending') NOT NULL DEFAULT 'pending',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `subscription_status` enum('none','monthly','yearly','lifetime') NOT NULL DEFAULT 'none',
  `subscription_expires` datetime DEFAULT NULL,
  `subscription_id` varchar(100) DEFAULT NULL,
  `payment_customer_id` varchar(100) DEFAULT NULL,
  `language` varchar(5) NOT NULL DEFAULT 'en',
  `currency` varchar(3) NOT NULL DEFAULT 'GBP',
  `timezone` varchar(50) NOT NULL DEFAULT 'Europe/London',
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_subscription` (`subscription_status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `posts`
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `media_type` enum('none','image','video','gallery') NOT NULL DEFAULT 'none',
  `media_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `media_metadata` json DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `is_blurred` tinyint(1) NOT NULL DEFAULT 0,
  `subscription_required` enum('none','monthly','yearly','lifetime') NOT NULL DEFAULT 'none',
  `one_time_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'GBP',
  `views` int(11) NOT NULL DEFAULT 0,
  `likes` int(11) NOT NULL DEFAULT 0,
  `comments_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','published','archived','deleted') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_order` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_premium` (`is_premium`),
  KEY `idx_featured` (`featured`),
  KEY `idx_created` (`created_at`),
  KEY `idx_scheduled` (`scheduled_at`),
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `comments`
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','rejected','hidden') NOT NULL DEFAULT 'pending',
  `likes` int(11) NOT NULL DEFAULT 0,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `likes`
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `type` enum('like','dislike','heart','fire') NOT NULL DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_post_like` (`user_id`, `post_id`),
  UNIQUE KEY `unique_user_comment_like` (`user_id`, `comment_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_comment_id` (`comment_id`),
  CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `subscriptions`
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_type` enum('monthly','yearly','lifetime') NOT NULL,
  `status` enum('active','inactive','canceled','expired','trial') NOT NULL DEFAULT 'active',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'GBP',
  `payment_gateway` enum('stripe','paypal','manual') NOT NULL,
  `gateway_subscription_id` varchar(100) DEFAULT NULL,
  `gateway_customer_id` varchar(100) DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `canceled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
  `payment_method` varchar(50) DEFAULT NULL,
  `last_payment_at` datetime DEFAULT NULL,
  `next_payment_at` datetime DEFAULT NULL,
  `failed_payments` int(11) NOT NULL DEFAULT 0,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_gateway_sub_id` (`gateway_subscription_id`),
  KEY `idx_ends_at` (`ends_at`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `payments`
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `type` enum('subscription','one_time','refund','chargeback') NOT NULL DEFAULT 'subscription',
  `status` enum('pending','completed','failed','canceled','refunded') NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'GBP',
  `payment_gateway` enum('stripe','paypal','manual') NOT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `gateway_invoice_id` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refund_reason` text DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_gateway_payment_id` (`gateway_payment_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `user_post_access`
CREATE TABLE `user_post_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `access_type` enum('subscription','one_time','gift','admin') NOT NULL DEFAULT 'subscription',
  `payment_id` int(11) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_post_access` (`user_id`, `post_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_granted_by` (`granted_by`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_access_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `site_settings`
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` longtext DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json','text') NOT NULL DEFAULT 'string',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `blocked_users`
CREATE TABLE `blocked_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`blocker_id`, `blocked_id`),
  KEY `idx_blocker_id` (`blocker_id`),
  KEY `idx_blocked_id` (`blocked_id`),
  CONSTRAINT `fk_block_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `login_logs`
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed','blocked') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_login_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `admin_logs`
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_target` (`target_type`, `target_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_admin_logs_user` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `email_templates`
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_key` varchar(100) NOT NULL UNIQUE,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `body_text` longtext DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `language` varchar(5) NOT NULL DEFAULT 'en',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_template_key` (`template_key`),
  KEY `idx_language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `email_queue`
CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `body_text` longtext DEFAULT NULL,
  `headers` json DEFAULT NULL,
  `priority` tinyint(4) NOT NULL DEFAULT 5,
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(4) NOT NULL DEFAULT 3,
  `status` enum('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `profanity_filter`
CREATE TABLE `profanity_filter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL,
  `replacement` varchar(100) DEFAULT '***',
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `language` varchar(5) NOT NULL DEFAULT 'en',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_word_language` (`word`, `language`),
  KEY `idx_language` (`language`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Insert default settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`) VALUES
('site_name', 'Chloe Belle', 'string', 'general', 'Site name displayed in title and headers', 1),
('site_description', 'Exclusive AI-generated content and experiences', 'string', 'general', 'Site meta description for SEO', 1),
('site_keywords', 'AI influencer, exclusive content, premium photos, subscription', 'string', 'general', 'Site meta keywords for SEO', 1),
('default_currency', 'GBP', 'string', 'payments', 'Default currency for payments', 1),
('supported_currencies', '["GBP","USD"]', 'json', 'payments', 'List of supported currencies', 1),
('default_language', 'en', 'string', 'general', 'Default site language', 1),
('registration_enabled', '1', 'boolean', 'users', 'Allow new user registrations', 1),
('email_verification_required', '1', 'boolean', 'users', 'Require email verification for new accounts', 0),
('comments_enabled', '1', 'boolean', 'content', 'Enable comments on posts', 1),
('comments_moderation', '1', 'boolean', 'content', 'Moderate comments before showing', 0),
('likes_enabled', '1', 'boolean', 'content', 'Enable likes on posts and comments', 1),
('profanity_filter_enabled', '1', 'boolean', 'content', 'Enable profanity filtering', 0),
('blur_protection_enabled', '1', 'boolean', 'content', 'Enable blur protection for premium content', 1),
('watermark_enabled', '1', 'boolean', 'content', 'Enable watermarks on images', 0),
('max_upload_size', '52428800', 'integer', 'uploads', 'Maximum upload size in bytes (50MB)', 0),
('allowed_image_types', '["jpg","jpeg","png","gif","webp"]', 'json', 'uploads', 'Allowed image file extensions', 0),
('allowed_video_types', '["mp4","mov","avi","wmv"]', 'json', 'uploads', 'Allowed video file extensions', 0),
('subscription_monthly_price_gbp', '9.99', 'string', 'subscriptions', 'Monthly subscription price in GBP', 1),
('subscription_monthly_price_usd', '12.99', 'string', 'subscriptions', 'Monthly subscription price in USD', 1),
('subscription_yearly_price_gbp', '99.99', 'string', 'subscriptions', 'Yearly subscription price in GBP', 1),
('subscription_yearly_price_usd', '129.99', 'string', 'subscriptions', 'Yearly subscription price in USD', 1),
('subscription_lifetime_price_gbp', '299.99', 'string', 'subscriptions', 'Lifetime subscription price in GBP', 1),
('subscription_lifetime_price_usd', '399.99', 'string', 'subscriptions', 'Lifetime subscription price in USD', 1),
('free_posts_limit', '3', 'integer', 'subscriptions', 'Number of free posts non-subscribers can view', 1),
('payment_gateway', 'sandbox', 'string', 'payments', 'Active payment gateway (sandbox, stripe, paypal)', 0),
('maintenance_mode', '0', 'boolean', 'general', 'Enable maintenance mode', 0),
('maintenance_message', 'Site is under maintenance. Please check back later.', 'text', 'general', 'Maintenance mode message', 1);

-- --------------------------------------------------------

-- Insert default email templates
INSERT INTO `email_templates` (`template_key`, `subject`, `body_html`, `body_text`, `variables`, `language`) VALUES
('welcome', 'Welcome to Chloe Belle!', 
'<h2>Welcome {{username}}!</h2><p>Thank you for joining Chloe Belle. Please verify your email by clicking the link below:</p><p><a href="{{verification_link}}">Verify Email</a></p>', 
'Welcome {{username}}! Thank you for joining Chloe Belle. Please verify your email: {{verification_link}}', 
'["username", "verification_link"]', 'en'),

('email_verification', 'Please verify your email address', 
'<h2>Email Verification</h2><p>Hi {{username}},</p><p>Please click the link below to verify your email address:</p><p><a href="{{verification_link}}">Verify Email</a></p><p>This link will expire in 24 hours.</p>',
'Hi {{username}}, Please verify your email: {{verification_link}} (expires in 24 hours)', 
'["username", "verification_link"]', 'en'),

('password_reset', 'Reset your password', 
'<h2>Password Reset</h2><p>Hi {{username}},</p><p>You requested a password reset. Click the link below to reset your password:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>This link will expire in 1 hour.</p>',
'Hi {{username}}, Reset your password: {{reset_link}} (expires in 1 hour)', 
'["username", "reset_link"]', 'en'),

('subscription_success', 'Subscription Activated!', 
'<h2>Welcome to Premium!</h2><p>Hi {{username}},</p><p>Your {{plan_type}} subscription has been activated successfully!</p><p>You now have access to all premium content.</p><p><a href="{{site_url}}">Start Exploring</a></p>',
'Hi {{username}}, Your {{plan_type}} subscription is now active! Start exploring: {{site_url}}', 
'["username", "plan_type", "site_url"]', 'en'),

('subscription_canceled', 'Subscription Canceled', 
'<h2>Subscription Canceled</h2><p>Hi {{username}},</p><p>Your subscription has been canceled. You will continue to have access until {{expires_at}}.</p><p>We hope to see you back soon!</p>',
'Hi {{username}}, Your subscription has been canceled. Access continues until {{expires_at}}', 
'["username", "expires_at"]', 'en'),

('payment_failed', 'Payment Failed', 
'<h2>Payment Issue</h2><p>Hi {{username}},</p><p>We had trouble processing your payment for your {{plan_type}} subscription.</p><p>Please update your payment method to continue enjoying premium access.</p><p><a href="{{payment_link}}">Update Payment Method</a></p>',
'Hi {{username}}, Payment failed for {{plan_type}} subscription. Update payment method: {{payment_link}}', 
'["username", "plan_type", "payment_link"]', 'en');

-- --------------------------------------------------------

-- Insert default profanity words (basic set)
INSERT INTO `profanity_filter` (`word`, `replacement`, `severity`, `language`) VALUES
('damn', 'd***', 'low', 'en'),
('hell', 'h***', 'low', 'en'),
('crap', 'c***', 'low', 'en'),
('shit', 's***', 'medium', 'en'),
('fuck', 'f***', 'high', 'en'),
('bitch', 'b****', 'high', 'en'),
('ass', 'a**', 'medium', 'en'),
('bastard', 'b******', 'high', 'en');

-- --------------------------------------------------------

-- Create indexes for better performance
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_posts_user_status ON posts(user_id, status);
CREATE INDEX idx_posts_premium_featured ON posts(is_premium, featured);
CREATE INDEX idx_comments_post_status ON comments(post_id, status);
CREATE INDEX idx_subscriptions_user_status ON subscriptions(user_id, status);
CREATE INDEX idx_payments_user_status ON payments(user_id, status);

-- --------------------------------------------------------

-- Create triggers for automatic updates

-- Trigger to update post comment count
DELIMITER $
CREATE TRIGGER update_post_comments_count 
AFTER INSERT ON comments 
FOR EACH ROW
BEGIN
    UPDATE posts 
    SET comments_count = (
        SELECT COUNT(*) 
        FROM comments 
        WHERE post_id = NEW.post_id AND status = 'approved'
    ) 
    WHERE id = NEW.post_id;
END$

CREATE TRIGGER update_post_comments_count_delete 
AFTER DELETE ON comments 
FOR EACH ROW
BEGIN
    UPDATE posts 
    SET comments_count = (
        SELECT COUNT(*) 
        FROM comments 
        WHERE post_id = OLD.post_id AND status = 'approved'
    ) 
    WHERE id = OLD.post_id;
END$

-- Trigger to update post likes count
CREATE TRIGGER update_post_likes_count 
AFTER INSERT ON likes 
FOR EACH ROW
BEGIN
    IF NEW.post_id IS NOT NULL THEN
        UPDATE posts 
        SET likes = (
            SELECT COUNT(*) 
            FROM likes 
            WHERE post_id = NEW.post_id
        ) 
        WHERE id = NEW.post_id;
    END IF;
END$

CREATE TRIGGER update_post_likes_count_delete 
AFTER DELETE ON likes 
FOR EACH ROW
BEGIN
    IF OLD.post_id IS NOT NULL THEN
        UPDATE posts 
        SET likes = (
            SELECT COUNT(*) 
            FROM likes 
            WHERE post_id = OLD.post_id
        ) 
        WHERE id = OLD.post_id;
    END IF;
END$

-- Trigger to update user subscription status
CREATE TRIGGER update_user_subscription_status 
AFTER INSERT ON subscriptions 
FOR EACH ROW
BEGIN
    UPDATE users 
    SET subscription_status = NEW.plan_type,
        subscription_expires = NEW.ends_at,
        subscription_id = NEW.gateway_subscription_id
    WHERE id = NEW.user_id;
END$

CREATE TRIGGER update_user_subscription_status_update 
AFTER UPDATE ON subscriptions 
FOR EACH ROW
BEGIN
    UPDATE users 
    SET subscription_status = CASE 
        WHEN NEW.status = 'active' THEN NEW.plan_type 
        ELSE 'none' 
    END,
    subscription_expires = CASE 
        WHEN NEW.status = 'active' THEN NEW.ends_at 
        ELSE NULL 
    END
    WHERE id = NEW.user_id;
END$

DELIMITER ;

-- --------------------------------------------------------

-- Create views for common queries

-- View for active subscribers
CREATE VIEW active_subscribers AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.subscription_status,
    u.subscription_expires,
    s.amount,
    s.currency,
    s.payment_gateway,
    s.auto_renew,
    s.created_at as subscribed_at
FROM users u
INNER JOIN subscriptions s ON u.id = s.user_id
WHERE s.status = 'active' 
AND (s.ends_at IS NULL OR s.ends_at > NOW());

-- View for published posts with user info
CREATE VIEW published_posts AS
SELECT 
    p.id,
    p.title,
    p.content,
    p.media_type,
    p.media_url,
    p.thumbnail_url,
    p.is_premium,
    p.subscription_required,
    p.one_time_price,
    p.currency,
    p.views,
    p.likes,
    p.comments_count,
    p.featured,
    p.created_at,
    u.username,
    u.avatar,
    u.role
FROM posts p
INNER JOIN users u ON p.user_id = u.id
WHERE p.status = 'published'
AND (p.scheduled_at IS NULL OR p.scheduled_at <= NOW());

-- View for user statistics
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.status,
    u.subscription_status,
    u.created_at,
    u.last_login,
    COUNT(DISTINCT p.id) as total_posts,
    COUNT(DISTINCT c.id) as total_comments,
    COUNT(DISTINCT l.id) as total_likes_given,
    COALESCE(SUM(pay.amount), 0) as total_spent
FROM users u
LEFT JOIN posts p ON u.id = p.user_id
LEFT JOIN comments c ON u.id = c.user_id
LEFT JOIN likes l ON u.id = l.user_id
LEFT JOIN payments pay ON u.id = pay.user_id AND pay.status = 'completed'
GROUP BY u.id;

-- View for revenue statistics
CREATE VIEW revenue_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN status = 'completed' AND type = 'subscription' THEN amount ELSE 0 END) as subscription_revenue,
    SUM(CASE WHEN status = 'completed' AND type = 'one_time' THEN amount ELSE 0 END) as one_time_revenue,
    currency
FROM payments
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
GROUP BY DATE(created_at), currency
ORDER BY date DESC;

-- --------------------------------------------------------

-- Create stored procedures for common operations

DELIMITER $

-- Procedure to get user access to a post
CREATE PROCEDURE GetUserPostAccess(
    IN user_id INT,
    IN post_id INT,
    OUT has_access BOOLEAN,
    OUT access_type VARCHAR(20)
)
BEGIN
    DECLARE subscription_active BOOLEAN DEFAULT FALSE;
    DECLARE post_premium BOOLEAN DEFAULT FALSE;
    DECLARE post_subscription_required VARCHAR(20) DEFAULT 'none';
    DECLARE one_time_access BOOLEAN DEFAULT FALSE;
    
    -- Check if post is premium
    SELECT is_premium, subscription_required 
    INTO post_premium, post_subscription_required
    FROM posts 
    WHERE id = post_id;
    
    -- If post is not premium, user has access
    IF post_premium = FALSE THEN
        SET has_access = TRUE;
        SET access_type = 'free';
    ELSE
        -- Check for one-time access
        SELECT COUNT(*) > 0 INTO one_time_access
        FROM user_post_access
        WHERE user_id = user_id AND post_id = post_id
        AND (expires_at IS NULL OR expires_at > NOW());
        
        IF one_time_access THEN
            SET has_access = TRUE;
            SET access_type = 'one_time';
        ELSE
            -- Check subscription access
            SELECT COUNT(*) > 0 INTO subscription_active
            FROM users u
            INNER JOIN subscriptions s ON u.id = s.user_id
            WHERE u.id = user_id 
            AND s.status = 'active'
            AND (s.ends_at IS NULL OR s.ends_at > NOW())
            AND (
                post_subscription_required = 'none' OR
                (post_subscription_required = 'monthly' AND s.plan_type IN ('monthly', 'yearly', 'lifetime')) OR
                (post_subscription_required = 'yearly' AND s.plan_type IN ('yearly', 'lifetime')) OR
                (post_subscription_required = 'lifetime' AND s.plan_type = 'lifetime')
            );
            
            IF subscription_active THEN
                SET has_access = TRUE;
                SET access_type = 'subscription';
            ELSE
                SET has_access = FALSE;
                SET access_type = 'none';
            END IF;
        END IF;
    END IF;
END$

-- Procedure to increment post views
CREATE PROCEDURE IncrementPostViews(IN post_id INT)
BEGIN
    UPDATE posts 
    SET views = views + 1 
    WHERE id = post_id;
END$

-- Procedure to clean up expired tokens
CREATE PROCEDURE CleanupExpiredTokens()
BEGIN
    UPDATE users 
    SET 
        email_verification_token = NULL,
        password_reset_token = NULL
    WHERE 
        (email_verification_token IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)) OR
        (password_reset_token IS NOT NULL AND password_reset_expires < NOW());
END$

-- Procedure to process subscription renewals
CREATE PROCEDURE ProcessSubscriptionRenewals()
BEGIN
    -- Update expired subscriptions
    UPDATE subscriptions 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND ends_at < NOW();
    
    -- Update user subscription status for expired subscriptions
    UPDATE users u
    INNER JOIN subscriptions s ON u.id = s.user_id
    SET u.subscription_status = 'none',
        u.subscription_expires = NULL
    WHERE s.status = 'expired';
END$

DELIMITER ;

-- --------------------------------------------------------

-- Create events for automated tasks

-- Event to clean up expired tokens daily
CREATE EVENT IF NOT EXISTS cleanup_expired_tokens
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupExpiredTokens();

-- Event to process subscription renewals hourly
CREATE EVENT IF NOT EXISTS process_subscription_renewals
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
DO
  CALL ProcessSubscriptionRenewals();

-- Event to clean up old login logs (keep 6 months)
CREATE EVENT IF NOT EXISTS cleanup_old_logs
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP
DO
  DELETE FROM login_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- --------------------------------------------------------

-- Insert sample data for testing (optional - can be removed in production)

-- Create Chloe Belle user account (will be updated by installer)
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `email_verified`, `created_at`) VALUES
('chloe_belle', 'chloe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Chloe', 'Belle', 'chloe', 'active', 1, NOW());

-- Get Chloe's user ID for sample posts
SET @chloe_id = LAST_INSERT_ID();

-- Insert sample posts
INSERT INTO `posts` (`user_id`, `title`, `content`, `media_type`, `is_premium`, `subscription_required`, `status`, `featured`, `featured_order`, `created_at`) VALUES
(@chloe_id, 'Welcome to my world!', 'Hi everyone! Welcome to my exclusive content platform. I\'m so excited to share this journey with you all! ✨', 'none', 0, 'none', 'published', 1, 1, NOW()),
(@chloe_id, 'Behind the scenes', 'Here\'s a little peek behind the scenes of my latest photoshoot! More exclusive content coming soon for subscribers 📸', 'image', 1, 'monthly', 'published', 1, 2, NOW()),
(@chloe_id, 'Exclusive video content', 'This premium video is available only for my yearly subscribers! Thank you for your amazing support 💖', 'video', 1, 'yearly', 'published', 1, 3, NOW());

COMMIT;

-- --------------------------------------------------------

-- Optional table for subscription plans (used by some API endpoints)
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `identifier` enum('monthly','yearly','lifetime') NOT NULL,
  `description` text DEFAULT NULL,
  `billing_cycle` enum('month','year','one_time') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'GBP',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_identifier_currency` (`identifier`,`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
