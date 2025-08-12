
<?php
session_start();
require_once '../config.php';
require_once '../settings.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Forbidden']);
    exit;
}
$userId = intval($_POST['user_id'] ?? 0);
$ban = intval($_POST['ban'] ?? 0) ? 1 : 0;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    // Ensure column exists
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS can_comment TINYINT(1) NOT NULL DEFAULT 1");
    $stmt = $pdo->prepare("UPDATE users SET can_comment = ? WHERE id = ?");
    $stmt->execute([$ban?0:1, $userId]);
    echo json_encode(['success'=>true,'can_comment'=>$ban?0:1]);
} catch (Exception $e) {
    error_log('toggle_comment_ban: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
