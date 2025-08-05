<?php
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is admin
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

    if (!$currentUser || !in_array($currentUser['role'], ['admin', 'chloe'])) {
        header('Location: ../feed/index.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Admin reports error: " . $e->getMessage());
    header('Location: ../feed/index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle report actions
if ($_POST) {
    if (isset($_POST['action']) && isset($_POST['report_id'])) {
        $reportId = (int)$_POST['report_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'dismiss':
                    $stmt = $pdo->prepare("UPDATE comment_reports SET status = 'dismissed', handled_by = ?, handled_at = NOW() WHERE id = ?");
                    $stmt->execute([$currentUser['id'], $reportId]);
                    $message = 'Report dismissed successfully.';
                    $messageType = 'success';
                    break;
                    
                case 'delete_comment':
                    // Get the comment ID from the report
                    $stmt = $pdo->prepare("SELECT comment_id FROM comment_reports WHERE id = ?");
                    $stmt->execute([$reportId]);
                    $report = $stmt->fetch();
                    
                    if ($report) {
                        // Delete the comment
                        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
                        $stmt->execute([$report['comment_id']]);
                        
                        // Update report status
                        $stmt = $pdo->prepare("UPDATE comment_reports SET status = 'action_taken', action_taken = 'comment_deleted', handled_by = ?, handled_at = NOW() WHERE id = ?");
                        $stmt->execute([$currentUser['id'], $reportId]);
                        
                        $message = 'Comment deleted and report resolved.';
                        $messageType = 'success';
                    }
                    break;
                    
                case 'warn_user':
                    // Mark report as handled with warning
                    $stmt = $pdo->prepare("UPDATE comment_reports SET status = 'action_taken', action_taken = 'user_warned', handled_by = ?, handled_at = NOW() WHERE id = ?");
                    $stmt->execute([$currentUser['id'], $reportId]);
                    $message = 'User warned and report resolved.';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            error_log("Report action error: " . $e->getMessage());
            $message = 'Error processing report action.';
            $messageType = 'danger';
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending', 'all', 'dismissed', 'resolved'];
if (!in_array($filter, $validFilters)) {
    $filter = 'pending';
}

// Build query based on filter
$whereClause = '';
switch ($filter) {
    case 'pending':
        $whereClause = "WHERE cr.status = 'pending'";
        break;
    case 'dismissed':
        $whereClause = "WHERE cr.status = 'dismissed'";
        break;
    case 'resolved':
        $whereClause = "WHERE cr.status = 'action_taken'";
        break;
    default:
        $whereClause = '';
}

// Get reports with comment and user info
try {
    $query = "
        SELECT cr.*, 
               c.content as comment_content, c.created_at as comment_created_at,
               reporter.username as reporter_username, reporter.avatar as reporter_avatar,
               commenter.username as commenter_username, commenter.avatar as commenter_avatar,
               handler.username as handler_username,
               p.title as post_title
        FROM comment_reports cr
        LEFT JOIN comments c ON cr.comment_id = c.id
        LEFT JOIN users reporter ON cr.reporter_id = reporter.id
        LEFT JOIN users commenter ON c.user_id = commenter.id
        LEFT JOIN users handler ON cr.handled_by = handler.id
        LEFT JOIN posts p ON c.post_id = p.id
        $whereClause
        ORDER BY cr.created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $reports = $stmt->fetchAll();
    
    // Get report counts for filters
    $countQuery = "SELECT status, COUNT(*) as count FROM comment_reports GROUP BY status";
    $countStmt = $pdo->query($countQuery);
    $counts = [];
    while ($row = $countStmt->fetch()) {
        $counts[$row['status']] = $row['count'];
    }
    $counts['all'] = array_sum($counts);
    
} catch (Exception $e) {
    error_log("Reports query error: " . $e->getMessage());
    $reports = [];
    $counts = [];
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

function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">Pending</span>';
        case 'dismissed':
            return '<span class="badge bg-secondary">Dismissed</span>';
        case 'action_taken':
            return '<span class="badge bg-success">Resolved</span>';
        default:
            return '<span class="badge bg-light text-dark">' . ucfirst($status) . '</span>';
    }
}

function getReasonBadge($reason) {
    $colors = [
        'spam' => 'bg-danger',
        'harassment' => 'bg-warning', 
        'inappropriate' => 'bg-info',
        'hate_speech' => 'bg-dark',
        'other' => 'bg-secondary'
    ];
    
    $color = $colors[$reason] ?? 'bg-secondary';
    return '<span class="badge ' . $color . '">' . ucfirst(str_replace('_', ' ', $reason)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment Reports - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --secondary-color: #a29bfe;
            --success-color: #00b894;
            --warning-color: #fdcb6e;
            --danger-color: #e17055;
            --dark-color: #2d3436;
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .admin-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .page-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }
        
        .filter-tabs {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .filter-btn {
            background: transparent;
            border: 2px solid transparent;
            color: #6c757d;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            text-decoration: none;
            margin-right: 0.5rem;
            transition: var(--transition);
        }
        
        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .filter-btn:hover {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: var(--transition);
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .report-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .report-content {
            padding: 1.5rem;
        }
        
        .comment-preview {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0 10px 10px 0;
            margin: 1rem 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            border-radius: 20px;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
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
            
            <div class="navbar-nav ms-auto">
                <a href="../feed/index.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i>Back to Feed
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="admin-container p-4">
            <div class="page-header">
                <h1><i class="fas fa-flag me-2"></i>Comment Reports</h1>
                <p class="mb-0 opacity-75">Manage reported comments and take appropriate actions</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?= $counts['pending'] ?? 0 ?></div>
                        <div class="text-muted">Pending Reports</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?= $counts['action_taken'] ?? 0 ?></div>
                        <div class="text-muted">Resolved</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?= $counts['dismissed'] ?? 0 ?></div>
                        <div class="text-muted">Dismissed</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?= $counts['all'] ?? 0 ?></div>
                        <div class="text-muted">Total Reports</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-tabs">
                <div class="d-flex align-items-center">
                    <strong class="me-3">Filter:</strong>
                    <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                        Pending <?= isset($counts['pending']) ? '(' . $counts['pending'] . ')' : '' ?>
                    </a>
                    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                        All Reports <?= isset($counts['all']) ? '(' . $counts['all'] . ')' : '' ?>
                    </a>
                    <a href="?filter=resolved" class="filter-btn <?= $filter === 'resolved' ? 'active' : '' ?>">
                        Resolved <?= isset($counts['action_taken']) ? '(' . $counts['action_taken'] . ')' : '' ?>
                    </a>
                    <a href="?filter=dismissed" class="filter-btn <?= $filter === 'dismissed' ? 'active' : '' ?>">
                        Dismissed <?= isset($counts['dismissed']) ? '(' . $counts['dismissed'] . ')' : '' ?>
                    </a>
                </div>
            </div>

            <!-- Reports List -->
            <div class="reports-list">
                <?php if (!empty($reports)): ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Report #<?= $report['id'] ?></strong>
                                        <span class="ms-2"><?= getStatusBadge($report['status']) ?></span>
                                        <span class="ms-2"><?= getReasonBadge($report['reason']) ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="report-content">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Reported By:</h6>
                                        <div class="user-info mb-3">
                                            <img src="<?= getAvatarUrl($report['reporter_avatar']) ?>" alt="Reporter" class="user-avatar">
                                            <span><?= htmlspecialchars($report['reporter_username']) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Comment Author:</h6>
                                        <div class="user-info mb-3">
                                            <img src="<?= getAvatarUrl($report['commenter_avatar']) ?>" alt="Commenter" class="user-avatar">
                                            <span><?= htmlspecialchars($report['commenter_username']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($report['post_title']): ?>
                                    <h6>Post: <span class="text-muted"><?= htmlspecialchars($report['post_title']) ?></span></h6>
                                <?php endif; ?>
                                
                                <div class="comment-preview">
                                    <h6>Reported Comment:</h6>
                                    <p class="mb-2"><?= nl2br(htmlspecialchars($report['comment_content'])) ?></p>
                                    <small class="text-muted">
                                        Posted on <?= date('M j, Y g:i A', strtotime($report['comment_created_at'])) ?>
                                    </small>
                                </div>
                                
                                <?php if ($report['status'] === 'pending'): ?>
                                    <div class="action-buttons">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                            <input type="hidden" name="action" value="dismiss">
                                            <button type="submit" class="btn btn-secondary btn-action">
                                                <i class="fas fa-times me-1"></i>Dismiss
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                            <input type="hidden" name="action" value="warn_user">
                                            <button type="submit" class="btn btn-warning btn-action">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Warn User
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this comment permanently?')">
                                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <button type="submit" class="btn btn-danger btn-action">
                                                <i class="fas fa-trash me-1"></i>Delete Comment
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Handled by:</strong> <?= htmlspecialchars($report['handler_username']) ?>
                                        on <?= date('M j, Y g:i A', strtotime($report['handled_at'])) ?>
                                        <?php if ($report['action_taken']): ?>
                                            <br><strong>Action:</strong> <?= ucfirst(str_replace('_', ' ', $report['action_taken'])) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-flag fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No reports found</h5>
                        <p class="text-muted">
                            <?php if ($filter === 'pending'): ?>
                                No pending reports at this time.
                            <?php else: ?>
                                No reports match the current filter.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>