<?php
/**
 * Test API Functionality
 * Upload this and visit to test if the APIs are working
 */

session_start();
require_once 'config.php';

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

    // Get first post for testing
    $stmt = $pdo->query("SELECT id, title FROM posts WHERE status = 'published' LIMIT 1");
    $post = $stmt->fetch();

    if (!$post) {
        die('No posts found to test with');
    }

    echo "<h2>🧪 API Functionality Test</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    echo "<p><strong>Testing with Post ID:</strong> {$post['id']} - {$post['title']}</p>";

} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<div id="results" style="margin-top: 20px;"></div>

<button onclick="testComments()" style="padding: 10px 20px; margin: 5px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer;">
    Test Comments API
</button>

<button onclick="testLikes()" style="padding: 10px 20px; margin: 5px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
    Test Likes API
</button>

<button onclick="createTestComment()" style="padding: 10px 20px; margin: 5px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">
    Create Test Comment
</button>

<button onclick="clearResults()" style="padding: 10px 20px; margin: 5px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
    Clear Results
</button>

<script>
const POST_ID = <?= $post['id'] ?>;

function logResult(title, data, success = true) {
    const results = document.getElementById('results');
    const color = success ? '#d4edda' : '#f8d7da';
    const icon = success ? '✅' : '❌';
    
    results.innerHTML += `
        <div style="margin: 10px 0; padding: 15px; background: ${color}; border-radius: 5px;">
            <h4>${icon} ${title}</h4>
            <pre style="background: white; padding: 10px; border-radius: 3px; overflow-x: auto;">${JSON.stringify(data, null, 2)}</pre>
        </div>
    `;
}

async function testComments() {
    try {
        const response = await fetch('api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'load',
                post_id: POST_ID
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            logResult('Comments API - Load Comments', data, true);
        } else {
            logResult('Comments API - Load Comments (Failed)', data, false);
        }
    } catch (error) {
        logResult('Comments API - Network Error', {error: error.message}, false);
    }
}

async function testLikes() {
    try {
        const response = await fetch('api/likes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'toggle',
                post_id: POST_ID
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            logResult('Likes API - Toggle Like', data, true);
        } else {
            logResult('Likes API - Toggle Like (Failed)', data, false);
        }
    } catch (error) {
        logResult('Likes API - Network Error', {error: error.message}, false);
    }
}

async function createTestComment() {
    try {
        const response = await fetch('api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create',
                post_id: POST_ID,
                content: 'This is a test comment created at ' + new Date().toLocaleTimeString()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            logResult('Comments API - Create Comment', data, true);
        } else {
            logResult('Comments API - Create Comment (Failed)', data, false);
        }
    } catch (error) {
        logResult('Comments API - Network Error', {error: error.message}, false);
    }
}

function clearResults() {
    document.getElementById('results').innerHTML = '';
}

// Auto-test on page load
window.addEventListener('load', function() {
    setTimeout(() => {
        testComments();
    }, 500);
});
</script>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    margin: 0;
    padding: 20px;
}

h2 {
    text-align: center;
    color: #333;
}

button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

pre {
    font-size: 12px;
    max-height: 300px;
    overflow-y: auto;
}
</style>

</div>