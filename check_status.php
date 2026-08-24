<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$reference = isset($_GET['ref']) ? strtoupper(trim($_GET['ref'])) : '';

if (empty($reference)) {
    echo json_encode(['status' => 'error', 'message' => 'Reference code required']);
    exit;
}

$host = 'mysql-ddc4692-rclever761-3f21.h.aivencloud.com';
$port = '23985';
$db   = 'defaultdb';
$user = 'avnadmin';
$pass = 'AVNS_Q1eviPJ1owGEHyV5PCy';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]);

    $stmt = $pdo->prepare("SELECT status, updated_at FROM deposits WHERE reference = ?");
    $stmt->execute([$reference]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'status' => 'found',
            'deposit_status' => $result['status'],
            'updated_at' => $result['updated_at']
        ]);
    } else {
        echo json_encode(['status' => 'not_found', 'deposit_status' => 'PENDING']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
