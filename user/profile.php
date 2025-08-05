<?php
/**
 * Enhanced Profile Page with Avatar Upload and Crop
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
    $message = "Database connection error. Please try again later.";
    $messageType = 'danger';
}

// Ensure upload directory exists
$avatarDir = '../uploads/avatars';
if (!is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

// Handle profile picture upload with crop data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cropped_image'])) {
    try {
        $croppedImageData = $_POST['cropped_image'];
        
        // Remove data:image/jpeg;base64, part
        $croppedImageData = preg_replace('#^data:image/[^;]+;base64,#', '', $croppedImageData);
        $croppedImageData = base64_decode($croppedImageData);
        
        if (!$croppedImageData) {
            throw new Exception('Invalid image data received.');
        }
        
        // Generate unique filename
        $filename = 'avatar_' . $currentUser['id'] . '_' . time() . '.jpg';
        $destination = $avatarDir . '/' . $filename;
        
        // Delete old avatar if exists
        if (!empty($currentUser['avatar'])) {
            $oldFile = $avatarDir . '/' . $currentUser['avatar'];
            $oldThumb = $avatarDir . '/thumb_' . $currentUser['avatar'];
            
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
            
            if (file_exists($oldThumb)) {
                unlink($oldThumb);
            }
        }
        
        // Save cropped image
        if (file_put_contents($destination, $croppedImageData)) {
            chmod($destination, 0644);
            
            // Create thumbnail
            if (extension_loaded('gd')) {
                $thumbPath = $avatarDir . '/thumb_' . $filename;
                createAvatarThumbnail($destination, $thumbPath, 200, 95);
            }
            
            // Update database
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $currentUser['id']]);
            
            // Update current user data
            $currentUser['avatar'] = $filename;
            
            $message = 'Profile picture updated successfully!';
            $messageType = 'success';
            
            // Clear any cache
            $_SESSION['avatar_updated'] = time();
            
        } else {
            throw new Exception('Failed to save cropped image. Please try again.');
        }
        
    } catch (Exception $e) {
        error_log("Avatar upload error: " . $e->getMessage());
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle regular file upload (for cropping preview)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    try {
        $file = $_FILES['profile_picture'];
        
        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed. Please try again.');
        }
        
        // Check file size (10MB max for cropping)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size allowed is 10MB.');
        }
        
        if ($file['size'] == 0) {
            throw new Exception('Empty file uploaded. Please select a valid image.');
        }
        
        // Check file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        if (!$extension || !in_array($extension, $allowedTypes)) {
            throw new Exception('Invalid file type. Please use JPG, PNG, or WebP format.');
        }
        
        // Verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('File is not a valid image.');
        }
        
        // Read and encode image for cropper
        $imageData = file_get_contents($file['tmp_name']);
        $base64Image = 'data:' . $imageInfo['mime'] . ';base64,' . base64_encode($imageData);
        
        // Store in session for cropper
        $_SESSION['temp_image'] = $base64Image;
        $_SESSION['show_crop_modal'] = true;
        
    } catch (Exception $e) {
        error_log("Avatar upload error: " . $e->getMessage());
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Enhanced thumbnail creation function
function createAvatarThumbnail($sourcePath, $destPath, $size = 200, $quality = 95) {
    if (!extension_loaded('gd') || !file_exists($sourcePath)) {
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
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = imagecreatefromwebp($sourcePath);
            } else {
                return false;
            }
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create thumbnail (already cropped, so just resize)
    $thumbnail = imagecreatetruecolor($size, $size);
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $size, $size, $sourceWidth, $sourceHeight);
    
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
    
    // Add cache buster if avatar was just updated
    $cacheBuster = isset($_SESSION['avatar_updated']) ? '?v=' . $_SESSION['avatar_updated'] : '';
    
    return file_exists($avatarPath) ? $avatarPath . $cacheBuster : '../assets/images/default-avatar.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 80px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar-brand {
            color: #667eea !important;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .nav-link {
            color: #495057 !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #667eea !important;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 0;
            overflow: hidden;
            margin: 20px auto;
            max-width: 1000px;
        }

        .profile-header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="0%" r="100%"><stop offset="0%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient></defs><rect width="100" height="20" fill="url(%23a)"/></svg>');
            opacity: 0.1;
        }

        .avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }

        .avatar-main {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .avatar-main:hover {
            transform: scale(1.05);
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

        .avatar-container:hover .upload-overlay {
            opacity: 1;
        }

        .upload-area {
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            padding: 40px;
            margin: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .upload-area:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .upload-area.dragging {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.7);
            transform: scale(1.02);
        }

        .profile-content {
            padding: 40px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .info-card .card-header {
            background: var(--secondary-gradient);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
        }

        .info-card .card-body {
            padding: 25px;
        }

        .info-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 16px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
        }

        .info-value {
            color: #6c757d;
            flex: 1;
        }

        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Crop Modal Styles */
        .crop-modal .modal-dialog {
            max-width: 800px;
        }

        .crop-container {
            max-height: 400px;
            background: #f8f9fa;
        }

        .crop-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #007bff;
            margin: 0 auto;
        }

        .crop-controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .profile-header {
                padding: 30px 20px;
            }
            
            .profile-content {
                padding: 20px;
            }
            
            .avatar-main {
                width: 120px;
                height: 120px;
            }
            
            .upload-area {
                margin: 20px 0;
                padding: 30px 20px;
            }

            .crop-modal .modal-dialog {
                max-width: 95%;
                margin: 10px auto;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-star me-2"></i>Chloe Belle
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../feed/">
                    <i class="fas fa-home me-1"></i>Feed
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="main-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-container">
                    <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" 
                         alt="Profile Picture" 
                         class="avatar-main" 
                         id="currentAvatar">
                    <div class="upload-overlay" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-camera fa-2x"></i>
                    </div>
                </div>
                <h2 class="mb-2"><?= htmlspecialchars($currentUser['username']) ?></h2>
                <p class="mb-0 opacity-75">
                    <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($currentUser['email']) ?>
                </p>
            </div>

            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Upload Area -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-camera me-2"></i>Update Profile Picture
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-area" id="uploadArea">
                                <input type="file" 
                                       id="fileInput" 
                                       name="profile_picture" 
                                       accept="image/*" 
                                       style="display: none;">
                                <div id="uploadText">
                                    <i class="fas fa-crop-alt fa-3x mb-3" style="opacity: 0.7;"></i>
                                    <h5 class="mb-2">Drop your image here or click to browse</h5>
                                    <p class="mb-0" style="opacity: 0.8;">
                                        JPG, PNG, or WebP • Maximum 10MB • Crop & resize tool included
                                    </p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- User Information -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i>Account Information
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-label">Username:</div>
                            <div class="info-value"><?= htmlspecialchars($currentUser['username']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?= htmlspecialchars($currentUser['email']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="info-label">Role:</div>
                            <div class="info-value">
                                <span class="badge bg-primary"><?= htmlspecialchars($currentUser['role']) ?></span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="info-label">Subscription:</div>
                            <div class="info-value">
                                <span class="badge bg-<?= $currentUser['subscription_status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars($currentUser['subscription_status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <a href="../feed/" class="btn-gradient me-3">
                                <i class="fas fa-arrow-left me-2"></i>Back to Feed
                            </a>
                            <a href="../auth/logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Crop Modal -->
    <div class="modal fade crop-modal" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropModalLabel">
                        <i class="fas fa-crop-alt me-2"></i>Crop Your Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="crop-container">
                        <img id="cropperImage" style="max-width: 100%;">
                    </div>
                    
                    <div class="crop-controls">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <label class="form-label mb-2">Preview:</label>
                                <div class="crop-preview" id="cropPreview"></div>
                            </div>
                            <div class="col-md-4 text-center">
                                <small class="text-muted d-block mb-2">Drag to move • Scroll to zoom</small>
                                <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="rotateCropper(-90)">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rotateCropper(90)">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveCroppedImage()">
                        <i class="fas fa-save me-2"></i>Save Avatar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        let cropper = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            const uploadArea = document.getElementById('uploadArea');
            const uploadForm = document.getElementById('uploadForm');
            const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));

            // Show crop modal if image was uploaded
            <?php if (isset($_SESSION['show_crop_modal']) && $_SESSION['show_crop_modal']): ?>
                showCropModal('<?= $_SESSION['temp_image'] ?>');
                <?php 
                unset($_SESSION['show_crop_modal']);
                unset($_SESSION['temp_image']);
                ?>
            <?php endif; ?>

            // Click to upload
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });

            uploadArea.addEventListener('drop', handleDrop, false);

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight(e) {
                uploadArea.classList.add('dragging');
            }

            function unhighlight(e) {
                uploadArea.classList.remove('dragging');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    fileInput.files = files;
                    handleFiles(files);
                }
            }

            // File input change
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    handleFiles(this.files);
                }
            });

            function handleFiles(files) {
                const file = files[0];
                
                // Basic validation
                if (file.size > 10 * 1024 * 1024) {
                    showAlert('File too large! Maximum size allowed is 10MB.', 'danger');
                    fileInput.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    showAlert('Invalid file type! Please use JPG, PNG, or WebP format.', 'danger');
                    fileInput.value = '';
                    return;
                }
                
                // Read file and show cropper
                const reader = new FileReader();
                reader.onload = function(e) {
                    showCropModal(e.target.result);
                };
                reader.readAsDataURL(file);
            }

            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const container = document.querySelector('.profile-content');
                const firstCard = container.querySelector('.info-card');
                container.insertBefore(alertDiv, firstCard);
                
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        });

        function showCropModal(imageSrc) {
            const cropperImage = document.getElementById('cropperImage');
            const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));
            
            cropperImage.src = imageSrc;
            cropModal.show();
            
            // Initialize cropper after modal is shown
            document.getElementById('cropModal').addEventListener('shown.bs.modal', function() {
                if (cropper) {
                    cropper.destroy();
                }
                
                cropper = new Cropper(cropperImage, {
                    aspectRatio: 1,
                    viewMode: 2,
                    preview: '#cropPreview',
                    background: false,
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            }, { once: true });
            
            // Clean up cropper when modal is hidden
            document.getElementById('cropModal').addEventListener('hidden.bs.modal', function() {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            });
        }

        function rotateCropper(degree) {
            if (cropper) {
                cropper.rotate(degree);
            }
        }

        function saveCroppedImage() {
            if (!cropper) {
                return;
            }
            
            // Get cropped canvas
            const canvas = cropper.getCroppedCanvas({
                width: 300,
                height: 300,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            // Convert to blob and submit
            canvas.toBlob(function(blob) {
                const reader = new FileReader();
                reader.onload = function() {
                    // Create form and submit cropped image
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'cropped_image';
                    input.value = reader.result;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                };
                reader.readAsDataURL(blob);
            }, 'image/jpeg', 0.9);
        }

        console.log('Profile page with crop functionality loaded');
    </script>
</body>
</html>