<?php
$host = "mysql-ddc4692-rclever761-3f21.aivencloud.com";
$dbname = "defaultdb";
$username = "avnadmin";
$password = "AVNS_Q1eviPJ1owGEHyV5PCy"; // Paste your copied password here
$port = "23985";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4", 
        $username, 
        $password, 
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
} catch (PDOException $e) {
    throw new Exception("Database connection failed: " . $e->getMessage());
}
?>
