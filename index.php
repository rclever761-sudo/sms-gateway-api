<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// 1. Read incoming payload from phone
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$message = $data['message'] ?? $_POST['message'] ?? '';

// 2. Extract reference code (e.g., REF-12345)
if (preg_match('/REF-\d+/i', $message, $matches)) {
    $reference = strtoupper($matches[0]);

    // Replace with your site's URL on InfinityFree where db-update.php is uploaded
    $infinityFreeUrl = 'http://my-smsgateway.free.nf/db-update.php';

    // Forward reference code to InfinityFree over HTTP
    $ch = curl_init($infinityFreeUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['reference' => $reference]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        'status' => 'forwarded',
        'reference' => $reference,
        'http_code' => $httpCode,
        'bridge_response' => $response
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No matching reference code found in SMS'
    ]);
}
