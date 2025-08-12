<?php
/**
 * Registration Handler for Chloe Belle Website
 * Handles user registration with validation and email verification
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

$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';
$agreeTerms = isset($input['agree_terms']) && $input['agree_terms'];

// Validate input
$errors = [];

if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3 || strlen($username) > 20) {
    $errors[] = 'Username must be between 3 and 20 characters';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
}

if (!$agreeTerms) {
    $errors[] = 'You must agree to the terms and conditions';
}

// Check password strength
$passwordScore = 0;
if (preg_match('/[a-z]/', $password)) $passwordScore++;
if (preg_match('/[A-Z]/', $password)) $passwordScore++;
if (preg_match('/[0-9]/', $password)) $passwordScore++;
if (preg_match('/[^a-zA-Z0-9]/', $password)) $passwordScore++;

if ($passwordScore < 3) {
    $errors[] = 'Password is too weak. Use a mix of uppercase, lowercase, numbers, and special characters';
}

// Rate limiting for registration
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "register_attempts_{$ip}";

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_limit_key . '_time'] = time();
}

// Reset counter if more than 1 hour has passed
if (time() - $_SESSION[$rate_limit_key . '_time'] > 3600) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_limit_key . '_time'] = time();
}

// Check rate limit (3 registrations per hour)
if ($_SESSION[$rate_limit_key] >= 3) {
    $errors[] = 'Too many registration attempts. Please try again later.';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
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

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Username is already taken'
        ]);
        exit;
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Email is already registered'
        ]);
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Generate email verification token
    $verificationToken = bin2hex(random_bytes(32));

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            username, email, password, role, status, email_verified, 
            email_verification_token, language, currency, created_at
        ) VALUES (?, ?, ?, 'user', 'active', 0, ?, 'en', 'GBP', NOW())
    ");
    
    $stmt->execute([
        $username,
        $email,
        $hashedPassword,
        $verificationToken
    ]);

    $userId = $pdo->lastInsertId();

    // Increment rate limit counter
    $_SESSION[$rate_limit_key]++;

    // Send verification email (simplified for now)
    $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/auth/verify-email.php?token=" . $verificationToken;
    
    // In a real application, you'd send an actual email here
    // For now, we'll just log it
    error_log("Verification email for {$email}: {$verificationLink}");

    // For development, auto-verify email
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET email_verified = 1, email_verification_token = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please check your email to verify your account.',
        'redirect' => '/',
        'user_id' => $userId,
        'verification_required' => !DEBUG_MODE
    ]);

} catch (PDOException $e) {
    error_log("Registration database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>