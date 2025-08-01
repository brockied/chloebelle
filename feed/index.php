<?php
/**
 * Main Feed Page for Chloe Belle Website
 * Displays posts with subscription-based access control
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

// Get posts with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$postsPerPage = 10;
$offset = ($page - 1) * $postsPerPage;

try {
    // Get total post count
    $totalPosts = $pdo->query("
        SELECT COUNT(*) FROM posts p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status = 'published'
    ")->fetchColumn();

    // Get posts
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
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$currentUser['id'], $postsPerPage, $offset]);
    $posts = $stmt->fetchAll();

    $totalPages = ceil($totalPosts / $postsPerPage);

} catch (Exception $e) {
    error_log("Feed query error: " . $e->getMessage());
    $posts = [];
    $totalPages = 0;
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
        
        .post-card {
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
        
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                margin-bottom: 2rem;
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
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($currentUser['username']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../user/profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../user/settings.php">Settings</a></li>
                            <?php if ($currentUser['role'] === 'chloe'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/posts.php">
                                    <i class="fas fa-cogs me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <?php if ($currentUser['role'] === 'admin'): ?>
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
            <!-- Main Feed -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Latest Posts</h2>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3">Page <?= $page ?> of <?= $totalPages ?></span>
                    </div>
                </div>

                <?php if (empty($posts)): ?>
                    <div class="post-card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h4>No posts yet</h4>
                            <p class="text-muted">Check back later for new content from Chloe Belle!</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($posts as $post): ?>
                    <?php $hasAccess = hasAccessToPost($post, $currentUser); ?>
                    <div class="card post-card">
                        <div class="card-body">
                            <!-- Post Header -->
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?= $post['avatar'] ? '../uploads/avatars/' . $post['avatar'] : '../assets/images/default-avatar.jpg' ?>" 
                                     alt="<?= htmlspecialchars($post['username']) ?>" class="user-avatar me-3">
                                
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($post['username']) ?>
                                        <?php if ($post['role'] === 'chloe'): ?>
                                            <i class="fas fa-star text-warning ms-1" title="Chloe Belle"></i>
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
                                <?php if ($post['media_type'] === 'image'): ?>
                                    <img src="<?= htmlspecialchars($post['media_url']) ?>" class="img-fluid rounded mb-3" alt="Post image">
                                <?php elseif ($post['media_type'] === 'video'): ?>
                                    <video controls class="w-100 rounded mb-3">
                                        <source src="<?= htmlspecialchars($post['media_url']) ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Post Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <button class="like-btn me-3 <?= $post['user_liked'] ? 'liked' : '' ?>" 
                                            data-post-id="<?= $post['id'] ?>">
                                        <i class="fas fa-heart me-1"></i>
                                        <span class="like-count"><?= $post['like_count'] ?></span>
                                    </button>
                                    
                                    <button class="like-btn me-3">
                                        <i class="fas fa-comment me-1"></i>
                                        <?= $post['comments_count'] ?>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
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

        console.log('🌟 Chloe Belle Feed loaded successfully!');
        console.log('👤 Current user:', '<?= htmlspecialchars($currentUser['username']) ?>');
        console.log('💎 Subscription:', '<?= $currentUser['subscription_status'] ?>');
    </script>
</body>
</html>