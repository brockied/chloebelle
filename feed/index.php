<?php
/**
 * Main Feed Page for Chloe Belle Website - Fixed Version with Comments
 * Displays posts with subscription-based access control and user avatars
 */

session_start();
require_once '../config.php';

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
        
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, title, content, is_premium, subscription_required, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'published', NOW())
        ");
        $stmt->execute([$currentUser['id'], $title, $content, $isPremium, $subscriptionRequired]);
        
        $message = "Post created successfully!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get posts with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$postsPerPage = 10;
$offset = ($page - 1) * $postsPerPage;

try {
    // Get total post count - Fixed SQL
    $totalPosts = $pdo->query("
        SELECT COUNT(*) FROM posts p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status = 'published'
    ")->fetchColumn();

    // Get posts with user avatar info - Fixed SQL with proper parameter binding
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.avatar,
            u.role,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT $postsPerPage OFFSET $offset
    ");
    $stmt->execute([$currentUser['id']]);
    $posts = $stmt->fetchAll();

    $totalPages = ceil($totalPosts / $postsPerPage);

} catch (Exception $e) {
    error_log("Feed query error: " . $e->getMessage());
    $posts = [];
    $totalPages = 0;
}

// Get comments for posts
$postIds = array_column($posts, 'id');
$comments = [];

if (!empty($postIds)) {
    $placeholders = str_repeat('?,', count($postIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            u.avatar,
            u.role,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id IN ($placeholders) AND c.is_deleted = 0
        ORDER BY c.created_at ASC
    ");
    
    $params = array_merge([$currentUser['id']], $postIds);
    $stmt->execute($params);
    $allComments = $stmt->fetchAll();
    
    // Group comments by post_id
    foreach ($allComments as $comment) {
        $comments[$comment['post_id']][] = $comment;
    }
}

// Helper function to check if user can comment
function canCommentOnPost($post, $user) {
    if (!$user) return false;
    if ($post['is_premium']) {
        return hasAccessToPost($post, $user);
    }
    return true;
}

// Helper function to get avatar URL with fallback
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

// Helper function to check if user has access to premium content
function hasAccessToPost($post, $user) {
    if (!$post['is_premium']) {
        return true; // Free post
    }
    
    if (!$user) {
        return false; // Not logged in
    }
    
    // Check subscription status
    $requiredLevel = $post['subscription_required'];
    $userLevel = $user['subscription_status'];
    
    if ($userLevel === 'none') {
        return false;
    }
    
    // Check subscription hierarchy
    $levels = ['monthly' => 1, 'yearly' => 2, 'lifetime' => 3];
    $userLevelValue = $levels[$userLevel] ?? 0;
    $requiredLevelValue = $levels[$requiredLevel] ?? 1;
    
    return $userLevelValue >= $requiredLevelValue;
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
        
        .post-card, .create-post-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .create-post-card {
            border: 2px dashed var(--primary-color);
            background: rgba(108, 92, 231, 0.05);
        }
        
        .premium-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .premium-overlay {
            position: relative;
            overflow: hidden;
        }
        
        .premium-blur {
            filter: blur(10px);
            transition: filter 0.3s ease;
        }
        
        .premium-lock {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(108, 92, 231, 0.9);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            border-color: var(--accent-color);
        }
        
        /* Special styling for Chloe Belle's avatar */
        .chloe-avatar {
            border: 3px solid var(--accent-color);
            box-shadow: 0 0 15px rgba(253, 121, 168, 0.4);
        }
        
        .admin-avatar {
            border: 3px solid #e74c3c;
            box-shadow: 0 0 15px rgba(231, 76, 60, 0.4);
        }
        
        .like-btn {
            background: none;
            border: none;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .like-btn:hover,
        .like-btn.liked {
            color: #e74c3c;
            transform: scale(1.1);
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
        
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            position: sticky;
            top: 100px;
        }
        
        .current-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-right: 8px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }
        
        /* Media sizing styles - Facebook-like */
        .post-media-container {
            margin: 15px 0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
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
        
        /* Multiple images grid */
        .post-media-grid {
            display: grid;
            gap: 5px;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .post-media-grid.grid-2 {
            grid-template-columns: 1fr 1fr;
        }
        
        .post-media-grid.grid-3 {
            grid-template-columns: 2fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        
        .post-media-grid.grid-3 img:first-child {
            grid-row: span 2;
        }
        
        .post-media-grid.grid-4 {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        
        .post-media-grid img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .post-media-grid img:hover {
            transform: scale(1.02);
            filter: brightness(1.1);
        }
        
        /* Image modal styles */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.8);
        }
        
        #imageModal .modal-content {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        #imageModal .modal-body {
            position: relative;
        }
        
        #imageModal .btn-close {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            opacity: 0.8;
        }
        
        #imageModal .btn-close:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.3);
        }

        /* Comment System Styles */
        .comments-section {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
            margin-top: 1rem;
            display: none;
        }

        .comments-section.show {
            display: block;
        }

        .comment-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .comment-item {
            background: #ffffff;
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .comment-item:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .comment-avatar.chloe-avatar {
            border-color: var(--accent-color);
            box-shadow: 0 0 10px rgba(253, 121, 168, 0.3);
        }

        .comment-avatar.admin-avatar {
            border-color: #e74c3c;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.3);
        }

        .comment-actions {
            display: none;
            gap: 0.5rem;
        }

        .comment-item:hover .comment-actions {
            display: flex;
        }

        .btn-comment {
            background: var(--primary-color);
            border: none;
            border-radius: 20px;
            color: white;
            padding: 8px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-comment:hover {
            background: var(--accent-color);
            transform: translateY(-1px);
        }

        .comment-toggle {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .comment-toggle:hover {
            color: var(--primary-color);
        }

        .comment-toggle.active {
            color: var(--primary-color);
            font-weight: 600;
        }

        .comment-premium-lock {
            background: rgba(108, 92, 231, 0.1);
            border: 2px dashed var(--primary-color);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin: 1rem 0;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .comment-like-btn.liked {
            color: #e74c3c;
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
            
            .post-media-grid img {
                height: 150px;
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
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3">Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
                    </div>
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
                            
                            <form method="POST">
                                <input type="hidden" name="create_post" value="1">
                                
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="title" placeholder="Post title (optional)">
                                </div>
                                
                                <div class="mb-3">
                                    <textarea class="form-control" name="content" rows="3" placeholder="What's on your mind?" required></textarea>
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
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-2">
                                            <i class="fas fa-image me-1"></i>Photo
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm">
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
                <?php endif; ?>

                <?php foreach ($posts as $post): ?>
                    <?php $hasAccess = hasAccessToPost($post, $currentUser); ?>
                    <div class="card post-card">
                        <div class="card-body">
                            <!-- Post Header -->
                            <div class="d-flex align-items-center mb-3">
                                <?php 
                                $avatarClass = 'user-avatar';
                                if ($post['role'] === 'chloe') {
                                    $avatarClass .= ' chloe-avatar';
                                } elseif ($post['role'] === 'admin') {
                                    $avatarClass .= ' admin-avatar';
                                }
                                ?>
                                <img src="<?= getAvatarUrl($post['avatar']) ?>" 
                                     alt="<?= htmlspecialchars($post['username']) ?>" 
                                     class="<?= $avatarClass ?> me-3">
                                
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($post['username']) ?>
                                        <?php if ($post['role'] === 'chloe'): ?>
                                            <i class="fas fa-star text-warning ms-1" title="Chloe Belle"></i>
                                        <?php elseif ($post['role'] === 'admin'): ?>
                                            <i class="fas fa-shield-alt text-danger ms-1" title="Administrator"></i>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted"><?= timeAgo($post['created_at']) ?></small>
                                </div>
                                
                                <?php if ($post['is_premium']): ?>
                                    <span class="premium-badge">
                                        <i class="fas fa-crown me-1"></i>Premium
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Post Title -->
                            <?php if ($post['title']): ?>
                                <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                            <?php endif; ?>

                            <!-- Post Content -->
                            <div class="post-content <?= !$hasAccess && $post['is_premium'] ? 'premium-overlay' : '' ?>">
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
                                             loading="lazy">
                                    <?php elseif ($post['media_type'] === 'video'): ?>
                                        <video controls class="post-media-video" preload="metadata">
                                            <source src="<?= htmlspecialchars($post['media_url']) ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php elseif ($post['media_type'] === 'gallery'): ?>
                                        <!-- Future: Gallery support with multiple images -->
                                        <?php 
                                        // Parse gallery URLs from metadata if available
                                        $galleryImages = json_decode($post['media_metadata'] ?? '[]', true);
                                        if (is_array($galleryImages) && count($galleryImages) > 1):
                                            $imageCount = count($galleryImages);
                                            $gridClass = 'grid-' . min($imageCount, 4);
                                        ?>
                                            <div class="post-media-grid <?= $gridClass ?>">
                                                <?php foreach (array_slice($galleryImages, 0, 4) as $index => $imageUrl): ?>
                                                    <img src="<?= htmlspecialchars($imageUrl) ?>" 
                                                         class="gallery-image" 
                                                         alt="Gallery image <?= $index + 1 ?>"
                                                         loading="lazy"
                                                         onclick="openImageModal('<?= htmlspecialchars($imageUrl) ?>', 'Gallery image <?= $index + 1 ?>')">
                                                    <?php if ($index === 3 && $imageCount > 4): ?>
                                                        <div class="gallery-overlay">+<?= $imageCount - 4 ?> more</div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <img src="<?= htmlspecialchars($post['media_url']) ?>" 
                                                 class="post-media-image" 
                                                 alt="<?= htmlspecialchars($post['title'] ?: 'Post image') ?>"
                                                 loading="lazy">
                                        <?php endif; ?>
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
                                        <?= $post['views'] ?? 0 ?>
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
                                <?php if (canCommentOnPost($post, $currentUser)): ?>
                                    <!-- Comment Form -->
                                    <div class="comment-form">
                                        <div class="d-flex align-items-start">
                                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" 
                                                 alt="Your Avatar" 
                                                 class="comment-avatar me-2">
                                            <div class="flex-grow-1">
                                                <textarea class="form-control mb-2" 
                                                         rows="2" 
                                                         placeholder="Write a comment..." 
                                                         id="comment-text-<?= $post['id'] ?>"
                                                         maxlength="1000"></textarea>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Be respectful and kind
                                                    </small>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="loading-spinner" id="loading-<?= $post['id'] ?>"></div>
                                                        <button class="btn-comment" onclick="submitComment(<?= $post['id'] ?>)">
                                                            <i class="fas fa-paper-plane me-1"></i>Comment
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($post['is_premium']): ?>
                                    <!-- Premium Comment Lock -->
                                    <div class="comment-premium-lock">
                                        <i class="fas fa-lock fa-2x mb-2 text-muted"></i>
                                        <h6>Premium Comments</h6>
                                        <p class="text-muted mb-3">
                                            Subscribe to join the conversation on this exclusive content.
                                        </p>
                                        <a href="../subscription/plans.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-crown me-1"></i>Subscribe Now
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Comments List -->
                                <div class="comments-list" id="comments-list-<?= $post['id'] ?>">
                                    <?php if (isset($comments[$post['id']])): ?>
                                        <?php foreach ($comments[$post['id']] as $comment): ?>
                                            <div class="comment-item" data-comment-id="<?= $comment['id'] ?>">
                                                <div class="d-flex align-items-start">
                                                    <?php 
                                                    $commentAvatarClass = 'comment-avatar';
                                                    if ($comment['role'] === 'chloe') {
                                                        $commentAvatarClass .= ' chloe-avatar';
                                                    } elseif ($comment['role'] === 'admin') {
                                                        $commentAvatarClass .= ' admin-avatar';
                                                    }
                                                    ?>
                                                    <img src="<?= getAvatarUrl($comment['avatar']) ?>" 
                                                         alt="<?= htmlspecialchars($comment['username']) ?>" 
                                                         class="<?= $commentAvatarClass ?> me-2">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <strong class="me-2">
                                                                <?= htmlspecialchars($comment['username']) ?>
                                                                <?php if ($comment['role'] === 'chloe'): ?>
                                                                    <i class="fas fa-star text-warning ms-1" title="Chloe Belle"></i>
                                                                <?php elseif ($comment['role'] === 'admin'): ?>
                                                                    <i class="fas fa-shield-alt text-danger ms-1" title="Administrator"></i>
                                                                <?php endif; ?>
                                                            </strong>
                                                            <small class="text-muted"><?= timeAgo($comment['created_at']) ?></small>
                                                        </div>
                                                        <p class="mb-1"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                                        <div class="comment-actions">
                                                            <button class="btn btn-link btn-sm p-0 me-2 text-muted comment-like-btn <?= $comment['user_liked'] ? 'liked' : '' ?>" 
                                                                    data-comment-id="<?= $comment['id'] ?>">
                                                                <i class="fas fa-heart me-1"></i>
                                                                <span class="comment-like-count"><?= $comment['like_count'] ?></span>
                                                            </button>
                                                            <?php if ($currentUser['id'] === $comment['user_id'] || in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                                                <button class="btn btn-link btn-sm p-0 text-danger ms-2" 
                                                                        onclick="deleteComment(<?= $comment['id'] ?>, <?= $post['id'] ?>)">
                                                                    <i class="fas fa-trash me-1"></i>Delete
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Posts pagination">
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
                                <i class="fas fa-crown me-2"></i>
                                <strong><?= ucfirst($currentUser['subscription_status']) ?> Subscriber</strong><br>
                                <small>
                                    <?php if ($currentUser['subscription_expires']): ?>
                                        Expires: <?= date('M j, Y', strtotime($currentUser['subscription_expires'])) ?>
                                    <?php else: ?>
                                        Lifetime access
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Stats -->
                    <div class="mb-4">
                        <h6>Community Stats</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Posts:</span>
                            <strong><?= $totalPosts ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Your Posts:</span>
                            <strong>0</strong>
                        </div>
                    </div>

                    <!-- Navigation Links -->
                    <div class="d-grid gap-2">
                        <a href="../user/profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                        <a href="../user/settings.php" class="btn btn-outline-secondary">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                            <a href="../admin/" class="btn btn-outline-success">
                                <i class="fas fa-cogs me-2"></i>Admin Panel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Premium content checkbox handler
        document.getElementById('is_premium')?.addEventListener('change', function() {
            const subscriptionSelect = document.getElementById('subscription_required');
            subscriptionSelect.disabled = !this.checked;
            if (!this.checked) {
                subscriptionSelect.value = 'none';
            }
        });

        // Like button functionality
        document.querySelectorAll('.like-btn[data-post-id]').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const isLiked = this.classList.contains('liked');
                
                // TODO: Implement AJAX like functionality
                console.log('Like post:', postId, 'Currently liked:', isLiked);
                
                // Toggle like state (placeholder)
                this.classList.toggle('liked');
                const countSpan = this.querySelector('.like-count');
                let count = parseInt(countSpan.textContent);
                countSpan.textContent = isLiked ? count - 1 : count + 1;
            });
        });

        // Image click to enlarge functionality
        document.querySelectorAll('.post-media-image').forEach(img => {
            img.addEventListener('click', function() {
                openImageModal(this.src, this.alt);
            });
        });

        // Function to open image in modal
        function openImageModal(src, alt) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('imageModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'imageModal';
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content bg-transparent border-0">
                            <div class="modal-body p-0 text-center">
                                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1050;"></button>
                                <img id="modalImage" src="" alt="" class="img-fluid rounded" style="max-height: 90vh;">
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            
            // Set image source and show modal
            document.getElementById('modalImage').src = src;
            document.getElementById('modalImage').alt = alt;
            new bootstrap.Modal(modal).show();
        }

        // Comment system functionality
        function toggleComments(postId) {
            const commentsSection = document.getElementById(`comments-${postId}`);
            const toggleBtn = document.querySelector(`[data-post-id="${postId}"].comment-toggle`);
            
            if (commentsSection.classList.contains('show')) {
                commentsSection.classList.remove('show');
                toggleBtn.classList.remove('active');
            } else {
                commentsSection.classList.add('show');
                toggleBtn.classList.add('active');
                
                setTimeout(() => {
                    commentsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }

        function submitComment(postId) {
            const textarea = document.getElementById(`comment-text-${postId}`);
            const loadingSpinner = document.getElementById(`loading-${postId}`);
            const commentText = textarea.value.trim();
            
            if (!commentText) {
                alert('Please enter a comment');
                return;
            }
            
            if (commentText.length > 1000) {
                alert('Comment is too long (max 1000 characters)');
                return;
            }
            
            loadingSpinner.style.display = 'block';
            textarea.disabled = true;
            
            fetch('../api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create',
                    post_id: postId,
                    content: commentText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentsList = document.getElementById(`comments-list-${postId}`);
                    const newCommentHtml = createCommentHTML(data.comment);
                    commentsList.insertAdjacentHTML('beforeend', newCommentHtml);
                    
                    const countElement = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = currentCount + 1;
                    
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    
                    const newComment = commentsList.lastElementChild;
                    newComment.style.background = '#d4edda';
                    setTimeout(() => {
                        newComment.style.background = '#ffffff';
                    }, 2000);
                    
                } else {
                    alert('Error: ' + (data.message || 'Failed to post comment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to post comment. Please try again.');
            })
            .finally(() => {
                loadingSpinner.style.display = 'none';
                textarea.disabled = false;
            });
        }

        function createCommentHTML(comment) {
            const roleIcon = comment.role === 'chloe' ? '<i class="fas fa-star text-warning ms-1" title="Chloe Belle"></i>' :
                            comment.role === 'admin' ? '<i class="fas fa-shield-alt text-danger ms-1" title="Administrator"></i>' : '';
            
            const avatarClass = comment.role === 'chloe' ? 'comment-avatar chloe-avatar' :
                               comment.role === 'admin' ? 'comment-avatar admin-avatar' : 'comment-avatar';
            
            return `
                <div class="comment-item fade-in" data-comment-id="${comment.id}">
                    <div class="d-flex align-items-start">
                        <img src="${comment.avatar_url}" 
                             alt="${comment.username}" 
                             class="${avatarClass} me-2">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <strong class="me-2">
                                    ${comment.username}${roleIcon}
                                </strong>
                                <small class="text-muted">just now</small>
                            </div>
                            <p class="mb-1">${comment.content}</p>
                            <div class="comment-actions">
                                <button class="btn btn-link btn-sm p-0 me-2 text-muted comment-like-btn" 
                                        data-comment-id="${comment.id}">
                                    <i class="fas fa-heart me-1"></i>
                                    <span class="comment-like-count">0</span>
                                </button>
                                <button class="btn btn-link btn-sm p-0 text-danger ms-2" 
                                        onclick="deleteComment(${comment.id}, ${comment.post_id})">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

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
                    commentElement.style.transition = 'all 0.3s ease';
                    commentElement.style.opacity = '0';
                    commentElement.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        commentElement.remove();
                    }, 300);
                    
                    const countElement = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = Math.max(0, currentCount - 1);
                    
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete comment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete comment. Please try again.');
            });
        }

        function likeComment(commentId) {
            const likeBtn = document.querySelector(`[data-comment-id="${commentId}"].comment-like-btn`);
            const likeCount = likeBtn.querySelector('.comment-like-count');
            const isLiked = likeBtn.classList.contains('liked');
            
            fetch('../api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: isLiked ? 'unlike' : 'like',
                    comment_id: commentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    likeBtn.classList.toggle('liked');
                    likeCount.textContent = data.like_count;
                    
                    likeBtn.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        likeBtn.style.transform = 'scale(1)';
                    }, 200);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Comment toggle buttons
            document.querySelectorAll('.comment-toggle').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.dataset.postId;
                    toggleComments(postId);
                });
            });

            // Comment like buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.comment-like-btn')) {
                    const btn = e.target.closest('.comment-like-btn');
                    const commentId = btn.dataset.commentId;
                    likeComment(commentId);
                }
            });

            // Auto-resize textareas
            document.querySelectorAll('textarea[id^="comment-text-"]').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });

                // Ctrl+Enter to submit
                textarea.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'Enter') {
                        const postId = this.id.split('-').pop();
                        submitComment(postId);
                    }
                });
            });
        });

        console.log('🌟 Chloe Belle Feed loaded successfully!');
        console.log('👤 Current user:', '<?= htmlspecialchars($currentUser['username']) ?>');
        console.log('💎 Subscription:', '<?= $currentUser['subscription_status'] ?>');
        console.log('🖼️ Avatar system enabled');
        console.log('📱 Media sizing optimized');
        console.log('💬 Comment system ready!');
    </script>
</body>
</html>