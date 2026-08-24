<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$rawInput = file_get_contents('php://input');
error_log("--> RAW INPUT: " . $rawInput);

$data = json_decode($rawInput, true);
$message = $data['message'] ?? $data['sms_body'] ?? $data['text'] ?? $_POST['message'] ?? $_POST['text'] ?? $rawInput ?? '';

if (preg_match('/REF-\d+/i', $message, $matches)) {
    $reference = strtoupper($matches[0]);

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

        $pdo->exec("CREATE TABLE IF NOT EXISTS deposits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(50) NULL,
            status VARCHAR(50) DEFAULT 'PENDING',
            updated_at DATETIME NULL
        )");

        $checkStmt = $pdo->prepare("SELECT status FROM deposits WHERE reference = ?");
        $checkStmt->execute([$reference]);
        
        if ($checkStmt->rowCount() === 0) {
            $insertStmt = $pdo->prepare("INSERT INTO deposits (reference, status) VALUES (?, 'PENDING')");
            $insertStmt->execute([$reference]);
        }

        $stmt = $pdo->prepare("UPDATE deposits SET status = 'SUCCESS', updated_at = NOW() WHERE reference = ?");
        $stmt->execute([$reference]);

        echo json_encode([
            'status' => 'success',
            'reference' => $reference,
            'rows_updated' => $stmt->rowCount()
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No matching reference code found in SMS']);
}
