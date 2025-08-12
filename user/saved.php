<?php
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

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
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        session_destroy();
        header('Location: ../auth/login.php');
        exit;
    }

    // Get saved posts
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT p.*, u.username, u.avatar, u.role as user_role,
               sp.saved_at,
               COUNT(DISTINCT c.id) as comment_count,
               COUNT(DISTINCT l.id) as like_count,
               MAX(CASE WHEN l.user_id = ? THEN 1 ELSE 0 END) as user_liked
        FROM saved_posts sp
        JOIN posts p ON sp.post_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN comments c ON p.id = c.post_id
        LEFT JOIN likes l ON p.id = l.post_id AND l.type = 'post'
        WHERE sp.user_id = ? AND p.status = 'published'
        GROUP BY p.id, sp.saved_at
        ORDER BY sp.saved_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset]);
    $savedPosts = $stmt->fetchAll();
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM saved_posts sp JOIN posts p ON sp.post_id = p.id WHERE sp.user_id = ? AND p.status = 'published'");
    $countStmt->execute([$_SESSION['user_id']]);
    $totalSaved = $countStmt->fetchColumn();
    
    // Check subscription status
    $subscriptionActive = in_array($currentUser['subscription_status'], ['monthly', 'yearly', 'lifetime']);

} catch (Exception $e) {
    error_log("Saved posts error: " . $e->getMessage());
    $savedPosts = [];
    $totalSaved = 0;
}

function getMediaUrls($mediaString) {
    if (empty($mediaString)) return [];
    
    $files = explode(',', $mediaString);
    $urls = [];
    
    foreach ($files as $file) {
        $file = trim($file);
        if ($file) {
            $urls[] = '../uploads/posts/' . $file;
        }
    }
    
    return $urls;
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
    <title>Saved Posts - Chloe Belle</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .saved-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .post-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .post-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .post-content {
            padding: 1.5rem;
        }
        
        .post-media-image {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .post-media-image:hover {
            transform: scale(1.02);
        }
        
        .post-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 0.9rem;
            padding: 0.5rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .action-btn:hover {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }
        
        .action-btn.liked {
            color: #e74c3c;
        }
        
        .saved-badge {
            background: linear-gradient(45deg, #6c5ce7, #a29bfe);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .premium-badge {
            background: linear-gradient(45deg, #f39c12, #e74c3c);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                        <a class="nav-link active" href="saved.php">
                            <i class="fas fa-bookmark me-1"></i>Saved
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
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?= htmlspecialchars($currentUser['username']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
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
        <div class="saved-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-bookmark me-2"></i>Saved Posts</h2>
                    <?php if ($totalSaved > 0): ?>
                        <p class="text-muted mb-0"><?= $totalSaved ?> saved post<?= $totalSaved !== 1 ? 's' : '' ?></p>
                    <?php endif; ?>
                </div>
                <a href="../feed/index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Feed
                </a>
            </div>

            <?php if (!empty($savedPosts)): ?>
                <div class="saved-posts-list">
                    <?php foreach ($savedPosts as $post): ?>
                        <?php 
                        $hasAccess = !$post['is_premium'] || $subscriptionActive || in_array($currentUser['role'], ['admin', 'chloe']);
                        $mediaUrls = getMediaUrls($post['media_url']);
                        ?>
                        <div class="post-card" data-post-id="<?= $post['id'] ?>">
                            <div class="post-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="user-info">
                                        <img src="<?= getAvatarUrl($post['avatar']) ?>" alt="<?= htmlspecialchars($post['username']) ?>" class="user-avatar">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($post['username']) ?></h6>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($post['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <span class="saved-badge">
                                            <i class="fas fa-bookmark me-1"></i>Saved
                                        </span>
                                        <?php if ($post['is_premium']): ?>
                                            <span class="premium-badge">
                                                <i class="fas fa-crown me-1"></i>Premium
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="post-content">
                                <?php if ($post['title']): ?>
                                    <h5 class="mb-3"><?= htmlspecialchars($post['title']) ?></h5>
                                <?php endif; ?>

                                <?php if ($post['content']): ?>
                                    <p class="mb-3"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($mediaUrls) && $hasAccess): ?>
                                    <div class="mb-3">
                                        <?php if ($post['media_type'] === 'image'): ?>
                                            <img src="<?= htmlspecialchars($mediaUrls[0]) ?>" alt="Post image" class="post-media-image" onclick="openImageModal('<?= htmlspecialchars($mediaUrls[0]) ?>')">
                                        <?php elseif ($post['media_type'] === 'video'): ?>
                                            <video class="post-media-image" controls>
                                                <source src="<?= htmlspecialchars($mediaUrls[0]) ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!empty($mediaUrls) && !$hasAccess): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-lock me-2"></i>
                                        <strong>Premium Content</strong> - Subscribe to view this media
                                    </div>
                                <?php endif; ?>

                                <small class="text-muted">
                                    <i class="fas fa-bookmark me-1"></i>
                                    Saved on <?= date('M j, Y g:i A', strtotime($post['saved_at'])) ?>
                                </small>
                            </div>

                            <div class="post-actions">
                                <div class="d-flex gap-3">
                                    <button class="action-btn like-btn <?= $post['user_liked'] ? 'liked' : '' ?>" data-post-id="<?= $post['id'] ?>" data-type="post">
                                        <i class="fas fa-heart"></i>
                                        <span class="like-count"><?= $post['like_count'] ?></span>
                                    </button>
                                    <button class="action-btn">
                                        <i class="fas fa-comment"></i>
                                        <span><?= $post['comment_count'] ?></span>
                                    </button>
                                    <button class="action-btn unsave-btn" data-post-id="<?= $post['id'] ?>" title="Remove from saved">
                                        <i class="fas fa-bookmark-slash"></i>
                                        <span>Unsave</span>
                                    </button>
                                </div>
                                <div>
                                    <a href="../feed/post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-1"></i>View Post
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalSaved > $limit): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $totalPages = ceil($totalSaved / $limit);
                            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): 
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bookmark"></i>
                    <h4>No Saved Posts Yet</h4>
                    <p class="mb-4">Save posts from the feed to view them here later.</p>
                    <a href="../feed/index.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Go to Feed
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0 text-center">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1050;"></button>
                    <img id="modalImage" src="" alt="Full size image" class="img-fluid" style="max-height: 90vh; border-radius: 10px;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Open image modal
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }

        // Like functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.like-btn')) {
                const btn = e.target.closest('.like-btn');
                const postId = btn.dataset.postId;
                const type = btn.dataset.type;
                
                fetch('../api/likes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        type: type
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.classList.toggle('liked');
                        const countSpan = btn.querySelector('.like-count');
                        if (countSpan) {
                            countSpan.textContent = data.count;
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });

        // Unsave functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.unsave-btn')) {
                const btn = e.target.closest('.unsave-btn');
                const postId = btn.dataset.postId;
                
                if (confirm('Remove this post from your saved items?')) {
                    fetch('../api/saved_posts.php', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            post_id: postId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the post card with animation
                            const postCard = btn.closest('.post-card');
                            postCard.style.transition = 'all 0.3s ease';
                            postCard.style.transform = 'translateX(-100%)';
                            postCard.style.opacity = '0';
                            
                            setTimeout(() => {
                                postCard.remove();
                                
                                // Check if no posts left
                                if (document.querySelectorAll('.post-card').length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        } else {
                            alert('Error removing post: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error removing post');
                    });
                }
            }
        });
    </script>
</body>
</html>