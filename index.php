<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// 1. Read incoming body payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Extract the message text
$message = $data['message'] ?? $_POST['message'] ?? '';

// 2. Database credentials (replace with your MySQL details)
$db_host = "sql111.infinityfree.com"; 
$db_name = "if0_42731565_Pay";       
$db_user = "if0_42731565";           
$db_pass = "YOUR_DATABASE_PASSWORD"; // Put your real DB password here

if (preg_match('/REF-\d+/i', $message, $matches)) {
    $reference = strtoupper($matches[0]);

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Find pending record matching reference
        $stmt = $pdo->prepare("SELECT * FROM deposits WHERE reference = ? AND status = 'PENDING'");
        $stmt->execute([$reference]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deposit) {
            // Update deposit status
            $update = $pdo->prepare("UPDATE deposits SET status = 'SUCCESS' WHERE reference = ?");
            $update->execute([$reference]);

            echo json_encode(["status" => "SUCCESS", "message" => "Updated $reference"]);
            exit;
        } else {
            echo json_encode(["status" => "NOT_FOUND", "message" => "No pending record for $reference"]);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "DB_ERROR", "message" => $e->getMessage()]);
        exit;
    }
}

echo json_encode(["status" => "NO_REF_FOUND", "received" => $message]);
?>
