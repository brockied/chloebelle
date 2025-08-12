<?php
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$messageType = 'info';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: ../auth/login.php');
        exit;
    }

    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['update_profile'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $bio = trim($_POST['bio'] ?? '');
            
            // Validate input
            if (empty($username) || empty($email)) {
                $message = 'Username and email are required.';
                $messageType = 'danger';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
                $messageType = 'danger';
            } else {
                // Check if username/email already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user['id']]);
                
                if ($stmt->fetch()) {
                    $message = 'Username or email already exists.';
                    $messageType = 'danger';
                } else {
                    // Update profile
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, bio = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$username, $email, $bio, $user['id']]);
                    
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch();
                }
            }
        }
        
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $message = 'All password fields are required.';
                $messageType = 'danger';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'danger';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'New passwords do not match.';
                $messageType = 'danger';
            } elseif (strlen($newPassword) < 6) {
                $message = 'New password must be at least 6 characters long.';
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                
                $message = 'Password changed successfully!';
                $messageType = 'success';
            }
        }
        
        if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
            $file = $_FILES['avatar'];
            
            if ($file['error'] === 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    $message = 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).';
                    $messageType = 'danger';
                } elseif ($file['size'] > $maxSize) {
                    $message = 'File size must be less than 5MB.';
                    $messageType = 'danger';
                } else {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $extension;
                    $uploadPath = '../uploads/avatars/' . $filename;
                    
                    // Create upload directory if it doesn't exist
                    if (!file_exists('../uploads/avatars/')) {
                        mkdir('../uploads/avatars/', 0755, true);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        // Create thumbnail
                        createAvatarThumbnail($uploadPath, '../uploads/avatars/thumb_' . $filename);
                        
                        // Delete old avatar if exists
                        if ($user['avatar'] && file_exists('../uploads/avatars/' . $user['avatar'])) {
                            unlink('../uploads/avatars/' . $user['avatar']);
                            if (file_exists('../uploads/avatars/thumb_' . $user['avatar'])) {
                                unlink('../uploads/avatars/thumb_' . $user['avatar']);
                            }
                        }
                        
                        // Update database
                        $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$filename, $user['id']]);
                        
                        $message = 'Avatar updated successfully!';
                        $messageType = 'success';
                        
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        $user = $stmt->fetch();
                    } else {
                        $message = 'Error uploading avatar. Please try again.';
                        $messageType = 'danger';
                    }
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Settings error: " . $e->getMessage());
    $message = 'An error occurred. Please try again.';
    $messageType = 'danger';
}

function createAvatarThumbnail($source, $destination, $size = 150) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) return false;
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Create source image resource
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Calculate crop dimensions (square crop from center)
    $cropSize = min($sourceWidth, $sourceHeight);
    $cropX = ($sourceWidth - $cropSize) / 2;
    $cropY = ($sourceHeight - $cropSize) / 2;
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($size, $size);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefill($thumbnail, 0, 0, $transparent);
    }
    
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, $cropX, $cropY, $size, $size, $cropSize, $cropSize);
    
    // Save thumbnail
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($thumbnail, $destination, 90);
            break;
        case 'image/png':
            $result = imagepng($thumbnail, $destination);
            break;
        case 'image/gif':
            $result = imagegif($thumbnail, $destination);
            break;
        case 'image/webp':
            $result = imagewebp($thumbnail, $destination, 90);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return $result;
}

function getAvatarUrl($avatar, $useThumb = true) {
    if (!$avatar) {
        return '../assets/images/default-avatar.jpg';
    }
    
    $avatarPath = $useThumb ? '../uploads/avatars/thumb_' . $avatar : '../uploads/avatars/' . $avatar;
    
    if ($useThumb && !file_exists($avatarPath)) {
        $avatarPath = '../uploads/avatars/' . $avatar;
    }
    
    return file_exists($avatarPath) ? $avatarPath : '../assets/images/default-avatar.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .settings-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .avatar-upload {
            position: relative;
            display: inline-block;
        }
        
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        
        .avatar-upload:hover .upload-overlay {
            opacity: 1;
        }
        
        .section-header {
            border-bottom: 2px solid #6c5ce7;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus {
            border-color: #6c5ce7;
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a4fd4, #8b7cf0);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(108, 92, 231, 0.9);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../feed/index.php">
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
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <img src="<?= getAvatarUrl($user['avatar']) ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?= htmlspecialchars($user['username']) ?>
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
                <div class="settings-container">
                    <div class="section-header">
                        <h4><i class="fas fa-user me-2"></i>Profile Information</h4>
                    </div>
                    
                    <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Avatar Settings -->
                <div class="settings-container">
                    <div class="section-header">
                        <h4><i class="fas fa-camera me-2"></i>Profile Picture</h4>
                    </div>
                    
                    <div class="text-center">
                        <div class="avatar-upload mb-3">
                            <img src="<?= getAvatarUrl($user['avatar']) ?>" alt="Current Avatar" class="avatar-preview" id="avatarPreview">
                            <div class="upload-overlay" onclick="document.getElementById('avatarInput').click()">
                                <i class="fas fa-camera text-white fa-2x"></i>
                            </div>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="avatarForm">
                            <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                            <button type="button" class="btn btn-outline-primary me-2" onclick="document.getElementById('avatarInput').click()">
                                <i class="fas fa-upload me-1"></i>Choose Photo
                            </button>
                            <button type="submit" name="upload_avatar" class="btn btn-primary" id="uploadBtn" style="display: none;">
                                <i class="fas fa-save me-1"></i>Save Avatar
                            </button>
                        </form>
                        
                        <small class="text-muted d-block mt-2">
                            Supported formats: JPEG, PNG, GIF, WebP. Max size: 5MB.
                        </small>
                    </div>
                </div>

                <!-- Password Settings -->
                <div class="settings-container">
                    <div class="section-header">
                        <h4><i class="fas fa-lock me-2"></i>Change Password</h4>
                    </div>
                    
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key me-1"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Information -->
                <div class="settings-container">
                    <div class="section-header">
                        <h4><i class="fas fa-info-circle me-2"></i>Account Information</h4>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>Account Type:</strong>
                            <span class="badge bg-primary ms-2"><?= ucfirst($user['role']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Subscription:</strong>
                            <span class="badge bg-<?= $user['subscription_status'] === 'none' ? 'secondary' : 'success' ?> ms-2">
                                <?= $user['subscription_status'] === 'none' ? 'Free' : ucfirst($user['subscription_status']) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Member Since:</strong>
                            <span class="text-muted"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Last Updated:</strong>
                            <span class="text-muted"><?= date('F j, Y', strtotime($user['updated_at'])) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($user['subscription_status'] === 'none'): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-star me-2"></i>
                            <strong>Upgrade to Premium!</strong>
                            <p class="mb-2">Get access to exclusive content and features.</p>
                            <a href="../subscription/plans.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-crown me-1"></i>View Plans
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                    document.getElementById('uploadBtn').style.display = 'inline-block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>