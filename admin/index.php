<?php
/**
 * Fixed Admin Dashboard - admin/index.php
 * Resolves PHP syntax errors (line 436 issue fixed)
 */

session_start();
require_once '../config.php';
require_once '../settings.php';

// Check if user is logged in and has admin/moderator access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Database connection
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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get dashboard statistics
$stats = [];

try {
    // Total users
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Active users (logged in within last 30 days)
    $stats['active_users'] = $pdo->query("
        SELECT COUNT(*) FROM users 
        WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetchColumn();
    
    // Total posts
    $stats['total_posts'] = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    
    // Published posts
    $stats['published_posts'] = $pdo->query("
        SELECT COUNT(*) FROM posts WHERE status = 'published'
    ")->fetchColumn();
    
    // Comments today
    $stats['comments_today'] = $pdo->query("
        SELECT COUNT(*) FROM comments 
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();
    
    // New users this month
    $stats['new_users_month'] = $pdo->query("
        SELECT COUNT(*) FROM users 
        WHERE YEAR(created_at) = YEAR(CURDATE()) 
        AND MONTH(created_at) = MONTH(CURDATE())
    ")->fetchColumn();
    
    // Revenue this month (if subscriptions table exists)
    $stats['revenue_month'] = 0;
    $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($stmt->rowCount() > 0) {
        $result = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) FROM payments 
            WHERE status = 'completed'
            AND YEAR(created_at) = YEAR(CURDATE()) 
            AND MONTH(created_at) = MONTH(CURDATE())
        ");
        $stats['revenue_month'] = $result->fetchColumn();
    }
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Set default values if query fails
    $stats = array_fill_keys([
        'total_users', 'active_users', 'total_posts', 
        'published_posts', 'comments_today', 'new_users_month', 'revenue_month'
    ], 0);
}

// Get recent activities
$recentActivities = [];
try {
    $recentActivities = $pdo->query("
        SELECT 
            u.username,
            p.title,
            p.created_at,
            'post' as type
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
}

// Get system info
$systemInfo = [
    'php_version' => phpversion(),
    'mysql_version' => $pdo->query("SELECT VERSION()")->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_usage' => function_exists('disk_free_space') ? disk_free_space('.') : 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_size' => ini_get('upload_max_filesize')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= getSiteName() ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #6c5ce7;
            --sidebar-width: 280px;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: left 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .admin-sidebar.show {
            left: 0;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-item {
            margin: 0.25rem 1rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 0;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.sidebar-open {
            margin-left: var(--sidebar-width);
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .mobile-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .welcome-title {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2d3436;
            margin: 0;
        }
        
        .stat-label {
            color: #636e72;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s ease;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .main-content.sidebar-open {
                margin-left: 0;
            }
            
            .welcome-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h4 class="mb-1">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </h4>
            <small class="opacity-75">Manage your website</small>
        </div>
        
        <div class="nav-menu">
            <div class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>
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
                    <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
                    <p class="welcome-subtitle">Here's what's happening with your website today.</p>
                </div>
                <div class="text-end">
                    <div class="fs-5 fw-bold"><?= date('M j, Y') ?></div>
                    <div class="opacity-75"><?= date('l') ?></div>
                </div>
            </div>
        </header>

        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                    <div class="stat-card fade-in-up">
                        <div class="stat-icon" style="background: linear-gradient(45deg, #ff6b6b, #ee5a52);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stat-value"><?= number_format($stats['total_users']) ?></h3>
                        <p class="stat-label">Total Users</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                    <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                        <div class="stat-icon" style="background: linear-gradient(45deg, #4ecdc4, #44a08d);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3 class="stat-value"><?= number_format($stats['active_users']) ?></h3>
                        <p class="stat-label">Active Users</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                    <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                        <div class="stat-icon" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3 class="stat-value"><?= number_format($stats['published_posts']) ?></h3>
                        <p class="stat-label">Published Posts</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                    <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                        <div class="stat-icon" style="background: linear-gradient(45deg, #f093fb, #f5576c);">
                            <i class="fas fa-pound-sign"></i>
                        </div>
                        <h3 class="stat-value">£<?= number_format($stats['revenue_month'], 2) ?></h3>
                        <p class="stat-label">Revenue This Month</p>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Recent Activities -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm h-100 fade-in-up" style="animation-delay: 0.4s;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock text-primary me-2"></i>Recent Activities
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentActivities)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p>No recent activities</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <div class="activity-icon me-3">
                                                <i class="fas fa-edit text-primary"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($activity['username']) ?> created a new post
                                                </div>
                                                <div class="text-muted small">
                                                    "<?= htmlspecialchars($activity['title']) ?>"
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 fade-in-up" style="animation-delay: 0.5s;">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-server text-success me-2"></i>System Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">PHP Version:</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($systemInfo['php_version']) ?></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">MySQL Version:</span>
                                        <span class="fw-semibold"><?= htmlspecialchars(substr($systemInfo['mysql_version'], 0, 10)) ?></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Memory Limit:</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($systemInfo['memory_limit']) ?></span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Upload Limit:</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($systemInfo['upload_max_size']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="d-grid gap-2">
                                <a href="settings.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-cog me-2"></i>System Settings
                                </a>
                                <a href="../" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-2"></i>View Website
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar functionality
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        // Mobile toggle click
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            const icon = mobileToggle.querySelector('i');
            if (sidebar.classList.contains('show')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Overlay click to close sidebar
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            mobileToggle.querySelector('i').classList.remove('fa-times');
            mobileToggle.querySelector('i').classList.add('fa-bars');
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

        console.log('🎉 Admin Dashboard loaded successfully!');
        console.log('⚙️ System: PHP <?= $systemInfo['php_version'] ?>, MySQL <?= substr($systemInfo['mysql_version'], 0, 10) ?>');
        console.log('👥 Users: <?= $stats['total_users'] ?> total, <?= $stats['active_users'] ?> active');
        console.log('📝 Posts: <?= $stats['published_posts'] ?> published');
    </script>
</body>
</html>