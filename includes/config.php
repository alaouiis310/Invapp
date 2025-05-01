<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'invapp');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1h minute timeout
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);

// Set charset
$conn->set_charset("utf8");

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
?>