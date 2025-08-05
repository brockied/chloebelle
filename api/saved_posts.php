<?php
/**
 * Saved Posts API
 * Handle saving/unsaving posts for users
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $_SESSION['user_id'];

    switch ($method) {
        case 'POST':
            // Save/unsave a post
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['post_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Post ID required']);
                exit;
            }
            
            $postId = (int)$input['post_id'];
            
            // Check if post exists
            $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Post not found']);
                exit;
            }
            
            // Check if already saved
            $stmt = $pdo->prepare("SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$userId, $postId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Unsave the post
                $stmt = $pdo->prepare("DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?");
                $stmt->execute([$userId, $postId]);
                
                echo json_encode([
                    'success' => true,
                    'saved' => false,
                    'message' => 'Post removed from saved items'
                ]);
            } else {
                // Save the post
                $stmt = $pdo->prepare("INSERT INTO saved_posts (user_id, post_id) VALUES (?, ?)");
                $stmt->execute([$userId, $postId]);
                
                echo json_encode([
                    'success' => true,
                    'saved' => true,
                    'message' => 'Post saved successfully'
                ]);
            }
            break;
            
        case 'GET':
            // Get user's saved posts
            $page = (int)($_GET['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $query = "
                SELECT p.*, u.username, u.avatar, u.role as user_role,
                       sp.saved_at,
                       COUNT(DISTINCT c.id) as comment_count,
                       COUNT(DISTINCT l.id) as like_count,
                       MAX(CASE WHEN l.user_id = ? THEN 1 ELSE 0 END) as user_liked
                FROM saved_posts sp
                JOIN posts p ON sp.post_id = p.id
                JOIN users u ON p.user_id = u.id
                LEFT JOIN comments c ON p.id = c.post_id
                LEFT JOIN likes l ON p.id = l.post_id AND l.type = 'post'
                WHERE sp.user_id = ?
                GROUP BY p.id, sp.saved_at
                ORDER BY sp.saved_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$userId, $userId, $limit, $offset]);
            $savedPosts = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM saved_posts WHERE user_id = ?");
            $countStmt->execute([$userId]);
            $totalSaved = $countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'posts' => $savedPosts,
                'total' => $totalSaved,
                'page' => $page,
                'has_more' => ($offset + $limit) < $totalSaved
            ]);
            break;
            
        case 'DELETE':
            // Remove specific saved post
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['post_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Post ID required']);
                exit;
            }
            
            $postId = (int)$input['post_id'];
            
            $stmt = $pdo->prepare("DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$userId, $postId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Post removed from saved items'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Post was not in saved items'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Saved posts API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>