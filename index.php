<?php
// Clear any existing buffers
if (ob_get_level()) ob_end_clean();

// Force CORS Headers on EVERY response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request instantly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

try {
    require_once 'db.php';

    // Parse incoming JSON body if present
    $inputJSON = json_decode(file_get_contents('php://input'), true);

    // Retrieve reference code from GET, POST, or JSON body
    $ref = $_GET['ref'] ?? $_POST['ref'] ?? $inputJSON['ref'] ?? null;

    if ($ref) {
        $ref = strtoupper(trim($ref));
        
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
        exit(0);
    }

    // Handle SMS Gateway Webhook
    $sms_text = $inputJSON['message'] ?? $_POST['message'] ?? '';

    if (!empty($sms_text)) {
        if (preg_match('/Reason:\s*(REF-\d+)/i', $sms_text, $matches)) {
            $extracted_ref = strtoupper(trim($matches[1]));

            $stmt = $pdo->prepare("
                INSERT INTO deposits (ref_code, deposit_status) 
                VALUES (?, 'SUCCESS') 
                ON DUPLICATE KEY UPDATE deposit_status = 'SUCCESS'
            ");
            $stmt->execute([$extracted_ref]);

            echo json_encode(["status" => "SUCCESS", "ref" => $extracted_ref]);
            exit(0);
        }
    }

    echo json_encode(["status" => "FAILED", "message" => "No reference or message received"]);
    exit(0);

} catch (Exception $e) {
    http_response_code(200); // Return 200 so browser reads JSON error instead of throwing network error
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit(0);
}
?>
