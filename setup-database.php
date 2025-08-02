<?php
/**
 * Simple Database Setup Script
 * This will create all tables and default data
 */

require_once 'config.php';

// Check if already set up
if (isset($_GET['force']) || !isset($_GET['confirm'])) {
    if (!isset($_GET['confirm'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Database Setup</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
                .btn { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
                .warning { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <h1>🗄️ Database Setup for Chloe Belle</h1>
            
            <div class="warning">
                <strong>⚠️ Warning:</strong> This will create all database tables and default data. 
                If tables already exist, this may overwrite existing data.
            </div>
            
            <h3>What this will create:</h3>
            <ul>
                <li>✅ Users table with admin account</li>
                <li>✅ Posts table with sample posts</li>
                <li>✅ Site settings with default values</li>
                <li>✅ Comments, likes, and subscription tables</li>
                <li>✅ All required indexes and relationships</li>
            </ul>
            
            <h3>Default accounts that will be created:</h3>
            <ul>
                <li><strong>Admin:</strong> username <code>admin</code>, password <code>secret</code></li>
                <li><strong>Chloe Belle:</strong> username <code>chloe_belle</code>, password <code>secret</code></li>
            </ul>
            
            <p>
                <a href="setup-database.php?confirm=1" class="btn">✅ Create Database Tables</a>
                <a href="index.php" style="margin-left: 10px;">❌ Cancel</a>
            </p>
        </body>
        </html>
        <?php
        exit;
    }
}

$errors = [];
$success = [];

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $success[] = "✅ Connected to database successfully";
    
    // Create Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL UNIQUE,
          `email` varchar(100) NOT NULL UNIQUE,
          `password` varchar(255) NOT NULL,
          `first_name` varchar(50) DEFAULT NULL,
          `last_name` varchar(50) DEFAULT NULL,
          `avatar` varchar(255) DEFAULT NULL,
          `role` enum('admin','moderator','user','chloe') NOT NULL DEFAULT 'user',
          `status` enum('active','inactive','banned','pending') NOT NULL DEFAULT 'active',
          `email_verified` tinyint(1) NOT NULL DEFAULT 1,
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
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_username` (`username`),
          KEY `idx_email` (`email`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Users table created";

    // Create Posts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `posts` (
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
          `status` enum('draft','published','archived','deleted') NOT NULL DEFAULT 'published',
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
          KEY `idx_featured` (`featured`),
          KEY `idx_premium` (`is_premium`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Posts table created";

    // Create Comments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `comments` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `post_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `parent_id` int(11) DEFAULT NULL,
          `content` text NOT NULL,
          `status` enum('pending','approved','rejected','hidden') NOT NULL DEFAULT 'approved',
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
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Comments table created";

    // Create Likes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `likes` (
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
          KEY `idx_comment_id` (`comment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Likes table created";

    // Create Subscriptions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `subscriptions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `plan_type` enum('monthly','yearly','lifetime') NOT NULL,
          `status` enum('active','inactive','canceled','expired','trial') NOT NULL DEFAULT 'active',
          `amount` decimal(10,2) NOT NULL,
          `currency` varchar(3) NOT NULL DEFAULT 'GBP',
          `payment_gateway` enum('stripe','paypal','manual') NOT NULL DEFAULT 'manual',
          `gateway_subscription_id` varchar(100) DEFAULT NULL,
          `gateway_customer_id` varchar(100) DEFAULT NULL,
          `starts_at` datetime NOT NULL,
          `ends_at` datetime DEFAULT NULL,
          `canceled_at` datetime DEFAULT NULL,
          `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Subscriptions table created";

    // Create Site Settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `site_settings` (
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
          KEY `idx_setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Site settings table created";

    // Create Login Logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) DEFAULT NULL,
          `email` varchar(100) DEFAULT NULL,
          `ip_address` varchar(45) NOT NULL,
          `user_agent` text DEFAULT NULL,
          `status` enum('success','failed','blocked') NOT NULL,
          `failure_reason` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_ip_address` (`ip_address`),
          KEY `idx_status` (`status`),
          KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Login logs table created";

    // Insert default admin user
    $adminPassword = password_hash('secret', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT IGNORE INTO `users` (username, email, password, role, status, email_verified, created_at) 
        VALUES ('admin', 'admin@chloebelle.vip', '$adminPassword', 'admin', 'active', 1, NOW())
    ");
    $success[] = "✅ Admin user created (admin/secret)";

    // Insert Chloe Belle user
    $chloePassword = password_hash('secret', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT IGNORE INTO `users` (username, email, password, first_name, last_name, role, status, email_verified, created_at) 
        VALUES ('chloe_belle', 'chloe@chloebelle.vip', '$chloePassword', 'Chloe', 'Belle', 'chloe', 'active', 1, NOW())
    ");
    $success[] = "✅ Chloe Belle user created (chloe_belle/secret)";

    // Get Chloe's user ID for sample posts
    $stmt = $pdo->query("SELECT id FROM users WHERE username = 'chloe_belle'");
    $chloeId = $stmt->fetchColumn();

    if ($chloeId) {
        // Insert sample posts
        $samplePosts = [
            [
                'title' => 'Welcome to my exclusive world! ✨',
                'content' => "Hi everyone! I'm so excited to welcome you to my exclusive content platform! 💖\n\nThis is where I'll be sharing my most personal moments, behind-the-scenes content, and exclusive photos and videos that you won't find anywhere else.\n\nThank you for being part of this journey with me - it means everything! More amazing content coming very soon! 🔥\n\n#Welcome #Exclusive #ChloeBelle",
                'is_premium' => 0,
                'subscription_required' => 'none',
                'featured' => 1,
                'featured_order' => 1
            ],
            [
                'title' => 'Behind the scenes - Premium Content 📸',
                'content' => "Here's an exclusive look behind the scenes of my latest photoshoot! 📸✨\n\nThis premium content is available for my monthly subscribers and above. You'll get to see the creative process, outtakes, and some of my favorite shots that didn't make it to social media.\n\nSubscribe to unlock this and hundreds of other exclusive posts! 💫",
                'is_premium' => 1,
                'subscription_required' => 'monthly',
                'featured' => 1,
                'featured_order' => 2
            ],
            [
                'title' => 'Exclusive video content for my VIP subscribers 🎬',
                'content' => "This special video content is exclusively for my yearly and lifetime subscribers! 🎬👑\n\nI share my thoughts, daily routines, and some really personal moments that I only want to share with my most dedicated supporters.\n\nThank you for believing in me and supporting my content - you're the reason I can keep creating! 💕\n\n#VIP #Exclusive #ThankYou",
                'is_premium' => 1,
                'subscription_required' => 'yearly',
                'featured' => 1,
                'featured_order' => 3
            ],
            [
                'title' => 'Good morning beautiful souls! ☀️',
                'content' => "Starting my day with gratitude and positive energy! ☀️💕\n\nWhat's everyone up to today? I love connecting with you all in the comments - your messages always brighten my day!\n\nSending love and good vibes to all my amazing supporters! ✨",
                'is_premium' => 0,
                'subscription_required' => 'none',
                'featured' => 0,
                'featured_order' => null
            ],
            [
                'title' => 'Workout Wednesday motivation! 💪',
                'content' => "Just finished an amazing workout session! 💪✨\n\nRemember, it's not about being perfect - it's about showing up for yourself every day. Even 10 minutes of movement counts!\n\nWhat's your favorite way to stay active? Let me know in the comments! 🏃‍♀️💕",
                'is_premium' => 0,
                'subscription_required' => 'none',
                'featured' => 0,
                'featured_order' => null
            ],
            [
                'title' => 'Premium lifestyle content - Subscriber exclusive 🌟',
                'content' => "Today I'm sharing some of my favorite lifestyle tips and daily routines that keep me feeling my best! 🌟\n\nThis detailed guide includes:\n📝 My morning routine\n🥗 Favorite healthy recipes\n📚 Books I'm currently reading\n💄 Skincare routine secrets\n\nOnly available for my premium subscribers! 💎",
                'is_premium' => 1,
                'subscription_required' => 'monthly',
                'featured' => 0,
                'featured_order' => null
            ]
        ];

        foreach ($samplePosts as $post) {
            $pdo->prepare("
                INSERT IGNORE INTO posts (user_id, title, content, is_premium, subscription_required, featured, featured_order, status, views, likes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'published', ?, ?, NOW())
            ")->execute([
                $chloeId,
                $post['title'],
                $post['content'],
                $post['is_premium'],
                $post['subscription_required'],
                $post['featured'],
                $post['featured_order'],
                rand(50, 500), // Random views
                rand(10, 100)  // Random likes
            ]);
        }
        $success[] = "✅ Sample posts created (" . count($samplePosts) . " posts)";
    }

    // Insert default site settings
    $defaultSettings = [
        ['site_name', 'Chloe Belle', 'string', 'general', 'Site name displayed in title and headers', 1],
        ['site_description', 'Exclusive AI-generated content and experiences', 'string', 'general', 'Site meta description for SEO', 1],
        ['site_keywords', 'AI influencer, exclusive content, premium photos, subscription', 'string', 'general', 'Site meta keywords for SEO', 1],
        ['default_currency', 'GBP', 'string', 'payments', 'Default currency for payments', 1],
        ['supported_currencies', '["GBP","USD"]', 'json', 'payments', 'List of supported currencies', 1],
        ['default_language', 'en', 'string', 'general', 'Default site language', 1],
        ['registration_enabled', '1', 'boolean', 'users', 'Allow new user registrations', 1],
        ['email_verification_required', '1', 'boolean', 'users', 'Require email verification for new accounts', 0],
        ['comments_enabled', '1', 'boolean', 'content', 'Enable comments on posts', 1],
        ['comments_moderation', '1', 'boolean', 'content', 'Moderate comments before showing', 0],
        ['likes_enabled', '1', 'boolean', 'content', 'Enable likes on posts and comments', 1],
        ['profanity_filter_enabled', '1', 'boolean', 'content', 'Enable profanity filtering', 0],
        ['blur_protection_enabled', '1', 'boolean', 'content', 'Enable blur protection for premium content', 1],
        ['watermark_enabled', '0', 'boolean', 'content', 'Enable watermarks on images', 0],
        ['subscription_monthly_price_gbp', '9.99', 'string', 'subscriptions', 'Monthly subscription price in GBP', 1],
        ['subscription_monthly_price_usd', '12.99', 'string', 'subscriptions', 'Monthly subscription price in USD', 1],
        ['subscription_yearly_price_gbp', '99.99', 'string', 'subscriptions', 'Yearly subscription price in GBP', 1],
        ['subscription_yearly_price_usd', '129.99', 'string', 'subscriptions', 'Yearly subscription price in USD', 1],
        ['subscription_lifetime_price_gbp', '299.99', 'string', 'subscriptions', 'Lifetime subscription price in GBP', 1],
        ['subscription_lifetime_price_usd', '399.99', 'string', 'subscriptions', 'Lifetime subscription price in USD', 1],
        ['free_posts_limit', '3', 'integer', 'subscriptions', 'Number of free posts non-subscribers can view', 1],
        ['payment_gateway', 'sandbox', 'string', 'payments', 'Active payment gateway (sandbox, stripe, paypal)', 0],
        ['maintenance_mode', '0', 'boolean', 'general', 'Enable maintenance mode', 0],
        ['maintenance_message', 'Site is under maintenance. Please check back later.', 'text', 'general', 'Maintenance mode message', 1]
    ];

    foreach ($defaultSettings as $setting) {
        $pdo->prepare("
            INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, category, description, is_public) 
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute($setting);
    }
    $success[] = "✅ Default site settings created";

} catch (Exception $e) {
    $errors[] = "❌ Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Setup Results</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .btn { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px 0 0; }
        .btn-success { background: #28a745; }
        .credentials { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🗄️ Database Setup Results</h1>

    <?php foreach ($success as $msg): ?>
        <div class="success"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $msg): ?>
        <div class="error"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
        <div class="credentials">
            <h3>🔑 Login Credentials:</h3>
            <p><strong>Admin Access:</strong><br>
            Username: <code>admin</code><br>
            Password: <code>secret</code></p>
            
            <p><strong>Chloe Belle Account:</strong><br>
            Username: <code>chloe_belle</code><br>
            Password: <code>secret</code></p>
        </div>

        <h3>🎉 Setup Complete!</h3>
        <p>Your database has been successfully set up with all tables and sample data.</p>
        
        <a href="index.php" class="btn">🏠 Go to Homepage</a>
        <a href="admin/index.php" class="btn btn-success">⚙️ Admin Panel</a>
        <a href="feed/index.php" class="btn">📱 View Feed</a>
        
        <p><strong>Important:</strong> Delete this <code>setup-database.php</code> file after testing for security!</p>
    <?php else: ?>
        <p>Please fix the errors above and try again.</p>
        <a href="setup-database.php" class="btn">🔄 Try Again</a>
    <?php endif; ?>
</body>
</html>