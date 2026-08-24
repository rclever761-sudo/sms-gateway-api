<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db.php';

    // Read payload from POST or GET
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);

    $code = $_REQUEST['code'] ?? $_REQUEST['ref'] ?? $_REQUEST['reference'] ?? $jsonData['code'] ?? $jsonData['ref'] ?? '';

    // If debug view is requested
    if (isset($_GET['action']) && $_GET['action'] === 'debug_logs') {
        $logs = $pdo->query("SELECT * FROM sms_logs ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        $txs = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "sms_logs" => $logs, "transactions" => $txs]);
        exit();
    }

    if (empty($code)) {
        echo json_encode(["status" => "error", "message" => "No reference code provided"]);
        exit();
    }

    // Clean digits and format variations
    $rawNum = preg_replace('/[^0-9]/', '', $code);
    $hyphenated = "REF-" . $rawNum;
    $clean = "REF" . $rawNum;

    // Search database for completed status
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE (ref_code = ? OR ref_code = ?) 
          AND (status = 'COMPLETED' OR deposit_status = 'COMPLETED')
        LIMIT 1
    ");
    $stmt->execute([$hyphenated, $clean]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transaction) {
        echo json_encode([
            "status" => "success",
            "deposit_status" => "COMPLETED",
            "payment_status" => "COMPLETED",
            "verified" => true,
            "message" => "Deposit confirmed!"
        ]);
    } else {
        echo json_encode([
            "status" => "pending",
            "deposit_status" => "PENDING",
            "verified" => false,
            "message" => "Payment not detected yet"
        ]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
