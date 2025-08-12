<?php
/**
 * Maintenance Mode Checker
 * Include this file at the top of pages that should respect maintenance mode
 * Usage: require_once 'includes/maintenance_check.php';
 */

// Function to get setting from database
function getMaintenanceSetting($key, $default = '') {
    try {
        require_once __DIR__ . '/../config.php';
        
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Check if maintenance mode is enabled
$maintenanceMode = getMaintenanceSetting('maintenance_mode', '0');

if ($maintenanceMode === '1') {
    // Check if user is admin
    $isAdmin = false;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $isAdmin = true;
    }
    
    // If not admin, show maintenance page
    if (!$isAdmin) {
        $maintenanceMessage = getMaintenanceSetting('maintenance_message', 'Site is under maintenance. Please check back later.');
        
        // Prevent redirect loops for maintenance page itself
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage !== 'maintenance.php') {
            // Redirect to maintenance page or show inline message
            if (file_exists(__DIR__ . '/../maintenance.php')) {
                header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/maintenance.php');
                exit;
            } else {
                // Show inline maintenance message
                showMaintenancePage($maintenanceMessage);
                exit;
            }
        }
    }
}

function showMaintenancePage($message) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Site Under Maintenance</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .maintenance-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                padding: 3rem;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                margin: 2rem;
            }
            
            .maintenance-icon {
                font-size: 4rem;
                color: #667eea;
                margin-bottom: 1.5rem;
            }
            
            .maintenance-title {
                font-size: 2rem;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 1rem;
            }
            
            .maintenance-message {
                font-size: 1.1rem;
                color: #6b7280;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            
            .admin-link {
                color: #667eea;
                text-decoration: none;
                font-size: 0.9rem;
                opacity: 0.7;
                transition: opacity 0.3s ease;
            }
            
            .admin-link:hover {
                opacity: 1;
                color: #667eea;
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>
            <h1 class="maintenance-title">Site Under Maintenance</h1>
            <p class="maintenance-message"><?= htmlspecialchars($message) ?></p>
            <p class="mb-0">
                <a href="admin/index.php" class="admin-link">
                    <i class="fas fa-user-shield me-1"></i>Admin Access
                </a>
            </p>
        </div>
    </body>
    </html>
    <?php
}
?>