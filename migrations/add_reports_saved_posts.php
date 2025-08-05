<?php
/**
 * Database Migration: Add Comment Reports and Saved Posts Tables
 * Run this file once to add the required tables
 */

require_once '../config.php';

echo "<h2>🔧 Adding Comment Reports and Saved Posts Tables</h2>";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<pre>";
    echo "Connected to database: " . DB_NAME . "\n\n";

    // Create comment_reports table
    echo "Creating comment_reports table...\n";
    $commentReportsTable = "
        CREATE TABLE IF NOT EXISTS comment_reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            comment_id INT NOT NULL,
            reporter_id INT NOT NULL,
            reason ENUM('spam', 'harassment', 'inappropriate', 'hate_speech', 'other') NOT NULL DEFAULT 'other',
            status ENUM('pending', 'dismissed', 'action_taken') NOT NULL DEFAULT 'pending',
            action_taken VARCHAR(100) NULL COMMENT 'Action taken if resolved (comment_deleted, user_warned, etc.)',
            handled_by INT NULL COMMENT 'Admin user ID who handled the report',
            handled_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
            
            INDEX idx_comment_reports_status (status),
            INDEX idx_comment_reports_created (created_at),
            INDEX idx_comment_reports_reporter (reporter_id),
            INDEX idx_comment_reports_comment (comment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($commentReportsTable);
    echo "✅ comment_reports table created successfully\n\n";

    // Create saved_posts table
    echo "Creating saved_posts table...\n";
    $savedPostsTable = "
        CREATE TABLE IF NOT EXISTS saved_posts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            post_id INT NOT NULL,
            saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_user_post (user_id, post_id),
            INDEX idx_saved_posts_user (user_id),
            INDEX idx_saved_posts_created (saved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($savedPostsTable);
    echo "✅ saved_posts table created successfully\n\n";

    // Add sample data for testing (optional)
    echo "Tables created successfully!\n\n";
    
    echo "📋 Summary:\n";
    echo "- ✅ comment_reports table: For handling reported comments\n";
    echo "- ✅ saved_posts table: For user bookmarked posts\n\n";
    
    echo "🔧 Next Steps:\n";
    echo "1. Add admin/reports.php to handle comment reports\n";
    echo "2. Add api/saved_posts.php for bookmark functionality\n";
    echo "3. Update feed to show saved posts section\n\n";
    
    echo "Features now available:\n";
    echo "- 📢 Comment reporting system\n";
    echo "- 💾 Save/bookmark posts functionality\n";
    echo "- 🛡️ Admin moderation panel\n";
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