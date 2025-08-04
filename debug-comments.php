<?php
/**
 * Debug Comments System
 * Upload this to your root directory and visit it to test the comments system
 */

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Please log in first by visiting your feed page');
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>🔍 Comments System Debug</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";

    // Test 1: Check tables exist
    echo "<h3>1. Database Tables Check</h3>";
    
    $tables = ['comments', 'likes', 'posts'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists<br>";
            } else {
                echo "❌ Table '$table' missing<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "<br>";
        }
    }

    // Test 2: Check table structure
    echo "<h3>2. Comments Table Structure</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE comments");
        $columns = $stmt->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }

    // Test 3: Check posts exist
    echo "<h3>3. Posts Check</h3>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM posts WHERE status = 'published'");
        $result = $stmt->fetch();
        echo "📊 Published posts: {$result['count']}<br>";
        
        if ($result['count'] > 0) {
            $stmt = $pdo->query("SELECT id, title, user_id FROM posts WHERE status = 'published' LIMIT 3");
            $posts = $stmt->fetchAll();
            echo "<strong>Sample posts:</strong><br>";
            foreach ($posts as $post) {
                echo "- Post ID: {$post['id']}, Title: '" . ($post['title'] ?: 'No title') . "', User ID: {$post['user_id']}<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }

    // Test 4: Check comments exist
    echo "<h3>4. Comments Check</h3>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM comments WHERE is_deleted = 0");
        $result = $stmt->fetch();
        echo "💬 Total comments: {$result['count']}<br>";
        
        if ($result['count'] > 0) {
            $stmt = $pdo->query("SELECT c.id, c.post_id, c.content, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.is_deleted = 0 LIMIT 3");
            $comments = $stmt->fetchAll();
            echo "<strong>Sample comments:</strong><br>";
            foreach ($comments as $comment) {
                echo "- Comment ID: {$comment['id']}, Post ID: {$comment['post_id']}, User: {$comment['username']}, Content: '" . substr($comment['content'], 0, 50) . "'<br>";
            }
        } else {
            echo "ℹ️ No comments found. Let's create a test comment.<br>";
            
            // Get first post
            $stmt = $pdo->query("SELECT id FROM posts WHERE status = 'published' LIMIT 1");
            $post = $stmt->fetch();
            
            if ($post) {
                // Create test comment
                $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$post['id'], $_SESSION['user_id'], 'This is a test comment created by the debug script!']);
                echo "✅ Test comment created for post ID: {$post['id']}<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }

    // Test 5: Check API files exist
    echo "<h3>5. API Files Check</h3>";
    
    $apiFiles = ['api/comments.php', 'api/likes.php'];
    foreach ($apiFiles as $file) {
        if (file_exists($file)) {
            echo "✅ File '$file' exists<br>";
        } else {
            echo "❌ File '$file' missing<br>";
        }
    }

    // Test 6: Test API endpoint directly
    echo "<h3>6. API Test</h3>";
    if (file_exists('api/comments.php')) {
        echo "<button onclick=\"testAPI()\" style='padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test Comments API</button><br>";
        echo "<div id='apiResult' style='margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 5px; display: none;'></div>";
    }

    // Test 7: Current user info
    echo "<h3>7. Current User</h3>";
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        echo "👤 Logged in as: {$user['username']} (ID: {$user['id']}, Role: {$user['role']})<br>";
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; padding: 20px;'>";
    echo "<h2>❌ Database Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<script>
async function testAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '🔄 Testing API...';
    
    try {
        // Test loading comments for first post
        const response = await fetch('api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'load',
                post_id: 1  // Test with post ID 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `
                <strong>✅ API Working!</strong><br>
                Comments loaded: ${data.comments.length}<br>
                <pre>${JSON.stringify(data, null, 2)}</pre>
            `;
        } else {
            resultDiv.innerHTML = `
                <strong>❌ API Error:</strong><br>
                ${data.message}<br>
                <pre>${JSON.stringify(data, null, 2)}</pre>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <strong>❌ Network Error:</strong><br>
            ${error.message}<br>
            Check browser console for more details.
        `;
        console.error('API Test Error:', error);
    }
}
</script>

<style>
body { 
    font-family: Arial, sans-serif; 
    background: #f5f5f5; 
    margin: 0; 
    padding: 20px; 
}
h2, h3 { 
    color: #333; 
}
table { 
    margin: 10px 0; 
}
th, td { 
    padding: 8px; 
    text-align: left; 
}
th { 
    background: #e9ecef; 
}
pre {
    background: white;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
    max-height: 300px;
}
</style>