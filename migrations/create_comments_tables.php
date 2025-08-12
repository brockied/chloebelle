<?php
/**
 * Comments Database Migration
 * Run this once to create the comments tables if they don't exist
 * Save as: migrations/create_comments_tables.php
 */

require_once '../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>🗄️ Creating Comments Tables</h2><pre>";

    // 1. Create comments table
    echo "Creating comments table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            content TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'hidden') DEFAULT 'approved',
            likes INT DEFAULT 0,
            is_edited TINYINT(1) DEFAULT 0,
            edited_at DATETIME DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_post_id (post_id),
            INDEX idx_user_id (user_id),
            INDEX idx_parent_id (parent_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Comments table created\n";

    // 2. Create likes table (if it doesn't exist)
    echo "Creating/updating likes table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            post_id INT DEFAULT NULL,
            comment_id INT DEFAULT NULL,
            type ENUM('like', 'dislike', 'heart', 'fire') DEFAULT 'like',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_user_post_like (user_id, post_id),
            UNIQUE KEY unique_user_comment_like (user_id, comment_id),
            INDEX idx_user_id (user_id),
            INDEX idx_post_id (post_id),
            INDEX idx_comment_id (comment_id),
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Likes table created/updated\n";

    // 3. Add comments_count column to posts table if it doesn't exist
    echo "Checking posts table for comments_count column...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM posts")->fetchAll();
    $hasCommentsCount = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'comments_count') {
            $hasCommentsCount = true;
            break;
        }
    }

    if (!$hasCommentsCount) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN comments_count INT DEFAULT 0 AFTER views");
        echo "✅ Added comments_count column to posts table\n";
    } else {
        echo "ℹ️ Comments_count column already exists in posts table\n";
    }

    // 4. Update existing posts' comment counts
    echo "Updating comment counts for existing posts...\n";
    $pdo->exec("
        UPDATE posts p 
        SET comments_count = (
            SELECT COUNT(*) 
            FROM comments c 
            WHERE c.post_id = p.id AND (c.status = 'approved' OR c.status IS NULL)
        )
    ");
    echo "✅ Comment counts updated\n";

    // 5. Add likes_count column to posts table if it doesn't exist
    echo "Checking posts table for likes_count column...\n";
    $hasLikesCount = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'likes_count') {
            $hasLikesCount = true;
            break;
        }
    }

    if (!$hasLikesCount) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN likes_count INT DEFAULT 0 AFTER comments_count");
        echo "✅ Added likes_count column to posts table\n";
    } else {
        echo "ℹ️ Likes_count column already exists in posts table\n";
    }

    // 6. Update existing posts' like counts
    echo "Updating like counts for existing posts...\n";
    $pdo->exec("
        UPDATE posts p 
        SET likes_count = (
            SELECT COUNT(*) 
            FROM likes l 
            WHERE l.post_id = p.id
        )
    ");
    echo "✅ Like counts updated\n";

    // 7. Create a trigger to automatically update comment counts (optional)
    echo "Creating triggers for automatic count updates...\n";
    
    // Drop triggers if they exist
    $pdo->exec("DROP TRIGGER IF EXISTS update_comment_count_insert");
    $pdo->exec("DROP TRIGGER IF EXISTS update_comment_count_delete");
    
    // Create insert trigger
    $pdo->exec("
        CREATE TRIGGER update_comment_count_insert
        AFTER INSERT ON comments
        FOR EACH ROW
        UPDATE posts SET comments_count = comments_count + 1 WHERE id = NEW.post_id
    ");
    
    // Create delete trigger
    $pdo->exec("
        CREATE TRIGGER update_comment_count_delete
        AFTER DELETE ON comments
        FOR EACH ROW
        UPDATE posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = OLD.post_id
    ");
    
    echo "✅ Triggers created for automatic comment count updates\n";

    echo "\n🎉 SUCCESS! Comments system is ready!\n";
    echo "\nNext steps:\n";
    echo "1. Replace your feed/index.php with the updated version\n";
    echo "2. Create the api/comments.php file\n";
    echo "3. Test commenting functionality\n";
    echo "\nFeatures now available:\n";
    echo "- ✅ View comments (loaded in PHP)\n";
    echo "- ✅ Add new comments (AJAX)\n";
    echo "- ✅ Delete comments (AJAX)\n";
    echo "- ✅ Like comments (AJAX)\n";
    echo "- ✅ Automatic comment/like counts\n";
    echo "- ✅ Premium content comment restrictions\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "<pre style='color: red;'>";
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 800px; 
    margin: 0 auto; 
    padding: 20px; 
    background: #f5f5f5;
}
pre { 
    background: #fff; 
    padding: 15px; 
    border-radius: 5px; 
    border-left: 4px solid #007cba;
    overflow-x: auto;
}
h2 { 
    color: #333; 
    text-align: center;
}
</style>