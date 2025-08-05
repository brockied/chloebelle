<?php
/**
 * Enhanced Admin Dashboard for Chloe Belle Website
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
    $stats['total_comments'] = $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'approved'")->fetchColumn() ?: 0;

    // Calculate growth percentages
    $stats['user_growth'] = $stats['total_users'] > 0 ? round(($stats['new_users_week'] / $stats['total_users']) * 100, 1) : 0;
    $stats['subscriber_conversion'] = $stats['total_users'] > 0 ? round(($stats['total_subscribers'] / $stats['total_users']) * 100, 1) : 0;

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
        'total_views', 'total_likes', 'total_comments', 'user_growth', 'subscriber_conversion'
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

        /* Sidebar Styles */
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
        .welcome-banner {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .welcome-subtitle {
            color: #6b7280;
            margin-top: 0.5rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }

        .stat-card.users::before { background: linear-gradient(135deg, var(--info-color), #1d4ed8); }
        .stat-card.posts::before { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-card.subscribers::before { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .stat-card.engagement::before { background: linear-gradient(135deg, var(--danger-color), #dc2626); }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.users { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
        .stat-icon.posts { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.subscribers { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .stat-icon.engagement { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .stat-change.positive {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stat-change.neutral {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        /* Activity Section */
        .activity-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .activity-item:hover {
            background: rgba(99, 102, 241, 0.05);
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 12px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .activity-icon.post { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .activity-icon.user { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .activity-icon.subscription { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .activity-description {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .activity-time {
            color: #9ca3af;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .welcome-banner {
                padding: 1.5rem;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
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

        /* Overlay for mobile */
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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .fade-in-delay {
            animation: fadeIn 0.6s ease-out 0.2s both;
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
                <a href="index.php" class="nav-link active">
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
                <a href="media.php" class="nav-link">
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
        <!-- Welcome Banner -->
        <header class="welcome-banner fade-in">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</h1>
                    <p class="welcome-subtitle">Here's what's happening with your Chloe Belle platform today.</p>
                </div>
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger fade-in"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid fade-in-delay">
            <div class="stat-card users">
                <div class="stat-header">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change <?= $stats['user_growth'] > 0 ? 'positive' : 'neutral' ?>">
                    <i class="fas fa-arrow-up"></i>
                    +<?= $stats['user_growth'] ?>% growth
                </div>
            </div>

            <div class="stat-card posts">
                <div class="stat-header">
                    <div class="stat-icon posts">
                        <i class="fas fa-edit"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_posts']) ?></div>
                <div class="stat-label">Total Posts</div>
                <div class="stat-change neutral">
                    <i class="fas fa-check"></i>
                    <?= $stats['published_posts'] ?> published
                </div>
            </div>

            <div class="stat-card subscribers">
                <div class="stat-header">
                    <div class="stat-icon subscribers">
                        <i class="fas fa-crown"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_subscribers']) ?></div>
                <div class="stat-label">Premium Subscribers</div>
                <div class="stat-change positive">
                    <i class="fas fa-percentage"></i>
                    <?= $stats['subscriber_conversion'] ?>% conversion
                </div>
            </div>

            <div class="stat-card engagement">
                <div class="stat-header">
                    <div class="stat-icon engagement">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_views']) ?></div>
                <div class="stat-label">Total Views</div>
                <div class="stat-change neutral">
                    <i class="fas fa-thumbs-up"></i>
                    <?= number_format($stats['total_likes']) ?> likes
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions fade-in-delay">
            <a href="posts.php" class="action-btn">
                <i class="fas fa-plus"></i>
                Create New Post
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="users.php" class="action-btn">
                <i class="fas fa-users-cog"></i>
                Manage Users
            </a>
            <a href="settings.php" class="action-btn">
                <i class="fas fa-cog"></i>
                Site Settings
            </a>
            <?php endif; ?>
            <a href="../feed/index.php" class="action-btn">
                <i class="fas fa-eye"></i>
                View Live Site
            </a>
        </div>

        <!-- Activity Sections -->
        <div class="row">
            <!-- Recent Users -->
            <div class="col-lg-6 mb-4">
                <div class="activity-section fade-in-delay">
                    <h2 class="section-title">
                        <i class="fas fa-user-plus"></i>
                        Recent Users
                    </h2>
                    
                    <?php if (empty($recent_users)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No users yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                        <div class="activity-item">
                            <div class="activity-icon user">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="activity-description"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                            <div class="activity-time"><?= timeAgo($user['created_at']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Posts -->
            <div class="col-lg-6 mb-4">
                <div class="activity-section fade-in-delay">
                    <h2 class="section-title">
                        <i class="fas fa-edit"></i>
                        Recent Posts
                    </h2>
                    
                    <?php if (empty($recent_posts)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-edit fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No posts yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_posts as $post): ?>
                        <div class="activity-item">
                            <div class="activity-icon post">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?= $post['title'] ? htmlspecialchars($post['title']) : 'Untitled Post' ?></div>
                                <div class="activity-description">by <?= htmlspecialchars($post['username']) ?></div>
                            </div>
                            <div class="activity-time"><?= timeAgo($post['created_at']) ?></div>
                        </div>
                        <?php endforeach; ?>
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

        console.log('🎉 Enhanced Admin Dashboard loaded successfully!');
        console.log('👥 Total users:', <?= $stats['total_users'] ?>);
        console.log('📝 Total posts:', <?= $stats['total_posts'] ?>);
        console.log('👑 Total subscribers:', <?= $stats['total_subscribers'] ?>);
    </script>
</body>
</html>