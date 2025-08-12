<?php
/**
 * Login Handler for Chloe Belle Website
 * Handles AJAX and regular form login requests
 */

session_start();
require_once '../config.php';

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Access denied');
}

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get posted data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fallback to regular POST data
    $input = $_POST;
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$remember = isset($input['remember']) && $input['remember'];

// Validate input
if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required'
    ]);
    exit;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "login_attempts_{$ip}";

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_limit_key . '_time'] = time();
}

// Reset counter if more than 15 minutes have passed
if (time() - $_SESSION[$rate_limit_key . '_time'] > 900) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_limit_key . '_time'] = time();
}

// Check rate limit (5 attempts per 15 minutes)
if ($_SESSION[$rate_limit_key] >= 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Too many login attempts. Please try again later.'
    ]);
    exit;
}

try {
    // Database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Find user by email
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, role, status, login_attempts, locked_until, last_login
        FROM users 
        WHERE email = ? AND status IN ('active', 'pending')
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Increment rate limit counter
        $_SESSION[$rate_limit_key]++;
        
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    // Check if account is locked
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        echo json_encode([
            'success' => false,
            'message' => 'Account is temporarily locked. Please try again later.'
        ]);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Increment login attempts for this user
        $attempts = $user['login_attempts'] + 1;
        
        // Lock account after 5 failed attempts for 30 minutes
        $locked_until = null;
        if ($attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
        }
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET login_attempts = ?, locked_until = ? 
            WHERE id = ?
        ");
        $stmt->execute([$attempts, $locked_until, $user['id']]);
        
        // Increment rate limit counter
        $_SESSION[$rate_limit_key]++;
        
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    // Successful login - reset counters
    $stmt = $pdo->prepare("
        UPDATE users 
        SET login_attempts = 0, locked_until = NULL, last_login = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);

    // Reset rate limiting
    $_SESSION[$rate_limit_key] = 0;

    // Create session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // Set remember me cookie if requested
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Store token in database (you'd need a remember_tokens table)
        // For now, we'll just extend the session
        $_SESSION['remember_me'] = true;
    }

    // Log successful login
    logLoginAttempt($pdo, $user['id'], $email, 'success');

    // Determine redirect URL based on role
    $redirect = '/';
    switch ($user['role']) {
        case 'admin':
            $redirect = '/admin/';
            break;
        case 'chloe':
            $redirect = '/admin/posts.php';
            break;
        case 'user':
        default:
            $redirect = '/feed/';
            break;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => $redirect,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Login database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

function logLoginAttempt($pdo, $userId, $email, $status) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, email, ip_address, user_agent, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $email,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $status
        ]);
    } catch (Exception $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}
?>