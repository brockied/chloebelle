<?php
/**
 * Comments API Endpoint
 * Handles all comment-related AJAX requests
 * 
 * Save as: /api/comments.php
 */

session_start();
require_once '../config.php';

// Set JSON header
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
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create':
            createComment($pdo, $currentUser, $input);
            break;
            
        case 'delete':
            deleteComment($pdo, $currentUser, $input);
            break;
            
        case 'like':
            likeComment($pdo, $currentUser, $input);
            break;
            
        case 'unlike':
            unlikeComment($pdo, $currentUser, $input);
            break;
            
        case 'load_more':
            loadMoreComments($pdo, $currentUser, $input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("Comments API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function createComment($pdo, $user, $input) {
    $postId = (int)($input['post_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        return;
    }
    
    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Comment content is required']);
        return;
    }
    
    if (strlen($content) > 1000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Comment is too long (max 1000 characters)']);
        return;
    }
    
    // Check if post exists
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND status = 'published'");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        return;
    }
    
    // Check if user can comment on this post
    if (!canUserCommentOnPost($post, $user)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to comment on this post']);
        return;
    }
    
    // Rate limiting - max 5 comments per minute
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM comments 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->execute([$user['id']]);
    $recentComments = $stmt->fetchColumn();
    
    if ($recentComments >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many comments. Please wait a moment.']);
        return;
    }
    
    // Create the comment
    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, user_id, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$postId, $user['id'], $content]);
    $commentId = $pdo->lastInsertId();
    
    // Update post comment count
    $stmt = $pdo->prepare("UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?");
    $stmt->execute([$postId]);
    
    // Get the created comment with user info
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
    
    // Add avatar URL
    $comment['avatar_url'] = getAvatarUrl($comment['avatar']);
    $comment['post_id'] = $postId;
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully',
        'comment' => $comment
    ]);
}

function deleteComment($pdo, $user, $input) {
    $commentId = (int)($input['comment_id'] ?? 0);
    
    if ($commentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
        return;
    }
    
    // Get comment info
    $stmt = $pdo->prepare("
        SELECT c.*, p.user_id as post_author_id 
        FROM comments c 
        JOIN posts p ON c.post_id = p.id 
        WHERE c.id = ? AND c.is_deleted = 0
    ");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        return;
    }
    
    // Check permissions
    $canDelete = $comment['user_id'] == $user['id'] || 
                 in_array($user['role'], ['admin', 'chloe']) ||
                 $comment['post_author_id'] == $user['id'];
    
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    // Soft delete the comment
    $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$commentId]);
    
    // Update post comment count
    $stmt = $pdo->prepare("UPDATE posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?");
    $stmt->execute([$comment['post_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Comment deleted']);
}

function likeComment($pdo, $user, $input) {
    $commentId = (int)($input['comment_id'] ?? 0);
    
    if ($commentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
        return;
    }
    
    // Check if comment exists
    $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$commentId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        return;
    }
    
    // Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $user['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already liked']);
        return;
    }
    
    // Add like
    $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
    $stmt->execute([$commentId, $user['id']]);
    
    // Update comment like count
    $stmt = $pdo->prepare("UPDATE comments SET likes_count = likes_count + 1 WHERE id = ?");
    $stmt->execute([$commentId]);
    
    // Get updated count
    $stmt = $pdo->prepare("SELECT likes_count FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $likeCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment liked',
        'like_count' => $likeCount
    ]);
}

function unlikeComment($pdo, $user, $input) {
    $commentId = (int)($input['comment_id'] ?? 0);
    
    if ($commentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
        return;
    }
    
    // Remove like
    $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $user['id']]);
    
    // Update comment like count
    $stmt = $pdo->prepare("UPDATE comments SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?");
    $stmt->execute([$commentId]);
    
    // Get updated count
    $stmt = $pdo->prepare("SELECT likes_count FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $likeCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment unliked',
        'like_count' => $likeCount
    ]);
}

function loadMoreComments($pdo, $user, $input) {
    $postId = (int)($input['post_id'] ?? 0);
    $offset = (int)($input['offset'] ?? 0);
    $limit = min(10, (int)($input['limit'] ?? 5));
    
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        return;
    }
    
    // Get comments
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            u.avatar,
            u.role,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ? AND c.is_deleted = 0
        ORDER BY c.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user['id'], $postId, $limit, $offset]);
    $comments = $stmt->fetchAll();
    
    // Add avatar URLs
    foreach ($comments as &$comment) {
        $comment['avatar_url'] = getAvatarUrl($comment['avatar']);
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'has_more' => count($comments) === $limit
    ]);
}

function canUserCommentOnPost($post, $user) {
    if ($post['is_premium']) {
        $requiredLevel = $post['subscription_required'];
        $userLevel = $user['subscription_status'];
        
        if ($userLevel === 'none') {
            return false;
        }
        
        $levels = ['monthly' => 1, 'yearly' => 2, 'lifetime' => 3];
        $userLevelValue = $levels[$userLevel] ?? 0;
        $requiredLevelValue = $levels[$requiredLevel] ?? 1;
        
        return $userLevelValue >= $requiredLevelValue;
    }
    
    return true;
}

function getAvatarUrl($avatar, $useThumb = true) {
    if (!$avatar) {
        return '../assets/images/default-avatar.jpg';
    }
    
    $avatarPath = $useThumb ? '../uploads/avatars/thumb_' . $avatar : '../uploads/avatars/' . $avatar;
    
    if ($useThumb && !file_exists($avatarPath)) {
        $avatarPath = '../uploads/avatars/' . $avatar;
    }
    
    return file_exists($avatarPath) ? $avatarPath : '../assets/images/default-avatar.jpg';
}
?>