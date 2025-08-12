
<?php
session_start();
require_once '../config.php';
require_once '../settings.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}
$payload = json_decode(file_get_contents('php://input'), true);
$subscriptionId = $payload['subscriptionID'] ?? null;
if (!$subscriptionId) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing subscription ID']);
    exit;
}
// TODO: validate with PayPal API using stored credentials
// For now, mark subscription active locally
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, provider, provider_subscription_id, status, plan_type, started_at) VALUES (?, 'paypal', ?, 'active', 'monthly', NOW())
        ON DUPLICATE KEY UPDATE status='active', canceled_at=NULL, ends_at=NULL");
    $stmt->execute([$_SESSION['user_id'], $subscriptionId]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    error_log('paypal-activate: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
