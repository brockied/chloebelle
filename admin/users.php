<?php
/**
 * Enhanced User Management Page for Chloe Belle Admin
 */

session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle user actions
$message = '';
$messageType = 'info';

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
        $userId = (int)($_POST['user_id'] ?? 0);

        switch ($action) {
            case 'ban':
                $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = "User banned successfully";
                $messageType = 'warning';
                break;
            
            case 'unban':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = "User unbanned successfully";
                $messageType = 'success';
                break;
            
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$userId]);
                $message = "User deleted successfully";
                $messageType = 'danger';
                break;
            
            case 'promote':
                $newRole = $_POST['new_role'] ?? 'user';
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);
                $message = "User role updated successfully";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get users with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$usersPerPage = 20;
$offset = ($page - 1) * $usersPerPage;
$search = $_GET['search'] ?? '';
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

    // Build search and filter query
    $whereConditions = [];
    $searchParams = [];
    
    if ($search) {
        $whereConditions[] = "(username LIKE ? OR email LIKE ?)";
        $searchParams = ["%$search%", "%$search%"];
    }
    
    if ($filter !== 'all') {
        switch ($filter) {
            case 'subscribers':
                $whereConditions[] = "subscription_status != 'none'";
                break;
            case 'banned':
                $whereConditions[] = "status = 'banned'";
                break;
            case 'admins':
                $whereConditions[] = "role IN ('admin', 'chloe')";
                break;
        }
    }
    
    $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

    // Get total count
    $countSql = "SELECT COUNT(*) FROM users $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($searchParams);
    $totalUsers = $countStmt->fetchColumn();

    // Get users
    $sql = "
        SELECT id, username, email, role, status, subscription_status, 
               subscription_expires, last_login, created_at
        FROM users 
        $whereClause
        ORDER BY created_at DESC 
        LIMIT $usersPerPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($searchParams);
    $users = $stmt->fetchAll();

    $totalPages = ceil($totalUsers / $usersPerPage);

    // Get quick stats
    $stats = [];
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $stats['subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status != 'none'")->fetchColumn();
    $stats['banned_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn();

} catch (Exception $e) {
    error_log("Admin users query error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
    $users = [];
    $totalPages = 0;
    $stats = ['total_users' => 0, 'active_users' => 0, 'subscribers' => 0, 'banned_users' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Chloe Belle Admin</title>
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

        /* Sidebar Styles - Same as dashboard */
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

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-card.total .stat-value { color: var(--info-color); }
        .stat-card.active .stat-value { color: var(--success-color); }
        .stat-card.subscribers .stat-value { color: var(--warning-color); }
        .stat-card.banned .stat-value { color: var(--danger-color); }

        /* Search and Filters */
        .controls-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .search-bar {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            background: white;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: white;
            color: var(--dark-color);
            border-radius: 50px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .filter-tab.active,
        .filter-tab:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: transparent;
        }

        /* Users Table */
        .users-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .users-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .table-responsive {
            border-radius: 0 0 20px 20px;
            overflow: hidden;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--light-color);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-details small {
            color: #6b7280;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-banned {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .role-chloe {
            background: linear-gradient(45deg, var(--warning-color), #d97706);
            color: white;
        }

        .role-user {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .subscription-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .subscription-none {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .subscription-premium {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        /* Action Dropdown */
        .action-dropdown {
            position: relative;
        }

        .action-toggle {
            background: none;
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .action-toggle:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6b7280;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-tabs {
                justify-content: center;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                margin-right: 0.5rem;
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

        /* Pagination */
        .pagination-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .page-link {
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--dark-color);
            padding: 0.75rem 1rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .page-link:hover,
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: transparent;
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
            <div class="nav-item">
                <a href="users.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </div>
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
                <h1 class="page-title">Users Management</h1>
                <p class="page-subtitle">Manage users, subscriptions, and permissions</p>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row fade-in-up">
            <div class="stat-card total">
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card active">
                <div class="stat-value"><?= number_format($stats['active_users']) ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card subscribers">
                <div class="stat-value"><?= number_format($stats['subscribers']) ?></div>
                <div class="stat-label">Subscribers</div>
            </div>
            <div class="stat-card banned">
                <div class="stat-value"><?= number_format($stats['banned_users']) ?></div>
                <div class="stat-label">Banned Users</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="controls-container fade-in-up">
            <form method="GET" class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" name="search" 
                       placeholder="Search users by username or email..." 
                       value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            </form>
            
            <div class="filter-tabs">
                <a href="?filter=all&search=<?= urlencode($search) ?>" 
                   class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    All Users
                </a>
                <a href="?filter=subscribers&search=<?= urlencode($search) ?>" 
                   class="filter-tab <?= $filter === 'subscribers' ? 'active' : '' ?>">
                    Subscribers
                </a>
                <a href="?filter=banned&search=<?= urlencode($search) ?>" 
                   class="filter-tab <?= $filter === 'banned' ? 'active' : '' ?>">
                    Banned
                </a>
                <a href="?filter=admins&search=<?= urlencode($search) ?>" 
                   class="filter-tab <?= $filter === 'admins' ? 'active' : '' ?>">
                    Admins
                </a>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-container fade-in-up">
            <div class="users-header">
                <h5 class="mb-0">Users (<?= number_format($totalUsers) ?> total)</h5>
                <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No users found</h3>
                    <p>No users match your search criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Subscription</th>
                                <th>Last Login</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h6>
                                                <?= htmlspecialchars($user['username']) ?>
                                                <?php if ($user['role'] === 'chloe'): ?>
                                                    <i class="fas fa-star text-warning ms-1" title="Chloe Belle"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <small><?= htmlspecialchars($user['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= $user['role'] ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $user['status'] ?>">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['subscription_status'] === 'none'): ?>
                                        <span class="subscription-badge subscription-none">Free</span>
                                    <?php else: ?>
                                        <span class="subscription-badge subscription-premium">
                                            <?= ucfirst($user['subscription_status']) ?>
                                        </span>
                                        <?php if ($user['subscription_expires']): ?>
                                            <br><small class="text-muted">
                                                Expires: <?= date('M j, Y', strtotime($user['subscription_expires'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <small><?= date('M j, Y', strtotime($user['last_login'])) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Never</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($user['role'] !== 'admin' || $user['id'] != $_SESSION['user_id']): ?>
                                        <div class="dropdown">
                                            <button class="action-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="ban">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-warning" 
                                                                    onclick="return confirm('Ban this user?')">
                                                                <i class="fas fa-ban me-2"></i>Ban User
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="unban">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="fas fa-check me-2"></i>Unban User
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                       data-bs-target="#roleModal" data-user-id="<?= $user['id'] ?>" 
                                                       data-username="<?= htmlspecialchars($user['username']) ?>"
                                                       data-current-role="<?= $user['role'] ?>">
                                                        <i class="fas fa-user-tag me-2"></i>Change Role
                                                    </a>
                                                </li>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger" 
                                                                onclick="return confirm('DELETE this user permanently? This cannot be undone!')">
                                                            <i class="fas fa-trash me-2"></i>Delete User
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted">Protected</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Users pagination" class="pagination-container fade-in-up">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>

    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Role Change Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="promote">
                        <input type="hidden" name="user_id" id="roleUserId">
                        
                        <p>Change role for: <strong id="roleUsername"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">New Role:</label>
                            <select class="form-select" name="new_role" required>
                                <option value="user">User</option>
                                <option value="moderator">Moderator</option>
                                <option value="chloe">Chloe</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning">
                            <small><strong>Warning:</strong> Changing user roles affects their permissions and access levels.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        // Role modal handler
        document.getElementById('roleModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const currentRole = button.getAttribute('data-current-role');
            
            document.getElementById('roleUserId').value = userId;
            document.getElementById('roleUsername').textContent = username;
            document.querySelector('[name="new_role"]').value = currentRole;
        });

        // Auto-submit search form on input
        document.querySelector('.search-input').addEventListener('input', function() {
            const form = this.closest('form');
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                form.submit();
            }, 500);
        });

        console.log('🎉 Enhanced Users Management loaded!');
        console.log('👥 Total users: <?= $totalUsers ?>');
        console.log('✨ Modern design with improved filtering');
    </script>
</body>
</html>
<!-- Ban/Unban Commenting Modal/Buttons -->
<script>
async function toggleCommentBan(userId, ban) {
    const form = new FormData();
    form.append('user_id', userId);
    form.append('ban', ban ? 1 : 0);
    const res = await fetch('../api/toggle_comment_ban.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data.success) {
        alert('Updated commenting status.');
        location.reload();
    } else {
        alert(data.message || 'Failed to update commenting status');
    }
}
</script>
