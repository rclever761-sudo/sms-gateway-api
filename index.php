<?php
// Set response headers
header('Content-Type: application/json');

// Include database connection
require_once 'db.php';

// Get incoming POST payload from SMS Forwarder
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

// Extract SMS text (supports JSON payload or standard POST parameter)
$sms_text = $data['message'] ?? $_POST['message'] ?? '';

if (!empty($sms_text)) {
    // REGEX: Matches "Reason:REF-34027" or "Reason: REF-34027"
    if (preg_match('/Reason:\s*(REF-\d+)/i', $sms_text, $matches)) {
        $extracted_ref = strtoupper(trim($matches[1]));

        try {
            // Update deposit status to SUCCESS in database
            $stmt = $pdo->prepare("UPDATE deposits SET deposit_status = 'SUCCESS' WHERE ref_code = ?");
            $stmt->execute([$extracted_ref]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["status" => "success", "message" => "Deposit marked SUCCESS", "ref" => $extracted_ref]);
            } else {
                echo json_encode(["status" => "not_found", "message" => "Reference code not in database", "ref" => $extracted_ref]);
            }
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(["status" => "ignored", "message" => "No valid reference code found in SMS"]);
?>
