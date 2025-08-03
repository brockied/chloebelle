<?php
/**
 * Post Management Page for Chloe Belle Admin - BETTER ACTION MENU VERSION
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

// Handle post actions
if ($_POST) {
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

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $isPremium = isset($_POST['is_premium']) ? 1 : 0;
                $subscriptionRequired = $_POST['subscription_required'] ?? 'none';
                $featured = isset($_POST['featured']) ? 1 : 0;
                
                if (empty($content)) {
                    throw new Exception('Content is required');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO posts (user_id, title, content, is_premium, subscription_required, featured, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $title, $content, $isPremium, $subscriptionRequired, $featured]);
                
                $message = "Post created successfully!";
                $messageType = 'success';
                break;
                
            case 'update_status':
                $postId = (int)$_POST['post_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE posts SET status = ? WHERE id = ?");
                $stmt->execute([$status, $postId]);
                
                $message = "Post status updated successfully!";
                $messageType = 'success';
                break;
                
            case 'delete':
                $postId = (int)$_POST['post_id'];
                
                // Only allow deletion of own posts unless admin
                $whereClause = $_SESSION['role'] === 'admin' ? "id = ?" : "id = ? AND user_id = ?";
                $params = $_SESSION['role'] === 'admin' ? [$postId] : [$postId, $_SESSION['user_id']];
                
                $stmt = $pdo->prepare("DELETE FROM posts WHERE $whereClause");
                $stmt->execute($params);
                
                $message = "Post deleted successfully!";
                $messageType = 'warning';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get posts with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$postsPerPage = 10;
$offset = ($page - 1) * $postsPerPage;
$filter = $_GET['filter'] ?? 'all';

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

    // Build WHERE clause based on user role and filter
    $whereConditions = [];
    $params = [];
    
    // Restrict to user's own posts if not admin
    if ($_SESSION['role'] === 'chloe') {
        $whereConditions[] = "p.user_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    // Add status filter if not 'all'
    if ($filter !== 'all') {
        $whereConditions[] = "p.status = ?";
        $params[] = $filter;
    }
    
    $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

    // Get total count
    $countSql = "SELECT COUNT(*) FROM posts p JOIN users u ON p.user_id = u.id $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalPosts = $countStmt->fetchColumn();

    // Get posts
    $sql = "
        SELECT p.*, u.username, u.role as user_role
        FROM posts p
        JOIN users u ON p.user_id = u.id
        $whereClause
        ORDER BY p.created_at DESC 
        LIMIT $postsPerPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    $totalPages = ceil($totalPosts / $postsPerPage);

} catch (Exception $e) {
    error_log("Posts query error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
    $posts = [];
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Management - Chloe Belle Admin</title>
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
        
        .post-card {
            transition: transform 0.2s;
            position: relative;
        }
        
        .post-card:hover {
            transform: translateY(-2px);
        }
        
        .post-card:hover .action-buttons {
            opacity: 1;
            transform: translateX(0);
        }
        
        .premium-badge {
            background: linear-gradient(45deg, #6c5ce7, #fd79a8);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
        }

        /* Better Action Menu - Slide out buttons */
        .action-buttons {
            position: absolute;
            top: 15px;
            right: 15px;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 5px;
            z-index: 10;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.1);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
        }

        .action-btn.view { color: #007bff; }
        .action-btn.edit { color: #28a745; }
        .action-btn.unpublish { color: #ffc107; }
        .action-btn.delete { color: #dc3545; }

        .action-btn:hover.view { background: #007bff; color: white; }
        .action-btn:hover.edit { background: #28a745; color: white; }
        .action-btn:hover.unpublish { background: #ffc107; color: white; }
        .action-btn:hover.delete { background: #dc3545; color: white; }

        /* Action panel alternative */
        .action-panel {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 10px;
            margin-top: 10px;
            display: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .action-panel.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .action-toggle {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.2rem;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }

        .action-toggle:hover {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary-color);
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

            .action-buttons {
                opacity: 1;
                transform: translateX(0);
                position: static;
                flex-direction: row;
                justify-content: center;
                margin-top: 10px;
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link active" href="posts.php">
                    <i class="fas fa-edit me-2"></i>Posts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="media.php">
                    <i class="fas fa-images me-2"></i>Media
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="roles.php">
                    <i class="fas fa-user-tag me-2"></i>Roles
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions.php">
                    <i class="fas fa-credit-card me-2"></i>Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
            <?php endif; ?>
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
                <h1>Post Management</h1>
                <p class="text-muted">Create and manage posts</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                    <i class="fas fa-plus me-2"></i>New Post
                </button>
                <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <a href="?filter=all" class="btn btn-<?= $filter === 'all' ? 'primary' : 'outline-primary' ?>">
                                All Posts
                            </a>
                            <a href="?filter=published" class="btn btn-<?= $filter === 'published' ? 'primary' : 'outline-primary' ?>">
                                Published
                            </a>
                            <a href="?filter=draft" class="btn btn-<?= $filter === 'draft' ? 'primary' : 'outline-primary' ?>">
                                Drafts
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-muted">
                            Showing <?= count($posts) ?> of <?= $totalPosts ?> posts
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posts -->
        <?php if (empty($posts)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-edit fa-3x text-muted mb-3"></i>
                    <h5>No posts found</h5>
                    <p class="text-muted">Create your first post to get started!</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                        <i class="fas fa-plus me-2"></i>Create Post
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($posts as $post): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card post-card h-100">
                        <!-- Hover Action Buttons -->
                        <div class="action-buttons">
                            <a href="../feed/post.php?id=<?= $post['id'] ?>" target="_blank" 
                               class="action-btn view" title="View Post">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php if ($post['status'] === 'draft'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <input type="hidden" name="status" value="published">
                                    <button type="submit" class="action-btn edit" title="Publish Post">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <input type="hidden" name="status" value="draft">
                                    <button type="submit" class="action-btn unpublish" title="Unpublish Post">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" class="action-btn delete" title="Delete Post"
                                        onclick="return confirm('Delete this post permanently?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>

                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-1">
                                        <?= $post['title'] ? htmlspecialchars($post['title']) : 'Untitled Post' ?>
                                        <?php if ($post['featured']): ?>
                                            <i class="fas fa-star text-warning ms-1" title="Featured"></i>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        by <?= htmlspecialchars($post['username']) ?>
                                        <?php if ($post['user_role'] === 'chloe'): ?>
                                            <i class="fas fa-star text-warning" title="Chloe Belle"></i>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="d-flex flex-column align-items-end">
                                    <?php if ($post['is_premium']): ?>
                                        <span class="premium-badge mb-1">Premium</span>
                                    <?php endif; ?>
                                    <span class="badge bg-<?= $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'secondary' : 'warning') ?>">
                                        <?= ucfirst($post['status']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <p class="card-text">
                                <?= nl2br(htmlspecialchars(substr($post['content'], 0, 150))) ?>
                                <?= strlen($post['content']) > 150 ? '...' : '' ?>
                            </p>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <small class="text-muted">
                                        <i class="fas fa-eye"></i> <?= $post['views'] ?>
                                    </small>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">
                                        <i class="fas fa-heart"></i> <?= $post['likes'] ?>
                                    </small>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">
                                        <i class="fas fa-comment"></i> <?= $post['comments_count'] ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                </small>
                                
                                <!-- Action Panel Toggle (Alternative to hover buttons) -->
                                <button class="action-toggle d-md-none" onclick="toggleActionPanel(<?= $post['id'] ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>

                            <!-- Mobile Action Panel -->
                            <div class="action-panel" id="actionPanel<?= $post['id'] ?>">
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="../feed/post.php?id=<?= $post['id'] ?>" target="_blank" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <?php if ($post['status'] === 'draft'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                            <input type="hidden" name="status" value="published">
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i> Publish
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                            <input type="hidden" name="status" value="draft">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-edit"></i> Draft
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Delete this post permanently?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Posts pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&filter=<?= $filter ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&filter=<?= $filter ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Create Post Modal -->
    <div class="modal fade" id="createPostModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title (Optional)</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   placeholder="Enter post title...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Content *</label>
                            <textarea class="form-control" id="content" name="content" rows="6" 
                                      placeholder="What's on your mind?" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium">
                                    <label class="form-check-label" for="is_premium">
                                        <i class="fas fa-crown text-warning me-1"></i>Premium Content
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="featured" name="featured">
                                    <label class="form-check-label" for="featured">
                                        <i class="fas fa-star text-warning me-1"></i>Featured Post
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="subscriptionOptions" style="display: none;">
                            <label for="subscription_required" class="form-label">Subscription Required</label>
                            <select class="form-select" id="subscription_required" name="subscription_required">
                                <option value="none">No subscription required</option>
                                <option value="monthly">Monthly subscribers</option>
                                <option value="yearly">Yearly subscribers</option>
                                <option value="lifetime">Lifetime subscribers</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Create Post
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Show/hide subscription options based on premium checkbox
        document.getElementById('is_premium').addEventListener('change', function() {
            const subscriptionOptions = document.getElementById('subscriptionOptions');
            subscriptionOptions.style.display = this.checked ? 'block' : 'none';
            
            if (!this.checked) {
                document.getElementById('subscription_required').value = 'none';
            }
        });

        // Toggle action panel for mobile
        function toggleActionPanel(postId) {
            const panel = document.getElementById('actionPanel' + postId);
            
            // Close all other panels first
            document.querySelectorAll('.action-panel').forEach(p => {
                if (p.id !== 'actionPanel' + postId) {
                    p.classList.remove('show');
                }
            });
            
            // Toggle current panel
            panel.classList.toggle('show');
        }

        // Close action panels when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.action-toggle') && !e.target.closest('.action-panel')) {
                document.querySelectorAll('.action-panel').forEach(panel => {
                    panel.classList.remove('show');
                });
            }
        });

        console.log('📝 Post Management loaded with better action menu');
        console.log('📊 Total posts: <?= $totalPosts ?>');
    </script>
</body>
</html>