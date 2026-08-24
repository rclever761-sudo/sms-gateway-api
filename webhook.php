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

    // Capture input across GET, POST, or JSON raw body
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);

    $sender = $_REQUEST['sender'] ?? $_REQUEST['from'] ?? $_REQUEST['phone'] ?? $jsonData['sender'] ?? $jsonData['from'] ?? $jsonData['phone'] ?? 'unknown';
    $message = $_REQUEST['message'] ?? $_REQUEST['text'] ?? $_REQUEST['body'] ?? $jsonData['message'] ?? $jsonData['text'] ?? $jsonData['body'] ?? $rawInput;

    if (empty($message)) {
        echo json_encode(["status" => "error", "message" => "Empty body"]);
        exit();
    }

    // Always log incoming payload
    $stmt = $pdo->prepare("INSERT INTO sms_logs (sender, message) VALUES (?, ?)");
    $stmt->execute([$sender, $message]);

    // Extract reference code
    if (preg_match('/REF-?\s*([A-Za-z0-9]+)/i', $message, $matches)) {
        $rawNum = trim($matches[1]);
        $ref_code = "REF-" . $rawNum;
        $ref_code_clean = "REF" . $rawNum;

        $amount = "10000.00";
        if (preg_match('/(?:UGX|UGx|ugx)\s*([\d,]+)/i', $message, $amtMatches)) {
            $amount = str_replace(',', '', $amtMatches[1]);
        }

        // Save hyphenated code
        $stmt1 = $pdo->prepare("
            INSERT INTO transactions (ref_code, amount, status, deposit_status) 
            VALUES (?, ?, 'COMPLETED', 'COMPLETED')
            ON DUPLICATE KEY UPDATE status = 'COMPLETED', deposit_status = 'COMPLETED'
        ");
        $stmt1->execute([$ref_code, $amount]);

        // Save unhyphenated code
        $stmt2 = $pdo->prepare("
            INSERT INTO transactions (ref_code, amount, status, deposit_status) 
            VALUES (?, ?, 'COMPLETED', 'COMPLETED')
            ON DUPLICATE KEY UPDATE status = 'COMPLETED', deposit_status = 'COMPLETED'
        ");
        $stmt2->execute([$ref_code_clean, $amount]);

        echo json_encode(["status" => "success", "ref" => $ref_code]);
    } else {
        echo json_encode(["status" => "logged_no_code", "raw" => $message]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
