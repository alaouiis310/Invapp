<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isAdmin()) {
    redirect('credit_note_view.php');
}

if (!isset($_GET['id'])) {
    redirect('credit_note_view.php');
}

$creditNoteId = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // First, get all products from the credit note to restore quantities
    $getProductsStmt = $conn->prepare("SELECT PRODUCT_ID, QUANTITY FROM BUY_CREDIT_NOTE_DETAILS WHERE ID_CREDIT_NOTE = ?");
    $getProductsStmt->bind_param("i", $creditNoteId);
    $getProductsStmt->execute();
    $productsResult = $getProductsStmt->get_result();
    
    if ($productsResult->num_rows > 0) {
        while ($product = $productsResult->fetch_assoc()) {
            // Restore product quantity
            $restoreStmt = $conn->prepare("UPDATE PRODUCT SET QUANTITY = QUANTITY + ? WHERE REFERENCE = ?");
            $restoreStmt->bind_param("is", $product['QUANTITY'], $product['PRODUCT_ID']);
            $restoreStmt->execute();
            $restoreStmt->close();
        }
    }
    $getProductsStmt->close();
    
    // Delete credit note details
    $stmt = $conn->prepare("DELETE FROM BUY_CREDIT_NOTE_DETAILS WHERE ID_CREDIT_NOTE = ?");
    $stmt->bind_param("i", $creditNoteId);
    $stmt->execute();
    $stmt->close();
    
    // Delete credit note header
    $stmt = $conn->prepare("DELETE FROM BUY_CREDIT_NOTE_HEADER WHERE ID_CREDIT_NOTE = ?");
    $stmt->bind_param("i", $creditNoteId);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Avoir/Retour supprimé avec succès!";
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = "Erreur lors de la suppression de l'avoir: " . $e->getMessage();
}

redirect('credit_note_view.php');
?>