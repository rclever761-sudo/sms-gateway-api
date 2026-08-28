
<?php
// 1. CORS & Pre-flight Request Handling
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection (InfinityFree Config)
$db_host = "sql208.infinityfree.com"; // Replace with your MySQL Hostname
$db_user = "if0_37123456";            // Replace with your MySQL Username
$db_pass = "Yourv6Password";         // Replace with your MySQL Password
$db_name = "if0_37123456_db";         // Replace with your Database Name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// 3. Process Incoming Request
$inputData = json_decode(file_get_contents("php_input"), true);
if (!$inputData) {
    $inputData = $_POST;
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($inputData['action']) ? $inputData['action'] : '');

// 4. Action Router
switch ($action) {
    case 'register':
        handleRegistration($conn, $inputData);
        break;
    case 'login':
        handleLogin($conn, $inputData);
        break;
    case 'get_user':
        handleGetUser($conn, $inputData);
        break;
    default:
        // Handle direct payload without explicit action parameter
        if (isset($inputData['phone']) && isset($inputData['password']) && isset($inputData['country_code'])) {
            handleRegistration($conn, $inputData);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid or missing action."]);
        }
        break;
}

$conn->close();

// --- Helper Functions ---

function handleRegistration($conn, $data) {
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $pass = isset($data['password']) ? trim($data['password']) : '';
    $country = isset($data['country_code']) ? trim($data['country_code']) : '';
    $refCode = isset($data['referral_code']) ? trim($data['referral_code']) : '';

    if (empty($phone) || empty($pass)) {
        echo json_encode(["status" => "error", "message" => "Phone and password are required."]);
        return;
    }

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Phone number already registered."]);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Generate unique referral code for new user
    $userRefCode = strtoupper(substr(md5($phone . time()), 0, 8));
    $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);

    // Insert new user record
    $stmt = $conn->prepare("INSERT INTO users (phone, password, country_code, referral_code, referred_by, balance) VALUES (?, ?, ?, ?, ?, 0.00)");
    $stmt->bind_param("sssss", $phone, $hashedPassword, $country, $userRefCode, $refCode);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Registration successful! Please log in.",
            "referral_code" => $userRefCode
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create user account."]);
    }
    $stmt->close();
}

function handleLogin($conn, $data) {
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $pass = isset($data['password']) ? trim($data['password']) : '';

    if (empty($phone) || empty($pass)) {
        echo json_encode(["status" => "error", "message" => "Phone and password required."]);
        return;
    }

    $stmt = $conn->prepare("SELECT id, password, referral_code, balance FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($pass, $row['password']) || $pass === $row['password']) {
            echo json_encode([
                "status" => "success",
                "message" => "Login successful.",
                "user" => [
                    "phone" => $phone,
                    "referral_code" => $row['referral_code'],
                    "balance" => $row['balance']
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Incorrect password."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "User account not found."]);
    }
    $stmt->close();
}

function handleGetUser($conn, $data) {
    $phone = isset($data['phone']) ? trim($data['phone']) : (isset($_GET['phone']) ? trim($_GET['phone']) : '');

    if (empty($phone)) {
        echo json_encode(["status" => "error", "message" => "Phone parameter required."]);
        return;
    }

    $stmt = $conn->prepare("SELECT phone, country_code, referral_code, referred_by, balance FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "user" => $user]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found."]);
    }
    $stmt->close();
}
?>
