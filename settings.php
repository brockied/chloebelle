<?php
/**
 * Site Settings Configuration
 * This file was missing and causing fatal errors
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Access denied');
}

/**
 * Get site setting value
 */
function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
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
            
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Settings error: " . $e->getMessage());
        }
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Update site setting
 */
function updateSetting($key, $value) {
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
        
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log("Update setting error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if maintenance mode is enabled
 */
function isMaintenanceMode() {
    // Enhanced: if maintenance is ON, allow logged-in admins (and API for logged-in users)
    $on = getSetting('maintenance_mode', '0') === '1';
    if (!$on) return false;
    
    // Safe session start
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    
    // 1) Admins/moderators bypass
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','moderator'])) {
        return false;
    }
    
    // 2) Allow logged-in users to hit API endpoints during maintenance
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/api/') === 0 && isset($_SESSION['user_id'])) {
        return false;
    }
    
    return true;
}

/**
 * Get site name
 */
function getSiteName() {
    return getSetting('site_name', SITE_NAME);
}

/**
 * Get site description
 */
function getSiteDescription() {
    return getSetting('site_description', 'Chloe Belle Website');
}

/**
 * Check if user registration is enabled
 */
function isRegistrationEnabled() {
    return getSetting('enable_registration', '1') === '1';
}

/**
 * Get maximum file upload size
 */
function getMaxUploadSize() {
    return (int)getSetting('max_upload_size', '52428800'); // 50MB default
}

/**
 * Get timezone
 */
function getSiteTimezone() {
    return getSetting('timezone', 'UTC');
}

/**
 * Get currency
 */
function getSiteCurrency() {
    return getSetting('currency', DEFAULT_CURRENCY);
}
?>