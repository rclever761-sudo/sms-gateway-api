<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

// -------------------------------------------------------------
// 1. BLOGGER VERIFICATION CHECK (GET request: ?ref=REF-XXXXX)
// -------------------------------------------------------------
if (isset($_GET['ref'])) {
    $ref = strtoupper(trim($_GET['ref']));
    
    $stmt = $pdo->prepare("SELECT deposit_status FROM deposits WHERE ref_code = ?");
    $stmt->execute([$ref]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(["status" => "found", "deposit_status" => $row['deposit_status']]);
    } else {
        echo json_encode(["status" => "not_found", "deposit_status" => "PENDING"]);
    }
    exit;
}

// -------------------------------------------------------------
// 2. INCOMING SMS WEBHOOK FROM ANDROID APP (POST request)
// -------------------------------------------------------------
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

// Get SMS text from either JSON or standard POST parameters
$sms_text = $data['message'] ?? $_POST['message'] ?? $inputData ?? '';

if (!empty($sms_text)) {
    // Extract reference code (handles "Reason:REF-10869" or "Reason: REF-10869")
    if (preg_match('/Reason:\s*(REF-\d+)/i', $sms_text, $matches)) {
        $extracted_ref = strtoupper(trim($matches[1]));

        try {
            // INSERT OR UPDATE: Creates the row if missing, or updates it to SUCCESS
            $stmt = $pdo->prepare("
                INSERT INTO deposits (ref_code, deposit_status) 
                VALUES (?, 'SUCCESS') 
                ON DUPLICATE KEY UPDATE deposit_status = 'SUCCESS'
            ");
            $stmt->execute([$extracted_ref]);

            echo json_encode(["status" => "success", "message" => "Updated reference to SUCCESS", "ref" => $extracted_ref]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            exit;
        }
    }
}

echo json_encode(["status" => "ignored", "message" => "No valid reference found in payload"]);
?>
