<?php
// Posts API for deleting posts

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Get current user
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    $action = $input['action'] ?? '';
    switch ($action) {
        case 'delete':
            $postId = (int)($input['post_id'] ?? 0);
            if (!$postId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid post id']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if (!$post) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Post not found']);
                exit;
            }

            if ($currentUser['role'] !== 'admin' && $currentUser['id'] != $post['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Not authorized']);
                exit;
            }

            $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM comments WHERE post_id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM likes WHERE post_id = ?')->execute([$postId]);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Posts API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}