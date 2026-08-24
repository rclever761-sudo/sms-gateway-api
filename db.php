<?php
$host = '188.166.50.24';
$dbname = 'defaultdb';
$username = 'avnadmin';
$password = 'AVNS_Q1eviPJ1owGEHyV5PCy'; // Replace with your real Aiven password
$port = 23985;

try {
    $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ]);
} catch (PDOException $e) {
    throw new Exception("Database connection failed: " . $e->getMessage());
}
?>
