<?php
// Save as: /debug-status.php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/settings.php';

header('Content-Type: text/plain');

echo "=== SESSION ===\n";
print_r($_SESSION);

echo "\n=== SETTINGS ===\n";
echo "maintenance_mode: " . (isMaintenanceMode() ? 'ON' : 'OFF') . "\n";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : '(undef)') . "\n";
echo "DEBUG_MODE: " . (defined('DEBUG_MODE') ? (DEBUG_MODE?'true':'false') : '(undef)') . "\n";

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "\n=== DB TEST ===\n";
    $u = $pdo->query("SELECT id, username, role FROM users ORDER BY id ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users sample: " . json_encode($u) . "\n";
    $counts = [];
    foreach (['posts','comments','subscriptions'] as $t) {
        try {
            $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        } catch (Exception $e) { $counts[$t] = 'err: '.$e->getMessage(); }
    }
    echo "Counts: " . json_encode($counts) . "\n";
} catch (Exception $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

echo "\n=== PHP INFO (limited) ===\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Loaded extensions: " . implode(', ', get_loaded_extensions()) . "\n";
