<?php
/**
 * Core Functions for Chloe Belle Website
 * Contains utility functions used throughout the application
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Direct access not permitted.');
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time();
    return $filename . '.' . strtolower($extension);
}

/**
 * Get file size in readable format
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Time ago helper
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 2592000) return floor($time / 86400) . 'd ago';
    if ($time < 31536000) return floor($time / 2592000) . 'mo ago';
    
    return floor($time / 31536000) . 'y ago';
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'GBP') {
    $symbols = [
        'GBP' => '£',
        'USD' => '
        ',
        'EUR' => '€'
    ];
    
    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . number_format($amount, 2);
}

/**
 * Get user's IP address
 */
function getUserIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent information
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Detect device type
 */
function getDeviceType() {
    $userAgent = getUserAgent();
    
    if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
        return 'mobile';
    } elseif (preg_match('/Tablet/', $userAgent)) {
        return 'tablet';
    }
    
    return 'desktop';
}

/**
 * Create thumbnail from image
 */
function createThumbnail($sourcePath, $destPath, $maxWidth = 300, $maxHeight = 300, $quality = 80) {
    if (!file_exists($sourcePath)) {
        return false;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceMime = $imageInfo['mime'];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    // Create source image
    switch ($sourceMime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($sourceMime == 'image/png' || $sourceMime == 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Save thumbnail
    $result = false;
    switch ($sourceMime) {
        case 'image/jpeg':
            $result = imagejpeg($thumbnail, $destPath, $quality);
            break;
        case 'image/png':
            $result = imagepng($thumbnail, $destPath);
            break;
        case 'image/gif':
            $result = imagegif($thumbnail, $destPath);
            break;
        case 'image/webp':
            $result = imagewebp($thumbnail, $destPath, $quality);
            break;
    }
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return $result;
}

/**
 * Add watermark to image
 */
function addWatermark($imagePath, $watermarkText = 'Chloe Belle', $opacity = 50) {
    if (!file_exists($imagePath)) {
        return false;
    }
    
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceMime = $imageInfo['mime'];
    
    // Create source image
    switch ($sourceMime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($imagePath);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Get image dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create watermark color with transparency
    $watermarkColor = imagecolorallocatealpha($image, 255, 255, 255, 127 - ($opacity * 1.27));
    
    // Set font size based on image size
    $fontSize = max(12, min($width, $height) / 20);
    
    // Calculate text position (bottom right)
    $textBox = imagettfbbox($fontSize, 0, __DIR__ . '/../assets/fonts/arial.ttf', $watermarkText);
    $textWidth = $textBox[4] - $textBox[0];
    $textHeight = $textBox[1] - $textBox[7];
    
    $x = $width - $textWidth - 20;
    $y = $height - 20;
    
    // Add watermark text
    if (file_exists(__DIR__ . '/../assets/fonts/arial.ttf')) {
        imagettftext($image, $fontSize, 0, $x, $y, $watermarkColor, __DIR__ . '/../assets/fonts/arial.ttf', $watermarkText);
    } else {
        imagestring($image, 5, $x, $y - 20, $watermarkText, $watermarkColor);
    }
    
    // Save image
    $result = false;
    switch ($sourceMime) {
        case 'image/jpeg':
            $result = imagejpeg($image, $imagePath, 90);
            break;
        case 'image/png':
            $result = imagepng($image, $imagePath);
            break;
    }
    
    imagedestroy($image);
    return $result;
}

/**
 * Blur image for premium content
 */
function blurImage($imagePath, $blurIntensity = 10) {
    if (!file_exists($imagePath)) {
        return false;
    }
    
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceMime = $imageInfo['mime'];
    
    // Create source image
    switch ($sourceMime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($imagePath);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Apply blur filter
    for ($i = 0; $i < $blurIntensity; $i++) {
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
    }
    
    // Save blurred image
    $pathInfo = pathinfo($imagePath);
    $blurredPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_blurred.' . $pathInfo['extension'];
    
    $result = false;
    switch ($sourceMime) {
        case 'image/jpeg':
            $result = imagejpeg($image, $blurredPath, 90);
            break;
        case 'image/png':
            $result = imagepng($image, $blurredPath);
            break;
    }
    
    imagedestroy($image);
    return $result ? $blurredPath : false;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'], $maxSize = 52428800) {
    $errors = [];
    
    if (!isset($file['error']) || is_array($file['error'])) {
        $errors[] = 'Invalid file upload';
        return $errors;
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            $errors[] = 'No file was uploaded';
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'File size exceeds limit';
            break;
        default:
            $errors[] = 'Unknown upload error';
            break;
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds ' . formatFileSize($maxSize);
    }
    
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv'
    ];
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedTypes)) {
        $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
    }
    
    if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
        $errors[] = 'Invalid file type';
    }
    
    return $errors;
}

/**
 * Upload file securely
 */
function uploadFile($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'], $maxSize = 52428800) {
    $errors = validateFileUpload($file, $allowedTypes, $maxSize);
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = generateSecureFilename($file['name']);
    $uploadPath = $uploadDir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'errors' => ['Failed to move uploaded file']];
    }
    
    // Set proper permissions
    chmod($uploadPath, 0644);
    
    return [
        'success' => true,
        'filename' => $filename,
        'path' => $uploadPath,
        'url' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $uploadPath),
        'size' => filesize($uploadPath),
        'mime_type' => mime_content_type($uploadPath)
    ];
}

/**
 * Profanity filter
 */
function filterProfanity($text) {
    if (!ENABLE_PROFANITY_FILTER) {
        return $text;
    }
    
    static $profanityList = null;
    
    if ($profanityList === null) {
        $profanityList = dbFetchAll(
            "SELECT word, replacement FROM profanity_filter WHERE is_active = 1 AND language = ?",
            [DEFAULT_LANGUAGE]
        );
    }
    
    foreach ($profanityList as $profanity) {
        $pattern = '/\b' . preg_quote($profanity['word'], '/') . '\b/i';
        $text = preg_replace($pattern, $profanity['replacement'], $text);
    }
    
    return $text;
}

/**
 * Send email using templates
 */
function sendEmail($to, $templateKey, $variables = [], $language = null) {
    if (!$language) {
        $language = DEFAULT_LANGUAGE;
    }
    
    // Get email template
    $template = dbFetch(
        "SELECT * FROM email_templates WHERE template_key = ? AND language = ? AND is_active = 1",
        [$templateKey, $language]
    );
    
    if (!$template) {
        error_log("Email template not found: {$templateKey}");
        return false;
    }
    
    // Replace variables in template
    $subject = $template['subject'];
    $bodyHtml = $template['body_html'];
    $bodyText = $template['body_text'];
    
    foreach ($variables as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $subject = str_replace($placeholder, $value, $subject);
        $bodyHtml = str_replace($placeholder, $value, $bodyHtml);
        $bodyText = str_replace($placeholder, $value, $bodyText);
    }
    
    // Queue email for sending
    $emailData = [
        'to_email' => $to,
        'subject' => $subject,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText,
        'priority' => 5,
        'status' => 'pending'
    ];
    
    $emailId = dbInsert('email_queue', $emailData);
    
    // Try to send immediately if possible
    if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
        return sendQueuedEmail($emailId);
    }
    
    return true; // Queued for later sending
}

/**
 * Send queued email
 */
function sendQueuedEmail($emailId) {
    $email = dbFetch("SELECT * FROM email_queue WHERE id = ? AND status = 'pending'", [$emailId]);
    
    if (!$email) {
        return false;
    }
    
    // Update status to sending
    dbUpdate('email_queue', ['status' => 'sending'], 'id = ?', [$emailId]);
    
    try {
        // Use PHPMailer or similar here
        // For now, we'll use basic mail() function
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
            'Reply-To: ' . FROM_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $success = mail($email['to_email'], $email['subject'], $email['body_html'], implode("\r\n", $headers));
        
        if ($success) {
            dbUpdate('email_queue', [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$emailId]);
            
            return true;
        } else {
            throw new Exception('mail() function failed');
        }
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        
        $attempts = $email['attempts'] + 1;
        $status = $attempts >= $email['max_attempts'] ? 'failed' : 'pending';
        
        dbUpdate('email_queue', [
            'status' => $status,
            'attempts' => $attempts,
            'error_message' => $e->getMessage()
        ], 'id = ?', [$emailId]);
        
        return false;
    }
}

/**
 * Get site setting
 */
function getSetting($key, $default = null) {
    static $settings = [];
    
    if (!isset($settings[$key])) {
        $setting = dbFetch("SELECT setting_value, setting_type FROM site_settings WHERE setting_key = ?", [$key]);
        
        if ($setting) {
            $value = $setting['setting_value'];
            
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = (bool)$value;
                    break;
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settings[$key] = $value;
        } else {
            $settings[$key] = $default;
        }
    }
    
    return $settings[$key];
}

/**
 * Set site setting
 */
function setSetting($key, $value, $type = 'string') {
    if ($type === 'json') {
        $value = json_encode($value);
    } elseif ($type === 'boolean') {
        $value = $value ? '1' : '0';
    }
    
    $exists = dbExists('site_settings', 'setting_key = ?', [$key]);
    
    if ($exists) {
        return dbUpdate('site_settings', [
            'setting_value' => $value,
            'setting_type' => $type
        ], 'setting_key = ?', [$key]);
    } else {
        return dbInsert('site_settings', [
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => $type
        ]);
    }
}

/**
 * Log admin action
 */
function logAdminAction($adminId, $action, $targetType = null, $targetId = null, $details = null) {
    $logData = [
        'admin_id' => $adminId,
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details' => $details ? json_encode($details) : null,
        'ip_address' => getUserIP(),
        'user_agent' => getUserAgent()
    ];
    
    return dbInsert('admin_logs', $logData);
}

/**
 * Check if user has subscription access to content
 */
function hasSubscriptionAccess($userId, $requiredLevel = 'monthly') {
    $user = dbFetch(
        "SELECT subscription_status, subscription_expires FROM users WHERE id = ?",
        [$userId]
    );
    
    if (!$user || $user['subscription_status'] === 'none') {
        return false;
    }
    
    // Check if subscription is expired
    if ($user['subscription_expires'] && strtotime($user['subscription_expires']) < time()) {
        return false;
    }
    
    $levels = ['monthly' => 1, 'yearly' => 2, 'lifetime' => 3];
    $userLevel = $levels[$user['subscription_status']] ?? 0;
    $requiredLevelValue = $levels[$requiredLevel] ?? 1;
    
    return $userLevel >= $requiredLevelValue;
}

/**
 * Get user's remaining free post views
 */
function getRemainingFreeViews($userId) {
    $limit = getSetting('free_posts_limit', 3);
    
    $viewedCount = dbCount(
        'user_post_access',
        'user_id = ? AND access_type = "free" AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
        [$userId]
    );
    
    return max(0, $limit - $viewedCount);
}

/**
 * Generate SEO-friendly URL slug
 */
function createSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    
    return $text;
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: {$url}");
    exit;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'text' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $message;
    }
    
    return null;
}

/**
 * Rate limiting
 */
function isRateLimited($identifier, $maxAttempts = 5, $timeWindow = 300) {
    $cacheKey = "rate_limit_{$identifier}";
    $attempts = $_SESSION[$cacheKey] ?? 0;
    $lastAttempt = $_SESSION[$cacheKey . '_time'] ?? 0;
    
    // Reset counter if time window has passed
    if (time() - $lastAttempt > $timeWindow) {
        $attempts = 0;
    }
    
    if ($attempts >= $maxAttempts) {
        return true;
    }
    
    // Increment attempts
    $_SESSION[$cacheKey] = $attempts + 1;
    $_SESSION[$cacheKey . '_time'] = time();
    
    return false;
}

/**
 * Clear rate limit
 */
function clearRateLimit($identifier) {
    $cacheKey = "rate_limit_{$identifier}";
    unset($_SESSION[$cacheKey], $_SESSION[$cacheKey . '_time']);
}

/**
 * Check maintenance mode
 */
function isMaintenanceMode() {
    return getSetting('maintenance_mode', false);
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    static $user = null;
    
    if ($user === null) {
        $user = dbFetch(
            "SELECT * FROM users WHERE id = ? AND status = 'active'",
            [$_SESSION['user_id']]
        );
    }
    
    return $user;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return getCurrentUser() !== null;
}

/**
 * Check if user has role
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Require admin
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * CSRF Protection
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSON Response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Error handler
 */
function handleError($message, $statusCode = 500) {
    error_log($message);
    
    if (DEBUG_MODE) {
        die($message);
    } else {
        http_response_code($statusCode);
        die('An error occurred. Please try again later.');
    }
}

/**
 * Initialize session securely
 */
function initSecureSession() {
    // Session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif ($_SESSION['last_regeneration'] < (time() - 300)) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Initialize secure session
initSecureSession();
?>
        '