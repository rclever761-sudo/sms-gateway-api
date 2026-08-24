<?php
// Set CORS and JSON Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Preflight request handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

// -------------------------------------------------------------
// 1. BLOGGER VERIFICATION CHECK (GET request: ?ref=REF-XXXXX)
// -------------------------------------------------------------
if (isset($_GET['ref'])) {
    $ref = strtoupper(trim($_GET['ref']));
    
    $stmt = $pdo->prepare("SELECT deposit_status FROM deposits WHERE ref_code = ?");
    $stmt->execute([$ref]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['deposit_status'] === 'SUCCESS') {
        echo json_encode([
            "status" => "found", 
            "deposit_status" => "SUCCESS",
            "message" => "Payment verified successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "pending", 
            "deposit_status" => "PENDING",
            "message" => "Payment not detected yet"
        ]);
    }
    exit;
}

// -------------------------------------------------------------
// 2. INCOMING SMS WEBHOOK FROM ANDROID APP (POST request)
// -------------------------------------------------------------
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

$sms_text = $data['message'] ?? $_POST['message'] ?? $inputData ?? '';

if (!empty($sms_text)) {
    if (preg_match('/Reason:\s*(REF-\d+)/i', $sms_text, $matches)) {
        $extracted_ref = strtoupper(trim($matches[1]));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO deposits (ref_code, deposit_status) 
                VALUES (?, 'SUCCESS') 
                ON DUPLICATE KEY UPDATE deposit_status = 'SUCCESS'
            ");
            $stmt->execute([$extracted_ref]);

            echo json_encode([
                "status" => "SUCCESS", 
                "message" => "Deposit recorded successfully", 
                "ref" => $extracted_ref
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "status" => "ERROR", 
                "message" => $e->getMessage()
            ]);
            exit;
        }
    }
}

echo json_encode([
    "status" => "FAILED", 
    "message" => "No valid reference found in payload"
]);
?>
