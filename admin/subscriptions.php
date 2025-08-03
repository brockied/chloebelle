<?php
/**
 * Subscription Management Page for Chloe Belle Admin
 */

session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
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

    // Get recent subscribers
    $recentSubscribers = $pdo->query("
        SELECT username, email, subscription_status, subscription_expires, created_at
        FROM users 
        WHERE subscription_status != 'none'
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll();

    // Get revenue data (placeholder since we don't have payments table populated yet)
    $monthlyRevenue = 0;
    $totalRevenue = 0;

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions - Chloe Belle Admin</title>
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
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
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
                <a class="nav-link" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
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
            <li class="nav-item">
                <a class="nav-link active" href="subscriptions.php">
                    <i class="fas fa-credit-card me-2"></i>Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
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
                <h1>Subscription Management</h1>
                <p class="text-muted">Monitor subscriptions and revenue</p>
            </div>
            <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Subscription Statistics -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                    <div class="stat-number text-primary"><?= $stats['total_subscribers'] ?? 0 ?></div>
                    <div class="text-muted">Total Subscribers</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                    <div class="stat-number text-info"><?= $stats['monthly_subscribers'] ?? 0 ?></div>
                    <div class="text-muted">Monthly</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                    <div class="stat-number text-success"><?= $stats['yearly_subscribers'] ?? 0 ?></div>
                    <div class="text-muted">Yearly</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <i class="fas fa-infinity fa-2x text-warning mb-2"></i>
                    <div class="stat-number text-warning"><?= $stats['lifetime_subscribers'] ?? 0 ?></div>
                    <div class="text-muted">Lifetime</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Revenue Overview -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Revenue Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>This Month</span>
                                    <h4 class="text-success mb-0">£<?= number_format($monthlyRevenue, 2) ?></h4>
                                </div>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 65%"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span>Total Revenue</span>
                                    <h4 class="text-primary mb-0">£<?= number_format($totalRevenue, 2) ?></h4>
                                </div>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 80%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Payment integration is not yet configured. Revenue data will appear here once payments are processed.
                        </div>
                        
                        <div class="text-center">
                            <a href="settings.php" class="btn btn-primary">
                                <i class="fas fa-credit-card me-2"></i>Configure Payment Gateway
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription Distribution -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>User Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Free Users</span>
                                <strong><?= $stats['free_users'] ?? 0 ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= $stats['free_users'] > 0 ? ($stats['free_users'] / (($stats['total_subscribers'] ?? 0) + ($stats['free_users'] ?? 0))) * 100 : 0 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Monthly</span>
                                <strong><?= $stats['monthly_subscribers'] ?? 0 ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= ($stats['total_subscribers'] ?? 0) > 0 ? (($stats['monthly_subscribers'] ?? 0) / ($stats['total_subscribers'] ?? 1)) * 100 : 0 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Yearly</span>
                                <strong><?= $stats['yearly_subscribers'] ?? 0 ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($stats['total_subscribers'] ?? 0) > 0 ? (($stats['yearly_subscribers'] ?? 0) / ($stats['total_subscribers'] ?? 1)) * 100 : 0 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Lifetime</span>
                                <strong><?= $stats['lifetime_subscribers'] ?? 0 ?></strong>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= ($stats['total_subscribers'] ?? 0) > 0 ? (($stats['lifetime_subscribers'] ?? 0) / ($stats['total_subscribers'] ?? 1)) * 100 : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Subscribers -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Recent Subscribers</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentSubscribers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No subscribers yet</h5>
                        <p class="text-muted">New subscribers will appear here once users start subscribing.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subscriber</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                    <th>Subscribed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSubscribers as $subscriber): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($subscriber['username']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($subscriber['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $subscriber['subscription_status'] === 'monthly' ? 'info' : ($subscriber['subscription_status'] === 'yearly' ? 'success' : 'warning') ?>">
                                            <?= ucfirst($subscriber['subscription_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                    </td>
                                    <td>
                                        <?php if ($subscriber['subscription_expires']): ?>
                                            <small><?= date('M j, Y', strtotime($subscriber['subscription_expires'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y', strtotime($subscriber['created_at'])) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subscription Plans Overview -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-crown me-2"></i>Current Subscription Plans</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white text-center">
                                        <h6 class="mb-0">Monthly Plan</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <h3 class="text-info">£9.99 <small>/month</small></h3>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>All premium content</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Monthly updates</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Community access</li>
                                        </ul>
                                        <div class="mt-3">
                                            <strong><?= $stats['monthly_subscribers'] ?? 0 ?></strong> subscribers
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white text-center">
                                        <h6 class="mb-0">Yearly Plan</h6>
                                        <small>Most Popular</small>
                                    </div>
                                    <div class="card-body text-center">
                                        <h3 class="text-success">£99.99 <small>/year</small></h3>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>All premium content</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Early access</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Exclusive content</li>
                                            <li><i class="fas fa-check text-success me-2"></i>20% savings</li>
                                        </ul>
                                        <div class="mt-3">
                                            <strong><?= $stats['yearly_subscribers'] ?? 0 ?></strong> subscribers
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark text-center">
                                        <h6 class="mb-0">Lifetime Plan</h6>
                                        <small>Best Value</small>
                                    </div>
                                    <div class="card-body text-center">
                                        <h3 class="text-warning">£299.99 <small>once</small></h3>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>All premium content</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Lifetime access</li>
                                            <li><i class="fas fa-check text-success me-2"></i>VIP support</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Special perks</li>
                                        </ul>
                                        <div class="mt-3">
                                            <strong><?= $stats['lifetime_subscribers'] ?? 0 ?></strong> subscribers
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="settings.php" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-2"></i>Edit Subscription Plans
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        console.log('💳 Subscription Management loaded');
        console.log('👥 Total subscribers:', <?= $stats['total_subscribers'] ?? 0 ?>);
        console.log('📊 Monthly:', <?= $stats['monthly_subscribers'] ?? 0 ?>, 'Yearly:', <?= $stats['yearly_subscribers'] ?? 0 ?>, 'Lifetime:', <?= $stats['lifetime_subscribers'] ?? 0 ?>);
    </script>
</body>
</html>