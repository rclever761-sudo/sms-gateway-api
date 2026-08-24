<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Log raw incoming payload to Render logs
$rawInput = file_get_contents('php://input');
error_log("Incoming SMS Payload: " . $rawInput);

$data = json_decode($rawInput, true);
$message = $data['message'] ?? $_POST['message'] ?? '';

if (preg_match('/REF-\d+/i', $message, $matches)) {
    $reference = strtoupper($matches[0]);
    error_log("Extracted Reference: " . $reference);

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

        // Auto-create table
        $pdo->exec("CREATE TABLE IF NOT EXISTS deposits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'PENDING',
            updated_at DATETIME NULL
        )");

        // Ensure record exists before updating
        $checkStmt = $pdo->prepare("SELECT id FROM deposits WHERE reference = ?");
        $checkStmt->execute([$reference]);
        if ($checkStmt->rowCount() === 0) {
            $insertStmt = $pdo->prepare("INSERT INTO deposits (reference, status) VALUES (?, 'PENDING')");
            $insertStmt->execute([$reference]);
        }

        // Update deposit status
        $stmt = $pdo->prepare("UPDATE deposits SET status = 'SUCCESS', updated_at = NOW() WHERE reference = ?");
        $stmt->execute([$reference]);

        error_log("SUCCESS: Updated database for reference " . $reference);

        echo json_encode([
            'status' => 'success',
            'reference' => $reference,
            'rows_updated' => $stmt->rowCount()
        ]);
    } catch (PDOException $e) {
        error_log("DATABASE ERROR: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    error_log("NO MATCH: Message did not contain REF-xxxx");
    echo json_encode([
        'status' => 'error',
        'message' => 'No matching reference code found in SMS'
    ]);
}
