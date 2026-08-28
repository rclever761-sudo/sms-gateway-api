<?php
// --- 1. CORS & PREFLIGHT HEADERS ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Respond immediately to browser OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- 2. DATABASE CONNECTION ---
$host = "sql111.infinityfree.com";
$db_user = "if0_42731565";
$db_pass = "AogWR4L3pnVR";
$db_name = "if0_42731565_Pay";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed: " . $conn->connect_error]);
    exit();
}

// --- 3. HELPER FUNCTIONS ---
function generateRefCode($length = 6) {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, $length));
}

// Get raw POST payload from fetch()
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// --- 4. ACTION ROUTING ---

// REGISTER USER
if ($action === 'register') {
    $country_code = $input['country_code'] ?? '';
    $phone = $input['phone'] ?? '';
    $password = $input['password'] ?? '';
    $referred_by = $input['referral_code'] ?? '';

    if (empty($phone) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Phone number and password are required."]);
        exit();
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Phone number is already registered."]);
        exit();
    }
    $stmt->close();

    // Hash password & generate referral code
    $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
    $my_ref_code = generateRefCode();

    $stmt = $conn->prepare("INSERT INTO users (phone, country_code, password, referral_code, referred_by, balance) VALUES (?, ?, ?, ?, ?, 0.00)");
    $stmt->bind_param("sssss", $phone, $country_code, $hashed_pass, $my_ref_code, $referred_by);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "User registered successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create account."]);
    }
    $stmt->close();
    exit();
}

// LOGIN USER
if ($action === 'login') {
    $phone = $input['phone'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($phone) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Phone number and password are required."]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, phone, password, referral_code, balance FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            unset($row['password']); // Remove password hash before sending response
            echo json_encode(["status" => "success", "user" => $row]);
        } else {
            echo json_encode(["status" => "error", "message" => "Incorrect password."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "User not found."]);
    }
    $stmt->close();
    exit();
}

$conn->close();
?>
