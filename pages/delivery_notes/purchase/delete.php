<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

if (!isAdmin()) {
    redirect('view.php');
}

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$bonId = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // Delete bon details first
    $stmt = $conn->prepare("DELETE FROM BON_LIVRAISON_ACHAT_DETAILS WHERE ID_BON = ?");
    $stmt->bind_param("i", $bonId);
    $stmt->execute();
    $stmt->close();
    
    // Delete bon header
    $stmt = $conn->prepare("DELETE FROM BON_LIVRAISON_ACHAT_HEADER WHERE ID = ?");
    $stmt->bind_param("i", $bonId);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Bon de livraison supprimé avec succès!";
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = "Erreur lors de la suppression du bon: " . $e->getMessage();
}

redirect('view.php');
?>