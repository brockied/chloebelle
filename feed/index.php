<?php
/**
 * Enhanced Feed Page with Multiple Photos, Live Stats, and Interactions
 */

session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;
$commentsEnabled = true;
$commentsModeration = false;

if ($isLoggedIn) {
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

        // Update user's last activity for live viewer tracking
        $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);

        // Load site settings
        $settings = [];
        try {
            $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $settingsStmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Settings query error: " . $e->getMessage());
        }

        $commentsEnabled = ($settings['comments_enabled'] ?? '1') === '1';
        $commentsModeration = ($settings['comments_moderation'] ?? '0') === '1';
    } catch (Exception $e) {
        error_log("Feed database error: " . $e->getMessage());
    }
} else {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'info';

// Handle new post creation with multiple photos - ONLY ADMIN
if ($_POST && isset($_POST['create_post']) && $currentUser['role'] === 'admin') {
    try {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $isPremium = isset($_POST['is_premium']) ? 1 : 0;
        $subscriptionRequired = $_POST['subscription_required'] ?? 'none';
        
        if (empty($content)) {
            throw new Exception('Content is required');
        }
        
        // Handle multiple media uploads
        $mediaUrls = [];
        $mediaType = 'none';
        $uploadPath = '../uploads/posts/';
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        // Check for multiple photo uploads
        if (isset($_FILES['photo_uploads']) && !empty($_FILES['photo_uploads']['name'][0])) {
            $photoFiles = $_FILES['photo_uploads'];
            $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            for ($i = 0; $i < count($photoFiles['name']) && $i < 5; $i++) {
                if ($photoFiles['error'][$i] === UPLOAD_ERR_OK) {
                    // Create individual file array in correct $_FILES format
                    $individualFile = [
                        'name' => $photoFiles['name'][$i],
                        'type' => $photoFiles['type'][$i],
                        'tmp_name' => $photoFiles['tmp_name'][$i],
                        'error' => $photoFiles['error'][$i],
                        'size' => $photoFiles['size'][$i]
                    ];
                    
                    // Validate file size
                    if ($individualFile['size'] > 10485760) { // 10MB
                        continue; // Skip large files
                    }
                    
                    // Validate file type
                    $fileExt = strtolower(pathinfo($individualFile['name'], PATHINFO_EXTENSION));
                    if (!in_array($fileExt, $allowedImageTypes)) {
                        continue; // Skip invalid types
                    }
                    
                    // Generate unique filename
                    $uniqueName = uniqid() . '_' . time() . '.' . $fileExt;
                    $destination = $uploadPath . $uniqueName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($individualFile['tmp_name'], $destination)) {
                        chmod($destination, 0644);
                        $mediaUrls[] = 'uploads/posts/' . $uniqueName;
                        $mediaType = 'image';
                    }
                }
            }
        }
        // Check for single video upload
        elseif (isset($_FILES['video_upload']) && $_FILES['video_upload']['error'] === UPLOAD_ERR_OK) {
            $allowedVideoTypes = ['mp4', 'mov', 'avi', 'wmv'];
            $uploadResult = uploadFile($_FILES['video_upload'], $uploadPath, $allowedVideoTypes, 52428800);
            
            if ($uploadResult['success']) {
                $mediaUrls[] = 'uploads/posts/' . basename($uploadResult['path']);
                $mediaType = 'video';
            }
        }
        
        // Store media URLs as JSON
        $mediaUrlsJson = !empty($mediaUrls) ? json_encode($mediaUrls) : null;
        
        // Insert post into database
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, title, content, media_type, media_url, is_premium, subscription_required, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())
        ");
        $stmt->execute([$currentUser['id'], $title, $content, $mediaType, $mediaUrlsJson, $isPremium, $subscriptionRequired]);
        
        $message = "Post created successfully with " . count($mediaUrls) . " media files!";
        $messageType = 'success';
        
        header("Location: index.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $message = "Post created successfully!";
    $messageType = 'success';
}

// Get posts with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$postsPerPage = 10;
$offset = ($page - 1) * $postsPerPage;

try {
    $countSql = "SELECT COUNT(*) FROM posts WHERE ((:is_admin=1) OR status = 'published')";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute([':is_admin' => ($currentUser && $currentUser['role']==='admin' ? 1 : 0)]);
$totalPosts = $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            u.username,
            u.avatar,
            u.role,
            COALESCE(p.likes_count, 0) as like_count,
            COALESCE(p.views, 0) as views,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :user_id) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE ((:is_admin = 1) OR p.status = 'published')
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':user_id', $currentUser['id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$postsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':is_admin', ($currentUser && $currentUser['role']==='admin' ? 1 : 0), PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();

    $totalPages = ceil($totalPosts / $postsPerPage);

} catch (Exception $e) {
    error_log("Feed query error: " . $e->getMessage());
    $posts = [];
    $totalPages = 1;
}

// Get comments (same as before)
$comments = [];
$commentCounts = []; // Track actual visible comment counts per post

// Initialize comment counts for all posts
if (!empty($posts)) {
    foreach ($posts as $post) {
        $commentCounts[$post['id']] = 0;
    }
}

if ($commentsEnabled && !empty($posts)) {
    $postIds = array_column($posts, 'id');
    if (!empty($postIds)) {
        $placeholders = str_repeat('?,', count($postIds) - 1) . '?';
        try {
            $hasStatusColumn = false;
            try {
                $columnCheck = $pdo->query("SHOW COLUMNS FROM comments LIKE 'status'")->fetch();
                $hasStatusColumn = (bool)$columnCheck;
            } catch (Exception $e) {
                $hasStatusColumn = false;
            }

            if ($commentsModeration && $hasStatusColumn) {
                $visibilityCondition = "c.status = 'approved'";
            } elseif ($hasStatusColumn) {
                $visibilityCondition = "(c.status != 'deleted' OR c.status IS NULL)";
            } else {
                $visibilityCondition = "(c.is_deleted = 0 OR c.is_deleted IS NULL)";
            }

            $sql = "
                SELECT c.*, u.username, u.avatar, u.role,
                       (SELECT COUNT(*) FROM likes WHERE comment_id = c.id) as like_count,
                       (SELECT COUNT(*) FROM likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id IN ($placeholders) AND $visibilityCondition
                ORDER BY c.created_at ASC
            ";
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$currentUser['id']], $postIds);
            $stmt->execute($params);
            $allComments = $stmt->fetchAll();

            foreach ($allComments as $comment) {
                $comments[$comment['post_id']][] = $comment;
                $commentCounts[$comment['post_id']]++;
            }
        } catch (Exception $e) {
            error_log("Comments query error: " . $e->getMessage());
        }
    }
}

// Get live viewers (users active in last 5 minutes)
$liveViewers = [];
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count, username, avatar, role
        FROM users 
        WHERE last_active >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        GROUP BY id
        ORDER BY last_active DESC
        LIMIT 10
    ");
    $liveViewers = $stmt->fetchAll();
    $totalLiveViewers = $pdo->query("
        SELECT COUNT(DISTINCT id) 
        FROM users 
        WHERE last_active >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ")->fetchColumn();
} catch (Exception $e) {
    $totalLiveViewers = 0;
    $liveViewers = [];
}

// Get recent likes (last 24 hours)
$recentLikes = [];
try {
    $stmt = $pdo->query("
        SELECT l.*, u.username, u.avatar, u.role, p.title, p.content
        FROM likes l
        JOIN users u ON l.user_id = u.id
        LEFT JOIN posts p ON l.post_id = p.id
        WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND l.post_id IS NOT NULL
        ORDER BY l.created_at DESC
        LIMIT 15
    ");
    $recentLikes = $stmt->fetchAll();
} catch (Exception $e) {
    $recentLikes = [];
}

// User subscription status
$hasSubscription = in_array($currentUser['subscription_status'], ['monthly', 'yearly', 'lifetime']);
$subscriptionExpires = $currentUser['subscription_expires'] ? new DateTime($currentUser['subscription_expires']) : null;
$subscriptionActive = $hasSubscription && (!$subscriptionExpires || $subscriptionExpires > new DateTime());

if (!$subscriptionActive && !isset($_SESSION['free_posts_viewed'])) {
    $_SESSION['free_posts_viewed'] = 0;
}

// Helper functions
function hasPostAccess($post, $user, $subscriptionActive) {
    if (in_array($user['role'], ['admin', 'chloe'])) return true;
    if (!$post['is_premium']) return true;
    if (!$subscriptionActive) return false;
    
    $userSubscription = $user['subscription_status'];
    $requiredSubscription = $post['subscription_required'];
    
    if ($requiredSubscription === 'yearly' && $userSubscription === 'monthly') {
        return false;
    }
    return true;
}

function canCommentOnPost($post, $user, $subscriptionActive, $commentsEnabled) {
    if (!$user || !$commentsEnabled) return false;
    return hasPostAccess($post, $user, $subscriptionActive);
}

function getAvatarUrl($avatar, $useThumb = false) {
    if (!$avatar) return '../assets/images/default-avatar.jpg';
    
    $avatarPath = $useThumb ? 
        '../uploads/avatars/thumb_' . $avatar : '../uploads/avatars/' . $avatar;
    
    if ($useThumb && !file_exists($avatarPath)) {
        $avatarPath = '../uploads/avatars/' . $avatar;
    }
    
    return file_exists($avatarPath) ? $avatarPath : '../assets/images/default-avatar.jpg';
}

function getMediaUrls($mediaUrl) {
    if (!$mediaUrl) return [];
    
    // Try to decode as JSON first (multiple files)
    $urls = json_decode($mediaUrl, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($urls)) {
        return array_map(function($url) {
            return str_starts_with($url, 'http') || str_starts_with($url, '../') ? $url : '../' . $url;
        }, $urls);
    }
    
    // Single file (legacy support)
    $url = str_starts_with($mediaUrl, 'http') || str_starts_with($mediaUrl, '../') ? $mediaUrl : '../' . $mediaUrl;
    return [$url];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 2592000) return floor($time / 86400) . 'd ago';
    if ($time < 31536000) return floor($time / 2592000) . 'mo ago';
    
    return floor($time / 31536000) . 'y ago';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - Chloe Belle</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding-top: 80px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: rgba(108, 92, 231, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .current-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .post-card,
        .create-post-card,
        .widget-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(108, 92, 231, 0.1);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            overflow: visible;
        }
        
        .post-card:hover,
        .create-post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(108, 92, 231, 0.2);
        }

        .post-card .card-body,
        .create-post-card .card-body,
        .widget-card .card-body {
            padding: 25px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .chloe-avatar {
            border-color: #ff6b9d !important;
            box-shadow: 0 0 15px rgba(255, 107, 157, 0.3);
        }
        
        .admin-avatar {
            border-color: #4ecdc4 !important;
            box-shadow: 0 0 15px rgba(78, 205, 196, 0.3);
        }
        
        .premium-badge {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: white;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .premium-overlay {
            position: relative;
            overflow: hidden;
            min-height: 150px;
        }
        
       .premium-blur {
            filter: blur(8px);
            pointer-events: none;
            transform: scale(1.02);
        }

        .premium-lock {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            background: rgba(0, 0, 0, 0.4);
            padding: 30px 20px;
            border-radius: 15px;
            backdrop-filter: blur(5px);
        }
        
        .like-btn,
        .comment-toggle {
            background: none;
            border: none;
            color: #6c757d;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .like-btn:hover,
        .comment-toggle:hover {
            background: #f8f9fa;
            color: #6c5ce7;
        }
        
        .like-btn.liked {
            color: #e74c3c;
        }
        
        /* Photo Grid Styles - Facebook-like */
        .photo-grid {
            display: grid;
            gap: 4px;
            margin: 15px 0;
            border-radius: 15px;
            overflow: hidden;
            max-width: 100%;
        }
        
        .photo-grid.single {
            grid-template-columns: 1fr;
            justify-items: center;
            background: transparent;
            border: none;
            padding: 0;
        }
        
        .photo-grid.double, 
        .photo-grid.triple, 
        .photo-grid.quad, 
        .photo-grid.penta {
            background: #ffffff;
            border: 1px solid #e9ecef;
            padding: 2px;
        }
        
        .photo-grid.double {
            grid-template-columns: 1fr 1fr;
        }
        
        .photo-grid.triple {
            grid-template-columns: 2fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        
        .photo-grid.quad {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }
        
        .photo-grid.penta {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr auto;
        }
        
        .photo-grid-item {
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #ffffff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .photo-grid-item:hover {
            transform: scale(1.01);
            z-index: 2;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .photo-grid-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            transition: all 0.3s ease;
            display: block;
            max-height: 100%;
        }
        
        .photo-grid-item:hover img {
            filter: brightness(1.1);
        }
        
        /* Facebook-like photo dimensions */
        .photo-grid.single .photo-grid-item {
            height: 350px;
            max-width: 400px;
            width: 100%;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        
        .photo-grid.single .photo-grid-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #ffffff;
        }
        
        .photo-grid.double .photo-grid-item {
            height: 250px;
        }
        
        .photo-grid.triple .photo-grid-item:first-child {
            grid-row: 1 / 3;
            height: 320px;
        }
        
        .photo-grid.triple .photo-grid-item:not(:first-child) {
            height: 156px;
        }
        
        .photo-grid.quad .photo-grid-item {
            height: 200px;
        }
        
        .photo-grid.penta .photo-grid-item:first-child {
            grid-row: 1 / 3;
            height: 250px;
        }
        
        .photo-grid.penta .photo-grid-item:not(:first-child) {
            height: 120px;
        }
        
        .photo-grid.penta .photo-grid-item:nth-child(5) {
            grid-column: 1 / 3;
            height: 120px;
        }
        
        .photo-more-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            backdrop-filter: blur(2px);
        }
        
        .post-media-video {
            width: 100%;
            max-height: 350px;
            border-radius: 15px;
            outline: none;
            object-fit: contain;
            background: #ffffff;
        }
        
        /* Widget Styles */
        .widgets-container {
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            overflow-x: visible;
        }
        
        .widgets-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .widgets-container::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .widgets-container::-webkit-scrollbar-thumb {
            background: rgba(108, 92, 231, 0.3);
            border-radius: 10px;
        }
        
        .widget-card {
            border: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(108, 92, 231, 0.15);
            margin-bottom: 25px;
            position: relative;
        }
        
        .widget-card .card-body {
            padding: 0;
        }
        
        .widget-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            font-weight: 700;
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .widget-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }
        
        .live-widget-header {
            background: linear-gradient(135deg, #ff6b9d, #ff8c42);
        }
        
        .love-widget-header {
            background: linear-gradient(135deg, #e74c3c, #fd79a8);
        }
        
        .widget-content {
            padding: 25px;
        }
        
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #fff;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 10px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        
        @keyframes pulse {
            0% { 
                opacity: 1; 
                transform: scale(1);
                box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            }
            50% { 
                opacity: 0.7; 
                transform: scale(1.2);
                box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
            }
            100% { 
                opacity: 1; 
                transform: scale(1);
                box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            }
        }
        
        .viewer-avatars-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            padding: 15px 0;
        }
        
        .viewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .viewer-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
        }
        
        .viewer-count-text {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 12px 16px;
            border-radius: 15px;
            margin-top: 15px;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .like-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .like-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 0;
        }
        
        .like-item:hover {
            background: rgba(108, 92, 231, 0.05);
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .like-item:last-child {
            border-bottom: none;
        }
        
        .like-emoji {
            font-size: 1.5rem;
            margin-right: 15px;
            animation: heartbeat 2s infinite;
            flex-shrink: 0;
        }
        
        @keyframes heartbeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.1); }
            28% { transform: scale(1); }
            42% { transform: scale(1.1); }
            70% { transform: scale(1); }
        }
        
        .like-user-info {
            flex-grow: 1;
            min-width: 0;
        }
        
        .like-username {
            font-weight: 600;
            color: #495057;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.95rem;
        }
        
        .like-time {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .likes-scroll-container {
            max-height: 350px;
            overflow-y: auto;
            padding-right: 8px;
        }
        
        .likes-scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .likes-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .likes-scroll-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        
        .likes-scroll-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a42a6);
        }
        
        .widget-footer {
            background: rgba(108, 92, 231, 0.05);
            padding: 18px 25px;
            text-align: center;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .widget-footer-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 25px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.4;
        }
        
        .empty-state h6 {
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Right sidebar fixes */
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(108, 92, 231, 0.15);
        }
        
        .sidebar-container {
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        
        .sidebar-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-container::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar-container::-webkit-scrollbar-thumb {
            background: rgba(108, 92, 231, 0.3);
            border-radius: 10px;
        }
        
        .sidebar .card-body {
            padding: 0;
        }
        
        .sidebar h6 {
            margin-bottom: 15px;
            font-weight: 600;
            color: #495057;
        }
        
        .sidebar .alert {
            border: none;
            border-radius: 15px;
            padding: 18px;
            margin-bottom: 20px;
        }
        
        .sidebar .btn {
            padding: 12px 20px;
            border-radius: 15px;
            font-weight: 500;
        }
        
        .sidebar .list-group-item {
            border: none;
            padding: 12px 0;
            background: transparent;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        
        .sidebar .list-group-item:hover {
            background: rgba(108, 92, 231, 0.05);
            padding-left: 10px;
        }
        
        /* Comments Section */
        .comments-section {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: 15px;
            display: none;
        }
        
        .comments-section.show {
            display: block;
        }
        
        .comment {
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .comment:last-child {
            border-bottom: none;
        }
        
        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* File Upload Styles */
        .multi-file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .file-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #dee2e6;
        }
        
        .file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-preview-remove {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .file-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .widgets-container,
            .sidebar-container {
                position: static;
                max-height: none;
                margin-bottom: 2rem;
            }
            
            .photo-grid.triple,
            .photo-grid.penta {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto;
            }
            
            .photo-grid.triple .photo-grid-item:first-child {
                grid-row: auto;
                grid-column: 1 / 3;
                height: 200px;
            }
            
            .photo-grid.penta .photo-grid-item:first-child {
                grid-row: auto;
                height: 200px;
            }
            
            .photo-grid.single .photo-grid-item {
                max-width: 100%;
                height: 280px;
            }
            
            .photo-grid.double .photo-grid-item,
            .photo-grid.quad .photo-grid-item {
                height: 180px;
            }
            
            .photo-grid.triple .photo-grid-item:not(:first-child) {
                height: 120px;
            }
            
            .photo-grid.penta .photo-grid-item:not(:first-child) {
                height: 120px;
            }
            
            .photo-grid.penta .photo-grid-item:nth-child(5) {
                grid-column: 1 / 3;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#" onclick="window.location.reload()">
                <i class="fas fa-star me-2"></i>Chloe Belle
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i>Feed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../subscription/plans.php">
                            <i class="fas fa-star me-1"></i>Subscribe
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['username']) ?>" class="current-user-avatar">
                            <?= htmlspecialchars($currentUser['username']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../user/profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="../user/settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <?php if ($currentUser['role'] === 'chloe'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/posts.php">
                                    <i class="fas fa-cogs me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../admin/">
                                    <i class="fas fa-shield-alt me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="row">
            <!-- Left Sidebar - Live Stats -->
            <div class="col-lg-3 order-lg-1 order-2">
                <div class="widgets-container">
                    <!-- Live Viewers Widget -->
                    <div class="widget-card">
                        <div class="widget-header live-widget-header">
                            <span class="live-indicator"></span>
                            Live Now (<?= $totalLiveViewers ?>)
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($liveViewers)): ?>
                                <div class="viewer-avatars-container">
                                    <?php foreach (array_slice($liveViewers, 0, 8) as $viewer): ?>
                                        <img src="<?= getAvatarUrl($viewer['avatar']) ?>" 
                                             alt="<?= htmlspecialchars($viewer['username']) ?>" 
                                             class="viewer-avatar"
                                             title="<?= htmlspecialchars($viewer['username']) ?>">
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($totalLiveViewers > 8): ?>
                                    <div class="viewer-count-text">
                                        +<?= $totalLiveViewers - 8 ?> more people online
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h6>All Quiet</h6>
                                    <p>No one else is online right now</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Likes Widget -->
                    <div class="widget-card">
                        <div class="widget-header love-widget-header">
                            <i class="fas fa-heart me-2"></i>Recent Love (24h)
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($recentLikes)): ?>
                                <div class="likes-scroll-container">
                                    <?php foreach ($recentLikes as $like): ?>
                                        <div class="like-item">
                                            <span class="like-emoji">❤️</span>
                                            <img src="<?= getAvatarUrl($like['avatar']) ?>" 
                                                 alt="<?= htmlspecialchars($like['username']) ?>" 
                                                 class="like-avatar">
                                            <div class="like-user-info">
                                                <div class="like-username"><?= htmlspecialchars($like['username']) ?></div>
                                                <div class="like-time"><?= timeAgo($like['created_at']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="widget-footer">
                                    <p class="widget-footer-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Spread the love by liking posts!
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-heart-broken"></i>
                                    <h6>No Recent Likes</h6>
                                    <p>Be the first to show some love!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Feed -->
            <div class="col-lg-6 order-lg-2 order-1">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Latest Posts</h2>
                    <div class="d-flex align-items-center">
                        <span class="live-indicator"></span>
                        <small class="text-muted">Live Feed</small>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Create Post Box (Admin Only) -->
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="card create-post-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <?php 
                                $avatarClass = 'user-avatar';
                                if ($currentUser['role'] === 'chloe') {
                                    $avatarClass .= ' chloe-avatar';
                                } elseif ($currentUser['role'] === 'admin') {
                                    $avatarClass .= ' admin-avatar';
                                }
                                ?>
                                <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" 
                                     alt="<?= htmlspecialchars($currentUser['username']) ?>" 
                                     class="<?= $avatarClass ?> me-3">
                                <div>
                                    <h6 class="mb-0">Create New Post</h6>
                                    <small class="text-muted">Share up to 5 photos or 1 video</small>
                                </div>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" id="createPostForm">
                                <input type="hidden" name="create_post" value="1">
                                
                                <!-- Hidden file inputs -->
                                <input type="file" id="photoUploads" name="photo_uploads[]" accept="image/*" multiple style="display: none;">
                                <input type="file" id="videoUpload" name="video_upload" accept="video/*" style="display: none;">
                                
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="title" placeholder="Post title (optional)">
                                </div>
                                
                                <div class="mb-3">
                                    <textarea class="form-control" name="content" rows="3" placeholder="What's on your mind?" required></textarea>
                                </div>
                                
                                <!-- Multi-file preview area -->
                                <div id="filePreview" style="display: none;">
                                    <div class="file-info">
                                        <span id="fileCount">0 files selected</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger float-end" onclick="clearFileSelection()">
                                            <i class="fas fa-times"></i> Clear All
                                        </button>
                                    </div>
                                    <div id="multiFilePreview" class="multi-file-preview"></div>
                                    <video id="videoPreview" class="post-media-video" style="display: none;" controls></video>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium">
                                            <label class="form-check-label" for="is_premium">
                                                <i class="fas fa-crown text-warning me-1"></i>Premium Content
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="subscription_required" id="subscription_required" disabled>
                                            <option value="none">No subscription required</option>
                                            <option value="monthly">Monthly subscribers</option>
                                            <option value="yearly">Yearly subscribers</option>
                                            <option value="lifetime">Lifetime subscribers</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="document.getElementById('photoUploads').click()">
                                            <i class="fas fa-images me-1"></i>Photos (Max 5)
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('videoUpload').click()">
                                            <i class="fas fa-video me-1"></i>Video
                                        </button>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Post
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Posts Display -->
                <?php if (empty($posts)): ?>
                    <div class="post-card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h4>No posts yet</h4>
                            <p class="text-muted">
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    Create your first post above to get started!
                                <?php else: ?>
                                    Check back later for new content from Chloe Belle!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                <div id="posts-container">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $hasAccess = hasPostAccess($post, $currentUser, $subscriptionActive);
                        
                        if (!$subscriptionActive && !in_array($currentUser['role'], ['admin', 'chloe'])) {
                            if ($post['is_premium'] || $_SESSION['free_posts_viewed'] >= 3) {
                                $hasAccess = false;
                            } else {
                                $_SESSION['free_posts_viewed']++;
                            }
                        }
                        ?>
                        
                        <div class="post-card" data-post-id="<?= $post['id'] ?>">
                            <div class="card-body">
                                <!-- Post Header -->
                                <div class="d-flex align-items-center mb-3">
                                    <?php 
                                    $posterAvatarClass = 'user-avatar';
                                    if ($post['role'] === 'chloe') {
                                        $posterAvatarClass .= ' chloe-avatar';
                                    } elseif ($post['role'] === 'admin') {
                                        $posterAvatarClass .= ' admin-avatar';
                                    }
                                    ?>
                                    <img src="<?= getAvatarUrl($post['avatar']) ?>" 
                                         alt="<?= htmlspecialchars($post['username']) ?>" 
                                         class="<?= $posterAvatarClass ?> me-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center">
                                            <h6 class="mb-0 me-2">
                                                <?= htmlspecialchars($post['username']) ?>
                                                <?php if ($post['role'] === 'chloe'): ?>
                                                    <i class="fas fa-heart text-danger ms-1" title="Chloe Belle"></i>
                                                <?php elseif ($post['role'] === 'admin'): ?>
                                                    <i class="fas fa-shield-alt text-primary ms-1" title="Admin"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <?php if ($post['is_premium'] && $hasAccess): ?>
                                                <span class="premium-badge">
                                                    <i class="fas fa-crown me-1"></i>Premium
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= timeAgo($post['created_at']) ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Post Title -->
                                <?php if ($post['title']): ?>
                                    <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                                <?php endif; ?>

                                <!-- Post Content -->
                                <?php if ($hasAccess || !$post['is_premium']): ?>
                                    <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                <?php elseif (!$post['media_url']): ?>
                                    <!-- Only blur text if there's no media -->
                                    <div class="premium-overlay" style="min-height: 120px;">
                                        <div class="premium-blur">
                                            <p class="card-text" style="padding: 20px 0;"><?= nl2br(htmlspecialchars(substr($post['content'], 0, 100))) ?>...</p>
                                        </div>
                                        <div class="premium-lock">
                                            <i class="fas fa-lock fa-2x mb-2"></i>
                                            <h6>Premium Content</h6>
                                            <p class="mb-3">Subscribe to unlock this exclusive content</p>
                                            <a href="../subscription/plans.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-crown me-1"></i>Subscribe Now
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Show normal text if there's media (media will be blurred instead) -->
                                    <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                <?php endif; ?>

                                <!-- Post Media -->
                                <?php if ($post['media_url']): ?>
                                    <?php 
                                    $mediaUrls = getMediaUrls($post['media_url']);
                                    $mediaCount = count($mediaUrls);
                                    
                                    // Check if user has access to ANY media content - BLUR ALL MEDIA FOR NON-SUBSCRIBERS
                                    $mediaAccess = $subscriptionActive || in_array($currentUser['role'], ['admin', 'chloe']);
                                    ?>
                                    
                                    <?php if ($mediaAccess): ?>
                                        <!-- Show normal media for subscribers/admin -->
                                        <?php if ($post['media_type'] === 'image' && !empty($mediaUrls)): ?>
                                            <div class="photo-grid <?= 
                                                $mediaCount === 1 ? 'single' : 
                                                ($mediaCount === 2 ? 'double' : 
                                                ($mediaCount === 3 ? 'triple' : 
                                                ($mediaCount === 4 ? 'quad' : 'penta'))) ?>">
                                                <?php foreach ($mediaUrls as $index => $mediaUrl): ?>
                                                    <div class="photo-grid-item" 
                                                         data-image-src="<?= htmlspecialchars($mediaUrl) ?>" 
                                                         data-image-index="<?= $index ?>" 
                                                         data-all-images="<?= htmlspecialchars(json_encode($mediaUrls)) ?>">
                                                        <img src="<?= htmlspecialchars($mediaUrl) ?>" 
                                                             alt="Post image <?= $index + 1 ?>"
                                                             loading="lazy">
                                                        <?php if ($index === 4 && $mediaCount > 5): ?>
                                                            <div class="photo-more-overlay">
                                                                +<?= $mediaCount - 5 ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif ($post['media_type'] === 'video' && !empty($mediaUrls)): ?>
                                            <div style="margin: 15px 0; display: flex; justify-content: center; background: #ffffff; border-radius: 15px; padding: 10px; border: 1px solid #e9ecef;">
                                                <video controls class="post-media-video" preload="metadata" style="max-height: 350px;">
                                                    <source src="<?= htmlspecialchars($mediaUrls[0]) ?>" type="video/mp4">
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Blur ALL media for non-subscribers -->
                                        <div class="premium-overlay" style="margin: 15px 0;">
                                            <div class="premium-blur">
                                                <?php if ($post['media_type'] === 'image' && !empty($mediaUrls)): ?>
                                                    <?php if ($mediaCount === 1): ?>
                                                        <div class="photo-grid single">
                                                            <div class="photo-grid-item">
                                                                <img src="<?= htmlspecialchars($mediaUrls[0]) ?>" alt="Premium content">
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="photo-grid <?= 
                                                            $mediaCount === 2 ? 'double' : 
                                                            ($mediaCount === 3 ? 'triple' : 
                                                            ($mediaCount === 4 ? 'quad' : 'penta')) ?>">
                                                            <?php foreach ($mediaUrls as $index => $mediaUrl): ?>
                                                                <div class="photo-grid-item">
                                                                    <img src="<?= htmlspecialchars($mediaUrl) ?>" 
                                                                         alt="Premium image <?= $index + 1 ?>">
                                                                    <?php if ($index === 4 && $mediaCount > 5): ?>
                                                                        <div class="photo-more-overlay">
                                                                            +<?= $mediaCount - 5 ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($post['media_type'] === 'video' && !empty($mediaUrls)): ?>
                                                    <div style="display: flex; justify-content: center; background: #ffffff; border-radius: 15px; padding: 10px; border: 1px solid #e9ecef;">
                                                        <video class="post-media-video" muted style="max-height: 350px;">
                                                            <source src="<?= htmlspecialchars($mediaUrls[0]) ?>" type="video/mp4">
                                                        </video>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="premium-lock">
                                                <i class="fas fa-lock fa-2x mb-2"></i>
                                                <h6>Exclusive Media</h6>
                                                <p class="mb-3">Subscribe to view this content</p>
                                                <a href="../subscription/plans.php" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-crown me-1"></i>Subscribe Now
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Post Actions -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <button class="like-btn me-3 <?= $post['user_liked'] ? 'liked' : '' ?>" 
                                                data-post-id="<?= $post['id'] ?>">
                                            <i class="fas fa-heart me-1"></i>
                                            <span class="like-count"><?= $post['like_count'] ?></span>
                                        </button>
                                        
                                        <?php if ($commentsEnabled): ?>
                                            <button class="comment-toggle me-3" data-post-id="<?= $post['id'] ?>">
                                                <i class="fas fa-comment me-1"></i>
                                                <span class="comment-count"><?= $commentCounts[$post['id']] ?? 0 ?></span> comments
                                            </button>
                                        <?php endif; ?>
                                        
                                        <span class="text-muted">
                                            <i class="fas fa-eye me-1"></i>
                                            <?= $post['views'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-flag me-2"></i>Report</a></li>
                                            <?php if ($currentUser['role'] === 'admin'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger delete-post" data-post-id="<?= $post['id'] ?>" href="#"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Comments Section -->
                                <?php if ($commentsEnabled): ?>
                                <div class="comments-section" id="comments-<?= $post['id'] ?>">
                                    <?php if (canCommentOnPost($post, $currentUser, $subscriptionActive, $commentsEnabled)): ?>
                                        <!-- Comment Form -->
                                        <form class="comment-form mb-3" data-post-id="<?= $post['id'] ?>">
                                            <div class="d-flex">
                                                <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" 
                                                     alt="<?= htmlspecialchars($currentUser['username']) ?>" 
                                                     class="comment-avatar me-2">
                                                <div class="flex-grow-1">
                                                    <textarea class="form-control form-control-sm comment-input" 
                                                            placeholder="Write a comment..." 
                                                            rows="2"></textarea>
                                                    <div class="text-end mt-2">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-paper-plane me-1"></i>Comment
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Comments List -->
                                    <div class="comments-list" id="comments-list-<?= $post['id'] ?>">
                                        <?php if (isset($comments[$post['id']])): ?>
                                            <?php foreach ($comments[$post['id']] as $comment): ?>
                                                <div class="comment" data-comment-id="<?= $comment['id'] ?>">
                                                    <div class="d-flex">
                                                        <img src="<?= getAvatarUrl($comment['avatar']) ?>" 
                                                             alt="<?= htmlspecialchars($comment['username']) ?>" 
                                                             class="comment-avatar me-2">
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center">
                                                                <strong class="me-2"><?= htmlspecialchars($comment['username']) ?></strong>
                                                                <small class="text-muted"><?= timeAgo($comment['created_at']) ?></small>
                                                                <?php if ($currentUser['id'] === $comment['user_id'] || in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                                                    <button class="btn btn-sm btn-link text-danger ms-auto" onclick="deleteComment(<?= $comment['id'] ?>, <?= $post['id'] ?>)">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted text-center">No comments yet. Be the first to comment!</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="col-lg-3 order-lg-3 order-3">
                <div class="sidebar-container">
                    <div class="sidebar">
                        <!-- Current User Info -->
                        <div class="text-center mb-4">
                            <img src="<?= getAvatarUrl($currentUser['avatar']) ?>" alt="Your Avatar" class="user-avatar mb-3" style="width: 80px; height: 80px;">
                            <h6><?= htmlspecialchars($currentUser['username']) ?></h6>
                            <a href="../user/profile.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-1"></i>Edit Profile
                            </a>
                        </div>

                        <!-- User Subscription Status -->
                        <div class="mb-4">
                            <h6>Your Subscription</h6>
                            <?php if ($currentUser['subscription_status'] === 'none'): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Free Account</strong><br>
                                    <small>Upgrade to access premium content</small>
                                </div>
                                <a href="../subscription/plans.php" class="btn btn-primary w-100">
                                    <i class="fas fa-crown me-2"></i>Subscribe Now
                                </a>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong><?= ucfirst($currentUser['subscription_status']) ?> Subscriber</strong><br>
                                    <?php if ($currentUser['subscription_expires']): ?>
                                        <small>Expires: <?= date('M j, Y', strtotime($currentUser['subscription_expires'])) ?></small>
                                    <?php else: ?>
                                        <small>Lifetime Access</small>
                                    <?php endif; ?>
                                </div>
                                <a href="../user/settings.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-cog me-1"></i>Manage Subscription
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Stats -->
                        <div class="mb-4">
                            <h6>Community Stats</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded">
                                        <strong><?= $totalPosts ?></strong><br>
                                        <small class="text-muted">Total Posts</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded">
                                        <strong><?= count($posts) ?></strong><br>
                                        <small class="text-muted">This Page</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h6>Quick Links</h6>
                            <div class="list-group list-group-flush">
                                <a href="../subscription/plans.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-star me-2"></i>Subscription Plans
                                </a>
                                <a href="../user/settings.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-cog me-2"></i>Account Settings
                                </a>
                                <?php if (in_array($currentUser['role'], ['admin', 'chloe'])): ?>
                                    <a href="../admin/index.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-shield-alt me-2"></i>Admin Panel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Image Modal with Gallery -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0 text-center position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index: 1050;"></button>
                    
                    <!-- Navigation arrows for multiple images -->
                    <button class="btn btn-light position-absolute start-0 top-50 translate-middle-y ms-3" 
                            id="prevImage" style="z-index: 1050; display: none;" onclick="changeModalImage(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn btn-light position-absolute end-0 top-50 translate-middle-y me-3" 
                            id="nextImage" style="z-index: 1050; display: none;" onclick="changeModalImage(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <img id="modalImage" src="" alt="" class="img-fluid rounded" style="max-height: 90vh;">
                    
                    <!-- Image counter -->
                    <div id="imageCounter" class="position-absolute bottom-0 start-50 translate-middle-x mb-3 text-white bg-dark bg-opacity-50 px-3 py-1 rounded" style="display: none;">
                        <span id="currentImageIndex">1</span> / <span id="totalImages">1</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let selectedFiles = [];
        let currentModalImages = [];
        let currentModalIndex = 0;

        // Premium subscription checkbox handler
        document.getElementById('is_premium')?.addEventListener('change', function() {
            document.getElementById('subscription_required').disabled = !this.checked;
            if (!this.checked) {
                document.getElementById('subscription_required').value = 'none';
            }
        });

        // Multiple photo upload handler
        document.getElementById('photoUploads')?.addEventListener('change', function() {
            const files = Array.from(this.files).slice(0, 5); // Max 5 files
            selectedFiles = files;
            
            if (files.length > 0) {
                document.getElementById('videoUpload').value = '';
                document.getElementById('fileCount').textContent = `${files.length} photo${files.length > 1 ? 's' : ''} selected`;
                document.getElementById('filePreview').style.display = 'block';
                document.getElementById('videoPreview').style.display = 'none';
                
                const preview = document.getElementById('multiFilePreview');
                preview.innerHTML = '';
                
                files.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'file-preview-item';
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${index + 1}">
                            <button type="button" class="file-preview-remove" onclick="removeFile(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        preview.appendChild(previewItem);
                    };
                    reader.readAsDataURL(file);
                });
            }
        });

        // Video upload handler
        document.getElementById('videoUpload')?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                
                document.getElementById('photoUploads').value = '';
                selectedFiles = [];
                document.getElementById('fileCount').textContent = `🎥 ${file.name} (${fileSize} MB)`;
                document.getElementById('filePreview').style.display = 'block';
                document.getElementById('multiFilePreview').innerHTML = '';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('videoPreview').src = e.target.result;
                    document.getElementById('videoPreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            
            if (selectedFiles.length === 0) {
                clearFileSelection();
                return;
            }
            
            // Update file count
            document.getElementById('fileCount').textContent = `${selectedFiles.length} photo${selectedFiles.length > 1 ? 's' : ''} selected`;
            
            // Rebuild preview
            const preview = document.getElementById('multiFilePreview');
            preview.innerHTML = '';
            
            selectedFiles.forEach((file, newIndex) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'file-preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${newIndex + 1}">
                        <button type="button" class="file-preview-remove" onclick="removeFile(${newIndex})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
            
            // Update the file input
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            document.getElementById('photoUploads').files = dt.files;
        }

        function clearFileSelection() {
            selectedFiles = [];
            document.getElementById('photoUploads').value = '';
            document.getElementById('videoUpload').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('multiFilePreview').innerHTML = '';
            document.getElementById('videoPreview').style.display = 'none';
        }

        // Enhanced image modal with gallery support
        function openImageModal(src, index = 0, allImages = []) {
            console.log('Opening modal with:', { src, index, allImages }); // Debug log
            
            currentModalImages = Array.isArray(allImages) ? allImages : [src];
            currentModalIndex = index;
            
            document.getElementById('modalImage').src = currentModalImages[currentModalIndex];
            document.getElementById('modalImage').alt = `Image ${currentModalIndex + 1}`;
            
            // Show/hide navigation controls
            const showNav = currentModalImages.length > 1;
            document.getElementById('prevImage').style.display = showNav ? 'block' : 'none';
            document.getElementById('nextImage').style.display = showNav ? 'block' : 'none';
            document.getElementById('imageCounter').style.display = showNav ? 'block' : 'none';
            
            if (showNav) {
                document.getElementById('currentImageIndex').textContent = currentModalIndex + 1;
                document.getElementById('totalImages').textContent = currentModalImages.length;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }

        function changeModalImage(direction) {
            currentModalIndex += direction;
            
            if (currentModalIndex < 0) {
                currentModalIndex = currentModalImages.length - 1;
            } else if (currentModalIndex >= currentModalImages.length) {
                currentModalIndex = 0;
            }
            
            document.getElementById('modalImage').src = currentModalImages[currentModalIndex];
            document.getElementById('currentImageIndex').textContent = currentModalIndex + 1;
        }

        // Keyboard navigation for modal
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('imageModal');
            if (modal.classList.contains('show')) {
                if (e.key === 'ArrowLeft') changeModalImage(-1);
                if (e.key === 'ArrowRight') changeModalImage(1);
                if (e.key === 'Escape') bootstrap.Modal.getInstance(modal).hide();
            }
        });

        // Event delegation for likes, comments, and photo modal
        document.addEventListener('click', function(e) {
            // Photo grid item click handler
            const photoGridItem = e.target.closest('.photo-grid-item');
            if (photoGridItem) {
                const imageSrc = photoGridItem.dataset.imageSrc;
                const imageIndex = parseInt(photoGridItem.dataset.imageIndex);
                const allImages = JSON.parse(photoGridItem.dataset.allImages);
                
                openImageModal(imageSrc, imageIndex, allImages);
                return;
            }

            const likeBtn = e.target.closest('.like-btn');
            if (likeBtn) {
                const postId = likeBtn.dataset.postId;
                const likeCount = likeBtn.querySelector('.like-count');
                const originalIcon = likeBtn.querySelector('i');
                originalIcon.className = 'fas fa-spinner fa-spin me-1';

                fetch('../api/likes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle', post_id: postId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        likeBtn.classList.toggle('liked', data.liked);
                        likeCount.textContent = data.like_count;
                        
                        // Refresh recent likes widget if like was added
                        if (data.liked) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }
                })
                .catch(error => console.error('Error:', error))
                .finally(() => {
                    originalIcon.className = 'fas fa-heart me-1';
                });
                return;
            }

            const toggle = e.target.closest('.comment-toggle');
            if (toggle) {
                const postId = toggle.dataset.postId;
                const commentsSection = document.getElementById(`comments-${postId}`);
                commentsSection.classList.toggle('show');
                return;
            }

            const deleteBtn = e.target.closest('.delete-post');
            if (deleteBtn) {
                e.preventDefault();
                const postId = deleteBtn.dataset.postId;
                deletePost(postId);
            }
        });

        // Comment form submission (same as before)
        document.addEventListener('submit', function(e) {
            if (!e.target.matches('.comment-form')) return;
            e.preventDefault();

            const form = e.target;
            const postId = form.dataset.postId;
            const textarea = form.querySelector('.comment-input');
            const content = textarea.value.trim();

            if (!content) {
                alert('Please enter a comment');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Posting...';
            submitBtn.disabled = true;

            fetch('../api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create', post_id: postId, content: content })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textarea.value = '';

                    const countElement = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = currentCount + 1;

                    const commentsList = document.getElementById(`comments-list-${postId}`);
                    const newCommentHtml = `
                        <div class="comment" data-comment-id="${data.comment.id}">
                            <div class="d-flex">
                                <img src="${data.comment.avatar_url}" alt="${data.comment.username}" class="comment-avatar me-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center">
                                        <strong class="me-2">${data.comment.username}</strong>
                                        <small class="text-muted">just now</small>
                                        <button class="btn btn-sm btn-link text-danger ms-auto" onclick="deleteComment(${data.comment.id}, ${postId})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <p class="mb-0">${data.comment.content}</p>
                                </div>
                            </div>
                        </div>
                    `;

                    if (commentsList.innerHTML.includes('No comments yet')) {
                        commentsList.innerHTML = newCommentHtml;
                    } else {
                        commentsList.insertAdjacentHTML('beforeend', newCommentHtml);
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to post comment'));
                }
            })
            .catch(error => {
                console.error('Error posting comment:', error);
                alert('Failed to post comment. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post?')) {
                return;
            }

            fetch('../api/posts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', post_id: postId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const postElement = document.querySelector(`[data-post-id="${postId}"]`);
                    postElement?.remove();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete post'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete post. Please try again.');
            });
        }

        function deleteComment(commentId, postId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }
            
            fetch('../api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', comment_id: commentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                    commentElement.remove();
                    
                    const countElement = document.querySelector(`[data-post-id="${postId}"] .comment-count`);
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = Math.max(0, currentCount - 1);
                    
                    const commentsList = document.getElementById(`comments-list-${postId}`);
                    if (commentsList.children.length === 0) {
                        commentsList.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete comment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete comment. Please try again.');
            });
        }

        // Form validation
        document.getElementById('createPostForm')?.addEventListener('submit', function(e) {
            const content = this.querySelector('[name="content"]').value.trim();
            if (!content) {
                e.preventDefault();
                alert('Please enter some content for your post.');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Posting...';
            submitBtn.disabled = true;
        });

        // Auto-refresh live stats every 30 seconds
        setInterval(() => {
            fetch('?ajax=live_stats')
                .then(response => response.json())
                .then(data => {
                    // Update live viewer count
                    if (data.live_viewers !== undefined) {
                        document.querySelector('.live-indicator').nextSibling.textContent = ` Live Now (${data.live_viewers})`;
                    }
                })
                .catch(error => console.log('Live stats update failed:', error));
        }, 30000);

        console.log('✨ Enhanced Feed page loaded successfully!');
        console.log('📊 Total posts:', <?= count($posts) ?>);
        console.log('👤 Current user role:', '<?= $currentUser['role'] ?>');
        console.log('💎 Subscription active:', <?= $subscriptionActive ? 'true' : 'false' ?>);
        console.log('👀 Live viewers:', <?= $totalLiveViewers ?>);
        console.log('❤️ Recent likes:', <?= count($recentLikes) ?>);
    </script>
</body>
</html>