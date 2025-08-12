<?php
/**
 * Comments API for Chloe Belle Website
 * Handles AJAX requests for loading, creating, and deleting comments
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

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

    // Get current user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        throw new Exception('User not found');
    }

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request format');
    }

    $action = $input['action'];

    switch ($action) {
        case 'load':
            // Load comments for a post
            if (!isset($input['post_id'])) {
                throw new Exception('Post ID is required');
            }

            $postId = (int)$input['post_id'];
            
            // Check if post exists and user has access
            $stmt = $pdo->prepare("
                SELECT p.*, u.role as author_role 
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = ? AND p.status = 'published'
            ");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if (!$post) {
                throw new Exception('Post not found');
            }

            // Get comments
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    u.username,
                    u.avatar,
                    u.role,
                    (SELECT COUNT(*) FROM likes WHERE comment_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ? AND (c.status = 'approved' OR c.status IS NULL)
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$currentUser['id'], $postId]);
            $comments = $stmt->fetchAll();

            // Format comments for response
            $formattedComments = [];
            foreach ($comments as $comment) {
                $avatarUrl = $comment['avatar'] ? 
                    '../uploads/avatars/' . $comment['avatar'] : 
                    '../assets/images/default-avatar.jpg';

                $formattedComments[] = [
                    'id' => $comment['id'],
                    'content' => htmlspecialchars($comment['content']),
                    'username' => htmlspecialchars($comment['username']),
                    'avatar_url' => $avatarUrl,
                    'role' => $comment['role'],
                    'like_count' => (int)$comment['like_count'],
                    'user_liked' => (bool)$comment['user_liked'],
                    'time_ago' => timeAgo($comment['created_at']),
                    'can_delete' => $currentUser['id'] == $comment['user_id'] || in_array($currentUser['role'], ['admin', 'chloe'])
                ];
            }

            $response = [
                'success' => true,
                'comments' => $formattedComments
            ];
            break;

        case 'create':
            // Create a new comment
            if (!isset($input['post_id']) || !isset($input['content'])) {
                throw new Exception('Post ID and content are required');
            }

            $postId = (int)$input['post_id'];
            $content = trim($input['content']);

            if (empty($content)) {
                throw new Exception('Comment content cannot be empty');
            }

            if (strlen($content) > 1000) {
                throw new Exception('Comment is too long (max 1000 characters)');
            }

            // Check if post exists and user has access
            $stmt = $pdo->prepare("
                SELECT p.*, u.role as author_role 
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = ? AND p.status = 'published'
            ");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if (!$post) {
                throw new Exception('Post not found');
            }

            // Check if user can comment on this post (premium content check)
            if ($post['is_premium']) {
                $hasSubscription = in_array($currentUser['subscription_status'], ['monthly', 'yearly', 'lifetime']);
                $subscriptionExpires = $currentUser['subscription_expires'] ? new DateTime($currentUser['subscription_expires']) : null;
                $subscriptionActive = $hasSubscription && (!$subscriptionExpires || $subscriptionExpires > new DateTime());
                
                if (!$subscriptionActive && !in_array($currentUser['role'], ['admin', 'chloe'])) {
                    throw new Exception('You need an active subscription to comment on premium content');
                }
            }

            // Insert comment
            $stmt = $pdo->prepare("
                INSERT INTO comments (post_id, user_id, content, status, created_at)
                VALUES (?, ?, ?, 'approved', NOW())
            ");
            $stmt->execute([$postId, $currentUser['id'], $content]);
            
            $commentId = $pdo->lastInsertId();

            // Update comment count on post
            $stmt = $pdo->prepare("
                UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?
            ");
            $stmt->execute([$postId]);

            // Get the created comment for response
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    u.username,
                    u.avatar,
                    u.role
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            $avatarUrl = $comment['avatar'] ? 
                '../uploads/avatars/' . $comment['avatar'] : 
                '../assets/images/default-avatar.jpg';

            $response = [
                'success' => true,
                'message' => 'Comment posted successfully',
                'comment' => [
                    'id' => $comment['id'],
                    'content' => htmlspecialchars($comment['content']),
                    'username' => htmlspecialchars($comment['username']),
                    'avatar_url' => $avatarUrl,
                    'role' => $comment['role'],
                    'post_id' => $postId,
                    'time_ago' => 'just now'
                ]
            ];
            break;

        case 'delete':
            // Delete a comment
            if (!isset($input['comment_id'])) {
                throw new Exception('Comment ID is required');
            }

            $commentId = (int)$input['comment_id'];

            // Get comment details
            $stmt = $pdo->prepare("
                SELECT c.*, p.id as post_id 
                FROM comments c 
                JOIN posts p ON c.post_id = p.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment) {
                throw new Exception('Comment not found');
            }

            // Check if user can delete this comment
            if ($currentUser['id'] != $comment['user_id'] && !in_array($currentUser['role'], ['admin', 'chloe'])) {
                throw new Exception('You can only delete your own comments');
            }

            // Delete the comment
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);

            // Update comment count on post
            $stmt = $pdo->prepare("
                UPDATE posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?
            ");
            $stmt->execute([$comment['post_id']]);

            $response = [
                'success' => true,
                'message' => 'Comment deleted successfully'
            ];
            break;

        case 'like':
        case 'unlike':
            // Like or unlike a comment
            if (!isset($input['comment_id'])) {
                throw new Exception('Comment ID is required');
            }

            $commentId = (int)$input['comment_id'];

            // Check if comment exists
            $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);
            if (!$stmt->fetch()) {
                throw new Exception('Comment not found');
            }

            if ($action === 'like') {
                // Add like
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO likes (user_id, comment_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $commentId]);
            } else {
                // Remove like
                $stmt = $pdo->prepare("
                    DELETE FROM likes WHERE user_id = ? AND comment_id = ?
                ");
                $stmt->execute([$currentUser['id'], $commentId]);
            }

            // Get updated like count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as like_count FROM likes WHERE comment_id = ?
            ");
            $stmt->execute([$commentId]);
            $likeCount = $stmt->fetch()['like_count'];

            $response = [
                'success' => true,
                'like_count' => (int)$likeCount,
                'message' => $action === 'like' ? 'Comment liked' : 'Comment unliked'
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    error_log("Comments API error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    // Set appropriate HTTP status code
    if ($e->getMessage() === 'User not logged in') {
        http_response_code(401);
    } elseif ($e->getMessage() === 'Post not found' || $e->getMessage() === 'Comment not found') {
        http_response_code(404);
    } elseif (strpos($e->getMessage(), 'can only delete') !== false || strpos($e->getMessage(), 'need an active subscription') !== false) {
        http_response_code(403);
    } else {
        http_response_code(400);
    }
}

// Helper function for time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 2592000) return floor($time / 86400) . 'd ago';
    if ($time < 31536000) return floor($time / 2592000) . 'mo ago';
    
    return floor($time / 31536000) . 'y ago';
}

echo json_encode($response);
?>