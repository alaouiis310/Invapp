<?php
require_once 'includes/config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect to dashboard
header('Location: pages/dashboard.php');
exit();
?>