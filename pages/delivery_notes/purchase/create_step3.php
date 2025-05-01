<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['purchase_invoice']) || empty($_SESSION['purchase_products'])) {
    $_SESSION['error_message'] = "Session data missing. Please start over.";
    redirect('create_step1.php');
}

$title = "Bon de Livraison - Étape 3/3";
include '../../../includes/headerin.php';

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    try {
        // First verify the supplier exists
        $checkSupplier = $conn->prepare("SELECT SUPPLIERNAME FROM SUPPLIER WHERE SUPPLIERNAME = ?");
        $checkSupplier->bind_param("s", $_SESSION['purchase_invoice']['supplier_name']);
        $checkSupplier->execute();

        if ($checkSupplier->get_result()->num_rows === 0) {
            throw new Exception("Le fournisseur '" . $_SESSION['purchase_invoice']['supplier_name'] . "' n'existe pas.");
        }
        $checkSupplier->close();

        // Insert bon header (with IMAGE if available)
        $stmt = $conn->prepare("INSERT INTO BON_LIVRAISON_ACHAT_HEADER (
            ID_BON, 
            SUPPLIER_NAME, 
            TOTAL_PRICE_TTC, 
            TOTAL_PRICE_HT, 
            TVA, 
            DATE,
            IMAGE
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

        foreach ($_SESSION['purchase_products'] as $product) {
            $productHT = $product['unit_price'] / 1.2;
            $productTVA = $productHT * 0.2;
            $productTotal = $product['unit_price'] * $product['quantity'];

            $totalHT += $productHT * $product['quantity'];
            $totalTVA += $productTVA * $product['quantity'];
            $totalTTC += $productTotal;
        }

        // Get the invoice image from session if it exists
        $invoiceImage = isset($_SESSION['purchase_invoice']['invoice_image']) ? $_SESSION['purchase_invoice']['invoice_image'] : null;

        $stmt->bind_param(
            "ssdddss",
            $_SESSION['purchase_invoice']['invoice_number'],
            $_SESSION['purchase_invoice']['supplier_name'],
            $totalTTC,
            $totalHT,
            $totalTVA,
            $_SESSION['purchase_invoice']['invoice_date'],
            $invoiceImage
        );

        if (!$stmt->execute()) {
            throw new Exception("Error saving bon header: " . $stmt->error);
        }

        $bonId = $conn->insert_id;
        $stmt->close();

        // Insert bon details and update products
        foreach ($_SESSION['purchase_products'] as $product) {
            // Check if product exists
            $checkStmt = $conn->prepare("SELECT ID FROM PRODUCT WHERE REFERENCE = ?");
            $checkStmt->bind_param("s", $product['reference']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            // In the product insertion section:
            if ($result->num_rows > 0) {
                // Product exists - update
                $row = $result->fetch_assoc();
                $productId = $row['ID'];

                $updateStmt = $conn->prepare("UPDATE PRODUCT SET 
        QUANTITY = QUANTITY + ?,
        PRICE = ?,
        TYPE = ?,
        IMAGE = ?,
        CATEGORY_NAME = ?
        WHERE ID = ?");

                $adjustedPrice = $product['unit_price'] * 1.2;
                $type = 'BL';

                // Initialize image variable properly
                $imageData = $product['image'] ?? null;

                // Bind parameters with correct types
                $updateStmt->bind_param(
                    "idsssi",  // integer, double, string, string, string, integer
                    $product['quantity'],
                    $adjustedPrice,
                    $type,
                    $imageData,
                    $product['category'],
                    $productId
                );

                // Special handling for BLOB parameter
                if ($imageData !== null) {
                    $updateStmt->send_long_data(3, $imageData);
                }

                if (!$updateStmt->execute()) {
                    throw new Exception("Error updating product: " . $updateStmt->error);
                }
                $updateStmt->close();
            } else {
                // Product doesn't exist - insert
                $insertStmt = $conn->prepare("INSERT INTO PRODUCT (
        REFERENCE,
        PRODUCT_NAME,
        PRICE,
        QUANTITY,
        CATEGORY_NAME,
        TYPE,
        IMAGE
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");

                $adjustedPrice = $product['unit_price'] * 1.2;
                $type = 'BL';
                $imageData = $product['image'] ?? null;

                $insertStmt->bind_param(
                    "ssdissb",  // string, string, double, integer, string, string, blob
                    $product['reference'],
                    $product['product_name'],
                    $adjustedPrice,
                    $product['quantity'],
                    $product['category'],
                    $type,
                    $imageData
                );

                // Special handling for BLOB parameter
                if ($imageData !== null) {
                    $insertStmt->send_long_data(6, $imageData);
                }

                if (!$insertStmt->execute()) {
                    throw new Exception("Error inserting product: " . $insertStmt->error);
                }
                $productId = $conn->insert_id;
                $insertStmt->close();


                // Update category count if not Uncategorized
                if ($product['category'] !== 'Uncategorized') {
                    $updateCatStmt = $conn->prepare("UPDATE CATEGORY SET 
                        NUMBER_OF_PRODUCTS = NUMBER_OF_PRODUCTS + 1 
                        WHERE CATEGORYNAME = ?");
                    $updateCatStmt->bind_param("s", $product['category']);

                    if (!$updateCatStmt->execute()) {
                        throw new Exception("Error updating category count: " . $updateCatStmt->error);
                    }
                    $updateCatStmt->close();
                }
            }

            // Insert bon detail
            $detailStmt = $conn->prepare("INSERT INTO BON_LIVRAISON_ACHAT_DETAILS (
                ID_BON,
                PRODUCT_ID,
                PRODUCT_NAME,
                QUANTITY,
                UNIT_PRICE_TTC,
                TOTAL_PRICE_TTC
            ) VALUES (?, ?, ?, ?, ?, ?)");

            $totalPrice = $product['unit_price'] * $product['quantity'];

            $detailStmt->bind_param(
                "issidd",
                $bonId,
                $product['reference'],
                $product['product_name'],
                $product['quantity'],
                $product['unit_price'],
                $totalPrice
            );

            if (!$detailStmt->execute()) {
                throw new Exception("Error saving bon details: " . $detailStmt->error);
            }
            $detailStmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Clear session data
        unset($_SESSION['purchase_invoice']);
        unset($_SESSION['purchase_products']);

        // Set success message
        $_SESSION['success_message'] = "Bon de livraison enregistré avec succès!";

        // Redirect to view page
        redirect('view.php');
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

foreach ($_SESSION['purchase_products'] as $product) {
    $productHT = $product['unit_price'] / 1.2;
    $productTVA = $productHT * 0.2;
    $productTotal = $product['unit_price'] * $product['quantity'];

    $totalHT += $productHT * $product['quantity'];
    $totalTVA += $productTVA * $product['quantity'];
    $totalTTC += $productTotal;
}
?>

<h1>Nouveau Bon de Livraison - Étape 3/3</h1>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error_message'];
                                    unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="invoice-summary">
    <h3>Résumé du Bon</h3>
    <p><strong>Fournisseur:</strong> <?php echo $_SESSION['purchase_invoice']['supplier_name']; ?></p>
    <p><strong>Numéro:</strong> <?php echo $_SESSION['purchase_invoice']['invoice_number']; ?></p>
    <p><strong>Date:</strong> <?php echo $_SESSION['purchase_invoice']['invoice_date']; ?></p>
</div>

<h3>Produits</h3>
<table>
    <thead>
        <tr>
            <th>Quantité</th>
            <th>Désignation</th>
            <th>Prix Unitaire</th>
            <th>Prix Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($_SESSION['purchase_products'] as $product): ?>
            <tr>
                <td><?php echo $product['quantity']; ?></td>
                <td><?php echo $product['product_name']; ?></td>
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
        <a href="create_step2.php" class="btn btn-secondary">Retour</a>
        <button type="submit" name="confirm" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../../includes/footerin.php'; ?>