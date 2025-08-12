<?php
/**
 * Enhanced Site Settings Page for Chloe Belle Admin - FIXED VERSION
 */

session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle settings update
if ($_POST) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // Create settings table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `site_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(100) NOT NULL UNIQUE,
                `setting_value` text DEFAULT NULL,
                `setting_type` enum('string','boolean','number','json') NOT NULL DEFAULT 'string',
                `category` varchar(50) DEFAULT 'general',
                `description` text DEFAULT NULL,
                `is_public` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_setting_key` (`setting_key`),
                KEY `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_general':
                $settings = [
                    'site_name' => $_POST['site_name'] ?? 'Chloe Belle',
                    'site_description' => $_POST['site_description'] ?? '',
                    'site_tagline' => $_POST['site_tagline'] ?? '',
                    'default_currency' => $_POST['default_currency'] ?? 'GBP',
                    'default_language' => $_POST['default_language'] ?? 'en',
                    'registration_enabled' => isset($_POST['registration_enabled']) ? '1' : '0',
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
                    'maintenance_message' => $_POST['maintenance_message'] ?? 'Site is under maintenance. Please check back later.',
                    'google_analytics_id' => $_POST['google_analytics_id'] ?? ''
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type, category) 
                        VALUES (?, ?, 'string', 'general') 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "General settings updated successfully!";
                $messageType = 'success';
                break;

            case 'update_subscription':
                $subscriptionSettings = [
                    'subscription_monthly_price_gbp' => $_POST['monthly_price_gbp'] ?? '9.99',
                    'subscription_monthly_price_usd' => $_POST['monthly_price_usd'] ?? '12.99',
                    'subscription_yearly_price_gbp' => $_POST['yearly_price_gbp'] ?? '99.99',
                    'subscription_yearly_price_usd' => $_POST['yearly_price_usd'] ?? '129.99',
                    'subscription_lifetime_price_gbp' => $_POST['lifetime_price_gbp'] ?? '299.99',
                    'subscription_lifetime_price_usd' => $_POST['lifetime_price_usd'] ?? '399.99',
                    'free_posts_limit' => $_POST['free_posts_limit'] ?? '3',
                    'trial_period_days' => $_POST['trial_period_days'] ?? '7',
                    'stripe_publishable_key' => $_POST['stripe_publishable_key'] ?? '',
                    'stripe_secret_key' => $_POST['stripe_secret_key'] ?? '',
                    'paypal_client_id' => $_POST['paypal_client_id'] ?? '',
                    'paypal_secret' => $_POST['paypal_secret'] ?? '',
                    'paypal_mode' => $_POST['paypal_mode'] ?? 'sandbox',
                    'paypal_plan_monthly' => $_POST['paypal_plan_monthly'] ?? '',
                    'paypal_plan_yearly' => $_POST['paypal_plan_yearly'] ?? '',
                    'payment_gateway' => $_POST['payment_gateway'] ?? 'stripe'
                ];
                
                foreach ($subscriptionSettings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type, category) 
                        VALUES (?, ?, 'string', 'subscription') 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "Subscription settings updated successfully!";
                $messageType = 'success';
                break;

            case 'update_content':
                $contentSettings = [
                    'comments_enabled' => isset($_POST['comments_enabled']) ? '1' : '0',
                    'comments_moderation' => isset($_POST['comments_moderation']) ? '1' : '0',
                    'likes_enabled' => isset($_POST['likes_enabled']) ? '1' : '0',
                    'blur_protection_enabled' => isset($_POST['blur_protection_enabled']) ? '1' : '0',
                    'watermark_enabled' => isset($_POST['watermark_enabled']) ? '1' : '0',
                    'profanity_filter_enabled' => isset($_POST['profanity_filter_enabled']) ? '1' : '0',
                    'auto_publish_posts' => isset($_POST['auto_publish_posts']) ? '1' : '0',
                    'max_post_length' => $_POST['max_post_length'] ?? '5000'
                ];
                
                foreach ($contentSettings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type, category) 
                        VALUES (?, ?, 'string', 'content') 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "Content settings updated successfully!";
                $messageType = 'success';
                break;

            case 'update_email':
                $emailSettings = [
                    'email_method' => $_POST['email_method'] ?? 'php_mail',
                    'smtp_host' => $_POST['smtp_host'] ?? '',
                    'smtp_port' => $_POST['smtp_port'] ?? '587',
                    'smtp_username' => $_POST['smtp_username'] ?? '',
                    'smtp_password' => $_POST['smtp_password'] ?? '',
                    'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                    'from_email' => $_POST['from_email'] ?? '',
                    'from_name' => $_POST['from_name'] ?? '',
                    'welcome_email_enabled' => isset($_POST['welcome_email_enabled']) ? '1' : '0'
                ];
                
                foreach ($emailSettings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type, category) 
                        VALUES (?, ?, 'string', 'email') 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "Email settings updated successfully!";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current settings
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $settings = [];
}

// Helper function to get setting value
function getSetting($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

// Get system information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => 'Unknown',
    'uploads_writable' => is_writable('../uploads'),
    'config_writable' => is_writable('../config.php'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'debug_mode' => defined('DEBUG_MODE') && DEBUG_MODE
];

try {
    $mysql_version = $pdo->query("SELECT VERSION() as version")->fetch()['version'];
    $systemInfo['mysql_version'] = $mysql_version;
} catch (Exception $e) {
    // Ignore error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Chloe Belle Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --sidebar-width: 280px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            margin-left: 0;
            transition: var(--transition);
            min-height: 100vh;
            padding: 2rem;
        }

        /* Header */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .page-subtitle {
            color: #6b7280;
            margin-top: 0.5rem;
        }

        /* Settings Tabs */
        .settings-tabs {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .tab-nav {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: #6b7280;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn.active,
        .tab-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        /* Settings Section */
        .settings-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .section-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-body {
            padding: 2rem;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .form-check {
            margin-bottom: 1rem;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-text {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Submit Button */
        .submit-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Payment Gateway Selector */
        .payment-gateway-selector {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .gateway-option {
            margin-bottom: 0.5rem;
        }

        .gateway-option input[type="radio"] {
            margin-right: 0.5rem;
        }

        /* Email Method Toggle */
        .email-method-toggle {
            margin-bottom: 2rem;
        }

        .smtp-settings {
            display: none;
            padding: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.02);
        }

        .smtp-settings.active {
            display: block;
        }

        /* System Info Cards */
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .info-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .info-icon.php { color: #777bb4; }
        .info-icon.mysql { color: #4479a1; }
        .info-icon.storage { color: var(--success-color); }
        .info-icon.debug { color: var(--warning-color); }

        .info-value {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .info-label {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .status-ok {
            color: var(--success-color);
        }

        .status-warning {
            color: var(--warning-color);
        }

        .status-error {
            color: var(--danger-color);
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .section-body {
                padding: 1.5rem;
            }

            .tab-nav {
                flex-direction: column;
            }

            .tab-btn {
                text-align: left;
            }

            .system-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateX(-100%);
            transition: var(--transition);
            z-index: 1000;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: white;
        }

        .sidebar-brand:hover {
            color: white;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: translateX(4px);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .main-content.sidebar-open {
            margin-left: var(--sidebar-width);
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            transition: var(--transition);
        }

        .mobile-toggle:hover {
            transform: scale(1.1);
        }

        @media (max-width: 992px) {
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .main-content.sidebar-open {
                margin-left: 0;
            }
        }

        /* Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-crown"></i>
                Chloe Belle Admin
            </a>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </div>
            <div class="nav-item">
                <a href="posts.php" class="nav-link">
                    <i class="fas fa-edit"></i>
                    Posts
                </a>
            </div>
            <div class="nav-item">
                <a href="media.php" class="nav-link">
                    <i class="fas fa-images"></i>
                    Media
                </a>
            </div>
            <div class="nav-item">
                <a href="roles.php" class="nav-link">
                    <i class="fas fa-user-shield"></i>
                    Roles
                </a>
            </div>
            <div class="nav-item">
                <a href="subscriptions.php" class="nav-link">
                    <i class="fas fa-credit-card"></i>
                    Subscriptions
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
            <div class="nav-item" style="margin-top: 2rem;">
                <a href="../feed/index.php" class="nav-link">
                    <i class="fas fa-eye"></i>
                    View Site
                </a>
            </div>
            <div class="nav-item">
                <a href="../index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Homepage
                </a>
            </div>
            <div class="nav-item">
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content" id="mainContent">
        <header class="page-header fade-in-up">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-subtitle">Configure your website settings and preferences</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="settings-tabs fade-in-up">
            <div class="tab-nav">
                <button class="tab-btn active" data-tab="general">
                    <i class="fas fa-globe"></i>
                    General
                </button>
                <button class="tab-btn" data-tab="subscription">
                    <i class="fas fa-crown"></i>
                    Subscriptions
                </button>
                <button class="tab-btn" data-tab="content">
                    <i class="fas fa-shield-alt"></i>
                    Content & Security
                </button>
                <button class="tab-btn" data-tab="email">
                    <i class="fas fa-envelope"></i>
                    Email
                </button>
                <button class="tab-btn" data-tab="system">
                    <i class="fas fa-server"></i>
                    System Info
                </button>
            </div>
        </div>

        <div class="settings-section active fade-in-up" id="general">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-globe"></i>
                    General Settings
                </h2>
            </div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_general">
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                   value="<?= htmlspecialchars(getSetting('site_name', 'Chloe Belle')) ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="default_currency" class="form-label">Default Currency</label>
                            <select class="form-select" id="default_currency" name="default_currency">
                                <option value="GBP" <?= getSetting('default_currency') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                <option value="USD" <?= getSetting('default_currency') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                <option value="EUR" <?= getSetting('default_currency') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars(getSetting('site_description', 'Exclusive AI-generated content and experiences')) ?></textarea>
                        <div class="form-text">This appears in search engine results and social media previews</div>
                    </div>

                    <div class="form-group">
                        <label for="site_tagline" class="form-label">Site Tagline</label>
                        <input type="text" class="form-control" id="site_tagline" name="site_tagline" 
                               value="<?= htmlspecialchars(getSetting('site_tagline', 'Premium Content & Exclusive Access')) ?>"
                               placeholder="A short tagline for your site">
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="default_language" class="form-label">Default Language</label>
                            <select class="form-select" id="default_language" name="default_language">
                                <option value="en" <?= getSetting('default_language') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= getSetting('default_language') === 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= getSetting('default_language') === 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="de" <?= getSetting('default_language') === 'de' ? 'selected' : '' ?>>German</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="google_analytics_id" class="form-label">Google Analytics ID</label>
                            <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id" 
                                   value="<?= htmlspecialchars(getSetting('google_analytics_id')) ?>"
                                   placeholder="G-XXXXXXXXXX">
                            <div class="form-text">Optional: For tracking website analytics</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="registration_enabled" name="registration_enabled" 
                                       <?= getSetting('registration_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="registration_enabled">
                                    Allow new user registrations
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?= getSetting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="maintenance_mode">
                                    <span class="text-warning">Enable maintenance mode</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="maintenance_message_group" style="display: <?= getSetting('maintenance_mode', '0') === '1' ? 'block' : 'none' ?>;">
                        <label for="maintenance_message" class="form-label">Maintenance Message</label>
                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="2"><?= htmlspecialchars(getSetting('maintenance_message', 'Site is under maintenance. Please check back later.')) ?></textarea>
                        <div class="form-text">Message shown to users when maintenance mode is enabled</div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Save General Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="settings-section fade-in-up" id="subscription">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-crown"></i>
                    Subscription Settings
                </h2>
            </div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_subscription">
                    
                    <div class="payment-gateway-selector">
                        <h5 class="mb-3">Payment Gateway</h5>
                        <div class="gateway-option">
                            <input type="radio" id="gateway_stripe" name="payment_gateway" value="stripe" 
                                   <?= getSetting('payment_gateway', 'stripe') === 'stripe' ? 'checked' : '' ?>>
                            <label for="gateway_stripe">Stripe</label>
                        </div>
                        <div class="gateway-option">
                            <input type="radio" id="gateway_paypal" name="payment_gateway" value="paypal" 
                                   <?= getSetting('payment_gateway') === 'paypal' ? 'checked' : '' ?>>
                            <label for="gateway_paypal">PayPal</label>
                        </div>
                        <div class="gateway-option">
                            <input type="radio" id="gateway_sandbox" name="payment_gateway" value="sandbox" 
                                   <?= getSetting('payment_gateway') === 'sandbox' ? 'checked' : '' ?>>
                            <label for="gateway_sandbox">Sandbox (Testing)</label>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Monthly Subscription</h5>
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label class="form-label">GBP Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01" class="form-control" name="monthly_price_gbp" 
                                               value="<?= htmlspecialchars(getSetting('subscription_monthly_price_gbp', '9.99')) ?>">
                                    </div>
                                </div>
                                <div class="col-6 form-group">
                                    <label class="form-label">USD Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" class="form-control" name="monthly_price_usd" 
                                               value="<?= htmlspecialchars(getSetting('subscription_monthly_price_usd', '12.99')) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="mb-3">Yearly Subscription</h5>
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label class="form-label">GBP Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01" class="form-control" name="yearly_price_gbp" 
                                               value="<?= htmlspecialchars(getSetting('subscription_yearly_price_gbp', '99.99')) ?>">
                                    </div>
                                </div>
                                <div class="col-6 form-group">
                                    <label class="form-label">USD Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" class="form-control" name="yearly_price_usd" 
                                               value="<?= htmlspecialchars(getSetting('subscription_yearly_price_usd', '129.99')) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Lifetime Subscription</h5>
                            <div class="row">
                                <div class="col-6 form-group">
                                    <label class="form-label">GBP Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01" class="form-control" name="lifetime_price_gbp" 
                                               value="<?= htmlspecialchars(getSetting('subscription_lifetime_price_gbp', '299.99')) ?>">
                                    </div>
                                </div>
                                <div class="col-6 form-group">
                                    <label class="form-label">USD Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" class="form-control" name="lifetime_price_usd" 
                                               value="<?= htmlspecialchars(getSetting('subscription_lifetime_price_usd', '399.99')) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="mb-3">Free Access Limits</h5>
                            <div class="form-group">
                                <label class="form-label">Free posts per user</label>
                                <input type="number" class="form-control" name="free_posts_limit" 
                                       value="<?= htmlspecialchars(getSetting('free_posts_limit', '3')) ?>" min="0">
                                <div class="form-text">Number of premium posts free users can view</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Trial period (days)</label>
                                <input type="number" class="form-control" name="trial_period_days" 
                                       value="<?= htmlspecialchars(getSetting('trial_period_days', '7')) ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <div id="stripe_settings" class="payment-settings">
                        <h5 class="mb-3">Stripe Configuration</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">Stripe Publishable Key</label>
                                <input type="text" class="form-control" name="stripe_publishable_key" 
                                       value="<?= htmlspecialchars(getSetting('stripe_publishable_key')) ?>"
                                       placeholder="pk_test_...">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Stripe Secret Key</label>
                                <input type="password" class="form-control" name="stripe_secret_key" 
                                       value="<?= htmlspecialchars(getSetting('stripe_secret_key')) ?>"
                                       placeholder="sk_test_...">
                            </div>
                        </div>
                    </div>

                    <div id="paypal_settings" class="payment-settings">
                        <h5 class="mb-3">PayPal Configuration</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">PayPal Client ID</label>
                                <input type="text" class="form-control" name="paypal_client_id" 
                                       value="<?= htmlspecialchars(getSetting('paypal_client_id')) ?>"
                                       placeholder="A...">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">PayPal Secret</label>
                                <input type="password" class="form-control" name="paypal_secret" 
                                       value="<?= htmlspecialchars(getSetting('paypal_secret')) ?>"
                                       placeholder="E...">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">PayPal Mode</label>
                                <select class="form-control" name="paypal_mode">
                                    <option value="sandbox" <?= getSetting('paypal_mode','sandbox')==='sandbox'?'selected':'' ?>>Sandbox</option>
                                    <option value="live" <?= getSetting('paypal_mode','sandbox')==='live'?'selected':'' ?>>Live</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">PayPal Plan ID (Monthly)</label>
                                <input type="text" class="form-control" name="paypal_plan_monthly" 
                                       value="<?= htmlspecialchars(getSetting('paypal_plan_monthly')) ?>"
                                       placeholder="P-xxxxxxxx">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">PayPal Plan ID (Yearly)</label>
                                <input type="text" class="form-control" name="paypal_plan_yearly" 
                                       value="<?= htmlspecialchars(getSetting('paypal_plan_yearly')) ?>"
                                       placeholder="P-xxxxxxxx">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Save Subscription Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="settings-section fade-in-up" id="content">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Content & Security Settings
                </h2>
            </div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_content">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Content Features</h5>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="comments_enabled" name="comments_enabled" 
                                       <?= getSetting('comments_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comments_enabled">
                                    <i class="fas fa-comment me-2"></i>Enable comments
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="comments_moderation" name="comments_moderation" 
                                       <?= getSetting('comments_moderation', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comments_moderation">
                                    <i class="fas fa-user-shield me-2"></i>Moderate comments before publishing
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="likes_enabled" name="likes_enabled" 
                                       <?= getSetting('likes_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="likes_enabled">
                                    <i class="fas fa-heart me-2"></i>Enable likes and reactions
                                </label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_publish_posts" name="auto_publish_posts" 
                                       <?= getSetting('auto_publish_posts', '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_publish_posts">
                                    <i class="fas fa-paper-plane me-2"></i>Auto-publish new posts
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Maximum post length (characters)</label>
                                <input type="number" class="form-control" name="max_post_length" 
                                       value="<?= htmlspecialchars(getSetting('max_post_length', '5000')) ?>" min="100">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="mb-3">Content Protection</h5>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="blur_protection_enabled" name="blur_protection_enabled" 
                                       <?= getSetting('blur_protection_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="blur_protection_enabled">
                                    <i class="fas fa-eye-slash me-2"></i>Blur premium content for non-subscribers
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="watermark_enabled" name="watermark_enabled" 
                                       <?= getSetting('watermark_enabled', '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="watermark_enabled">
                                    <i class="fas fa-copyright me-2"></i>Add watermarks to images
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="profanity_filter_enabled" name="profanity_filter_enabled" 
                                       <?= getSetting('profanity_filter_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="profanity_filter_enabled">
                                    <i class="fas fa-filter me-2"></i>Enable profanity filter
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Save Content Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="settings-section fade-in-up" id="email">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-envelope"></i>
                    Email Settings
                </h2>
            </div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    
                    <div class="email-method-toggle">
                        <h5 class="mb-3">Email Method</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="email_php_mail" name="email_method" value="php_mail" 
                                   <?= getSetting('email_method', 'php_mail') === 'php_mail' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email_php_mail">
                                <strong>PHP Mail (cPanel Default)</strong><br>
                                <small class="text-muted">Use your hosting provider's default mail system (recommended for cPanel hosting)</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="email_smtp" name="email_method" value="smtp" 
                                   <?= getSetting('email_method') === 'smtp' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email_smtp">
                                <strong>SMTP</strong><br>
                                <small class="text-muted">Use external SMTP server (Gmail, Outlook, etc.)</small>
                            </label>
                        </div>
                    </div>

                    <h5 class="mb-3">Email Headers</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">From Email</label>
                            <input type="email" class="form-control" name="from_email" 
                                   value="<?= htmlspecialchars(getSetting('from_email')) ?>"
                                   placeholder="noreply@yourdomain.com" required>
                            <div class="form-text">Email address that emails will be sent from</div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">From Name</label>
                            <input type="text" class="form-control" name="from_name" 
                                   value="<?= htmlspecialchars(getSetting('from_name', 'Chloe Belle')) ?>" required>
                            <div class="form-text">Display name for sent emails</div>
                        </div>
                    </div>

                    <div class="smtp-settings <?= getSetting('email_method') === 'smtp' ? 'active' : '' ?>" id="smtp_settings">
                        <h5 class="mb-3">SMTP Configuration</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host" 
                                       value="<?= htmlspecialchars(getSetting('smtp_host')) ?>"
                                       placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" name="smtp_port" 
                                       value="<?= htmlspecialchars(getSetting('smtp_port', '587')) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" name="smtp_username" 
                                       value="<?= htmlspecialchars(getSetting('smtp_username')) ?>"
                                       placeholder="your-email@domain.com">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" name="smtp_password" 
                                       value="<?= htmlspecialchars(getSetting('smtp_password')) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">Encryption</label>
                                <select class="form-select" name="smtp_encryption">
                                    <option value="tls" <?= getSetting('smtp_encryption') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= getSetting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= getSetting('smtp_encryption') === 'none' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3">Email Features</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="welcome_email_enabled" name="welcome_email_enabled" 
                               <?= getSetting('welcome_email_enabled', '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="welcome_email_enabled">
                            Send welcome emails to new users
                        </label>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Save Email Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="settings-section fade-in-up" id="system">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-server"></i>
                    System Information
                </h2>
            </div>
            <div class="section-body">
                <div class="system-info-grid">
                    <div class="info-card">
                        <div class="info-icon php">
                            <i class="fab fa-php"></i>
                        </div>
                        <div class="info-value"><?= $systemInfo['php_version'] ?></div>
                        <div class="info-label">PHP Version</div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon mysql">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="info-value"><?= $systemInfo['mysql_version'] ?></div>
                        <div class="info-label">MySQL Version</div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon storage <?= $systemInfo['uploads_writable'] ? 'status-ok' : 'status-error' ?>">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="info-value <?= $systemInfo['uploads_writable'] ? 'status-ok' : 'status-error' ?>">
                            <?= $systemInfo['uploads_writable'] ? 'Writable' : 'Not Writable' ?>
                        </div>
                        <div class="info-label">Upload Directory</div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon debug <?= $systemInfo['debug_mode'] ? 'status-warning' : 'status-ok' ?>">
                            <i class="fas fa-bug"></i>
                        </div>
                        <div class="info-value <?= $systemInfo['debug_mode'] ? 'status-warning' : 'status-ok' ?>">
                            <?= $systemInfo['debug_mode'] ? 'Enabled' : 'Disabled' ?>
                        </div>
                        <div class="info-label">Debug Mode</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-value"><?= $systemInfo['memory_limit'] ?></div>
                            <div class="info-label">Memory Limit</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-value"><?= $systemInfo['max_execution_time'] ?>s</div>
                            <div class="info-label">Max Execution Time</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-value"><?= $systemInfo['upload_max_filesize'] ?></div>
                            <div class="info-label">Max Upload Size</div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>System Tips:</h6>
                    <ul class="mb-0">
                        <li>Regularly backup your database and uploaded files</li>
                        <li>Disable debug mode in production for security</li>
                        <li>Monitor your subscription pricing against competitors</li>
                        <li>Test email configuration with a test message</li>
                        <li>Keep PHP and MySQL versions updated for security</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mobileToggle = document.getElementById('mobileToggle');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            // Update toggle icon
            const icon = mobileToggle.querySelector('i');
            if (sidebar.classList.contains('show')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }

        // Event listeners
        mobileToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking nav links on mobile
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    toggleSidebar();
                }
            });
        });

        // Desktop sidebar toggle
        if (window.innerWidth > 992) {
            mainContent.classList.add('sidebar-open');
            sidebar.classList.add('show');
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.add('show');
                mainContent.classList.add('sidebar-open');
                sidebarOverlay.classList.remove('show');
                mobileToggle.querySelector('i').classList.remove('fa-times');
                mobileToggle.querySelector('i').classList.add('fa-bars');
            } else {
                mainContent.classList.remove('sidebar-open');
            }
        });

        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show corresponding section
                document.querySelectorAll('.settings-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Maintenance mode warning and message toggle
        document.getElementById('maintenance_mode').addEventListener('change', function() {
            const messageGroup = document.getElementById('maintenance_message_group');
            if (this.checked) {
                if (!confirm('Are you sure you want to enable maintenance mode? This will prevent users from accessing the site.')) {
                    this.checked = false;
                    return;
                }
                messageGroup.style.display = 'block';
            } else {
                messageGroup.style.display = 'none';
            }
        });

        // Email method toggle
        document.querySelectorAll('input[name="email_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const smtpSettings = document.getElementById('smtp_settings');
                if (this.value === 'smtp') {
                    smtpSettings.classList.add('active');
                } else {
                    smtpSettings.classList.remove('active');
                }
            });
        });

        // Payment gateway toggle
        document.querySelectorAll('input[name="payment_gateway"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const stripeSettings = document.getElementById('stripe_settings');
                const paypalSettings = document.getElementById('paypal_settings');
                
                // Hide all payment settings first
                stripeSettings.style.display = 'none';
                paypalSettings.style.display = 'none';
                
                // Show relevant settings
                if (this.value === 'stripe') {
                    stripeSettings.style.display = 'block';
                } else if (this.value === 'paypal') {
                    paypalSettings.style.display = 'block';
                }
            });
        });

        // Initialize payment gateway visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            const activeGateway = document.querySelector('input[name="payment_gateway"]:checked');
            if (activeGateway) {
                activeGateway.dispatchEvent(new Event('change'));
            }
        });

        console.log('🎉 Enhanced Settings page loaded!');
        console.log('⚙️ System: PHP <?= $systemInfo['php_version'] ?>, MySQL <?= $systemInfo['mysql_version'] ?>');
        console.log('📧 Email method: <?= getSetting('email_method', 'php_mail') ?>');
        console.log('💳 Payment gateway: <?= getSetting('payment_gateway', 'stripe') ?>');
        console.log('🔧 Maintenance mode: <?= getSetting('maintenance_mode', '0') === '1' ? 'Enabled' : 'Disabled' ?>');
    </script>
</body>
</html>