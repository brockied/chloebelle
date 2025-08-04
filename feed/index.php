<?php
/**
 * Main Feed Page for Chloe Belle Website - Fixed Version with Working Comments
 * Displays posts with working comments, likes, and media upload
 */

session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;

if ($isLoggedIn) {
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

    } catch (Exception $e) {
        error_log("Feed database error: " . $e->getMessage());
    }
} else {
    // Redirect to homepage if not logged in
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle new post creation
if ($_POST && isset($_POST['create_post']) && in_array($currentUser['role'], ['admin', 'chloe'])) {
    try {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $isPremium = isset($_POST['is_premium']) ? 1 : 0;
        $subscriptionRequired = $_POST['subscription_required'] ?? 'none';
        
        if (empty($content)) {
            throw new Exception('Content is required');
        }
        
        // Handle media upload
        $mediaUrl = null;
        $mediaType = 'none';
        $uploadPath = '../uploads/posts/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        // Check for photo upload
        if (isset($_FILES['photo_upload']) && $_FILES['photo_upload']['error'] === UPLOAD_ERR_OK) {
            $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $uploadResult = uploadFile($_FILES['photo_upload'], $uploadPath, $allowedImageTypes, 10485760); // 10MB
            
            if ($uploadResult['success']) {
                $mediaUrl = 'uploads/posts/' . basename($uploadResult['path']);
                $mediaType = 'image';
            } else {
                throw new Exception('Image upload failed: ' . implode(', ', $uploadResult['errors']));
            }
        }
        // Check for video upload
        elseif (isset($_FILES['video_upload']) && $_FILES['video_upload']['error'] === UPLOAD_ERR_OK) {
            $allowedVideoTypes = ['mp4', 'mov', 'avi', 'wmv'];
            $uploadResult = uploadFile($_FILES['video_upload'], $uploadPath, $allowedVideoTypes, 52428800); // 50MB
            
            if ($uploadResult['success']) {
                $mediaUrl = 'uploads/posts/' . basename($uploadResult['path']);
                $mediaType = 'video';
            } else {
                throw new Exception('Video upload failed: ' . implode(', ', $uploadResult['errors']));
            }
        }
        
        // Insert post into database
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, title, content, media_type, media_url, is_premium, subscription_required, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())
        ");
        $stmt->execute([$currentUser['id'], $title, $content, $mediaType, $mediaUrl, $isPremium, $subscriptionRequired]);
        
        $message = "Post created successfully!";
        $messageType = 'success';
        
        // Redirect to prevent resubmission
        header("Location: index.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $message = "Post created successfully!";
    $messageType = 'success';
}

// Get posts with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$postsPerPage = 10;
$offset = ($page - 1) * $postsPerPage;

try {
    // Get total post count
    $totalPosts = $pdo->query("
        SELECT COUNT(*) FROM posts 
        WHERE status = 'published'
    ")->fetchColumn();

    // Get posts with user info and engagement data
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.avatar,
            u.role,
            COALESCE(p.likes_count, 0) as like_count,
            COALESCE(p.comments_count, 0) as comments_count,
            COALESCE(p.views, 0) as views,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$currentUser['id'], $postsPerPage, $offset]);
    $posts = $stmt->fetchAll();

    $totalPages = ceil($totalPosts / $postsPerPage);

} catch (Exception $e) {
    error_log("Feed query error: " . $e->getMessage());
    $posts = [];
    $totalPages = 1;
}

// Get comments for all posts on this page
$comments = [];
if (!empty($posts)) {
    $postIds = array_column($posts, 'id');
    if (!empty($postIds)) {
        $placeholders = str_repeat('?,', count($postIds) - 1) . '?';
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    u.username,
                    u.avatar,
                    u.role,
                    (SELECT COUNT(*) FROM likes WHERE comment_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id IN ($placeholders) AND (c.status = 'approved' OR c.status IS NULL)
                ORDER BY c.created_at ASC
            ");
            
            $params = array_merge([$currentUser['id']], $postIds);
            $stmt->execute($params);
            $allComments = $stmt->fetchAll();
            
            // Group comments by post_id
            foreach ($allComments as $comment) {
                $comments[$comment['post_id']][] = $comment;
            }
            
        } catch (Exception $e) {
            error_log("Comments query error: " . $e->getMessage());
        }
    }
}

// Get user's subscription status
$hasSubscription = in_array($currentUser['subscription_status'], ['monthly', 'yearly', 'lifetime']);
$subscriptionExpires = $currentUser['subscription_expires'] ? new DateTime($currentUser['subscription_expires']) : null;
$subscriptionActive = $hasSubscription && (!$subscriptionExpires || $subscriptionExpires > new DateTime());

// Track free post views for non-subscribers
if (!$subscriptionActive && !isset($_SESSION['free_posts_viewed'])) {
    $_SESSION['free_posts_viewed'] = 0;
}

// Helper function to check post access
function hasPostAccess($post, $user, $subscriptionActive) {
    // Admins and Chloe can see everything
    if (in_array($user['role'], ['admin', 'chloe'])) {
        return true;
    }
    
    // Non-premium posts are always accessible
    if (!$post['is_premium']) {
        return true;
    }
    
    // Premium posts require active subscription
    if (!$subscriptionActive) {
        return false;
    }
    
    // Check subscription level requirements
    $userSubscription = $user['subscription_status'];
    $requiredSubscription = $post['subscription_required'];
    
    if ($requiredSubscription === 'yearly' && $userSubscription === 'monthly') {
        return false;
    }
    
    return true;
}

// Helper function to check if user can comment
function canCommentOnPost($post, $user, $subscriptionActive) {
    // Must be logged in
    if (!$user) return false;
    
    // Must have access to the post
    return hasPostAccess($post, $user, $subscriptionActive);
}

// Get avatar URL with fallback
function getAvatarUrl($avatar, $useThumb = false) {
    if (!$avatar) {
        return '../assets/images/default-avatar.jpg';
    }
    
    $avatarPath = $useThumb ? 
        '../uploads/avatars/thumb_' . $avatar : '../uploads/avatars/' . $avatar;
    
    // Check if thumbnail exists, fallback to original
    if ($useThumb && !file_exists($avatarPath)) {
        $avatarPath = '../uploads/avatars/' . $avatar;
    }
    
    return file_exists($avatarPath) ? $avatarPath : '../assets/images/default-avatar.jpg';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 2592000) return floor($time / 86400) . 'd ago';
    if ($time < 31536000) return floor($time / 2592000) . 'mo ago';
    
    return floor($time / 31536000) . 'y ago';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - Chloe Belle</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding-top: 80px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: rgba(108, 92, 231, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .current-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .post-card,
        .create-post-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(108, 92, 231, 0.1);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            overflow: visible;
        }
        
        .post-card:hover,
        .create-post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(108, 92, 231, 0.2);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .chloe-avatar {
            border-color: #ff6b9d !important;
            box-shadow: 0 0 15px rgba(255, 107, 157, 0.3);
        }
        
        .admin-avatar {
            border-color: #4ecdc4 !important;
            box-shadow: 0 0 15px rgba(78, 205, 196, 0.3);
        }
        
        .premium-badge {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: white;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .premium-overlay {
            position: relative;
            overflow: hidden;
        }
        
        .premium-blur {
            filter: blur(10px);
        }
        
        .premium-lock {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            background: rgba(0, 0, 0, 0.8);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .like-btn,
        .comment-toggle {
            background: none;
            border: none;
            color: #6c757d;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .like-btn:hover,
        .comment-toggle:hover {
            background: #f8f9fa;
            color: #6c5ce7;
        }
        
        .like-btn.liked {
            color: #e74c3c;
        }
        
        .post-media-container {
            margin: 15px 0;
        }
        
        .post-media-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 15px;
        }
        
        .post-media-image:hover {
            transform: scale(1.02);
            filter: brightness(1.1);
        }
        
        .post-media-video {
            width: 100%;
            max-height: 500px;
            border-radius: 15px;
            outline: none;
        }
        
        /* File upload preview */
        .file-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin: 10px 0;
        }
        
        .file-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 0.9rem;
        }
        
        /* Comments Section */
        .comments-section {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: 15px;
            display: none;
        }
        
        .comments-section.show {
            display: block;
        }
        
        .comment {
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .comment:last-child {
            border-bottom: none;
        }
        
        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .sidebar {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(108, 92, 231, 0.1);
            position: sticky;
            top: 100px;
        }
        
        /* Drag and drop styles */
        .drag-over {
            border: 2px dashed #6c5ce7 !important;
            background: rgba(108, 92, 231, 0.1) !important;
            transform: scale(1.02);
            transition: all 0.3s ease;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6c5ce7;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                margin-bottom: 2rem;
            }
            
            .post-media-image,
            .post-media-video {
                max-height: 300px;
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
                        <a class="nav-link active" href="index.php">
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
                            <li><a class="dropdown-item" href="../user/profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="../user/settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <?php if ($currentUser['role'] === 'chloe'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/posts.php">
                                    <i class="fas fa-cogs me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <?php if ($currentUser['role'] === 'admin'): ?>
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

    <div class="container mt-5 pt-4">
        <div class="row">
            <!-- Main Feed -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Latest Posts</h2>
                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-3">Page <?= $page ?> of <?= $totalPages ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Create Post Box (Admin/Chloe Only) -->
                <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                    <div class="card create-post-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <?php 
                                $avatarClass = 'user-avatar';
                                if ($currentUser['role'] === 'chloe') {
                                    $avatarClass .= ' chloe-avatar';
                                } elseif ($currentUser['role'] === 'admin') {
                                    $avatarClass .= ' admin-avatar';
                                }
                                ?>
                                <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" 
                                     alt="<?= htmlspecialchars($currentUser['username']) ?>" 
                                     class="<?= $avatarClass ?> me-3">
                                <div>
                                    <h6 class="mb-0">Create New Post</h6>
                                    <small class="text-muted">Share something with your audience</small>
                                </div>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" id="createPostForm">
                                <input type="hidden" name="create_post" value="1">
                                
                                <!-- Hidden file inputs -->
                                <input type="file" id="photoUpload" name="photo_upload" accept="image/*" style="display: none;">
                                <input type="file" id="videoUpload" name="video_upload" accept="video/*" style="display: none;">
                                
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="title" placeholder="Post title (optional)">
                                </div>
                                
                                <div class="mb-3">
                                    <textarea class="form-control" name="content" rows="3" placeholder="What's on your mind?" required></textarea>
                                </div>
                                
                                <!-- File preview area -->
                                <div id="filePreview" style="display: none;">
                                    <div class="file-info">
                                        <span id="fileName"></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger float-end" onclick="clearFileSelection()">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                    <img id="imagePreview" class="file-preview" style="display: none;">
                                    <video id="videoPreview" class="file-preview" style="display: none;" controls></video>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium">
                                            <label class="form-check-label" for="is_premium">
                                                <i class="fas fa-crown text-warning me-1"></i>Premium Content
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="subscription_required" id="subscription_required" disabled>
                                            <option value="none">No subscription required</option>
                                            <option value="monthly">Monthly subscribers</option>
                                            <option value="yearly">Yearly subscribers</option>
                                            <option value="lifetime">Lifetime subscribers</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="document.getElementById('photoUpload').click()">
                                            <i class="fas fa-image me-1"></i>Photo
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('videoUpload').click()">
                                            <i class="fas fa-video me-1"></i>Video
                                        </button>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Post
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Posts Display -->
                <?php if (empty($posts)): ?>
                    <div class="post-card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h4>No posts yet</h4>
                            <p class="text-muted">
                                <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                    Create your first post above to get started!
                                <?php else: ?>
                                    Check back later for new content from Chloe Belle!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $hasAccess = hasPostAccess($post, $currentUser, $subscriptionActive);
                        
                        // For non-subscribers, limit free post access
                        if (!$subscriptionActive && !in_array($currentUser['role'], ['admin', 'chloe'])) {
                            if ($post['is_premium'] || $_SESSION['free_posts_viewed'] >= 3) {
                                $hasAccess = false;
                            } else {
                                $_SESSION['free_posts_viewed']++;
                            }
                        }
                        ?>
                        
                        <div class="post-card" data-post-id="<?= $post['id'] ?>">
                            <div class="card-body">
                                <!-- Post Header -->
                                <div class="d-flex align-items-center mb-3">
                                    <?php 
                                    $posterAvatarClass = 'user-avatar';
                                    if ($post['role'] === 'chloe') {
                                        $posterAvatarClass .= ' chloe-avatar';
                                    } elseif ($post['role'] === 'admin') {
                                        $posterAvatarClass .= ' admin-avatar';
                                    }
                                    ?>
                                    <img src="<?= getAvatarUrl($post['avatar']) ?>" 
                                         alt="<?= htmlspecialchars($post['username']) ?>" 
                                         class="<?= $posterAvatarClass ?> me-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center">
                                            <h6 class="mb-0 me-2">
                                                <?= htmlspecialchars($post['username']) ?>
                                                <?php if ($post['role'] === 'chloe'): ?>
                                                    <i class="fas fa-heart text-danger ms-1" title="Chloe Belle"></i>
                                                <?php elseif ($post['role'] === 'admin'): ?>
                                                    <i class="fas fa-shield-alt text-primary ms-1" title="Admin"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <?php if ($post['is_premium']): ?>
                                                <span class="premium-badge">
                                                    <i class="fas fa-crown me-1"></i>Premium
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= timeAgo($post['created_at']) ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Post Title -->
                                <?php if ($post['title']): ?>
                                    <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                                <?php endif; ?>

                                <!-- Post Content -->
                                <div class="<?= !$hasAccess && $post['is_premium'] ? 'premium-overlay' : '' ?>">
                                    <?php if ($hasAccess || !$post['is_premium']): ?>
                                        <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                    <?php else: ?>
                                        <div class="premium-blur">
                                            <p class="card-text"><?= nl2br(htmlspecialchars(substr($post['content'], 0, 100))) ?>...</p>
                                        </div>
                                        <div class="premium-lock">
                                            <i class="fas fa-lock fa-2x mb-2"></i>
                                            <h6>Premium Content</h6>
                                            <p class="mb-3">Subscribe to unlock this exclusive content</p>
                                            <a href="../subscription/plans.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-crown me-1"></i>Subscribe Now
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Post Media -->
                                <?php if ($post['media_url'] && $hasAccess): ?>
                                    <div class="post-media-container">
                                        <?php if ($post['media_type'] === 'image'): ?>
                                            <img src="<?= htmlspecialchars($post['media_url']) ?>" 
                                                 class="post-media-image" 
                                                 alt="<?= htmlspecialchars($post['title'] ?: 'Post image') ?>"
                                                 loading="lazy"
                                                 onclick="openImageModal('<?= htmlspecialchars($post['media_url']) ?>', '<?= htmlspecialchars($post['title'] ?: 'Post image') ?>')">
                                        <?php elseif ($post['media_type'] === 'video'): ?>
                                            <video controls class="post-media-video" preload="metadata">
                                                <source src="<?= htmlspecialchars($post['media_url']) ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($post['media_url'] && !$hasAccess && $post['is_premium']): ?>
                                    <!-- Blurred preview for premium media -->
                                    <div class="post-media-container premium-overlay">
                                        <div class="premium-blur">
                                            <?php if ($post['media_type'] === 'image'): ?>
                                                <img src="<?= htmlspecialchars($post['media_url']) ?>" 
                                                     class="post-media-image" 
                                                     alt="Premium content"
                                                     style="filter: blur(15px);">
                                            <?php elseif ($post['media_type'] === 'video'): ?>
                                                <video class="post-media-video" style="filter: blur(15px);" muted>
                                                    <source src="<?= htmlspecialchars($post['media_url']) ?>" type="video/mp4">
                                                </video>
                                            <?php endif; ?>
                                        </div>
                                        <div class="premium-lock">
                                            <i class="fas fa-lock fa-2x mb-2"></i>
                                            <h6>Premium Media</h6>
                                            <p class="mb-3">Subscribe to view this content</p>
                                            <a href="../subscription/plans.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-crown me-1"></i>Subscribe Now
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Post Actions -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <button class="like-btn me-3 <?= $post['user_liked'] ? 'liked' : '' ?>" 
                                                data-post-id="<?= $post['id'] ?>">
                                            <i class="fas fa-heart me-1"></i>
                                            <span class="like-count"><?= $post['like_count'] ?></span>
                                        </button>
                                        
                                        <button class="comment-toggle me-3" data-post-id="<?= $post['id'] ?>">
                                            <i class="fas fa-comment me-1"></i>
                                            <span class="comment-count"><?= $post['comments_count'] ?></span> comments
                                        </button>
                                        
                                        <span class="text-muted">
                                            <i class="fas fa-eye me-1"></i>
                                            <?= $post['views'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-share me-2"></i>Share</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-bookmark me-2"></i>Save</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-flag me-2"></i>Report</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Comments Section -->
                                <div class="comments-section" id="comments-<?= $post['id'] ?>">
                                    <?php if (canCommentOnPost($post, $currentUser, $subscriptionActive)): ?>
                                        <!-- Comment Form -->
                                        <form class="comment-form mb-3" data-post-id="<?= $post['id'] ?>">
                                            <div class="d-flex">
                                                <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" 
                                                     alt="<?= htmlspecialchars($currentUser['username']) ?>" 
                                                     class="comment-avatar me-2">
                                                <div class="flex-grow-1">
                                                    <textarea class="form-control form-control-sm comment-input" 
                                                            placeholder="Write a comment..." 
                                                            rows="2"></textarea>
                                                    <div class="text-end mt-2">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-paper-plane me-1"></i>Comment
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Comments List -->
                                    <div class="comments-list" id="comments-list-<?= $post['id'] ?>">
                                        <?php if (isset($comments[$post['id']])): ?>
                                            <?php foreach ($comments[$post['id']] as $comment): ?>
                                                <div class="comment" data-comment-id="<?= $comment['id'] ?>">
                                                    <div class="d-flex">
                                                        <img src="<?= getAvatarUrl($comment['avatar']) ?>" 
                                                             alt="<?= htmlspecialchars($comment['username']) ?>" 
                                                             class="comment-avatar me-2">
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center">
                                                                <strong class="me-2"><?= htmlspecialchars($comment['username']) ?></strong>
                                                                <small class="text-muted"><?= timeAgo($comment['created_at']) ?></small>
                                                                <?php if ($currentUser['id'] === $comment['user_id'] || in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                                                    <button class="btn btn-sm btn-link text-danger ms-auto" onclick="deleteComment(<?= $comment['id'] ?>, <?= $post['id'] ?>)">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted text-center">No comments yet. Be the first to comment!</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Feed pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar">
                    <!-- Current User Info -->
                    <div class="text-center mb-4">
                        <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="Your Avatar" class="user-avatar mb-3" style="width: 80px; height: 80px;">
                        <h6><?= htmlspecialchars($currentUser['username']) ?></h6>
                        <a href="../user/profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit me-1"></i>Edit Profile
                        </a>
                    </div>

                    <!-- User Subscription Status -->
                    <div class="mb-4">
                        <h6>Your Subscription</h6>
                        <?php if ($currentUser['subscription_status'] === 'none'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Free Account</strong><br>
                                <small>Upgrade to access premium content</small>
                            </div>
                            <a href="../subscription/plans.php" class="btn btn-primary w-100">
                                <i class="fas fa-crown me-2"></i>Subscribe Now
                            </a>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong><?= ucfirst($currentUser['subscription_status']) ?> Subscriber</strong><br>
                                <?php if ($currentUser['subscription_expires']): ?>
                                    <small>Expires: <?= date('M j, Y', strtotime($currentUser['subscription_expires'])) ?></small>
                                <?php else: ?>
                                    <small>Lifetime Access</small>
                                <?php endif; ?>
                            </div>
                            <a href="../user/settings.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-cog me-1"></i>Manage Subscription
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Stats -->
                    <div class="mb-4">
                        <h6>Community Stats</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <strong><?= $totalPosts ?></strong><br>
                                    <small class="text-muted">Total Posts</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <strong><?= count($posts) ?></strong><br>
                                    <small class="text-muted">This Page</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h6>Quick Links</h6>
                        <div class="list-group list-group-flush">
                            <a href="../subscription/plans.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-star me-2"></i>Subscription Plans
                            </a>
                            <a href="../user/settings.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i>Account Settings
                            </a>
                            <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                <a href="../admin/index.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-shield-alt me-2"></i>Admin Panel
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0 text-center">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1050;"></button>
                    <img id="modalImage" src="" alt="" class="img-fluid rounded" style="max-height: 90vh;">
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Premium subscription checkbox handler
        document.getElementById('is_premium')?.addEventListener('change', function() {
            document.getElementById('subscription_required').disabled = !this.checked;
            if (!this.checked) {
                document.getElementById('subscription_required').value = 'none';
            }
        });

        // Photo upload handler
        document.getElementById('photoUpload')?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                
                document.getElementById('videoUpload').value = '';
                document.getElementById('fileName').textContent = `📷 ${file.name} (${fileSize} MB)`;
                document.getElementById('filePreview').style.display = 'block';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                    document.getElementById('videoPreview').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

        // Video upload handler
        document.getElementById('videoUpload')?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                
                document.getElementById('photoUpload').value = '';
                document.getElementById('fileName').textContent = `🎥 ${file.name} (${fileSize} MB)`;
                document.getElementById('filePreview').style.display = 'block';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('videoPreview').src = e.target.result;
                    document.getElementById('videoPreview').style.display = 'block';
                    document.getElementById('imagePreview').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

        // Clear file selection
        function clearFileSelection() {
            document.getElementById('photoUpload').value = '';
            document.getElementById('videoUpload').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('videoPreview').style.display = 'none';
        }

        // Like button functionality
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const likeCount = this.querySelector('.like-count');
                const isLiked = this.classList.contains('liked');
                
                // Show loading state
                const originalIcon = this.querySelector('i');
                originalIcon.className = 'fas fa-spinner fa-spin me-1';
                
                fetch('../api/likes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'toggle',
                        post_id: postId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.toggle('liked', data.liked);
                        likeCount.textContent = data.like_count;
                    } else {
                        console.error('Like error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                })
                .finally(() => {
                    // Restore heart icon
                    originalIcon.className = 'fas fa-heart me-1';
                });
            });
        });

        // Comment toggle functionality
        document.querySelectorAll('.comment-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const commentsSection = document.getElementById(`comments-${postId}`);
                
                commentsSection.classList.toggle('show');
            });
        });

        // Comment form submission
        document.querySelectorAll('.comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const postId = this.dataset.postId;
                const textarea = this.querySelector('.comment-input');
                const content = textarea.value.trim();
                
                if (!content) {
                    alert('Please enter a comment');
                    return;
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Posting...';
                submitBtn.disabled = true;
                
                fetch('../api/comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'create',
                        post_id: postId,
                        content: content
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = '';
                        
                        // Update comment count
                        const countElement = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                        const currentCount = parseInt(countElement.textContent);
                        countElement.textContent = currentCount + 1;
                        
                        // Add new comment to list
                        const commentsList = document.getElementById(`comments-list-${postId}`);
                        const newCommentHtml = `
                            <div class="comment" data-comment-id="${data.comment.id}">
                                <div class="d-flex">
                                    <img src="${data.comment.avatar_url}" alt="${data.comment.username}" class="comment-avatar me-2">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center">
                                            <strong class="me-2">${data.comment.username}</strong>
                                            <small class="text-muted">just now</small>
                                            <button class="btn btn-sm btn-link text-danger ms-auto" onclick="deleteComment(${data.comment.id}, ${postId})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <p class="mb-0">${data.comment.content}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        if (commentsList.innerHTML.includes('No comments yet')) {
                            commentsList.innerHTML = newCommentHtml;
                        } else {
                            commentsList.insertAdjacentHTML('beforeend', newCommentHtml);
                        }
                        
                    } else {
                        alert('Error: ' + (data.message || 'Failed to post comment'));
                    }
                })
                .catch(error => {
                    console.error('Error posting comment:', error);
                    alert('Failed to post comment. Please try again.');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        });

        // Delete comment function
        function deleteComment(commentId, postId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }
            
            fetch('../api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    comment_id: commentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                    commentElement.remove();
                    
                    // Update comment count
                    const countElement = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = Math.max(0, currentCount - 1);
                    
                    // Check if no comments left
                    const commentsList = document.getElementById(`comments-list-${postId}`);
                    if (commentsList.children.length === 0) {
                        commentsList.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete comment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete comment. Please try again.');
            });
        }

        // Image modal functionality
        function openImageModal(src, alt) {
            document.getElementById('modalImage').src = src;
            document.getElementById('modalImage').alt = alt;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // Form validation
        document.getElementById('createPostForm')?.addEventListener('submit', function(e) {
            const content = this.querySelector('[name="content"]').value.trim();
            if (!content) {
                e.preventDefault();
                alert('Please enter some content for your post.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Posting...';
            submitBtn.disabled = true;
        });

        console.log('✨ Feed page loaded successfully!');
        console.log('📊 Total posts:', <?= count($posts) ?>);
        console.log('👤 Current user role:', '<?= $currentUser['role'] ?>');
        console.log('💎 Subscription active:', <?= $subscriptionActive ? 'true' : 'false' ?>);
        console.log('💬 Comments loaded in PHP: <?= array_sum(array_map("count", $comments)) ?> total');
    </script>
</body>
</html>