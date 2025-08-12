<?php
/**
 * MINIMAL WORKING Comments Migration
 * Just creates the essential tables - no triggers, no complex queries
 * 
 * Save as: /migrations/minimal_comments.php
 */

require_once '../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>Creating Comments Tables</h2><pre>";

    // 1. Create comments table
    echo "Creating comments table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) DEFAULT 0,
            likes_count INT DEFAULT 0
        )
    ");
    echo "✅ Comments table created\n\n";

    // 2. Create comment_likes table  
    echo "Creating comment_likes table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (comment_id, user_id)
        )
    ");
    echo "✅ Comment_likes table created\n\n";

    // 3. Add comments_count to posts (check if exists first)
    echo "Adding comments_count to posts table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM posts")->fetchAll();
    $hasCommentsCount = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'comments_count') {
            $hasCommentsCount = true;
            break;
        }
    }

    if (!$hasCommentsCount) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN comments_count INT DEFAULT 0");
        echo "✅ Comments_count column added\n";
    } else {
        echo "ℹ️ Comments_count column already exists\n";
    }

    // 4. Set all existing posts to 0 comments
    echo "Initializing comment counts...\n";
    $pdo->exec("UPDATE posts SET comments_count = 0");
    echo "✅ Comment counts initialized\n\n";

    echo "🎉 SUCCESS! All tables created.\n";
    echo "Next: Create /api/comments.php and update your feed page.\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "<pre style='color: red;'>";
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "</pre>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
h2 { color: #333; }
</style>