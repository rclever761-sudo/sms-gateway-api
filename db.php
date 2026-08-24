<?php
$host = '188.166.50.24';
$dbname = 'defaultdb';
$username = 'avnadmin';
$password = 'AVNS_Q1eviPJ1owGEHyV5PCy';
$port = 23985;

try {
    $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]);

    // Ensure table exists with all required columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ref_code VARCHAR(50) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'PENDING',
        deposit_status VARCHAR(20) DEFAULT 'PENDING',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Add column if table already existed without it
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN deposit_status VARCHAR(20) DEFAULT 'PENDING'");
    } catch (PDOException $e) {
        // Ignored if column already exists
    }

} catch (PDOException $e) {
    throw new Exception("Database connection failed: " . $e->getMessage());
}
?>
