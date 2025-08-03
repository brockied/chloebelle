<?php
/**
 * User Management Page for Chloe Belle Admin - FIXED VERSION
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

// Get users with pagination - FIXED VERSION
$page = max(1, (int)($_GET['page'] ?? 1));
$usersPerPage = 20;
$offset = ($page - 1) * $usersPerPage;
$search = $_GET['search'] ?? '';

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

    // Build search query
    $searchQuery = '';
    $searchParams = [];
    if ($search) {
        $searchQuery = "WHERE username LIKE ? OR email LIKE ?";
        $searchParams = ["%$search%", "%$search%"];
    }

    // Get total count
    $countSql = "SELECT COUNT(*) FROM users $searchQuery";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($searchParams);
    $totalUsers = $countStmt->fetchColumn();

    // Get users - FIXED: Use proper parameter binding for LIMIT and OFFSET
    $sql = "
        SELECT id, username, email, role, status, subscription_status, 
               subscription_expires, last_login, created_at
        FROM users 
        $searchQuery
        ORDER BY created_at DESC 
        LIMIT $usersPerPage OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($searchParams);
    $users = $stmt->fetchAll();

    $totalPages = ceil($totalUsers / $usersPerPage);

} catch (Exception $e) {
    error_log("Admin users query error: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
    $users = [];
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Chloe Belle Admin</title>
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
        
        .user-row:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            font-size: 0.8rem;
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
                <a class="nav-link active" href="users.php">
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
                <a class="nav-link" href="subscriptions.php">
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
                <h1>User Management</h1>
                <p class="text-muted">Manage users, subscriptions, and permissions</p>
            </div>
            <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
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

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search users by username or email..." 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Users (<?= $totalUsers ?> total)</h5>
                <div>
                    <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No users found</h5>
                        <p class="text-muted">No users match your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
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
                                <tr class="user-row">
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            <?php if ($user['role'] === 'chloe'): ?>
                                                <i class="fas fa-star text-warning ms-1" title="Chloe Belle"></i>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'chloe' ? 'primary' : 'secondary') ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge status-badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['subscription_status'] === 'none'): ?>
                                            <span class="badge bg-light text-dark">Free</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary"><?= ucfirst($user['subscription_status']) ?></span>
                                            <?php if ($user['subscription_expires']): ?>
                                                <br><small class="text-muted">Expires: <?= date('M j, Y', strtotime($user['subscription_expires'])) ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <small><?= date('M j, Y g:i A', strtotime($user['last_login'])) ?></small>
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
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Users pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

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
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
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

        console.log('👥 User Management loaded (FIXED VERSION)');
        console.log('📊 Total users: <?= $totalUsers ?>');
    </script>
</body>
</html>