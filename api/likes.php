<?php
/**
 * Fixed Likes API - api/likes.php
 * Now matches your actual database structure
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Debug logging
error_log("Likes API called with: " . file_get_contents('php://input'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

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

    // Get current user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }
    
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'toggle':
            toggleLike($pdo, $currentUser, $input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }

} catch (Exception $e) {
    error_log("Likes API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function toggleLike($pdo, $user, $input) {
    $postId = (int)($input['post_id'] ?? 0);
    
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        return;
    }
    
    // Check if post exists
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
    $stmt->execute([$postId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        return;
    }
    
    // Check if user already liked this post
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user['id'], $postId]);
    $existingLike = $stmt->fetch();
    
    if ($existingLike) {
        // Unlike - remove the like
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user['id'], $postId]);
        
        $liked = false;
        $message = 'Post unliked';
    } else {
        // Like - add the like (using proper table structure)
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, post_id, type, created_at) VALUES (?, ?, 'like', NOW())");
        $stmt->execute([$user['id'], $postId]);
        
        $liked = true;
        $message = 'Post liked';
    }
    
    // Update post like count by counting actual likes
    $stmt = $pdo->prepare("
        UPDATE posts 
        SET likes_count = (SELECT COUNT(*) FROM likes WHERE post_id = ?) 
        WHERE id = ?
    ");
    $stmt->execute([$postId, $postId]);
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COALESCE(likes_count, 0) as likes_count FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $result = $stmt->fetch();
    $likeCount = $result['likes_count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'liked' => $liked,
        'like_count' => $likeCount
    ]);
}
?>