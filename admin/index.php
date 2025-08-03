<?php
/**
 * Admin Dashboard for Chloe Belle Website - CLEAN VERSION
 */

session_start();
require_once '../config.php';

// Check if user is logged in and has admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'chloe'])) {
    header('Location: ../index.php');
    exit;
}

// Get dashboard statistics
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

    // Get basic stats
    $stats = [];
    
    // User stats
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $stats['new_users_today'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['new_users_week'] = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

    // Post stats
    $stats['total_posts'] = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $stats['published_posts'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn();
    $stats['draft_posts'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn();
    $stats['premium_posts'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE is_premium = 1")->fetchColumn();

    // Subscription stats
    $stats['total_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status != 'none'")->fetchColumn();
    $stats['monthly_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'monthly'")->fetchColumn();
    $stats['yearly_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'yearly'")->fetchColumn();
    $stats['lifetime_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'lifetime'")->fetchColumn();

    // Content engagement
    $stats['total_views'] = $pdo->query("SELECT COALESCE(SUM(views), 0) FROM posts")->fetchColumn();
    $stats['total_likes'] = $pdo->query("SELECT COALESCE(SUM(likes), 0) FROM posts")->fetchColumn();
    $stats['total_comments'] = $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'approved'")->fetchColumn();

    // Recent activity
    $recent_users = $pdo->query("
        SELECT username, email, created_at, subscription_status 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();

    $recent_posts = $pdo->query("
        SELECT p.title, p.content, p.status, p.views, p.likes, p.created_at, u.username
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC 
        LIMIT 5
    ")->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
    $stats = array_fill_keys([
        'total_users', 'active_users', 'new_users_today', 'new_users_week',
        'total_posts', 'published_posts', 'draft_posts', 'premium_posts',
        'total_subscribers', 'monthly_subscribers', 'yearly_subscribers', 'lifetime_subscribers',
        'total_views', 'total_likes', 'total_comments'
    ], 0);
    $recent_users = [];
    $recent_posts = [];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 2592000) return floor($time / 86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Chloe Belle Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --sidebar-bg: #2d3436;
            --sidebar-text: #ddd;
        }
        
        body { 
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
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
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        
        .activity-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .activity-header {
            background: linear-gradient(135deg, var(--primary-color), #a29bfe);
            color: white;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
            margin-bottom: 0;
        }
        
        .activity-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .activity-item:hover {
            background-color: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), #a29bfe);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
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
                <a class="nav-link active" href="index.php">
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
                <a class="nav-link" href="posts.php">
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
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
                    <p class="mb-0 opacity-75">Here's what's happening with your Chloe Belle platform today.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-outline-light d-lg-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row">
            <!-- User Stats -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-primary"><?php echo number_format($stats['total_users']); ?></div>
                            <div class="stat-label">Total Users</div>
                            <small class="text-muted">
                                <i class="fas fa-arrow-up text-success"></i>
                                <?php echo $stats['new_users_week']; ?> this week
                            </small>
                        </div>
                        <div class="stat-icon text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posts Stats -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-success"><?php echo number_format($stats['total_posts']); ?></div>
                            <div class="stat-label">Total Posts</div>
                            <small class="text-muted">
                                <?php echo $stats['published_posts']; ?> published
                            </small>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-edit"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscribers Stats -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-warning"><?php echo number_format($stats['total_subscribers']); ?></div>
                            <div class="stat-label">Subscribers</div>
                            <small class="text-muted">
                                <?php echo round($stats['total_users'] > 0 ? ($stats['total_subscribers'] / $stats['total_users']) * 100 : 0, 1); ?>% conversion
                            </small>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-crown"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Engagement Stats -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-info"><?php echo number_format($stats['total_views']); ?></div>
                            <div class="stat-label">Total Views</div>
                            <small class="text-muted">
                                <?php echo number_format($stats['total_likes']); ?> likes
                            </small>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-heart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="activity-card">
                    <h6 class="activity-header">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h6>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="posts.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Create Post
                                </a>
                            </div>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <div class="col-md-3 mb-2">
                                    <a href="users.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-users me-2"></i>Manage Users
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="settings.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-3 mb-2">
                                <a href="../feed/index.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-eye me-2"></i>View Site
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <h6 class="activity-header">
                        <i class="fas fa-user-plus me-2"></i>Recent Users
                    </h6>
                    <?php if (empty($recent_users)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-users fa-2x mb-2 opacity-50"></i>
                            <p>No users yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $user['subscription_status'] === 'none' ? 'secondary' : 'primary'; ?>">
                                            <?php echo ucfirst($user['subscription_status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($user['created_at']); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Posts -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <h6 class="activity-header">
                        <i class="fas fa-edit me-2"></i>Recent Posts
                    </h6>
                    <?php if (empty($recent_posts)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-edit fa-2x mb-2 opacity-50"></i>
                            <p>No posts yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_posts as $post): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <strong><?php echo $post['title'] ? htmlspecialchars($post['title']) : 'Untitled Post'; ?></strong>
                                        <br>
                                        <small class="text-muted">by <?php echo htmlspecialchars($post['username']); ?></small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars(substr($post['content'], 0, 60)); ?>...
                                        </small>
                                    </div>
                                    <div class="text-end ms-2">
                                        <span class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($post['created_at']); ?></small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-eye"></i> <?php echo $post['views']; ?> 
                                            <i class="fas fa-heart ms-1"></i> <?php echo $post['likes']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        console.log('📊 Admin Dashboard loaded successfully!');
        console.log('👥 Total users:', <?php echo $stats['total_users']; ?>);
        console.log('📝 Total posts:', <?php echo $stats['total_posts']; ?>);
        console.log('👑 Total subscribers:', <?php echo $stats['total_subscribers']; ?>);
    </script>
</body>
</html>