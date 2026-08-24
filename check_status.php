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

    $code = $_GET['code'] ?? $_GET['ref'] ?? $_GET['reference'] ?? '';

    if (empty($code)) {
        echo json_encode(["status" => "error", "message" => "No code provided"]);
        exit();
    }

    $rawNum = preg_replace('/[^0-9]/', '', $code);
    $hyphenated = "REF-" . $rawNum;
    $clean = "REF" . $rawNum;

    // Check database for either format
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
            "message" => "Payment confirmed",
            "data" => $transaction
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
