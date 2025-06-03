<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['delivery_credit_note']) || empty($_SESSION['delivery_credit_note_products'])) {
    $_SESSION['error_message'] = "Session data missing. Please start over.";
    redirect('credit_note_bl_step1.php');
}

$title = "Avoir/Retour Bon de Livraison - Étape 3/3";
include '../../includes/header.php';

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    try {
        // First verify the client exists
        $checkClient = $conn->prepare("SELECT CLIENTNAME FROM CLIENT WHERE CLIENTNAME = ?");
        $checkClient->bind_param("s", $_SESSION['delivery_credit_note']['client_name']);
        $checkClient->execute();
        
        if ($checkClient->get_result()->num_rows === 0) {
            throw new Exception("Le client '".$_SESSION['delivery_credit_note']['client_name']."' n'existe pas.");
        }
        $checkClient->close();

        // Verify the original delivery exists
        $checkDelivery = $conn->prepare("SELECT ID FROM BON_LIVRAISON_VENTE_HEADER WHERE ID_BON = ?");
        $checkDelivery->bind_param("s", $_SESSION['delivery_credit_note']['delivery_number']);
        $checkDelivery->execute();
        $deliveryResult = $checkDelivery->get_result();
        
        if ($deliveryResult->num_rows === 0) {
            throw new Exception("Le bon de livraison '".$_SESSION['delivery_credit_note']['delivery_number']."' n'existe pas.");
        }
        $deliveryRow = $deliveryResult->fetch_assoc();
        $originalDeliveryId = $deliveryRow['ID'];
        $checkDelivery->close();

        // Insert credit note header with image
        $stmt = $conn->prepare("INSERT INTO DELIVERY_CREDIT_NOTE_HEADER (
            CREDIT_NOTE_NUMBER, 
            CLIENT_NAME, 
            DELIVERY_NUMBER,
            TOTAL_PRICE_TTC, 
            TOTAL_PRICE_HT, 
            TVA, 
            DATE,
            IMAGE
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

        foreach ($_SESSION['delivery_credit_note_products'] as $product) {
            $productHT = $product['unit_price'] / 1.2;
            $productTVA = $productHT * 0.2;
            $productTotal = $product['unit_price'] * $product['quantity'];

            $totalHT += $productHT * $product['quantity'];
            $totalTVA += $productTVA * $product['quantity'];
            $totalTTC += $productTotal;
        }

        // Get credit note image from session
        $creditNoteImage = isset($_SESSION['delivery_credit_note']['credit_note_image']) ? $_SESSION['delivery_credit_note']['credit_note_image'] : null;

        $stmt->bind_param(
            "sssdddss",
            $_SESSION['delivery_credit_note']['credit_note_number'],
            $_SESSION['delivery_credit_note']['client_name'],
            $_SESSION['delivery_credit_note']['delivery_number'],
            $totalTTC,
            $totalHT,
            $totalTVA,
            $_SESSION['delivery_credit_note']['credit_note_date'],
            $creditNoteImage
        );

        if (!$stmt->execute()) {
            throw new Exception("Error saving credit note header: " . $stmt->error);
        }

        $creditNoteId = $conn->insert_id;
        $stmt->close();

        // Process each product
        foreach ($_SESSION['delivery_credit_note_products'] as $product) {
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
            
            // Update product quantity (add back to stock)
            $updateStmt = $conn->prepare("UPDATE PRODUCT SET 
                QUANTITY = QUANTITY + ?
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
            $detailStmt = $conn->prepare("INSERT INTO DELIVERY_CREDIT_NOTE_DETAILS (
                ID_CREDIT_NOTE,
                PRODUCT_ID,
                PRODUCT_NAME,
                QUANTITY,
                UNIT_PRICE_TTC,
                TOTAL_PRICE_TTC
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
        unset($_SESSION['delivery_credit_note']);
        unset($_SESSION['delivery_credit_note_products']);

        // Set success message
        $_SESSION['success_message'] = "Avoir/Retour bon de livraison enregistré avec succès!";

        // Redirect to view page
        redirect('credit_note_bl_view.php');
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

foreach ($_SESSION['delivery_credit_note_products'] as $product) {
    $productHT = $product['unit_price'] / 1.2;
    $productTVA = $productHT * 0.2;
    $productTotal = $product['unit_price'] * $product['quantity'];

    $totalHT += $productHT * $product['quantity'];
    $totalTVA += $productTVA * $product['quantity'];
    $totalTTC += $productTotal;
}
?>

<h1>Nouvel Avoir/Retour Bon de Livraison - Étape 3/3</h1>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error_message'];
                                    unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="invoice-summary">
    <h3>Résumé de l'Avoir</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['delivery_credit_note']['client_name']; ?></p>
    <p><strong>Numéro Bon de Livraison:</strong> <?php echo $_SESSION['delivery_credit_note']['delivery_number']; ?></p>
    <p><strong>Numéro Avoir:</strong> <?php echo $_SESSION['delivery_credit_note']['credit_note_number']; ?></p>
    <p><strong>Date:</strong> <?php echo $_SESSION['delivery_credit_note']['credit_note_date']; ?></p>
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
        <?php foreach ($_SESSION['delivery_credit_note_products'] as $product): ?>
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
        <a href="credit_note_bl_step2.php" class="btn btn-secondary">Retour</a>
        <button type="submit" name="confirm" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>