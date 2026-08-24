<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if (!file_exists('db.php')) {
        throw new Exception("db.php file is missing from the server.");
    }
    require_once 'db.php';

    // 1. Ensure transactions table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ref_code VARCHAR(50) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'PENDING',
        deposit_status VARCHAR(20) DEFAULT 'PENDING',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Automatically add deposit_status column if missing
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN deposit_status VARCHAR(20) DEFAULT 'PENDING'");
    } catch (PDOException $e) {
        // Ignored if column already exists
    }

    // 3. Automatically add status column if missing
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN status VARCHAR(20) DEFAULT 'PENDING'");
    } catch (PDOException $e) {
        // Ignored if column already exists
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Handle deposit status check from Blogger frontend
    if ($action === 'check_status' || isset($_POST['ref_code']) || isset($_GET['ref_code'])) {
        $ref_code = $_POST['ref_code'] ?? $_GET['ref_code'] ?? '';

        if (empty($ref_code)) {
            echo json_encode(["status" => "error", "message" => "Missing reference code."]);
            exit();
        }

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE ref_code = ?");
        $stmt->execute([$ref_code]);
        $txn = $stmt->fetch();

        if ($txn) {
            $status = $txn['deposit_status'] ?? $txn['status'] ?? 'PENDING';
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

    // Default API active status message
    echo json_encode(["status" => "online", "message" => "SMS Gateway API is active."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server/DB Error: " . $e->getMessage()]);
}
?>
