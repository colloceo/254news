<?php
// Load environment variables (recommended for production)
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'newsers';

// Database configuration
$conn = new mysqli($host, $user, $pass, $dbname, 3306);

// Check connection
if ($conn->connect_error) {
    // Log error to a file or monitoring system in production instead of displaying it
    error_log("Connection failed: " . $conn->connect_error);
    die("Database connection error. Please try again later.");
}

// Set character encoding to UTF-8
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $conn->error);
    die("Database configuration error.");
}

// Set connection timeout
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Enable error reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>