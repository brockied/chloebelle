<?php
/**
 * Role Management Page for Chloe Belle Admin
 * Create and manage user roles with subscription-based assignments
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

    // Create roles table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_roles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `role_name` varchar(50) NOT NULL UNIQUE,
            `role_display_name` varchar(100) NOT NULL,
            `role_description` text DEFAULT NULL,
            `permissions` json DEFAULT NULL,
            `subscription_level` enum('none','monthly','yearly','lifetime') NOT NULL DEFAULT 'none',
            `auto_assign_on_subscription` tinyint(1) NOT NULL DEFAULT 0,
            `role_color` varchar(7) DEFAULT '#6c5ce7',
            `role_icon` varchar(50) DEFAULT 'fas fa-user',
            `is_system_role` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_role_name` (`role_name`),
            KEY `idx_subscription_level` (`subscription_level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Insert default system roles if they don't exist
    $systemRoles = [
        [
            'role_name' => 'user',
            'role_display_name' => 'Regular User',
            'role_description' => 'Standard user with basic access',
            'permissions' => json_encode(['view_free_content', 'comment', 'like']),
            'subscription_level' => 'none',
            'auto_assign_on_subscription' => 0,
            'role_color' => '#6c757d',
            'role_icon' => 'fas fa-user',
            'is_system_role' => 1
        ],
        [
            'role_name' => 'subscriber',
            'role_display_name' => 'Premium Subscriber',
            'role_description' => 'Subscriber with access to premium content',
            'permissions' => json_encode(['view_free_content', 'view_premium_content', 'comment', 'like', 'early_access']),
            'subscription_level' => 'monthly',
            'auto_assign_on_subscription' => 1,
            'role_color' => '#28a745',
            'role_icon' => 'fas fa-crown',
            'is_system_role' => 1
        ],
        [
            'role_name' => 'vip',
            'role_display_name' => 'VIP Member',
            'role_description' => 'VIP member with exclusive privileges',
            'permissions' => json_encode(['view_free_content', 'view_premium_content', 'view_vip_content', 'comment', 'like', 'early_access', 'exclusive_chat']),
            'subscription_level' => 'yearly',
            'auto_assign_on_subscription' => 1,
            'role_color' => '#ffc107',
            'role_icon' => 'fas fa-gem',
            'is_system_role' => 1
        ],
        [
            'role_name' => 'lifetime',
            'role_display_name' => 'Lifetime Member',
            'role_description' => 'Lifetime access with all privileges',
            'permissions' => json_encode(['view_free_content', 'view_premium_content', 'view_vip_content', 'view_lifetime_content', 'comment', 'like', 'early_access', 'exclusive_chat', 'priority_support']),
            'subscription_level' => 'lifetime',
            'auto_assign_on_subscription' => 1,
            'role_color' => '#dc3545',
            'role_icon' => 'fas fa-infinity',
            'is_system_role' => 1
        ],
        [
            'role_name' => 'moderator',
            'role_display_name' => 'Moderator',
            'role_description' => 'Community moderator with management privileges',
            'permissions' => json_encode(['view_free_content', 'view_premium_content', 'comment', 'like', 'moderate_comments', 'moderate_posts', 'manage_users']),
            'subscription_level' => 'none',
            'auto_assign_on_subscription' => 0,
            'role_color' => '#17a2b8',
            'role_icon' => 'fas fa-shield-alt',
            'is_system_role' => 1
        ],
        [
            'role_name' => 'chloe',
            'role_display_name' => 'Chloe Belle',
            'role_description' => 'Content creator and site owner',
            'permissions' => json_encode(['all_permissions']),
            'subscription_level' => 'none',
            'auto_assign_on_subscription' => 0,
            'role_color' => '#fd79a8',
            'role_icon' => 'fas fa-star',
            'is_system_role' => 1
        ],
        [
            'role_name' => 'admin',
            'role_display_name' => 'Administrator',
            'role_description' => 'Full system administrator',
            'permissions' => json_encode(['all_permissions']),
            'subscription_level' => 'none',
            'auto_assign_on_subscription' => 0,
            'role_color' => '#dc3545',
            'role_icon' => 'fas fa-user-shield',
            'is_system_role' => 1
        ]
    ];

    foreach ($systemRoles as $role) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_roles 
            (role_name, role_display_name, role_description, permissions, subscription_level, auto_assign_on_subscription, role_color, role_icon, is_system_role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $role['role_name'],
            $role['role_display_name'],
            $role['role_description'],
            $role['permissions'],
            $role['subscription_level'],
            $role['auto_assign_on_subscription'],
            $role['role_color'],
            $role['role_icon'],
            $role['is_system_role']
        ]);
    }

} catch (Exception $e) {
    error_log("Role management database error: " . $e->getMessage());
    $message = "Database error: " . $e->getMessage();
    $messageType = 'danger';
}

// Handle role actions
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_role':
                $roleName = strtolower(trim($_POST['role_name'] ?? ''));
                $displayName = trim($_POST['role_display_name'] ?? '');
                $description = trim($_POST['role_description'] ?? '');
                $subscriptionLevel = $_POST['subscription_level'] ?? 'none';
                $autoAssign = isset($_POST['auto_assign_on_subscription']) ? 1 : 0;
                $roleColor = $_POST['role_color'] ?? '#6c5ce7';
                $roleIcon = $_POST['role_icon'] ?? 'fas fa-user';
                $permissions = $_POST['permissions'] ?? [];
                
                // Validate role name
                if (empty($roleName) || !preg_match('/^[a-z_]{3,20}$/', $roleName)) {
                    throw new Exception('Role name must be 3-20 characters, lowercase letters and underscores only');
                }
                
                if (empty($displayName)) {
                    throw new Exception('Display name is required');
                }
                
                // Check if role already exists
                $stmt = $pdo->prepare("SELECT id FROM user_roles WHERE role_name = ?");
                $stmt->execute([$roleName]);
                if ($stmt->fetch()) {
                    throw new Exception('Role name already exists');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_roles 
                    (role_name, role_display_name, role_description, permissions, subscription_level, auto_assign_on_subscription, role_color, role_icon, is_system_role)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $roleName,
                    $displayName,
                    $description,
                    json_encode($permissions),
                    $subscriptionLevel,
                    $autoAssign,
                    $roleColor,
                    $roleIcon
                ]);
                
                $message = "Role '$displayName' created successfully!";
                $messageType = 'success';
                break;
                
            case 'update_role':
                $roleId = (int)$_POST['role_id'];
                $displayName = trim($_POST['role_display_name'] ?? '');
                $description = trim($_POST['role_description'] ?? '');
                $subscriptionLevel = $_POST['subscription_level'] ?? 'none';
                $autoAssign = isset($_POST['auto_assign_on_subscription']) ? 1 : 0;
                $roleColor = $_POST['role_color'] ?? '#6c5ce7';
                $roleIcon = $_POST['role_icon'] ?? 'fas fa-user';
                $permissions = $_POST['permissions'] ?? [];
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($displayName)) {
                    throw new Exception('Display name is required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE user_roles 
                    SET role_display_name = ?, role_description = ?, permissions = ?, subscription_level = ?, 
                        auto_assign_on_subscription = ?, role_color = ?, role_icon = ?, is_active = ?
                    WHERE id = ? AND is_system_role = 0
                ");
                $stmt->execute([
                    $displayName,
                    $description,
                    json_encode($permissions),
                    $subscriptionLevel,
                    $autoAssign,
                    $roleColor,
                    $roleIcon,
                    $isActive,
                    $roleId
                ]);
                
                $message = "Role updated successfully!";
                $messageType = 'success';
                break;
                
            case 'delete_role':
                $roleId = (int)$_POST['role_id'];
                
                // Check if role is in use
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = (SELECT role_name FROM user_roles WHERE id = ?)");
                $stmt->execute([$roleId]);
                $usersCount = $stmt->fetchColumn();
                
                if ($usersCount > 0) {
                    throw new Exception("Cannot delete role: $usersCount users are assigned to this role");
                }
                
                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE id = ? AND is_system_role = 0");
                $stmt->execute([$roleId]);
                
                $message = "Role deleted successfully!";
                $messageType = 'success';
                break;
                
            case 'assign_user_role':
                $userId = (int)$_POST['user_id'];
                $newRole = $_POST['new_role'] ?? '';
                
                // Verify role exists
                $stmt = $pdo->prepare("SELECT role_name FROM user_roles WHERE role_name = ? AND is_active = 1");
                $stmt->execute([$newRole]);
                if (!$stmt->fetch()) {
                    throw new Exception('Invalid role selected');
                }
                
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);
                
                $message = "User role updated successfully!";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get all roles
try {
    $roles = $pdo->query("
        SELECT r.*, 
               (SELECT COUNT(*) FROM users WHERE role = r.role_name) as user_count
        FROM user_roles r 
        ORDER BY r.is_system_role DESC, r.role_name ASC
    ")->fetchAll();
    
    // Get recent users for role assignment
    $recentUsers = $pdo->query("
        SELECT id, username, email, role, subscription_status 
        FROM users 
        WHERE role != 'admin'
        ORDER BY created_at DESC 
        LIMIT 20
    ")->fetchAll();
    
} catch (Exception $e) {
    error_log("Role fetch error: " . $e->getMessage());
    $roles = [];
    $recentUsers = [];
}

// Available permissions
$availablePermissions = [
    'view_free_content' => 'View Free Content',
    'view_premium_content' => 'View Premium Content',
    'view_vip_content' => 'View VIP Content',
    'view_lifetime_content' => 'View Lifetime Content',
    'comment' => 'Post Comments',
    'like' => 'Like Posts',
    'early_access' => 'Early Access to Content',
    'exclusive_chat' => 'Exclusive Chat Access',
    'priority_support' => 'Priority Support',
    'moderate_comments' => 'Moderate Comments',
    'moderate_posts' => 'Moderate Posts',
    'manage_users' => 'Manage Users',
    'upload_media' => 'Upload Media',
    'create_posts' => 'Create Posts'
];

// Available icons
$availableIcons = [
    'fas fa-user' => 'User',
    'fas fa-crown' => 'Crown',
    'fas fa-gem' => 'Gem',
    'fas fa-star' => 'Star',
    'fas fa-infinity' => 'Infinity',
    'fas fa-shield-alt' => 'Shield',
    'fas fa-user-shield' => 'Admin Shield',
    'fas fa-heart' => 'Heart',
    'fas fa-fire' => 'Fire',
    'fas fa-trophy' => 'Trophy',
    'fas fa-medal' => 'Medal',
    'fas fa-diamond' => 'Diamond'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - Chloe Belle Admin</title>
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
        
        .role-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .role-card:hover {
            transform: translateY(-3px);
        }
        
        .role-header {
            padding: 1.5rem;
            color: white;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .role-icon {
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .system-role-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .custom-role-badge {
            background: rgba(255, 255, 255, 0.9);
            color: #333;
        }
        
        .permission-badge {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 0.125rem;
            display: inline-block;
        }
        
        .color-picker {
            width: 50px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .icon-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .icon-option {
            padding: 0.75rem;
            text-align: center;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .icon-option:hover,
        .icon-option.selected {
            border-color: var(--primary-color);
            background: rgba(108, 92, 231, 0.1);
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
                <a class="nav-link active" href="roles.php">
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
                <h1>Role Management</h1>
                <p class="text-muted">Create and manage user roles with subscription-based assignments</p>
            </div>
            <div>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                    <i class="fas fa-plus me-2"></i>Create Role
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

        <!-- Roles Grid -->
        <div class="row">
            <?php foreach ($roles as $role): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card role-card">
                        <div class="role-header" style="background-color: <?= htmlspecialchars($role['role_color']) ?>">
                            <div class="d-flex align-items-center">
                                <i class="<?= htmlspecialchars($role['role_icon']) ?> role-icon"></i>
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($role['role_display_name']) ?></h6>
                                    <small class="opacity-75"><?= htmlspecialchars($role['role_name']) ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="role-badge <?= $role['is_system_role'] ? 'system-role-badge' : 'custom-role-badge' ?>">
                                    <?= $role['is_system_role'] ? 'System' : 'Custom' ?>
                                </span>
                                <?php if (!$role['is_active']): ?>
                                    <div><small class="opacity-75">Inactive</small></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <p class="text-muted mb-3"><?= htmlspecialchars($role['role_description']) ?></p>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block mb-2">Subscription Level:</small>
                                <span class="badge bg-<?= $role['subscription_level'] === 'none' ? 'secondary' : 'primary' ?>">
                                    <?= ucfirst($role['subscription_level']) ?>
                                </span>
                                <?php if ($role['auto_assign_on_subscription']): ?>
                                    <span class="badge bg-success ms-1">Auto-Assign</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block mb-2">Permissions:</small>
                                <div>
                                    <?php 
                                    $permissions = json_decode($role['permissions'], true) ?: [];
                                    if (in_array('all_permissions', $permissions)): ?>
                                        <span class="permission-badge">All Permissions</span>
                                    <?php else: ?>
                                        <?php foreach (array_slice($permissions, 0, 3) as $permission): ?>
                                            <span class="permission-badge">
                                                <?= $availablePermissions[$permission] ?? $permission ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($permissions) > 3): ?>
                                            <span class="permission-badge">+<?= count($permissions) - 3 ?> more</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i><?= $role['user_count'] ?> users
                                </small>
                                
                                <div class="btn-group">
                                    <?php if (!$role['is_system_role']): ?>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editRole(<?= htmlspecialchars(json_encode($role)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['role_display_name']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- User Role Assignment Section -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-cog me-2"></i>Assign User Roles
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Current Role</th>
                                <th>Subscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $userRole = array_filter($roles, function($r) use ($user) { 
                                            return $r['role_name'] === $user['role']; 
                                        });
                                        $userRole = reset($userRole);
                                        ?>
                                        <?php if ($userRole): ?>
                                            <span class="badge" style="background-color: <?= $userRole['role_color'] ?>">
                                                <i class="<?= $userRole['role_icon'] ?> me-1"></i>
                                                <?= htmlspecialchars($userRole['role_display_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['subscription_status'] === 'none' ? 'light text-dark' : 'success' ?>">
                                            <?= ucfirst($user['subscription_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="assignUserRole(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= $user['role'] ?>')">
                                            <i class="fas fa-user-tag me-1"></i>Change Role
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div class="modal fade" id="createRoleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_role">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role_name" class="form-label">Role Name</label>
                                <input type="text" class="form-control" id="role_name" name="role_name" 
                                       placeholder="e.g., premium_user" pattern="[a-z_]{3,20}" required>
                                <small class="text-muted">Lowercase letters and underscores only, 3-20 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role_display_name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="role_display_name" name="role_display_name" 
                                       placeholder="e.g., Premium User" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role_description" class="form-label">Description</label>
                            <textarea class="form-control" id="role_description" name="role_description" rows="2" 
                                      placeholder="Brief description of this role"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subscription_level" class="form-label">Subscription Level</label>
                                <select class="form-select" id="subscription_level" name="subscription_level">
                                    <option value="none">No Subscription Required</option>
                                    <option value="monthly">Monthly Subscription</option>
                                    <option value="yearly">Yearly Subscription</option>
                                    <option value="lifetime">Lifetime Subscription</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role_color" class="form-label">Role Color</label>
                                <div class="d-flex align-items-center">
                                    <input type="color" class="color-picker me-2" id="role_color" name="role_color" value="#6c5ce7">
                                    <span class="text-muted">Used for badges and highlights</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_assign_on_subscription" name="auto_assign_on_subscription">
                                <label class="form-check-label" for="auto_assign_on_subscription">
                                    Auto-assign when user subscribes to this level
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role Icon</label>
                            <div class="icon-selector" id="iconSelector">
                                <?php foreach ($availableIcons as $iconClass => $iconName): ?>
                                    <div class="icon-option" data-icon="<?= $iconClass ?>" title="<?= $iconName ?>">
                                        <i class="<?= $iconClass ?>"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="role_icon" name="role_icon" value="fas fa-user">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="row">
                                <?php foreach ($availablePermissions as $permKey => $permName): ?>
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="perm_<?= $permKey ?>" 
                                                   name="permissions[]" value="<?= $permKey ?>">
                                            <label class="form-check-label" for="perm_<?= $permKey ?>">
                                                <?= $permName ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editRoleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="role_id" id="edit_role_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role Name</label>
                                <input type="text" class="form-control" id="edit_role_name" disabled>
                                <small class="text-muted">Role name cannot be changed</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_role_display_name" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="edit_role_display_name" name="role_display_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_role_description" name="role_description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_subscription_level" class="form-label">Subscription Level</label>
                                <select class="form-select" id="edit_subscription_level" name="subscription_level">
                                    <option value="none">No Subscription Required</option>
                                    <option value="monthly">Monthly Subscription</option>
                                    <option value="yearly">Yearly Subscription</option>
                                    <option value="lifetime">Lifetime Subscription</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_role_color" class="form-label">Role Color</label>
                                <input type="color" class="color-picker" id="edit_role_color" name="role_color">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="edit_auto_assign" name="auto_assign_on_subscription">
                                    <label class="form-check-label" for="edit_auto_assign">
                                        Auto-assign
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                    <label class="form-check-label" for="edit_is_active">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role Icon</label>
                            <div class="icon-selector" id="editIconSelector">
                                <?php foreach ($availableIcons as $iconClass => $iconName): ?>
                                    <div class="icon-option" data-icon="<?= $iconClass ?>" title="<?= $iconName ?>">
                                        <i class="<?= $iconClass ?>"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="edit_role_icon" name="role_icon">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="row" id="editPermissions">
                                <?php foreach ($availablePermissions as $permKey => $permName): ?>
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_perm_<?= $permKey ?>" 
                                                   name="permissions[]" value="<?= $permKey ?>">
                                            <label class="form-check-label" for="edit_perm_<?= $permKey ?>">
                                                <?= $permName ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign User Role Modal -->
    <div class="modal fade" id="assignUserRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_user_role">
                        <input type="hidden" name="user_id" id="assign_user_id">
                        
                        <p>Change role for: <strong id="assign_username"></strong></p>
                        
                        <div class="mb-3">
                            <label for="assign_new_role" class="form-label">New Role</label>
                            <select class="form-select" name="new_role" id="assign_new_role" required>
                                <?php foreach ($roles as $role): ?>
                                    <?php if ($role['is_active']): ?>
                                        <option value="<?= $role['role_name'] ?>" 
                                                data-color="<?= $role['role_color'] ?>"
                                                data-icon="<?= $role['role_icon'] ?>">
                                            <?= htmlspecialchars($role['role_display_name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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

        // Icon selector functionality
        function setupIconSelector(containerId, hiddenInputId) {
            const container = document.getElementById(containerId);
            const hiddenInput = document.getElementById(hiddenInputId);
            
            container.addEventListener('click', function(e) {
                const iconOption = e.target.closest('.icon-option');
                if (iconOption) {
                    // Remove previous selection
                    container.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selection to clicked option
                    iconOption.classList.add('selected');
                    
                    // Update hidden input
                    hiddenInput.value = iconOption.dataset.icon;
                }
            });
        }

        setupIconSelector('iconSelector', 'role_icon');
        setupIconSelector('editIconSelector', 'edit_role_icon');

        // Edit role function
        function editRole(role) {
            document.getElementById('edit_role_id').value = role.id;
            document.getElementById('edit_role_name').value = role.role_name;
            document.getElementById('edit_role_display_name').value = role.role_display_name;
            document.getElementById('edit_role_description').value = role.role_description || '';
            document.getElementById('edit_subscription_level').value = role.subscription_level;
            document.getElementById('edit_role_color').value = role.role_color;
            document.getElementById('edit_auto_assign').checked = role.auto_assign_on_subscription == 1;
            document.getElementById('edit_is_active').checked = role.is_active == 1;
            document.getElementById('edit_role_icon').value = role.role_icon;
            
            // Select the correct icon
            document.querySelectorAll('#editIconSelector .icon-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.dataset.icon === role.role_icon) {
                    opt.classList.add('selected');
                }
            });
            
            // Set permissions
            const permissions = JSON.parse(role.permissions || '[]');
            document.querySelectorAll('#editPermissions input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = permissions.includes(checkbox.value);
            });
            
            new bootstrap.Modal(document.getElementById('editRoleModal')).show();
        }

        // Delete role function
        function deleteRole(roleId, roleName) {
            if (confirm(`Are you sure you want to delete the role "${roleName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" name="role_id" value="${roleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Assign user role function
        function assignUserRole(userId, username, currentRole) {
            document.getElementById('assign_user_id').value = userId;
            document.getElementById('assign_username').textContent = username;
            document.getElementById('assign_new_role').value = currentRole;
            
            new bootstrap.Modal(document.getElementById('assignUserRoleModal')).show();
        }

        // Form validation
        document.getElementById('role_name').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z_]/g, '');
        });

        console.log('🛡️ Role Management loaded');
        console.log('📊 Total roles:', <?= count($roles) ?>);
    </script>
</body>
</html>