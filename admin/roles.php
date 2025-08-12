<?php
/**
 * Enhanced Role Management Page for Chloe Belle Admin
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
            'role_color' => '#6b7280',
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
            'role_color' => '#10b981',
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
            'role_color' => '#f59e0b',
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
            'role_color' => '#ef4444',
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
            'role_color' => '#3b82f6',
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
            'role_color' => '#8b5cf6',
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
            'role_color' => '#dc2626',
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
                $roleColor = $_POST['role_color'] ?? '#6366f1';
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
                $roleColor = $_POST['role_color'] ?? '#6366f1';
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
    <title>Roles Management - Chloe Belle Admin</title>
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

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Roles Grid */
        .roles-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .role-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .role-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .role-header {
            padding: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .role-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .role-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .role-details h6 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .role-details small {
            opacity: 0.8;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
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

        .role-body {
            padding: 1.5rem;
        }

        .role-description {
            color: #6b7280;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .role-meta {
            margin-bottom: 1rem;
        }

        .role-meta-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .role-meta-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
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

        .auto-assign-badge {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .permissions-section {
            margin-bottom: 1rem;
        }

        .permission-badge {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 0.125rem;
            display: inline-block;
        }

        .role-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-count {
            color: #6b7280;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .role-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .role-btn {
            padding: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background: white;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .role-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--primary-color);
        }

        .role-btn.danger:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        .role-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* User Assignment Section */
        .assignment-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .assignment-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .assignment-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .users-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-email {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .roles-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .roles-container,
            .assignment-section {
                padding: 1.5rem;
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

        /* Form Elements */
        .color-picker {
            width: 60px;
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
            background: #f8f9fa;
        }

        .icon-option {
            padding: 0.75rem;
            text-align: center;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }

        .icon-option:hover,
        .icon-option.selected {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
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
                <a href="roles.php" class="nav-link active">
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
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="page-title">Roles Management</h1>
                    <p class="page-subtitle">Create and manage user roles with subscription-based assignments</p>
                </div>
                <div class="header-actions">
                    <button class="action-btn primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                        <i class="fas fa-plus"></i>
                        Create Role
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

        <!-- Roles Grid -->
        <div class="roles-container fade-in-up">
            <div class="roles-grid stagger-animation">
                <?php foreach ($roles as $role): ?>
                    <div class="role-card">
                        <div class="role-header" style="background: linear-gradient(135deg, <?= htmlspecialchars($role['role_color']) ?>, <?= htmlspecialchars($role['role_color']) ?>dd)">
                            <div class="role-info">
                                <div class="role-icon">
                                    <i class="<?= htmlspecialchars($role['role_icon']) ?>"></i>
                                </div>
                                <div class="role-details">
                                    <h6><?= htmlspecialchars($role['role_display_name']) ?></h6>
                                    <small><?= htmlspecialchars($role['role_name']) ?></small>
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
                        
                        <div class="role-body">
                            <p class="role-description"><?= htmlspecialchars($role['role_description']) ?></p>
                            
                            <div class="role-meta">
                                <div class="role-meta-item">
                                    <span class="role-meta-label">Subscription Level:</span>
                                    <span class="subscription-badge <?= $role['subscription_level'] === 'none' ? 'subscription-none' : 'subscription-premium' ?>">
                                        <?= ucfirst($role['subscription_level']) ?>
                                    </span>
                                </div>
                                <?php if ($role['auto_assign_on_subscription']): ?>
                                    <div class="role-meta-item">
                                        <span class="role-meta-label">Auto-Assignment:</span>
                                        <span class="auto-assign-badge">Enabled</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="permissions-section">
                                <div class="role-meta-label mb-2">Permissions:</div>
                                <div>
                                    <?php 
                                    $permissions = json_decode($role['permissions'], true) ?: [];
                                    if (in_array('all_permissions', $permissions)): ?>
                                        <span class="permission-badge">All Permissions</span>
                                    <?php else: ?>
                                        <?php foreach (array_slice($permissions, 0, 4) as $permission): ?>
                                            <span class="permission-badge">
                                                <?= $availablePermissions[$permission] ?? $permission ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($permissions) > 4): ?>
                                            <span class="permission-badge">+<?= count($permissions) - 4 ?> more</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="role-actions">
                                <div class="user-count">
                                    <i class="fas fa-users"></i>
                                    <?= $role['user_count'] ?> users
                                </div>
                                
                                <div class="role-buttons">
                                    <?php if (!$role['is_system_role']): ?>
                                        <button class="role-btn" 
                                                onclick="editRole(<?= htmlspecialchars(json_encode($role)) ?>)"
                                                title="Edit Role">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="role-btn danger" 
                                                onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['role_display_name']) ?>')"
                                                title="Delete Role">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="role-btn" disabled title="System Role - Protected">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- User Role Assignment Section -->
        <div class="assignment-section fade-in-up">
            <div class="assignment-header">
                <i class="fas fa-user-cog"></i>
                <h2 class="assignment-title">Assign User Roles</h2>
            </div>
            
            <div class="users-table">
                <table class="table mb-0">
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
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $userRole = array_filter($roles, function($r) use ($user) { 
                                        return $r['role_name'] === $user['role']; 
                                    });
                                    $userRole = reset($userRole);
                                    ?>
                                    <?php if ($userRole): ?>
                                        <span class="subscription-badge subscription-premium" 
                                              style="background: <?= $userRole['role_color'] ?>">
                                            <i class="<?= $userRole['role_icon'] ?> me-1"></i>
                                            <?= htmlspecialchars($userRole['role_display_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="subscription-badge subscription-none">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="subscription-badge <?= $user['subscription_status'] === 'none' ? 'subscription-none' : 'subscription-premium' ?>">
                                        <?= ucfirst($user['subscription_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="role-btn" 
                                            onclick="assignUserRole(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= $user['role'] ?>')"
                                            title="Change Role">
                                        <i class="fas fa-user-tag me-1"></i>Change Role
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

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
                                    <input type="color" class="color-picker me-2" id="role_color" name="role_color" value="#6366f1">
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

        console.log('🎉 Enhanced Roles Management loaded!');
        console.log('🛡️ Total roles:', <?= count($roles) ?>);
        console.log('✨ Modern design with improved role cards');
    </script>
</body>
</html>