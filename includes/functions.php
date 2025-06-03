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
        redirect('../../login.php');
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

function getSetting($key, $default = '') {
    global $conn;
    $result = $conn->query("SELECT SETTING_VALUE FROM APP_SETTINGS WHERE SETTING_KEY = '$key'");
    return $result->num_rows > 0 ? $result->fetch_assoc()['SETTING_VALUE'] : $default;
}

function generateInvoiceNumber($prefix = 'FAC') {
    global $conn;
    
    // Get the starting number from settings
    $result = $conn->query("SELECT SETTING_VALUE FROM APP_SETTINGS WHERE SETTING_KEY = 'INVOICE_START_NUMBER'");
    $startNumber = $result->num_rows > 0 ? (int)$result->fetch_assoc()['SETTING_VALUE'] : 1;
    
    // Generate the number with leading zeros
    $invoiceNumber = $prefix . str_pad($startNumber, 5, '0', STR_PAD_LEFT);
    
    // Increment and save the next number
    $nextNumber = $startNumber + 1;
    $conn->query("UPDATE APP_SETTINGS SET SETTING_VALUE = $nextNumber WHERE SETTING_KEY = 'INVOICE_START_NUMBER'");
    
    return $invoiceNumber;
}



function generateDeliveryNumber($prefix = 'BLV') {
    global $conn;
    
    // Get the starting number from settings
    $result = $conn->query("SELECT SETTING_VALUE FROM APP_SETTINGS WHERE SETTING_KEY = 'DELIVERY_START_NUMBER'");
    $startNumber = $result->num_rows > 0 ? (int)$result->fetch_assoc()['SETTING_VALUE'] : 1;
    
    // Generate the number with leading zeros
    $deliveryNumber = $prefix . str_pad($startNumber, 5, '0', STR_PAD_LEFT);
    
    // Increment and save the next number
    $nextNumber = $startNumber + 1;
    $conn->query("UPDATE APP_SETTINGS SET SETTING_VALUE = $nextNumber WHERE SETTING_KEY = 'DELIVERY_START_NUMBER'");
    
    return $deliveryNumber;
}


function getUserSetting($userId, $key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?");
    $stmt->bind_param("is", $userId, $key);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : $default;
}
?>