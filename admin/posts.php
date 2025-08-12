<?php
/**
 * Enhanced Post Management Page for Chloe Belle Admin
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
$postsPerPage = 12;
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
        SELECT p.*, u.username, u.role as user_role,
               (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.status = 'approved') as comments_count
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
    <title>Posts Management - Chloe Belle Admin</title>
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

        /* Filters */
        .filters-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filter-btn {
            padding: 0.75rem 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: white;
            color: var(--dark-color);
            border-radius: 50px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        /* Posts Grid */
        .posts-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .post-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .post-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .post-card:hover .post-actions {
            opacity: 1;
            transform: translateY(0);
        }

        .post-content {
            padding: 1.5rem;
        }

        .post-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .post-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            line-height: 1.4;
            flex: 1;
        }

        .post-excerpt {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .post-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .post-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .status-published {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-draft {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .premium-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(45deg, var(--warning-color), #d97706);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .post-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: #9ca3af;
        }

        .post-stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Action Menu */
        .post-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            opacity: 0;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 10;
        }

        .actions-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .actions-toggle:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: scale(1.05);
        }

        .actions-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(0, 0, 0, 0.1);
            overflow: hidden;
            min-width: 160px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .actions-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .action-item:hover {
            background: #f8fafc;
        }

        .action-item.view:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .action-item.edit:hover {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .action-item.delete:hover {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .header-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .action-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .action-btn.secondary {
            background: white;
            color: var(--dark-color);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .posts-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .post-actions {
                opacity: 1;
                transform: translateY(0);
                position: static;
                margin-top: 1rem;
                display: flex;
                justify-content: center;
            }

            .actions-menu {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                display: none;
                margin-top: 0;
            }

            .actions-menu.show {
                display: block;
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
        .pagination {
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
            margin-bottom: 1.5rem;
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
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a href="posts.php" class="nav-link active">
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
        <!-- Header -->
        <header class="page-header fade-in-up">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title">Posts Management</h1>
                    <p class="page-subtitle">Create, edit, and manage all your content</p>
                </div>
                <div class="header-actions">
                    <button class="action-btn primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                        <i class="fas fa-plus"></i>
                        New Post
                    </button>
                </div>
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

        <!-- Filters -->
        <div class="filters-container fade-in-up">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                        All Posts
                    </a>
                    <a href="?filter=published" class="filter-btn <?= $filter === 'published' ? 'active' : '' ?>">
                        Published
                    </a>
                    <a href="?filter=draft" class="filter-btn <?= $filter === 'draft' ? 'active' : '' ?>">
                        Drafts
                    </a>
                </div>
                <span class="text-muted">
                    <?= count($posts) ?> of <?= $totalPosts ?> posts
                </span>
            </div>
        </div>

        <!-- Posts Grid -->
        <?php if (empty($posts)): ?>
            <div class="posts-container fade-in-up">
                <div class="empty-state">
                    <i class="fas fa-edit"></i>
                    <h3>No posts found</h3>
                    <p>Create your first post to get started!</p>
                    <button class="action-btn primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                        <i class="fas fa-plus"></i>
                        Create Post
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="posts-container fade-in-up">
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                    <article class="post-card">
                        <?php if ($post['is_premium']): ?>
                            <div class="premium-badge">
                                <i class="fas fa-crown me-1"></i>Premium
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-actions">
                            <div class="actions-toggle" onclick="toggleActions(<?= $post['id'] ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </div>
                            <div class="actions-menu" id="actionsMenu<?= $post['id'] ?>">
                                <a href="../feed/post.php?id=<?= $post['id'] ?>" target="_blank" class="action-item view">
                                    <i class="fas fa-eye"></i>
                                    View Post
                                </a>
                                
                                <?php if ($post['status'] === 'draft'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="status" value="published">
                                        <button type="submit" class="action-item edit">
                                            <i class="fas fa-check"></i>
                                            Publish
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit" class="action-item edit">
                                            <i class="fas fa-edit"></i>
                                            Make Draft
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <button type="submit" class="action-item delete"
                                            onclick="return confirm('Delete this post permanently?')">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="post-content">
                            <div class="post-header">
                                <h3 class="post-title">
                                    <?= $post['title'] ? htmlspecialchars($post['title']) : 'Untitled Post' ?>
                                    <?php if ($post['featured']): ?>
                                        <i class="fas fa-star text-warning ms-1" title="Featured"></i>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            
                            <p class="post-excerpt">
                                <?= nl2br(htmlspecialchars(substr($post['content'], 0, 150))) ?>
                                <?= strlen($post['content']) > 150 ? '...' : '' ?>
                            </p>
                            
                            <div class="post-meta">
                                <span class="post-status status-<?= $post['status'] ?>">
                                    <?= ucfirst($post['status']) ?>
                                </span>
                                <small class="text-muted">
                                    by <?= htmlspecialchars($post['username']) ?>
                                    <?php if ($post['user_role'] === 'chloe'): ?>
                                        <i class="fas fa-star text-warning" title="Chloe Belle"></i>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="post-stats">
                                <div class="post-stat">
                                    <i class="fas fa-eye"></i>
                                    <span><?= number_format($post['views']) ?></span>
                                </div>
                                <div class="post-stat">
                                    <i class="fas fa-heart"></i>
                                    <span><?= number_format($post['likes']) ?></span>
                                </div>
                                <div class="post-stat">
                                    <i class="fas fa-comment"></i>
                                    <span><?= number_format($post['comments_count']) ?></span>
                                </div>
                                <div class="post-stat">
                                    <i class="fas fa-clock"></i>
                                    <span><?= date('M j, Y', strtotime($post['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Posts pagination" class="pagination fade-in-up">
                    <ul class="pagination justify-content-center mb-0">
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
    </main>

    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

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

        // Actions menu functionality
        function toggleActions(postId) {
            const menu = document.getElementById(`actionsMenu${postId}`);
            const allMenus = document.querySelectorAll('.actions-menu');
            
            // Close all other menus
            allMenus.forEach(m => {
                if (m !== menu) {
                    m.classList.remove('show');
                }
            });
            
            // Toggle current menu
            menu.classList.toggle('show');
        }

        // Close action menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.post-actions')) {
                document.querySelectorAll('.actions-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Show/hide subscription options based on premium checkbox
        document.getElementById('is_premium').addEventListener('change', function() {
            const subscriptionOptions = document.getElementById('subscriptionOptions');
            subscriptionOptions.style.display = this.checked ? 'block' : 'none';
            
            if (!this.checked) {
                document.getElementById('subscription_required').value = 'none';
            }
        });

        console.log('🎉 Enhanced Posts Management loaded!');
        console.log('📝 Total posts: <?= $totalPosts ?>');
        console.log('✨ Modern design with improved UX');
    </script>
</body>
</html>