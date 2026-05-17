<?php
$host = getenv('DINEMATE_DB_HOST') ?: 'localhost';
$dbname = getenv('DINEMATE_DB_NAME') ?: 'Dinemate';
$username = getenv('DINEMATE_DB_USER') ?: 'root';
$password = getenv('DINEMATE_DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please try again later.");
}
