<?php
/**
 * User Profile Page for Chloe Belle Website - Improved Layout
 * Allows users to upload profile pictures and manage their account
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
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        header('Location: ../auth/logout.php');
        exit;
    }

} catch (Exception $e) {
    error_log("Profile database error: " . $e->getMessage());
    $message = "Database connection error";
    $messageType = 'danger';
}

// Handle profile picture upload
if ($_POST && isset($_FILES['profile_picture'])) {
    try {
        $file = $_FILES['profile_picture'];
        
        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error occurred');
        }
        
        // Check file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size is 5MB');
        }
        
        // Check file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('Invalid file type. Please upload JPG, PNG, or WebP images only');
        }
        
        // Verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('Invalid image file');
        }
        
        // Create avatars directory if it doesn't exist
        $avatarDir = '../uploads/avatars';
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'avatar_' . $currentUser['id'] . '_' . time() . '.' . $extension;
        $destination = $avatarDir . '/' . $filename;
        
        // Delete old avatar if exists
        if ($currentUser['avatar'] && file_exists($avatarDir . '/' . $currentUser['avatar'])) {
            unlink($avatarDir . '/' . $currentUser['avatar']);
            
            // Also delete thumbnail
            $oldThumb = $avatarDir . '/thumb_' . $currentUser['avatar'];
            if (file_exists($oldThumb)) {
                unlink($oldThumb);
            }
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Set proper permissions
            chmod($destination, 0644);
            
            // Create thumbnail (150x150)
            createAvatarThumbnail($destination, $avatarDir . '/thumb_' . $filename, 150, 150);
            
            // Update database
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $currentUser['id']]);
            
            // Update session and current user data
            $currentUser['avatar'] = $filename;
            
            $message = 'Profile picture updated successfully!';
            $messageType = 'success';
        } else {
            throw new Exception('Failed to upload file. Check directory permissions');
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle profile info updates
if ($_POST && isset($_POST['update_profile'])) {
    try {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $currentUser['id']]);
        if ($stmt->fetch()) {
            throw new Exception('Email address is already in use');
        }
        
        // Update profile
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $email, $currentUser['id']]);
        
        // Update current user data
        $currentUser['first_name'] = $firstName;
        $currentUser['last_name'] = $lastName;
        $currentUser['email'] = $email;
        $_SESSION['email'] = $email;
        
        $message = 'Profile updated successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        if (!password_verify($currentPassword, $currentUser['password'])) {
            throw new Exception('Current password is incorrect');
        }
        
        // Validate new password
        if (strlen($newPassword) < 8) {
            throw new Exception('New password must be at least 8 characters long');
        }
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match');
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $currentUser['id']]);
        
        $message = 'Password changed successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Helper function to create avatar thumbnail
function createAvatarThumbnail($sourcePath, $destPath, $size = 150, $quality = 90) {
    if (!file_exists($sourcePath)) {
        return false;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceMime = $imageInfo['mime'];
    
    // Create source image
    switch ($sourceMime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Calculate crop dimensions for square thumbnail
    $cropSize = min($sourceWidth, $sourceHeight);
    $cropX = ($sourceWidth - $cropSize) / 2;
    $cropY = ($sourceHeight - $cropSize) / 2;
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($size, $size);
    
    // Preserve transparency for PNG
    if ($sourceMime == 'image/png') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $size, $size, $transparent);
    }
    
    // Resize and crop to square
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, $cropX, $cropY, $size, $size, $cropSize, $cropSize);
    
    // Save thumbnail as JPEG
    $result = imagejpeg($thumbnail, $destPath, $quality);
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return $result;
}

function getAvatarUrl($avatar, $useThumb = true) {
    if (!$avatar) {
        return '../assets/images/default-avatar.jpg';
    }
    
    $avatarPath = $useThumb ? '../uploads/avatars/thumb_' . $avatar : '../uploads/avatars/' . $avatar;
    
    // Check if thumbnail exists, fallback to original
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
    <title>My Profile - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --accent-color: #fd79a8;
            --gradient-primary: linear-gradient(135deg, #6c5ce7, #a29bfe);
            --gradient-accent: linear-gradient(135deg, #fd79a8, #fdcb6e);
        }
        
        body {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8e5ff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(108, 92, 231, 0.9) !important;
            box-shadow: 0 2px 20px rgba(108, 92, 231, 0.3);
        }
        
        .profile-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="1" fill="white" opacity="0.05"/><circle cx="10" cy="90" r="1" fill="white" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }
        
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .profile-card:hover {
            transform: translateY(-2px);
        }
        
        .avatar-section {
            position: relative;
            text-align: center;
            z-index: 1;
        }
        
        .avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.3);
            transition: all 0.3s ease;
        }
        
        .avatar-large:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 35px rgba(108, 92, 231, 0.4);
        }
        
        .avatar-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(108, 92, 231, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .avatar-container:hover .avatar-upload-overlay {
            opacity: 1;
        }
        
        .subscription-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .subscription-badge.lifetime {
            background: var(--gradient-accent);
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.15);
            transform: translateY(-1px);
        }
        
        .btn {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.6);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            display: block;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        .section-header {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem 1.5rem;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-header i {
            font-size: 1.2rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .upload-zone {
            border: 2px dashed var(--primary-color);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: rgba(108, 92, 231, 0.02);
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 1rem 0;
        }
        
        .upload-zone:hover {
            background: rgba(108, 92, 231, 0.05);
            border-color: var(--accent-color);
            transform: translateY(-2px);
        }
        
        .current-user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-right: 8px;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
            background: rgba(108, 92, 231, 0.1);
        }
        
        .progress-bar {
            background: var(--gradient-primary);
            border-radius: 10px;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }
        
        .alert-info {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary-color);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .row {
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 1.5rem 0;
                margin-bottom: 1rem;
            }
            
            .avatar-large {
                width: 100px;
                height: 100px;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="current-user-avatar">
                            <?= htmlspecialchars($currentUser['username']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/">
                                    <i class="fas fa-shield-alt me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <div class="profile-header mt-5 pt-3">
        <div class="container">
            <div class="avatar-section">
                <div class="avatar-container">
                    <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="Profile Picture" class="avatar-large" id="currentAvatar">
                    <div class="avatar-upload-overlay" onclick="document.getElementById('profilePicture').click()">
                        <i class="fas fa-camera fa-lg text-white"></i>
                    </div>
                </div>
                
                <h2 class="mb-2"><?= htmlspecialchars($currentUser['username']) ?>
                    <?php if ($currentUser['role'] === 'chloe'): ?>
                        <i class="fas fa-star text-warning ms-2" title="Chloe Belle"></i>
                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                        <i class="fas fa-shield-alt text-light ms-2" title="Administrator"></i>
                    <?php endif; ?>
                </h2>
                
                <?php if ($currentUser['first_name'] || $currentUser['last_name']): ?>
                    <p class="mb-3 opacity-75"><?= htmlspecialchars(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])) ?></p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <?php if ($currentUser['subscription_status'] === 'none'): ?>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-user me-1"></i>Free Account
                        </span>
                    <?php else: ?>
                        <span class="subscription-badge <?= $currentUser['subscription_status'] === 'lifetime' ? 'lifetime' : '' ?>">
                            <i class="fas fa-crown"></i>
                            <?= ucfirst($currentUser['subscription_status']) ?> Subscriber
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Account Stats -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number">0</span>
                        <div class="stat-label">Posts</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">0</span>
                        <div class="stat-label">Likes</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">0</span>
                        <div class="stat-label">Comments</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= date('M \'y', strtotime($currentUser['created_at'])) ?></span>
                        <div class="stat-label">Joined</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Picture Upload -->
            <div class="col-lg-6 mb-4">
                <div class="profile-card">
                    <div class="section-header">
                        <i class="fas fa-camera"></i>
                        Profile Picture
                    </div>
                    <div class="card-body">
                        <!-- Hidden File Input -->
                        <form method="POST" enctype="multipart/form-data" id="avatarForm">
                            <input type="file" id="profilePicture" name="profile_picture" accept="image/*" style="display: none;">
                        </form>

                        <div class="upload-zone" onclick="document.getElementById('profilePicture').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-3"></i>
                            <h6 class="mb-2">Click to upload new picture</h6>
                            <p class="text-muted mb-0">JPG, PNG, or WebP up to 5MB<br>
                            <small>Image will be automatically cropped to square</small></p>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Your profile picture appears next to your posts and comments
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="col-lg-6 mb-4">
                <div class="profile-card">
                    <div class="section-header">
                        <i class="fas fa-user"></i>
                        Account Information
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>"
                                           placeholder="Enter first name">
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>"
                                           placeholder="Enter last name">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($currentUser['email']) ?>" 
                                       placeholder="Enter email address" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['username']) ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Change Password -->
            <div class="col-lg-8 mb-4">
                <div class="profile-card">
                    <div class="section-header">
                        <i class="fas fa-lock"></i>
                        Change Password
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" 
                                           placeholder="Enter current password" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Enter new password" required>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm new password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4 mb-4">
                <div class="profile-card">
                    <div class="section-header">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="../feed/index.php" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>Back to Feed
                            </a>
                            
                            <?php if ($currentUser['subscription_status'] === 'none'): ?>
                                <a href="../subscription/plans.php" class="btn btn-primary">
                                    <i class="fas fa-crown me-2"></i>Upgrade Account
                                </a>
                            <?php endif; ?>
                            
                            <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                <a href="../admin/" class="btn btn-outline-success">
                                    <i class="fas fa-cogs me-2"></i>Admin Panel
                                </a>
                            <?php endif; ?>
                            
                            <a href="../auth/logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                Your data is secure and encrypted
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit avatar form when file is selected
        document.getElementById('profilePicture').addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File too large! Please choose an image under 5MB.');
                    this.value = '';
                    return;
                }
                
                // Show preview immediately
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('currentAvatar').src = e.target.result;
                };
                reader.readAsDataURL(file);
                
                // Submit form
                document.getElementById('avatarForm').submit();
            }
        });

        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                document.getElementById('confirm_password').focus();
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                document.getElementById('new_password').focus();
                return false;
            }
        });

        // Form field animations
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        console.log('👤 Profile page loaded');
        console.log('🔐 User role:', '<?= $currentUser['role'] ?>');
        console.log('💎 Subscription:', '<?= $currentUser['subscription_status'] ?>');
        console.log('🎨 Enhanced UI loaded');
    </script>
</body>
</html>