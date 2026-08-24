<?php
// Prevent PHP output buffering issues
if (ob_get_level()) ob_end_clean();

// Set CORS and JSON response headers immediately
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Wrap EVERYTHING inside try-catch to stop HTTP 500 errors
try {
    if (!file_exists('db.php')) {
        throw new Exception("db.php file is missing from the server.");
    }

    require_once 'db.php';

    if (!isset($pdo)) {
        throw new Exception("Database connection variable \$pdo is not set in db.php.");
    }

    // Read incoming parameters
    $inputJSON = json_decode(file_get_contents('php://input'), true);
    $ref = $_POST['ref'] ?? $_GET['ref'] ?? $inputJSON['ref'] ?? null;

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

    // Handle incoming SMS Webhook
    $sms_text = $_POST['message'] ?? $inputJSON['message'] ?? '';

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

    echo json_encode(["status" => "FAILED", "message" => "No reference code provided"]);
    exit(0);

} catch (Throwable $e) {
    // Send 200 status so the front-end receives the error description instead of dying on HTTP 500
    http_response_code(200);
    echo json_encode([
        "status" => "error", 
        "deposit_status" => "ERROR", 
        "message" => $e->getMessage()
    ]);
    exit(0);
}
?>
