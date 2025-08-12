<?php
/**
 * Enhanced Subscription Management Page for Chloe Belle Admin
 */

session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle subscription actions
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
            case 'update_user_subscription':
                $userId = (int)$_POST['user_id'];
                $newStatus = $_POST['subscription_status'];
                $expiresAt = $_POST['subscription_expires'] ?? null;
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET subscription_status = ?, subscription_expires = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $expiresAt, $userId]);
                
                $message = "User subscription updated successfully!";
                $messageType = 'success';
                break;
                
            case 'bulk_expire_subscriptions':
                // Find and expire subscriptions that are past due
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET subscription_status = 'none', subscription_expires = NULL 
                    WHERE subscription_expires IS NOT NULL 
                    AND subscription_expires < NOW() 
                    AND subscription_status != 'lifetime'
                ");
                $affectedRows = $stmt->execute();
                
                $message = "Expired subscriptions have been processed!";
                $messageType = 'info';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get subscription statistics and data
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

    // Get subscription stats
    $stats = [];
    $stats['total_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status != 'none'")->fetchColumn();
    $stats['monthly_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'monthly'")->fetchColumn();
    $stats['yearly_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'yearly'")->fetchColumn();
    $stats['lifetime_subscribers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'lifetime'")->fetchColumn();
    $stats['free_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'none'")->fetchColumn();
    $stats['expiring_soon'] = $pdo->query("
        SELECT COUNT(*) FROM users 
        WHERE subscription_expires IS NOT NULL 
        AND subscription_expires BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND subscription_status != 'lifetime'
    ")->fetchColumn();

    // Calculate revenue estimates (placeholder since we don't have payments table)
    $monthlyPrice = 9.99;
    $yearlyPrice = 99.99;
    $lifetimePrice = 299.99;
    
    $monthlyRevenue = $stats['monthly_subscribers'] * $monthlyPrice;
    $yearlyRevenue = $stats['yearly_subscribers'] * $yearlyPrice;
    $lifetimeRevenue = $stats['lifetime_subscribers'] * $lifetimePrice;
    $totalRevenue = $monthlyRevenue + $yearlyRevenue + $lifetimeRevenue;

    // Get recent subscribers
    $recentSubscribers = $pdo->query("
        SELECT username, email, subscription_status, subscription_expires, created_at
        FROM users 
        WHERE subscription_status != 'none'
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll();

    // Get subscription distribution data for chart
    $subscriptionData = [
        'free' => $stats['free_users'],
        'monthly' => $stats['monthly_subscribers'],
        'yearly' => $stats['yearly_subscribers'],
        'lifetime' => $stats['lifetime_subscribers']
    ];

    // Get users with expiring subscriptions
    $expiringSubscriptions = $pdo->query("
        SELECT username, email, subscription_status, subscription_expires
        FROM users 
        WHERE subscription_expires IS NOT NULL 
        AND subscription_expires BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        AND subscription_status != 'lifetime'
        ORDER BY subscription_expires ASC
        LIMIT 10
    ")->fetchAll();

    // Get all subscribers for management
    $page = max(1, (int)($_GET['page'] ?? 1));
    $usersPerPage = 15;
    $offset = ($page - 1) * $usersPerPage;
    $filter = $_GET['filter'] ?? 'all';

    $whereClause = $filter === 'all' ? "subscription_status != 'none'" : "subscription_status = '$filter'";
    
    $allSubscribers = $pdo->query("
        SELECT id, username, email, subscription_status, subscription_expires, created_at
        FROM users 
        WHERE $whereClause
        ORDER BY created_at DESC 
        LIMIT $usersPerPage OFFSET $offset
    ")->fetchAll();

    $totalSubscribers = $pdo->query("SELECT COUNT(*) FROM users WHERE $whereClause")->fetchColumn();
    $totalPages = ceil($totalSubscribers / $usersPerPage);

} catch (Exception $e) {
    error_log("Subscription management error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
    $stats = array_fill_keys(['total_subscribers', 'monthly_subscribers', 'yearly_subscribers', 'lifetime_subscribers', 'free_users', 'expiring_soon'], 0);
    $recentSubscribers = [];
    $expiringSubscriptions = [];
    $allSubscribers = [];
    $totalRevenue = 0;
}

function timeUntilExpiry($expiryDate) {
    if (!$expiryDate) return 'Never';
    
    $now = new DateTime();
    $expiry = new DateTime($expiryDate);
    $diff = $now->diff($expiry);
    
    if ($expiry < $now) {
        return 'Expired';
    } elseif ($diff->days == 0) {
        return 'Today';
    } elseif ($diff->days <= 7) {
        return $diff->days . ' days';
    } else {
        return $expiry->format('M j, Y');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions Management - Chloe Belle Admin</title>
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

        /* Sidebar Styles - Same as other pages */
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }

        .stat-card.total::before { background: linear-gradient(135deg, var(--info-color), #1d4ed8); }
        .stat-card.monthly::before { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-card.yearly::before { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-card.lifetime::before { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .stat-card.revenue::before { background: linear-gradient(135deg, var(--danger-color), #dc2626); }
        .stat-card.expiring::before { background: linear-gradient(135deg, #ff6b6b, #ff5252); }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }

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
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, var(--info-color), #1d4ed8); }
        .stat-icon.monthly { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-icon.yearly { background: linear-gradient(135deg, var(--success-color), #059669); }
        .stat-icon.lifetime { background: linear-gradient(135deg, var(--warning-color), #d97706); }
        .stat-icon.revenue { background: linear-gradient(135deg, var(--danger-color), #dc2626); }
        .stat-icon.expiring { background: linear-gradient(135deg, #ff6b6b, #ff5252); }

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
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        /* Chart Container */
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        /* Distribution Chart */
        .distribution-chart {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: center;
        }

        .chart-visual {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }

        .chart-legend {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .legend-color.free { background: #6b7280; }
        .legend-color.monthly { background: var(--primary-color); }
        .legend-color.yearly { background: var(--success-color); }
        .legend-color.lifetime { background: var(--warning-color); }

        .legend-label {
            flex: 1;
            font-weight: 500;
            color: var(--dark-color);
        }

        .legend-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Revenue Overview */
        .revenue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .revenue-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .revenue-amount {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .revenue-amount.monthly { color: var(--primary-color); }
        .revenue-amount.yearly { color: var(--success-color); }
        .revenue-amount.lifetime { color: var(--warning-color); }
        .revenue-amount.total { color: var(--danger-color); }

        .revenue-label {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Data Table */
        .data-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-body {
            padding: 0;
        }

        .table-responsive {
            border-radius: 0;
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

        /* Subscription Badges */
        .subscription-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .subscription-monthly {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .subscription-yearly {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .subscription-lifetime {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .subscription-none {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        /* Status Indicators */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-active { background: var(--success-color); }
        .status-expiring { background: var(--warning-color); }
        .status-expired { background: var(--danger-color); }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background: white;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: transparent;
        }

        .action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            color: white;
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

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .distribution-chart {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .chart-visual {
                width: 150px;
                height: 150px;
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

        .stagger-animation > * {
            animation: fadeInUp 0.5s ease-out both;
        }

        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
        .stagger-animation > *:nth-child(5) { animation-delay: 0.5s; }
        .stagger-animation > *:nth-child(6) { animation-delay: 0.6s; }
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
                <a href="users.php" class="nav-link">
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
                <a href="subscriptions.php" class="nav-link active">
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
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title">Subscription Management</h1>
                    <p class="page-subtitle">Monitor subscriptions, revenue, and manage user access</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="bulk_expire_subscriptions">
                        <button type="submit" class="action-btn" onclick="return confirm('Process expired subscriptions?')">
                            <i class="fas fa-clock"></i>
                            Process Expired
                        </button>
                    </form>
                    <a href="settings.php" class="action-btn primary">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
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

        <!-- Statistics Grid -->
        <div class="stats-grid stagger-animation fade-in-up">
            <div class="stat-card total">
                <div class="stat-header">
                    <div class="stat-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_subscribers']) ?></div>
                <div class="stat-label">Total Subscribers</div>
                <div class="stat-change">
                    <i class="fas fa-arrow-up"></i>
                    Active subscribers
                </div>
            </div>

            <div class="stat-card monthly">
                <div class="stat-header">
                    <div class="stat-icon monthly">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['monthly_subscribers']) ?></div>
                <div class="stat-label">Monthly Plans</div>
                <div class="stat-change">
                    <i class="fas fa-pound-sign"></i>
                    £<?= number_format($monthlyRevenue, 2) ?> revenue
                </div>
            </div>

            <div class="stat-card yearly">
                <div class="stat-header">
                    <div class="stat-icon yearly">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['yearly_subscribers']) ?></div>
                <div class="stat-label">Yearly Plans</div>
                <div class="stat-change">
                    <i class="fas fa-pound-sign"></i>
                    £<?= number_format($yearlyRevenue, 2) ?> revenue
                </div>
            </div>

            <div class="stat-card lifetime">
                <div class="stat-header">
                    <div class="stat-icon lifetime">
                        <i class="fas fa-infinity"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['lifetime_subscribers']) ?></div>
                <div class="stat-label">Lifetime Plans</div>
                <div class="stat-change">
                    <i class="fas fa-pound-sign"></i>
                    £<?= number_format($lifetimeRevenue, 2) ?> revenue
                </div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-icon revenue">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-value">£<?= number_format($totalRevenue, 0) ?></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-change">
                    <i class="fas fa-calculator"></i>
                    Estimated value
                </div>
            </div>

            <div class="stat-card expiring">
                <div class="stat-header">
                    <div class="stat-icon expiring">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['expiring_soon']) ?></div>
                <div class="stat-label">Expiring Soon</div>
                <div class="stat-change">
                    <i class="fas fa-clock"></i>
                    Next 7 days
                </div>
            </div>
        </div>

        <!-- Charts and Analytics -->
        <div class="row">
            <!-- Subscription Distribution -->
            <div class="col-lg-6">
                <div class="chart-container fade-in-up">
                    <div class="chart-header">
                        <i class="fas fa-chart-pie"></i>
                        <h3 class="chart-title">Subscription Distribution</h3>
                    </div>
                    <div class="distribution-chart">
                        <div class="chart-visual">
                            <!-- Simple visual representation -->
                            <div style="display: flex; flex-direction: column; gap: 0.5rem; width: 100%;">
                                <div style="background: #6b7280; height: 30px; border-radius: 8px; width: <?= $stats['free_users'] > 0 ? ($stats['free_users'] / ($stats['total_subscribers'] + $stats['free_users'])) * 100 : 0 ?>%; min-width: 20px;"></div>
                                <div style="background: var(--primary-color); height: 30px; border-radius: 8px; width: <?= $stats['total_subscribers'] > 0 ? ($stats['monthly_subscribers'] / ($stats['total_subscribers'] + $stats['free_users'])) * 100 : 0 ?>%; min-width: 20px;"></div>
                                <div style="background: var(--success-color); height: 30px; border-radius: 8px; width: <?= $stats['total_subscribers'] > 0 ? ($stats['yearly_subscribers'] / ($stats['total_subscribers'] + $stats['free_users'])) * 100 : 0 ?>%; min-width: 20px;"></div>
                                <div style="background: var(--warning-color); height: 30px; border-radius: 8px; width: <?= $stats['total_subscribers'] > 0 ? ($stats['lifetime_subscribers'] / ($stats['total_subscribers'] + $stats['free_users'])) * 100 : 0 ?>%; min-width: 20px;"></div>
                            </div>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <div class="legend-color free"></div>
                                <span class="legend-label">Free Users</span>
                                <span class="legend-value"><?= number_format($stats['free_users']) ?></span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color monthly"></div>
                                <span class="legend-label">Monthly</span>
                                <span class="legend-value"><?= number_format($stats['monthly_subscribers']) ?></span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color yearly"></div>
                                <span class="legend-label">Yearly</span>
                                <span class="legend-value"><?= number_format($stats['yearly_subscribers']) ?></span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color lifetime"></div>
                                <span class="legend-label">Lifetime</span>
                                <span class="legend-value"><?= number_format($stats['lifetime_subscribers']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Breakdown -->
            <div class="col-lg-6">
                <div class="chart-container fade-in-up">
                    <div class="chart-header">
                        <i class="fas fa-pound-sign"></i>
                        <h3 class="chart-title">Revenue Breakdown</h3>
                    </div>
                    <div class="revenue-grid">
                        <div class="revenue-item">
                            <div class="revenue-amount monthly">£<?= number_format($monthlyRevenue, 2) ?></div>
                            <div class="revenue-label">Monthly Revenue</div>
                        </div>
                        <div class="revenue-item">
                            <div class="revenue-amount yearly">£<?= number_format($yearlyRevenue, 2) ?></div>
                            <div class="revenue-label">Yearly Revenue</div>
                        </div>
                        <div class="revenue-item">
                            <div class="revenue-amount lifetime">£<?= number_format($lifetimeRevenue, 2) ?></div>
                            <div class="revenue-label">Lifetime Revenue</div>
                        </div>
                        <div class="revenue-item">
                            <div class="revenue-amount total">£<?= number_format($totalRevenue, 2) ?></div>
                            <div class="revenue-label">Total Revenue</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Revenue calculations are estimates based on current subscription counts and pricing. Actual revenue may vary.
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Subscribers -->
        <div class="data-section fade-in-up">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-user-plus"></i>
                    Recent Subscribers
                </h2>
            </div>
            <div class="section-body">
                <?php if (empty($recentSubscribers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-crown"></i>
                        <h3>No subscribers yet</h3>
                        <p>New subscribers will appear here once users start subscribing to premium plans.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Subscriber</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                    <th>Subscribed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSubscribers as $subscriber): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($subscriber['username']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($subscriber['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="subscription-badge subscription-<?= $subscriber['subscription_status'] ?>">
                                            <i class="fas fa-<?= $subscriber['subscription_status'] === 'monthly' ? 'calendar-alt' : ($subscriber['subscription_status'] === 'yearly' ? 'calendar' : 'infinity') ?>"></i>
                                            <?= ucfirst($subscriber['subscription_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-indicator status-active"></span>
                                        Active
                                    </td>
                                    <td>
                                        <?php if ($subscriber['subscription_expires']): ?>
                                            <small><?= timeUntilExpiry($subscriber['subscription_expires']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y', strtotime($subscriber['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <button class="action-btn" onclick="editSubscription('<?= $subscriber['username'] ?>')">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expiring Subscriptions -->
        <?php if (!empty($expiringSubscriptions)): ?>
        <div class="data-section fade-in-up">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Expiring Subscriptions
                </h2>
            </div>
            <div class="section-body">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Current Plan</th>
                                <th>Expires</th>
                                <th>Days Left</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiringSubscriptions as $expiring): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($expiring['username']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($expiring['email']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="subscription-badge subscription-<?= $expiring['subscription_status'] ?>">
                                        <?= ucfirst($expiring['subscription_status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($expiring['subscription_expires'])) ?></td>
                                <td>
                                    <?php 
                                    $daysLeft = ceil((strtotime($expiring['subscription_expires']) - time()) / (60 * 60 * 24));
                                    $statusClass = $daysLeft <= 3 ? 'status-expired' : ($daysLeft <= 7 ? 'status-expiring' : 'status-active');
                                    ?>
                                    <span class="status-indicator <?= $statusClass ?>"></span>
                                    <?= $daysLeft ?> days
                                </td>
                                <td>
                                    <button class="action-btn" onclick="sendRenewalEmail('<?= htmlspecialchars($expiring['email']) ?>')">
                                        <i class="fas fa-envelope"></i>
                                        Send Reminder
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Subscribers Management -->
        <div class="filters-container fade-in-up">
            <div class="d-flex justify-content-between align-items-center">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                        All Subscribers
                    </a>
                    <a href="?filter=monthly" class="filter-tab <?= $filter === 'monthly' ? 'active' : '' ?>">
                        Monthly
                    </a>
                    <a href="?filter=yearly" class="filter-tab <?= $filter === 'yearly' ? 'active' : '' ?>">
                        Yearly
                    </a>
                    <a href="?filter=lifetime" class="filter-tab <?= $filter === 'lifetime' ? 'active' : '' ?>">
                        Lifetime
                    </a>
                </div>
                <span class="text-muted">
                    <?= count($allSubscribers) ?> of <?= $totalSubscribers ?> subscribers
                </span>
            </div>
        </div>

        <div class="data-section fade-in-up">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-users-cog"></i>
                    Manage Subscribers
                </h2>
            </div>
            <div class="section-body">
                <?php if (empty($allSubscribers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-filter"></i>
                        <h3>No subscribers found</h3>
                        <p>No subscribers match the selected filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Plan</th>
                                    <th>Expires</th>
                                    <th>Member Since</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allSubscribers as $subscriber): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($subscriber['username']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($subscriber['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="subscription-badge subscription-<?= $subscriber['subscription_status'] ?>">
                                            <i class="fas fa-<?= $subscriber['subscription_status'] === 'monthly' ? 'calendar-alt' : ($subscriber['subscription_status'] === 'yearly' ? 'calendar' : 'infinity') ?>"></i>
                                            <?= ucfirst($subscriber['subscription_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($subscriber['subscription_expires']): ?>
                                            <?= timeUntilExpiry($subscriber['subscription_expires']) ?>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y', strtotime($subscriber['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                       data-bs-target="#editSubscriptionModal" 
                                                       data-user-id="<?= $subscriber['id'] ?>"
                                                       data-username="<?= htmlspecialchars($subscriber['username']) ?>"
                                                       data-current-status="<?= $subscriber['subscription_status'] ?>"
                                                       data-expires="<?= $subscriber['subscription_expires'] ?>">
                                                        <i class="fas fa-edit me-2"></i>Edit Subscription
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="users.php?search=<?= urlencode($subscriber['username']) ?>">
                                                        <i class="fas fa-user me-2"></i>View User Profile
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Subscribers pagination" class="pagination-container fade-in-up">
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
    </main>

    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Edit Subscription Modal -->
    <div class="modal fade" id="editSubscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user_subscription">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <p>Editing subscription for: <strong id="editUsername"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Subscription Status</label>
                            <select class="form-select" name="subscription_status" id="editSubscriptionStatus" required>
                                <option value="none">No Subscription</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="lifetime">Lifetime</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="expiresField">
                            <label class="form-label">Expires At (Optional)</label>
                            <input type="datetime-local" class="form-control" name="subscription_expires" id="editSubscriptionExpires">
                            <div class="form-text">Leave empty for lifetime subscriptions or to remove expiry.</div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <small><strong>Warning:</strong> Changing subscription status affects user access to premium content.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Subscription</button>
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

        // Edit subscription modal handler
        document.getElementById('editSubscriptionModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const currentStatus = button.getAttribute('data-current-status');
            const expires = button.getAttribute('data-expires');
            
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').textContent = username;
            document.getElementById('editSubscriptionStatus').value = currentStatus;
            
            if (expires && expires !== 'null') {
                // Convert MySQL datetime to datetime-local format
                const date = new Date(expires);
                const localDateTime = new Date(date.getTime() - (date.getTimezoneOffset() * 60000)).toISOString().slice(0, -1);
                document.getElementById('editSubscriptionExpires').value = localDateTime;
            } else {
                document.getElementById('editSubscriptionExpires').value = '';
            }
            
            // Show/hide expires field based on subscription type
            toggleExpiresField(currentStatus);
        });

        // Toggle expires field visibility
        document.getElementById('editSubscriptionStatus').addEventListener('change', function() {
            toggleExpiresField(this.value);
        });

        function toggleExpiresField(status) {
            const expiresField = document.getElementById('expiresField');
            if (status === 'lifetime' || status === 'none') {
                expiresField.style.display = 'none';
            } else {
                expiresField.style.display = 'block';
            }
        }

        // Utility functions
        function editSubscription(username) {
            // Find the user in the table and trigger the modal
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const usernameCell = row.querySelector('strong');
                if (usernameCell && usernameCell.textContent === username) {
                    const editButton = row.querySelector('[data-bs-target="#editSubscriptionModal"]');
                    if (editButton) {
                        editButton.click();
                    }
                }
            });
        }

        function sendRenewalEmail(email) {
            if (confirm(`Send renewal reminder to ${email}?`)) {
                // In a real implementation, this would trigger an email send
                alert('Renewal reminder email would be sent. (Feature not yet implemented)');
            }
        }

        console.log('🎉 Enhanced Subscription Management loaded!');
        console.log('💳 Total subscribers:', <?= $stats['total_subscribers'] ?>);
        console.log('💰 Estimated revenue: £<?= number_format($totalRevenue, 2) ?>');
        console.log('⚠️ Expiring soon:', <?= $stats['expiring_soon'] ?>);
    </script>
</body>
</html>