<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isAdmin()) {
    redirect('view.php');
}

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$invoiceId = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // Delete invoice details first
    $stmt = $conn->prepare("DELETE FROM BUY_INVOICE_DETAILS WHERE ID_INVOICE = ?");
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $stmt->close();
    
    // Delete invoice header
    $stmt = $conn->prepare("DELETE FROM BUY_INVOICE_HEADER WHERE ID_INVOICE = ?");
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Facture d'achat supprimée avec succès!";
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = "Erreur lors de la suppression de la facture: " . $e->getMessage();
}

redirect('view.php');
?>