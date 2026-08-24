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
    if (!file_exists('db.php')) {
        throw new Exception("db.php file is missing from the server.");
    }
    require_once 'db.php';

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Quick log inspector endpoint
    if ($action === 'debug_logs') {
        $stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY id DESC LIMIT 5");
        $logs = $stmt->fetchAll();
        $txns = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 5")->fetchAll();
        echo json_encode(["status" => "success", "sms_logs" => $logs, "transactions" => $txns]);
        exit();
    }

    // Handle deposit status check from Blogger
    if ($action === 'check_status' || isset($_POST['ref_code']) || isset($_GET['ref_code'])) {
        $ref_code = trim($_POST['ref_code'] ?? $_GET['ref_code'] ?? '');

        if (empty($ref_code)) {
            echo json_encode(["status" => "error", "message" => "Missing reference code."]);
            exit();
        }

        // Standardize input formats: REF-19078 -> REF19078 & REF-19078
        $rawRef = strtoupper($ref_code);
        $cleanRef = str_replace('-', '', $rawRef); // e.g. REF19078
        $hyphenRef = (strpos($rawRef, '-') !== false) ? $rawRef : str_replace('REF', 'REF-', $rawRef); // e.g. REF-19078

        // Query database for both hyphenated and non-hyphenated versions
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE ref_code = ? OR ref_code = ? OR REPLACE(ref_code, '-', '') = ?");
        $stmt->execute([$rawRef, $hyphenRef, $cleanRef]);
        $txn = $stmt->fetch();

        if ($txn) {
            $status = strtoupper($txn['deposit_status'] ?? $txn['status'] ?? 'PENDING');
            if ($status === 'COMPLETED' || $status === 'SUCCESS') {
                echo json_encode(["status" => "success", "message" => "✅ Deposit Confirmed!"]);
            } else {
                echo json_encode(["status" => "pending", "message" => "⏳ Payment not detected yet. Send money with reference code and try again."]);
            }
        } else {
            echo json_encode(["status" => "pending", "message" => "⏳ Payment not detected yet. Send money with reference code and try again."]);
        }
        exit();
    }

    echo json_encode(["status" => "online", "message" => "SMS Gateway API is active."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server/DB Error: " . $e->getMessage()]);
}
?>
