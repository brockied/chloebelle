<?php
/**
 * Enhanced Media Management Page for Chloe Belle Admin
 */

session_start();
require_once '../config.php';

// Check if user is logged in and has access (admin or chloe)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'chloe'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle file uploads
if ($_POST && isset($_FILES)) {
    try {
        $uploadType = $_POST['upload_type'] ?? '';
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($uploadType === 'homepage_hero') {
            $file = $_FILES['homepage_hero'] ?? null;
            
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Validate file
                $fileInfo = pathinfo($file['name']);
                $extension = strtolower($fileInfo['extension']);
                
                if (!in_array($extension, $allowedTypes)) {
                    throw new Exception('Invalid file type. Please upload JPG, PNG, or WebP images only.');
                }
                
                if ($file['size'] > $maxSize) {
                    throw new Exception('File too large. Maximum size is 10MB.');
                }
                
                // Verify it's actually an image
                $imageInfo = getimagesize($file['tmp_name']);
                if (!$imageInfo) {
                    throw new Exception('Invalid image file.');
                }
                
                // Create upload directory if it doesn't exist
                $uploadDir = '../uploads/chloe';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Backup current image
                $currentImage = $uploadDir . '/profile.jpg';
                if (file_exists($currentImage)) {
                    $backupName = $uploadDir . '/profile_backup_' . date('Y-m-d_H-i-s') . '.jpg';
                    copy($currentImage, $backupName);
                }
                
                // Move uploaded file
                $destination = $uploadDir . '/profile.jpg';
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Set proper permissions
                    chmod($destination, 0644);
                    
                    // Create thumbnail for admin preview
                    $thumbnailPath = $uploadDir . '/profile_thumb.jpg';
                    createThumbnail($destination, $thumbnailPath, 300, 300);
                    
                    $message = 'Homepage hero image updated successfully!';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to upload file. Check directory permissions.');
                }
            } else {
                throw new Exception('No file uploaded or upload error occurred.');
            }
        }
        
        if ($uploadType === 'featured_gallery') {
            $files = $_FILES['featured_images'] ?? null;
            
            if ($files && is_array($files['name'])) {
                $uploadDir = '../uploads/featured';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $uploadedCount = 0;
                $errors = [];
                
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $fileName = $files['name'][$i];
                        $tempFile = $files['tmp_name'][$i];
                        $fileSize = $files['size'][$i];
                        
                        // Validate file
                        $fileInfo = pathinfo($fileName);
                        $extension = strtolower($fileInfo['extension']);
                        
                        if (!in_array($extension, $allowedTypes)) {
                            $errors[] = "Skipped {$fileName}: Invalid file type";
                            continue;
                        }
                        
                        if ($fileSize > $maxSize) {
                            $errors[] = "Skipped {$fileName}: File too large";
                            continue;
                        }
                        
                        // Generate unique filename
                        $newFileName = 'featured_' . uniqid() . '.' . $extension;
                        $destination = $uploadDir . '/' . $newFileName;
                        
                        if (move_uploaded_file($tempFile, $destination)) {
                            chmod($destination, 0644);
                            createThumbnail($destination, $uploadDir . '/thumb_' . $newFileName, 300, 300);
                            $uploadedCount++;
                        } else {
                            $errors[] = "Failed to upload {$fileName}";
                        }
                    }
                }
                
                if ($uploadedCount > 0) {
                    $message = "Successfully uploaded {$uploadedCount} featured images!";
                    $messageType = 'success';
                    
                    if (!empty($errors)) {
                        $message .= ' ' . implode(', ', $errors);
                    }
                } else {
                    $message = 'No files were uploaded. ' . implode(', ', $errors);
                    $messageType = 'warning';
                }
            }
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle image deletion
if (isset($_GET['delete']) && isset($_GET['type'])) {
    $deleteType = $_GET['type'];
    $filename = $_GET['delete'];
    
    try {
        if ($deleteType === 'featured' && preg_match('/^featured_[a-f0-9]+\.(jpg|jpeg|png|webp)$/i', $filename)) {
            $filePath = '../uploads/featured/' . $filename;
            $thumbPath = '../uploads/featured/thumb_' . $filename;
            
            if (file_exists($filePath)) {
                unlink($filePath);
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
                $message = 'Image deleted successfully';
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        $message = 'Error deleting image: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current images
$currentHeroImage = '../uploads/chloe/profile.jpg';
$heroImageExists = file_exists($currentHeroImage);
$heroImageUrl = $heroImageExists ? 'uploads/chloe/profile.jpg?v=' . filemtime($currentHeroImage) : null;

// Get featured images
$featuredImages = [];
$featuredDir = '../uploads/featured';
if (is_dir($featuredDir)) {
    $files = scandir($featuredDir);
    foreach ($files as $file) {
        if (preg_match('/^featured_[a-f0-9]+\.(jpg|jpeg|png|webp)$/i', $file)) {
            $featuredImages[] = [
                'filename' => $file,
                'url' => 'uploads/featured/' . $file,
                'thumb_url' => 'uploads/featured/thumb_' . $file,
                'size' => file_exists($featuredDir . '/' . $file) ? filesize($featuredDir . '/' . $file) : 0,
                'modified' => file_exists($featuredDir . '/' . $file) ? filemtime($featuredDir . '/' . $file) : 0
            ];
        }
    }
    // Sort by modification time (newest first)
    usort($featuredImages, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Helper function to create thumbnail
function createThumbnail($sourcePath, $destPath, $maxWidth = 300, $maxHeight = 300, $quality = 80) {
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
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
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
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if ($sourceMime == 'image/png') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Save thumbnail
    $result = imagejpeg($thumbnail, $destPath, $quality);
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return $result;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Management - Chloe Belle Admin</title>
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

        /* Sidebar Styles - Same as other pages */
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

        /* Main Content */
        .main-content {
            margin-left: 0;
            transition: var(--transition);
            min-height: 100vh;
            padding: 2rem;
        }

        .main-content.sidebar-open {
            margin-left: var(--sidebar-width);
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

        /* Media Cards */
        .media-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
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

        /* Upload Zone */
        .upload-zone {
            border: 2px dashed var(--primary-color);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            background: rgba(99, 102, 241, 0.05);
            transition: var(--transition);
            cursor: pointer;
            margin-bottom: 1.5rem;
        }

        .upload-zone:hover {
            border-color: var(--secondary-color);
            background: rgba(139, 92, 246, 0.08);
            transform: translateY(-2px);
        }

        .upload-zone.dragover {
            border-color: var(--secondary-color);
            background: rgba(139, 92, 246, 0.1);
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .upload-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .upload-description {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .upload-btn {
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

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Current Image Display */
        .current-image {
            text-align: center;
            margin-bottom: 2rem;
        }

        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            margin-bottom: 1rem;
        }

        .image-info {
            background: rgba(99, 102, 241, 0.1);
            padding: 1rem;
            border-radius: 12px;
            color: var(--dark-color);
        }

        /* Featured Gallery */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .gallery-item {
            position: relative;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
        }

        .gallery-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .gallery-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: var(--transition);
        }

        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }

        .gallery-actions {
            display: flex;
            gap: 0.5rem;
        }

        .gallery-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            transition: var(--transition);
        }

        .gallery-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .gallery-info {
            padding: 1rem;
            text-align: center;
        }

        .gallery-size {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

            .upload-zone {
                padding: 2rem 1rem;
            }

            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
            }
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
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a href="posts.php" class="nav-link">
                    <i class="fas fa-edit"></i>
                    Posts
                </a>
            </div>
            <div class="nav-item">
                <a href="media.php" class="nav-link active">
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="nav-item">
                <a href="subscriptions.php" class="nav-link">
                    <i class="fas fa-credit-card"></i>
                    Subscriptions
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item" style="margin-top: 2rem;">
                <a href="../feed/index.php" class="nav-link">
                    <i class="fas fa-eye"></i>
                    View Site
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

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Header -->
        <header class="page-header fade-in-up">
            <div>
                <h1 class="page-title">Media Management</h1>
                <p class="page-subtitle">Upload and manage images for your website</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Homepage Hero Image Section -->
        <div class="media-section fade-in-up">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-home"></i>
                    Homepage Hero Image
                </h2>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h5>Current Hero Image</h5>
                        <?php if ($heroImageExists): ?>
                            <div class="current-image">
                                <img src="../<?= $heroImageUrl ?>" alt="Current Hero Image" class="image-preview">
                                <div class="image-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Last updated: <?= date('M j, Y g:i A', filemtime($currentHeroImage)) ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-image"></i>
                                <h6>No hero image uploaded yet</h6>
                                <p>Upload an image to display on your homepage</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-6">
                        <h5>Upload New Hero Image</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_type" value="homepage_hero">
                            
                            <div class="upload-zone" onclick="document.getElementById('homepage_hero').click()">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h6 class="upload-title">Click to upload or drag & drop</h6>
                                <p class="upload-description">
                                    JPG, PNG, or WebP up to 10MB<br>
                                    Recommended: 1200x800 pixels
                                </p>
                                <input type="file" id="homepage_hero" name="homepage_hero" accept="image/*" style="display: none;" required>
                            </div>
                            
                            <button type="submit" class="upload-btn">
                                <i class="fas fa-upload"></i>
                                Upload Hero Image
                            </button>
                        </form>
                        
                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Tip:</strong> This image appears next to the login form on your homepage. 
                                A backup of your current image will be saved automatically.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Featured Gallery Images Section -->
        <div class="media-section fade-in-up">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-images"></i>
                    Featured Gallery Images
                </h2>
            </div>
            <div class="section-body">
                <!-- Upload New Featured Images -->
                <div class="mb-4">
                    <h5>Upload Featured Images</h5>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_type" value="featured_gallery">
                        
                        <div class="upload-zone" onclick="document.getElementById('featured_images').click()">
                            <div class="upload-icon">
                                <i class="fas fa-images"></i>
                            </div>
                            <h6 class="upload-title">Upload Multiple Images</h6>
                            <p class="upload-description">
                                Select multiple JPG, PNG, or WebP files<br>
                                Each file up to 10MB
                            </p>
                            <input type="file" id="featured_images" name="featured_images[]" accept="image/*" multiple style="display: none;" required>
                        </div>
                        
                        <button type="submit" class="upload-btn">
                            <i class="fas fa-upload"></i>
                            Upload Featured Images
                        </button>
                    </form>
                </div>

                <!-- Current Featured Images -->
                <div>
                    <h5>Current Featured Images (<?= count($featuredImages) ?>)</h5>
                    
                    <?php if (empty($featuredImages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-images"></i>
                            <h6>No featured images uploaded yet</h6>
                            <p>Upload some images to display in your homepage gallery.</p>
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid">
                            <?php foreach ($featuredImages as $image): ?>
                                <div class="gallery-item">
                                    <?php if (file_exists('../' . $image['thumb_url'])): ?>
                                        <img src="../<?= $image['thumb_url'] ?>?v=<?= $image['modified'] ?>" alt="Featured Image">
                                    <?php else: ?>
                                        <img src="../<?= $image['url'] ?>?v=<?= $image['modified'] ?>" alt="Featured Image">
                                    <?php endif; ?>
                                    
                                    <div class="gallery-overlay">
                                        <div class="gallery-actions">
                                            <a href="../<?= $image['url'] ?>" target="_blank" class="gallery-btn" title="View Full Size">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?delete=<?= urlencode($image['filename']) ?>&type=featured" 
                                               class="gallery-btn" title="Delete Image"
                                               onclick="return confirm('Delete this image?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="gallery-info">
                                        <div class="gallery-size"><?= formatFileSize($image['size']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Toggle Button -->
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

        // Drag and drop functionality
        function setupDragAndDrop(zoneSelector, inputSelector) {
            const zone = document.querySelector(zoneSelector);
            const input = document.querySelector(inputSelector);
            
            if (!zone || !input) return;
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                zone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight(e) {
                zone.classList.add('dragover');
            }
            
            function unhighlight(e) {
                zone.classList.remove('dragover');
            }
            
            zone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                input.files = files;
                
                if (files.length > 0) {
                    const fileName = files.length > 1 ? `${files.length} files selected` : files[0].name;
                    zone.querySelector('.upload-title').textContent = fileName;
                }
            }
        }
        
        // Setup drag and drop for both upload zones
        setupDragAndDrop('.upload-zone:first-of-type', '#homepage_hero');
        setupDragAndDrop('.upload-zone:last-of-type', '#featured_images');
        
        // File input change handlers
        document.getElementById('homepage_hero')?.addEventListener('change', function() {
            if (this.files.length > 0) {
                this.closest('.upload-zone').querySelector('.upload-title').textContent = this.files[0].name;
            }
        });
        
        document.getElementById('featured_images')?.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileName = this.files.length > 1 ? `${this.files.length} files selected` : this.files[0].name;
                this.closest('.upload-zone').querySelector('.upload-title').textContent = fileName;
            }
        });

        console.log('🎉 Enhanced Media Management loaded!');
        console.log('🖼️ Featured images:', <?= count($featuredImages) ?>);
        console.log('✨ Drag & drop support enabled');
    </script>
</body>
</html>