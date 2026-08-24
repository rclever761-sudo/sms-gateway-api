<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db.php';

    // Ensure sms_logs table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender VARCHAR(50),
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 1. Capture incoming SMS data from Android Forwarder App
    $sender = $_POST['sender'] ?? $_POST['from'] ?? '';
    $message = $_POST['message'] ?? $_POST['text'] ?? '';

    if (empty($message)) {
        // Handle raw JSON input if app sends JSON payload
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $sender = $jsonData['sender'] ?? $jsonData['from'] ?? $sender;
        $message = $jsonData['message'] ?? $jsonData['text'] ?? $message;
    }

    if (empty($message)) {
        echo json_encode(["status" => "error", "message" => "No SMS message payload received."]);
        exit();
    }

    // 2. Log raw SMS entry
    $stmt = $pdo->prepare("INSERT INTO sms_logs (sender, message) VALUES (?, ?)");
    $stmt->execute([$sender, $message]);

    // 3. Extract reference code (e.g., REF-71348 or 71348)
    preg_match('/REF-?\d+/i', $message, $matches);
    
    if (!empty($matches[0])) {
        $ref_code = strtoupper(str_replace('-', '', $matches[0])); // Normalize format: REF71348

        // Check if transaction exists, or create/update it to COMPLETED
        $checkStmt = $pdo->prepare("SELECT * FROM transactions WHERE ref_code = ? OR ref_code = ?");
        $checkStmt->execute([$ref_code, str_replace('REF', 'REF-', $ref_code)]);
        $txn = $checkStmt->fetch();

        if ($txn) {
            $updateStmt = $pdo->prepare("UPDATE transactions SET status = 'COMPLETED', deposit_status = 'COMPLETED' WHERE id = ?");
            $updateStmt->execute([$txn['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO transactions (ref_code, amount, status, deposit_status) VALUES (?, 10000.00, 'COMPLETED', 'COMPLETED')");
            $insertStmt->execute([$ref_code]);
        }

        echo json_encode(["status" => "success", "message" => "Transaction approved for " . $ref_code]);
        exit();
    }

    echo json_encode(["status" => "ignored", "message" => "SMS logged but no REF code detected."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Webhook Error: " . $e->getMessage()]);
}
?>
