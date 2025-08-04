<div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Tip:</strong> This image appears next to the login form on your homepage. 
                                Images are stored in full quality for the best viewing experience.
                            </small>
                        </div><?php
/**
 * MINIMAL Media Management - No Image Processing
 * Just handles file uploads without any GD or thumbnail creation
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config.php';

// Check if user is logged in and has access (admin or chloe)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'chloe'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle success messages from redirects
if (isset($_GET['hero_success']) && $_GET['hero_success'] == '1') {
    $message = 'Homepage hero image uploaded successfully!';
    $messageType = 'success';
} elseif (isset($_GET['featured_success']) && is_numeric($_GET['featured_success'])) {
    $count = intval($_GET['featured_success']);
    $message = "Successfully uploaded {$count} featured image" . ($count > 1 ? 's' : '') . "!";
    $messageType = 'success';
} elseif (isset($_GET['delete_success']) && $_GET['delete_success'] == '1') {
    $message = 'Image deleted successfully!';
    $messageType = 'success';
}

// Get featured images (no thumbnails needed for now)
$featuredImages = [];
$featuredDir = '../uploads/featured';
if (is_dir($featuredDir)) {
    $files = scandir($featuredDir);
    foreach ($files as $file) {
        if (preg_match('/^featured_[a-f0-9]+\.(jpg|jpeg|png|webp)$/i', $file)) {
            $featuredImages[] = [
                'filename' => $file,
                'url' => 'uploads/featured/' . $file,
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

// Handle file uploads - MINIMAL VERSION
if ($_POST && isset($_FILES)) {
    try {
        $uploadType = $_POST['upload_type'] ?? '';
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($uploadType === 'homepage_hero') {
            $file = $_FILES['homepage_hero'] ?? null;
            
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Basic validation
                $fileInfo = pathinfo($file['name']);
                $extension = strtolower($fileInfo['extension']);
                
                if (!in_array($extension, $allowedTypes)) {
                    throw new Exception('Invalid file type. Please upload JPG, PNG, or WebP images only.');
                }
                
                if ($file['size'] > $maxSize) {
                    throw new Exception('File too large. Maximum size is 10MB.');
                }
                
                // Create upload directory
                $uploadDir = '../uploads/chloe';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('Failed to create upload directory.');
                    }
                }
                
                // Simple file move - NO IMAGE PROCESSING
                $destination = $uploadDir . '/profile.jpg';
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    chmod($destination, 0644);
                    $message = 'Homepage hero image uploaded successfully!';
                    $messageType = 'success';
                    
                    // Refresh page to show new image
                    header('Location: media.php?hero_success=1');
                    exit;
                } else {
                    throw new Exception('Failed to upload file.');
                }
            } else {
                throw new Exception('No file uploaded or upload error occurred.');
            }
        }
        
        if ($uploadType === 'featured_gallery') {
            $files = $_FILES['featured_images'] ?? null;
            
            if ($files && is_array($files['name'])) {
                // Create upload directory
                $uploadDir = '../uploads/featured';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('Failed to create featured directory.');
                    }
                }
                
                // Check 3-image limit
                $existingCount = count($featuredImages);
                $filesToUpload = count(array_filter($files['name']));
                
                if ($existingCount + $filesToUpload > 3) {
                    throw new Exception("Cannot upload {$filesToUpload} images. Maximum 3 total. Currently have {$existingCount}.");
                }
                
                $uploadedCount = 0;
                $errors = [];
                
                for ($i = 0; $i < count($files['name']); $i++) {
                    if (empty($files['name'][$i])) continue;
                    
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $fileName = $files['name'][$i];
                        $tempFile = $files['tmp_name'][$i];
                        $fileSize = $files['size'][$i];
                        
                        // Basic validation
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
                        
                        // Generate filename and move file - NO IMAGE PROCESSING
                        $newFileName = 'featured_' . uniqid() . '.' . $extension;
                        $destination = $uploadDir . '/' . $newFileName;
                        
                        if (move_uploaded_file($tempFile, $destination)) {
                            chmod($destination, 0644);
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
                        $message .= ' (' . implode(', ', $errors) . ')';
                    }
                    
                    // Refresh page to show new images
                    header('Location: media.php?featured_success=' . $uploadedCount);
                    exit;
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
            
            if (file_exists($filePath)) {
                unlink($filePath);
                header('Location: media.php?delete_success=1');
                exit;
            }
        }
    } catch (Exception $e) {
        $message = 'Error deleting image: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current hero image
$currentHeroImage = '../uploads/chloe/profile.jpg';
$heroImageExists = file_exists($currentHeroImage);
$heroImageUrl = $heroImageExists ? 'uploads/chloe/profile.jpg?v=' . filemtime($currentHeroImage) : null;

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
        
        .media-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .upload-zone {
            border: 2px dashed #6c5ce7;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9ff;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-zone:hover {
            border-color: #5a4fcf;
            background: #f0f0ff;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .featured-item {
            position: relative;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .featured-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .featured-item .overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .featured-item:hover .overlay {
            opacity: 1;
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
                <a class="nav-link active" href="media.php">
                    <i class="fas fa-images me-2"></i>Media
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
                <h1>Media Management</h1>
                <p class="text-muted">Upload and manage images for your website (Maximum 3 featured images)</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Homepage Hero Image Section -->
        <div class="media-card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-home me-2"></i>Homepage Hero Image
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Current Hero Image</h6>
                        <?php if ($heroImageExists): ?>
                            <img src="../<?= $heroImageUrl ?>" alt="Current Hero Image" class="image-preview mb-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Last updated: <?= date('M j, Y g:i A', filemtime($currentHeroImage)) ?>
                            </p>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No hero image uploaded yet
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Upload New Hero Image</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_type" value="homepage_hero">
                            
                            <div class="upload-zone" onclick="document.getElementById('homepage_hero').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h6>Click to upload or drag & drop</h6>
                                <p class="text-muted mb-0">
                                    JPG, PNG, or WebP up to 10MB<br>
                                    Recommended: 1200x800 pixels
                                </p>
                                <input type="file" id="homepage_hero" name="homepage_hero" accept="image/*" style="display: none;" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-upload me-2"></i>Upload Hero Image
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Featured Gallery Images Section -->
        <div class="media-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-images me-2"></i>Featured Gallery Images (3 Maximum)
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>Upload Featured Images</h6>
                        <span class="badge bg-<?= count($featuredImages) >= 3 ? 'danger' : 'primary' ?>">
                            <?= count($featuredImages) ?>/3 Images
                        </span>
                    </div>
                    
                    <?php if (count($featuredImages) >= 3): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Maximum Reached:</strong> You already have 3 featured images. 
                            Delete some images first if you want to upload new ones.
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_type" value="featured_gallery">
                            
                            <div class="upload-zone" onclick="document.getElementById('featured_images').click()">
                                <i class="fas fa-images fa-3x text-success mb-3"></i>
                                <h6>Upload TikTok Format Images</h6>
                                <p class="text-muted mb-0">
                                    Select up to <?= 3 - count($featuredImages) ?> JPG, PNG, or WebP files<br>
                                    Each file up to 10MB • Best: 1080x1920 (9:16 ratio)
                                </p>
                                <input type="file" id="featured_images" name="featured_images[]" accept="image/*" multiple style="display: none;" required>
                            </div>
                            
                            <button type="submit" class="btn btn-success mt-3">
                                <i class="fas fa-upload me-2"></i>Upload Featured Images
                            </button>
                        </form>
                        
                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Quality Tip:</strong> Images are stored in full quality for the best viewing experience. 
                                TikTok format (9:16 ratio) works best for the homepage display.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Current Featured Images -->
                <div>
                    <h6>Homepage Featured Images (<?= count($featuredImages) ?>/3)</h6>
                    
                    <?php if (empty($featuredImages)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No featured images uploaded yet. Upload up to 3 images for your homepage gallery.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-<?= count($featuredImages) >= 3 ? 'success' : 'warning' ?> mb-3">
                            <i class="fas fa-<?= count($featuredImages) >= 3 ? 'check-circle' : 'info-circle' ?> me-2"></i>
                            <?php if (count($featuredImages) >= 3): ?>
                                Perfect! You have all 3 featured images for your homepage gallery.
                            <?php else: ?>
                                You can upload <?= 3 - count($featuredImages) ?> more image(s) to complete your homepage gallery.
                            <?php endif; ?>
                        </div>
                        
                        <div class="featured-grid">
                            <?php foreach ($featuredImages as $index => $image): ?>
                                <div class="featured-item">
                                    <img src="../<?= $image['url'] ?>?v=<?= $image['modified'] ?>" alt="Featured Image <?= $index + 1 ?>">
                                    
                                    <div class="overlay">
                                        <div class="text-center">
                                            <div class="mb-2">
                                                <small class="text-white fw-bold">Position <?= $index + 1 ?>/3</small>
                                            </div>
                                            <div class="btn-group">
                                                <a href="../<?= $image['url'] ?>" target="_blank" class="btn btn-sm btn-light">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?delete=<?= urlencode($image['filename']) ?>&type=featured" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Delete this image from position <?= $index + 1 ?>?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                            <div class="mt-2">
                                                <small><?= formatFileSize($image['size']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File input handlers
        document.getElementById('homepage_hero')?.addEventListener('change', function() {
            if (this.files.length > 0) {
                this.closest('.upload-zone').querySelector('h6').textContent = this.files[0].name;
            }
        });
        
        document.getElementById('featured_images')?.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileName = this.files.length > 1 ? `${this.files.length} files selected` : this.files[0].name;
                this.closest('.upload-zone').querySelector('h6').textContent = fileName;
            }
        });

        console.log('🖼️ Media Management loaded');
        console.log('📊 Featured images:', <?= count($featuredImages) ?>);
        console.log('🎯 Maximum allowed: 3 images');
    </script>
</body>
</html>