<?php
// Clear output buffer to prevent stray characters
ob_clean();

// Allow request from any origin (Blogger)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle browser preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once 'db.php';

// Catch reference code from either GET or POST
$ref = $_GET['ref'] ?? $_POST['ref'] ?? null;

if ($ref) {
    $ref = strtoupper(trim($ref));
    
    try {
        $stmt = $pdo->prepare("SELECT deposit_status FROM deposits WHERE ref_code = ?");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && strtoupper($row['deposit_status']) === 'SUCCESS') {
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
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit(0);
}

// Android SMS Gateway Webhook Handler
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

            echo json_encode(["status" => "SUCCESS", "ref" => $extracted_ref]);
            exit(0);
        } catch (PDOException $e) {
            echo json_encode(["status" => "ERROR", "message" => $e->getMessage()]);
            exit(0);
        }
    }
}

echo json_encode(["status" => "FAILED", "message" => "No valid reference found"]);
exit(0);
?>
