<?php
/**
 * Site Settings Page for Chloe Belle Admin
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

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_general':
                $settings = [
                    'site_name' => $_POST['site_name'] ?? 'Chloe Belle',
                    'site_description' => $_POST['site_description'] ?? '',
                    'default_currency' => $_POST['default_currency'] ?? 'GBP',
                    'default_language' => $_POST['default_language'] ?? 'en',
                    'registration_enabled' => isset($_POST['registration_enabled']) ? '1' : '0',
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0'
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type) 
                        VALUES (?, ?, 'string') 
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
                    'free_posts_limit' => $_POST['free_posts_limit'] ?? '3'
                ];
                
                foreach ($subscriptionSettings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type) 
                        VALUES (?, ?, 'string') 
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
                    'profanity_filter_enabled' => isset($_POST['profanity_filter_enabled']) ? '1' : '0'
                ];
                
                foreach ($contentSettings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, setting_type) 
                        VALUES (?, ?, 'boolean') 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = 'boolean'
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "Content settings updated successfully!";
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
            --primary-color: #6c5ce7;
            --sidebar-bg: #2d3436;
            --sidebar-text: #ddd;
        }
        
        body { background: #f8f9fa; }
        
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            color: var(--sidebar-text);
        }
        
        .sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        
        .settings-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .settings-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-star me-2"></i>Chloe Belle Admin
            </a>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="posts.php">
                    <i class="fas fa-edit me-2"></i>Posts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="media.php">
                    <i class="fas fa-images me-2"></i>Media
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="roles.php">
                    <i class="fas fa-user-tag me-2"></i>Roles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions.php">
                    <i class="fas fa-credit-card me-2"></i>Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="../feed/index.php">
                    <i class="fas fa-eye me-2"></i>View Site
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Site Settings</h1>
                <p class="text-muted">Configure your website settings</p>
            </div>
            <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- General Settings -->
        <div class="settings-section">
            <h5 class="settings-header">
                <i class="fas fa-globe me-2"></i>General Settings
            </h5>
            <div class="p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_general">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                   value="<?= htmlspecialchars(getSetting('site_name', 'Chloe Belle')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="default_currency" class="form-label">Default Currency</label>
                            <select class="form-select" id="default_currency" name="default_currency">
                                <option value="GBP" <?= getSetting('default_currency') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                <option value="USD" <?= getSetting('default_currency') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                <option value="EUR" <?= getSetting('default_currency') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars(getSetting('site_description', 'Exclusive AI-generated content and experiences')) ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="default_language" class="form-label">Default Language</label>
                            <select class="form-select" id="default_language" name="default_language">
                                <option value="en" <?= getSetting('default_language') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= getSetting('default_language') === 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= getSetting('default_language') === 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="de" <?= getSetting('default_language') === 'de' ? 'selected' : '' ?>>German</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="registration_enabled" name="registration_enabled" 
                                       <?= getSetting('registration_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="registration_enabled">
                                    Allow new user registrations
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?= getSetting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="maintenance_mode">
                                    <span class="text-warning">Enable maintenance mode</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save General Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Subscription Settings -->
        <div class="settings-section">
            <h5 class="settings-header">
                <i class="fas fa-crown me-2"></i>Subscription Settings
            </h5>
            <div class="p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_subscription">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Monthly Subscription</h6>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">GBP Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01" class="form-control" name="monthly_price_gbp" 
                                               value="<?= htmlspecialchars(getSetting('subscription_monthly_price_gbp', '9.99')) ?>">
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
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
                            <h6 class="mb-3">Yearly Subscription</h6>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">GBP Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01" class="form-control" name="yearly_price_gbp" 
                                               value="<?= htmlspecialchars(getSetting('subscription_yearly_price_gbp', '99.99')) ?>">
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
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
                            <h6 class="mb-3">Lifetime Subscription</h6>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">GBP Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">£</span>
                                        <input type="number" step="0.01" class="form-control" name="lifetime_price_gbp" 
                                               value="<?= htmlspecialchars(getSetting('subscription_lifetime_price_gbp', '299.99')) ?>">
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
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
                            <h6 class="mb-3">Free Access Limits</h6>
                            <div class="mb-3">
                                <label class="form-label">Free posts per user</label>
                                <input type="number" class="form-control" name="free_posts_limit" 
                                       value="<?= htmlspecialchars(getSetting('free_posts_limit', '3')) ?>" min="0">
                                <div class="form-text">Number of premium posts free users can view</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Subscription Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Content Settings -->
        <div class="settings-section">
            <h5 class="settings-header">
                <i class="fas fa-shield-alt me-2"></i>Content & Security Settings
            </h5>
            <div class="p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_content">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Content Features</h6>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="comments_enabled" name="comments_enabled" 
                                       <?= getSetting('comments_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comments_enabled">
                                    <i class="fas fa-comment me-2"></i>Enable comments
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="comments_moderation" name="comments_moderation" 
                                       <?= getSetting('comments_moderation', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comments_moderation">
                                    <i class="fas fa-user-shield me-2"></i>Moderate comments before publishing
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="likes_enabled" name="likes_enabled" 
                                       <?= getSetting('likes_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="likes_enabled">
                                    <i class="fas fa-heart me-2"></i>Enable likes and reactions
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3">Content Protection</h6>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="blur_protection_enabled" name="blur_protection_enabled" 
                                       <?= getSetting('blur_protection_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="blur_protection_enabled">
                                    <i class="fas fa-eye-slash me-2"></i>Blur premium content for non-subscribers
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="watermark_enabled" name="watermark_enabled" 
                                       <?= getSetting('watermark_enabled', '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="watermark_enabled">
                                    <i class="fas fa-copyright me-2"></i>Add watermarks to images
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="profanity_filter_enabled" name="profanity_filter_enabled" 
                                       <?= getSetting('profanity_filter_enabled', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="profanity_filter_enabled">
                                    <i class="fas fa-filter me-2"></i>Enable profanity filter
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Content Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- System Information -->
        <div class="settings-section">
            <h5 class="settings-header">
                <i class="fas fa-info-circle me-2"></i>System Information
            </h5>
            <div class="p-4">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-code fa-2x text-primary mb-2"></i>
                                <h6>PHP Version</h6>
                                <span class="badge bg-info"><?= PHP_VERSION ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-database fa-2x text-success mb-2"></i>
                                <h6>Database</h6>
                                <span class="badge bg-success">Connected</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-folder fa-2x text-<?= is_writable('../uploads') ? 'success' : 'danger' ?> mb-2"></i>
                                <h6>Upload Directory</h6>
                                <span class="badge bg-<?= is_writable('../uploads') ? 'success' : 'danger' ?>">
                                    <?= is_writable('../uploads') ? 'Writable' : 'Not Writable' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shield-alt fa-2x text-<?= defined('DEBUG_MODE') && DEBUG_MODE ? 'warning' : 'success' ?> mb-2"></i>
                                <h6>Debug Mode</h6>
                                <span class="badge bg-<?= defined('DEBUG_MODE') && DEBUG_MODE ? 'warning' : 'success' ?>">
                                    <?= defined('DEBUG_MODE') && DEBUG_MODE ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Quick Tips:</h6>
                    <ul class="mb-0">
                        <li>Regularly backup your database and uploaded files</li>
                        <li>Disable debug mode in production for security</li>
                        <li>Monitor your subscription pricing against competitors</li>
                        <li>Test payment flows in sandbox mode before going live</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Maintenance mode warning
        document.getElementById('maintenance_mode').addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('Are you sure you want to enable maintenance mode? This will prevent users from accessing the site.')) {
                    this.checked = false;
                }
            }
        });

        console.log('⚙️ Settings page loaded');
        console.log('🏠 Site name:', '<?= getSetting('site_name', 'Chloe Belle') ?>');
        console.log('💱 Default currency:', '<?= getSetting('default_currency', 'GBP') ?>');
    </script>
</body>
</html>