<?php
/**
 * User Settings Page for Chloe Belle Website
 */

session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

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

    // Get current user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../auth/logout.php');
        exit;
    }

    // Handle form submissions
    if ($_POST) {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_profile':
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $language = $_POST['language'] ?? 'en';
                $currency = $_POST['currency'] ?? 'GBP';
                $timezone = $_POST['timezone'] ?? 'Europe/London';

                // Validate input
                if (empty($username) || empty($email)) {
                    throw new Exception('Username and email are required');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address');
                }

                // Check if username/email already taken by other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Username or email already taken');
                }

                // Update user profile
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, first_name = ?, last_name = ?, 
                        language = ?, currency = ?, timezone = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username, $email, $firstName, $lastName,
                    $language, $currency, $timezone, $_SESSION['user_id']
                ]);

                // Update session
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;

                $message = "Profile updated successfully!";
                $messageType = 'success';

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                break;

            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($currentPassword) || empty($newPassword)) {
                    throw new Exception('All password fields are required');
                }

                if (!password_verify($currentPassword, $user['password'])) {
                    throw new Exception('Current password is incorrect');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New passwords do not match');
                }

                if (strlen($newPassword) < 8) {
                    throw new Exception('New password must be at least 8 characters long');
                }

                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);

                $message = "Password changed successfully!";
                $messageType = 'success';
                break;

            case 'update_preferences':
                $notifications = isset($_POST['notifications_enabled']) ? 1 : 0;

                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET notifications_enabled = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$notifications, $_SESSION['user_id']]);

                $message = "Preferences updated successfully!";
                $messageType = 'success';

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                break;
        }
    }

} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --accent-color: #fd79a8;
            --gradient-primary: linear-gradient(135deg, #6c5ce7, #a29bfe);
        }
        
        body {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8e5ff 100%);
            min-height: 100vh;
        }
        
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(108, 92, 231, 0.9) !important;
        }
        
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .settings-header {
            background: var(--gradient-primary);
            color: white;
            padding: 15px 20px;
            border-radius: 20px 20px 0 0;
            margin: 0;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.4);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-star me-2"></i>Chloe Belle
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../feed/index.php">
                            <i class="fas fa-home me-1"></i>Feed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../subscription/plans.php">
                            <i class="fas fa-star me-1"></i>Subscribe
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($user['username']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item active" href="settings.php">Settings</a></li>
                            <?php if ($user['role'] === 'chloe'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/posts.php">
                                    <i class="fas fa-cogs me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/">Admin Panel</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-cog me-2"></i>Account Settings</h2>
                    <a href="../feed/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Feed
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile Settings -->
                <div class="settings-card">
                    <h5 class="settings-header">
                        <i class="fas fa-user me-2"></i>Profile Information
                    </h5>
                    <div class="p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="language" class="form-label">Language</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="en" <?= $user['language'] === 'en' ? 'selected' : '' ?>>English</option>
                                        <option value="es" <?= $user['language'] === 'es' ? 'selected' : '' ?>>Español</option>
                                        <option value="fr" <?= $user['language'] === 'fr' ? 'selected' : '' ?>>Français</option>
                                        <option value="de" <?= $user['language'] === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="currency" class="form-label">Currency</label>
                                    <select class="form-select" id="currency" name="currency">
                                        <option value="GBP" <?= $user['currency'] === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                        <option value="USD" <?= $user['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                        <option value="EUR" <?= $user['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="Europe/London" <?= $user['timezone'] === 'Europe/London' ? 'selected' : '' ?>>London (GMT)</option>
                                        <option value="America/New_York" <?= $user['timezone'] === 'America/New_York' ? 'selected' : '' ?>>New York (EST)</option>
                                        <option value="America/Los_Angeles" <?= $user['timezone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>Los Angeles (PST)</option>
                                        <option value="Europe/Paris" <?= $user['timezone'] === 'Europe/Paris' ? 'selected' : '' ?>>Paris (CET)</option>
                                        <option value="Asia/Tokyo" <?= $user['timezone'] === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo (JST)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Settings -->
                <div class="settings-card">
                    <h5 class="settings-header">
                        <i class="fas fa-lock me-2"></i>Change Password
                    </h5>
                    <div class="p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Notification Preferences -->
                <div class="settings-card">
                    <h5 class="settings-header">
                        <i class="fas fa-bell me-2"></i>Notification Preferences
                    </h5>
                    <div class="p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notifications_enabled" 
                                       name="notifications_enabled" <?= $user['notifications_enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifications_enabled">
                                    <i class="fas fa-envelope me-2"></i>Email Notifications
                                </label>
                                <div class="form-text">Receive email notifications for new posts, comments, and updates</div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="settings-card">
                    <h5 class="settings-header">
                        <i class="fas fa-info-circle me-2"></i>Account Information
                    </h5>
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Account Type:</span>
                                    <strong><?= ucfirst($user['role']) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Member Since:</span>
                                    <strong><?= date('M j, Y', strtotime($user['created_at'])) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Subscription:</span>
                                    <strong><?= ucfirst($user['subscription_status']) ?></strong>
                                </div>
                            </div>
                            <?php if ($user['subscription_expires']): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Expires:</span>
                                    <strong><?= date('M j, Y', strtotime($user['subscription_expires'])) ?></strong>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($user['subscription_status'] === 'none'): ?>
                        <div class="text-center mt-3">
                            <a href="../subscription/plans.php" class="btn btn-primary">
                                <i class="fas fa-crown me-2"></i>Upgrade to Premium
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (confirmPassword) this.classList.add('is-valid');
            }
        });

        console.log('⚙️ User Settings loaded successfully!');
    </script>
</body>
</html>