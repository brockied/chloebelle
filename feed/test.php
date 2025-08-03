<?php
/**
 * Simple Feed Test Page - for debugging
 * Save as: feed/test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config.php';

echo "<h2>Feed Debug Test</h2>";

// Test 1: Check if user is logged in
echo "<h3>1. User Login Check</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User is logged in. ID: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ User not logged in. Redirecting...<br>";
    echo "<a href='../auth/login.php'>Click here to login</a><br>";
    exit;
}

// Test 2: Database connection
echo "<h3>2. Database Connection</h3>";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Database connected successfully<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 3: Get current user
echo "<h3>3. Current User</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    
    if ($currentUser) {
        echo "✅ User found: " . htmlspecialchars($currentUser['username']) . "<br>";
        echo "Role: " . htmlspecialchars($currentUser['role']) . "<br>";
        echo "Subscription: " . htmlspecialchars($currentUser['subscription_status']) . "<br>";
    } else {
        echo "❌ User not found in database<br>";
        exit;
    }
} catch (Exception $e) {
    echo "❌ User query error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 4: Check tables exist
echo "<h3>4. Database Tables</h3>";
$tables = ['users', 'posts', 'comments', 'comment_likes'];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "✅ Table '$table' exists with $count records<br>";
    } catch (Exception $e) {
        echo "❌ Table '$table' error: " . $e->getMessage() . "<br>";
    }
}

// Test 5: Check posts query
echo "<h3>5. Posts Query Test</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.avatar,
            u.role,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $posts = $stmt->fetchAll();
    
    echo "✅ Posts query successful. Found " . count($posts) . " posts<br>";
    
    if (!empty($posts)) {
        echo "<strong>Sample post:</strong><br>";
        echo "- Title: " . htmlspecialchars($posts[0]['title'] ?: 'No title') . "<br>";
        echo "- Author: " . htmlspecialchars($posts[0]['username']) . "<br>";
        echo "- Comments count: " . ($posts[0]['comments_count'] ?? 'NULL') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Posts query error: " . $e->getMessage() . "<br>";
    
    // Check if comments_count column exists
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM posts")->fetchAll();
        $hasCommentsCount = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'comments_count') {
                $hasCommentsCount = true;
                break;
            }
        }
        
        if (!$hasCommentsCount) {
            echo "❌ Missing comments_count column. Run this SQL:<br>";
            echo "<code>ALTER TABLE posts ADD COLUMN comments_count INT DEFAULT 0;</code><br>";
        }
    } catch (Exception $e2) {
        echo "❌ Column check error: " . $e2->getMessage() . "<br>";
    }
}

// Test 6: Comments query
echo "<h3>6. Comments Query Test</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            u.avatar,
            u.role
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.is_deleted = 0
        LIMIT 3
    ");
    $stmt->execute();
    $comments = $stmt->fetchAll();
    
    echo "✅ Comments query successful. Found " . count($comments) . " comments<br>";
} catch (Exception $e) {
    echo "❌ Comments query error: " . $e->getMessage() . "<br>";
}

echo "<h3>7. Recommendations</h3>";
echo "If all tests pass, your main feed should work. Try these:<br>";
echo "1. Go back to <a href='index.php'>feed/index.php</a><br>";
echo "2. If it still doesn't work, check the PHP error log<br>";
echo "3. View page source to see if there are any PHP errors<br>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 20px; }
code { background: #f4f4f4; padding: 2px 4px; }
</style>