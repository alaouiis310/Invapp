<?php
// Common functions used throughout the application

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../login.php');
        exit();
    }
}

function sanitizeInput($data) {
    global $conn;
    return htmlspecialchars(strip_tags($conn->real_escape_string($data)));
}

function isAdmin() {
    // Make sure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in and is admin
    return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function calculateTVA($priceHT) {
    return $priceHT * 0.2; // Assuming 20% TVA
}

function calculateTTC($priceHT) {
    return $priceHT * 1.2; // HT + 20% TVA
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function generateInvoiceNumber($prefix = 'INV') {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

function generateDeliveryNumber($prefix = 'BLV') {
    // Get current year and month
    $yearMonth = date('Ym');
    
    // Generate a random 4-digit number
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Combine to create the delivery number
    return $prefix . '-' . $yearMonth . '-' . $random;
}
?>