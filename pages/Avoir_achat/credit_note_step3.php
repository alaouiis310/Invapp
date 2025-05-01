<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['credit_note']) || empty($_SESSION['credit_note_products'])) {
    $_SESSION['error_message'] = "Session data missing. Please start over.";
    redirect('credit_note_step1.php');
}

$title = "Avoir/Retour d'Achat - Étape 3/3";
include '../../includes/header.php';

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    try {
        // First verify the supplier exists
        $checkSupplier = $conn->prepare("SELECT SUPPLIERNAME FROM SUPPLIER WHERE SUPPLIERNAME = ?");
        $checkSupplier->bind_param("s", $_SESSION['credit_note']['supplier_name']);
        $checkSupplier->execute();
        
        if ($checkSupplier->get_result()->num_rows === 0) {
            throw new Exception("Le fournisseur '".$_SESSION['credit_note']['supplier_name']."' n'existe pas.");
        }
        $checkSupplier->close();

        // Verify the original invoice exists
        $checkInvoice = $conn->prepare("SELECT ID_INVOICE FROM BUY_INVOICE_HEADER WHERE INVOICE_NUMBER = ?");
        $checkInvoice->bind_param("s", $_SESSION['credit_note']['invoice_number']);
        $checkInvoice->execute();
        $invoiceResult = $checkInvoice->get_result();
        
        if ($invoiceResult->num_rows === 0) {
            throw new Exception("La facture originale '".$_SESSION['credit_note']['invoice_number']."' n'existe pas.");
        }
        $invoiceRow = $invoiceResult->fetch_assoc();
        $originalInvoiceId = $invoiceRow['ID_INVOICE'];
        $checkInvoice->close();

        // Insert credit note header with image
        $stmt = $conn->prepare("INSERT INTO BUY_CREDIT_NOTE_HEADER (
            CREDIT_NOTE_NUMBER, 
            SUPPLIER_NAME, 
            INVOICE_NUMBER,
            TOTAL_PRICE_TTC, 
            TOTAL_PRICE_HT, 
            TOTAL_PRICE_TVA, 
            DATE,
            IMAGE
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

        foreach ($_SESSION['credit_note_products'] as $product) {
            $productHT = $product['unit_price'] / 1.2;
            $productTVA = $productHT * 0.2;
            $productTotal = $product['unit_price'] * $product['quantity'];

            $totalHT += $productHT * $product['quantity'];
            $totalTVA += $productTVA * $product['quantity'];
            $totalTTC += $productTotal;
        }

        // Get credit note image from session
        $creditNoteImage = isset($_SESSION['credit_note']['credit_note_image']) ? $_SESSION['credit_note']['credit_note_image'] : null;

        $stmt->bind_param(
            "sssdddss",
            $_SESSION['credit_note']['credit_note_number'],
            $_SESSION['credit_note']['supplier_name'],
            $_SESSION['credit_note']['invoice_number'],
            $totalTTC,
            $totalHT,
            $totalTVA,
            $_SESSION['credit_note']['credit_note_date'],
            $creditNoteImage
        );

        if (!$stmt->execute()) {
            throw new Exception("Error saving credit note header: " . $stmt->error);
        }

        $creditNoteId = $conn->insert_id;
        $stmt->close();

        // Process each product
        foreach ($_SESSION['credit_note_products'] as $product) {
            // Check if product exists
            $checkStmt = $conn->prepare("SELECT ID, QUANTITY FROM PRODUCT WHERE REFERENCE = ?");
            $checkStmt->bind_param("s", $product['reference']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Le produit avec la référence '".$product['reference']."' n'existe pas.");
            }
            
            $row = $result->fetch_assoc();
            $productId = $row['ID'];
            $currentQuantity = $row['QUANTITY'];
            
            if ($currentQuantity < $product['quantity']) {
                throw new Exception("Quantité insuffisante en stock pour le produit '".$product['reference']."'.");
            }
            
            // Update product quantity (subtract)
            $updateStmt = $conn->prepare("UPDATE PRODUCT SET 
                QUANTITY = QUANTITY - ?
                WHERE ID = ?");

            $updateStmt->bind_param(
                "ii",
                $product['quantity'],
                $productId
            );

            if (!$updateStmt->execute()) {
                throw new Exception("Error updating product quantity: " . $updateStmt->error);
            }
            $updateStmt->close();

            // Insert credit note detail
            $detailStmt = $conn->prepare("INSERT INTO BUY_CREDIT_NOTE_DETAILS (
                ID_CREDIT_NOTE,
                PRODUCT_ID,
                PRODUCT_NAME,
                QUANTITY,
                UNIT_PRICE_TTC,
                TOTAL_PRICE
            ) VALUES (?, ?, ?, ?, ?, ?)");

            $totalPrice = $product['unit_price'] * $product['quantity'];

            $detailStmt->bind_param(
                "issidd",
                $creditNoteId,
                $product['reference'],
                $product['product_name'],
                $product['quantity'],
                $product['unit_price'],
                $totalPrice
            );

            if (!$detailStmt->execute()) {
                throw new Exception("Error saving credit note details: " . $detailStmt->error);
            }
            $detailStmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Clear session data
        unset($_SESSION['credit_note']);
        unset($_SESSION['credit_note_products']);

        // Set success message
        $_SESSION['success_message'] = "Avoir/Retour d'achat enregistré avec succès!";

        // Redirect to view page
        redirect('credit_note_view.php');
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}

// Calculate totals for display
$totalHT = 0;
$totalTVA = 0;
$totalTTC = 0;

foreach ($_SESSION['credit_note_products'] as $product) {
    $productHT = $product['unit_price'] / 1.2;
    $productTVA = $productHT * 0.2;
    $productTotal = $product['unit_price'] * $product['quantity'];

    $totalHT += $productHT * $product['quantity'];
    $totalTVA += $productTVA * $product['quantity'];
    $totalTTC += $productTotal;
}
?>

<h1>Nouvel Avoir/Retour d'Achat - Étape 3/3</h1>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error_message'];
                                    unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="invoice-summary">
    <h3>Résumé de l'Avoir</h3>
    <p><strong>Fournisseur:</strong> <?php echo $_SESSION['credit_note']['supplier_name']; ?></p>
    <p><strong>Numéro Facture Originale:</strong> <?php echo $_SESSION['credit_note']['invoice_number']; ?></p>
    <p><strong>Numéro Avoir:</strong> <?php echo $_SESSION['credit_note']['credit_note_number']; ?></p>
    <p><strong>Date:</strong> <?php echo $_SESSION['credit_note']['credit_note_date']; ?></p>
</div>

<h3>Produits</h3>
<table class="table">
    <thead>
        <tr>
            <th>Référence</th>
            <th>Désignation</th>
            <th>Quantité</th>
            <th>Prix Unitaire</th>
            <th>Prix Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($_SESSION['credit_note_products'] as $product): ?>
            <tr>
                <td><?php echo $product['reference']; ?></td>
                <td><?php echo $product['product_name']; ?></td>
                <td><?php echo $product['quantity']; ?></td>
                <td><?php echo number_format($product['unit_price'], 2); ?> DH</td>
                <td><?php echo number_format($product['unit_price'] * $product['quantity'], 2); ?> DH</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">Totaux</th>
            <th>HT: <?php echo number_format($totalHT, 2); ?> DH</th>
            <th>TVA: <?php echo number_format($totalTVA, 2); ?> DH</th>
            <th>TTC: <?php echo number_format($totalTTC, 2); ?> DH</th>
        </tr>
    </tfoot>
</table>

<form action="" method="post">
    <div class="form-actions">
        <a href="credit_note_step2.php" class="btn btn-secondary">Retour</a>
        <button type="submit" name="confirm" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>