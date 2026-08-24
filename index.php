<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// 1. Read incoming body payload sent by SMS Forwarder
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$message = $data['message'] ?? $_POST['message'] ?? '';

// 2. Extract reference code (e.g., REF-12345)
if (preg_match('/REF-\d+/i', $message, $matches)) {
    $reference = strtoupper($matches[0]);

    // Your exact Aiven Database Credentials
    $host = 'mysql-ddc4692-rclever761-3f21.aivencloud.com';
    $port = '25985';
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

        $stmt = $pdo->prepare("UPDATE deposits SET status = 'SUCCESS', updated_at = NOW() WHERE reference = ? AND status = 'PENDING'");
        $stmt->execute([$reference]);

        echo json_encode([
            'status' => 'success',
            'reference' => $reference,
            'rows_updated' => $stmt->rowCount()
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No matching reference code found in SMS'
    ]);
}
