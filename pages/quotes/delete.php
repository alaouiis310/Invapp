<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isAdmin()) {
    redirect('../view.php');
}

if (!isset($_GET['id'])) {
    redirect('../view.php');
}

$quoteId = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // Delete quote details first
    $stmt = $conn->prepare("DELETE FROM DEVIS_DETAILS WHERE ID_DEVIS = ?");
    $stmt->bind_param("i", $quoteId);
    $stmt->execute();
    $stmt->close();
    
    // Delete quote header
    $stmt = $conn->prepare("DELETE FROM DEVIS_HEADER WHERE ID = ?");
    $stmt->bind_param("i", $quoteId);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Devis supprimé avec succès!";
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = "Erreur lors de la suppression du devis: " . $e->getMessage();
}

redirect('../view.php');
?>