
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env from hardwareSystem root
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Database credentials
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'] ?? '5432';
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$db;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Database connection failed.");
}
