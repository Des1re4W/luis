
<?php

$host = "db.pfyvduwfjoybdgtfeofs.supabase.co";
$port = "5432";
$db   = "postgres";
$user = "postgres";
$pass = "9NNgtbg2!253717228";

try {
    $conn = new PDO(
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
    die("Database connection failed.");
}
?>
