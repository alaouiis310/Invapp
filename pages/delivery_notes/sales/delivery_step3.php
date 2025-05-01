<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['sales_delivery']) || empty($_SESSION['sales_products'])) {
    redirect('delivery_step1.php');
}

$title = "Bon de Livraison Vente - Étape 3/3";
include '../../../includes/headerin.php';

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    // Verify client exists (should exist from step1, but double-check)
    $verifyClient = $conn->prepare("SELECT ID FROM CLIENT WHERE CLIENTNAME = ?");
    $verifyClient->bind_param("s", $_SESSION['sales_delivery']['client_name']);
    $verifyClient->execute();

    if ($verifyClient->get_result()->num_rows === 0) {
        // Create client if somehow missing
        $createClient = $conn->prepare("INSERT INTO CLIENT (CLIENTNAME, ICE) VALUES (?, ?)");
        $createClient->bind_param(
            "ss",
            $_SESSION['sales_delivery']['client_name'],
            $_SESSION['sales_delivery']['company_ice']
        );
        $createClient->execute();
        $createClient->close();
    }
    $verifyClient->close();

    try {
        // Insert delivery header
        $stmt = $conn->prepare("INSERT INTO BON_LIVRAISON_VENTE_HEADER (
            ID_BON, 
            CLIENT_NAME, 
            COMPANY_ICE, 
            INVOICE_TYPE, 
            PAYMENT_METHOD,
            TOTAL_PRICE_TTC, 
            TOTAL_PRICE_HT, 
            TVA, 
            DATE
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;

        foreach ($_SESSION['sales_products'] as $product) {
            $productHT = $product['unit_price'] / 1.2;
            $productTVA = $productHT * 0.2;
            $productTotal = $product['unit_price'] * $product['quantity'];

            $totalHT += $productHT * $product['quantity'];
            $totalTVA += $productTVA * $product['quantity'];
            $totalTTC += $productTotal;
        }

        // Calculate values first
        $totalHT = (float)$totalHT;
        $totalTVA = (float)$totalTVA;
        $totalTTC = (float)$totalTTC;

        // Create a date variable
        $deliveryDate = date('Y-m-d');

        $stmt->bind_param(
            "sssssddds",
            $_SESSION['sales_delivery']['delivery_number'],
            $_SESSION['sales_delivery']['client_name'],
            $_SESSION['sales_delivery']['company_ice'],
            $_SESSION['sales_delivery']['delivery_type'],
            $_SESSION['sales_delivery']['payment_method'],
            $totalTTC,
            $totalHT,
            $totalTVA,
            $deliveryDate
        );
        $stmt->execute();
        $deliveryId = $conn->insert_id;
        $stmt->close();

        // Insert delivery details and update stock
        foreach ($_SESSION['sales_products'] as $productId => $product) {
            // Update product quantity
            $updateStmt = $conn->prepare("UPDATE PRODUCT SET QUANTITY = QUANTITY - ? WHERE ID = ?");
            $updateStmt->bind_param("ii", $product['quantity'], $productId);
            $updateStmt->execute();
            $updateStmt->close();

            // Insert delivery detail
            $detailStmt = $conn->prepare("INSERT INTO BON_LIVRAISON_VENTE_DETAILS (
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
                $deliveryId,
                $product['reference'],
                $product['product_name'],
                $product['quantity'],
                $product['unit_price'],
                $totalPrice
            );
            $detailStmt->execute();
            $detailStmt->close();
        }

        // Commit transaction
        $conn->commit();

        // Generate PDF
        require_once '../../../libs/fpdf/fpdf.php';

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);

        // Delivery header
        $pdf->Cell(0, 10, 'Bon de Livraison Vente', 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Numero: ' . $_SESSION['sales_delivery']['delivery_number'], 0, 1);
        $pdf->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 1);
        $pdf->Cell(0, 10, 'Client: ' . $_SESSION['sales_delivery']['client_name'], 0, 1);
        $pdf->Cell(0, 10, 'Méthode de Paiement: ' . $_SESSION['sales_delivery']['payment_method'], 0, 1);

        if ($_SESSION['sales_delivery']['delivery_type'] === 'Company') {
            $pdf->Cell(0, 10, 'ICE: ' . $_SESSION['sales_delivery']['company_ice'], 0, 1);
        }

        $pdf->Ln(10);

        // Products table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 10, 'Quantite', 1);
        $pdf->Cell(60, 10, 'Designation', 1);
        $pdf->Cell(40, 10, 'Prix Unitaire', 1);
        $pdf->Cell(40, 10, 'Prix Total', 1, 1);

        $pdf->SetFont('Arial', '', 12);
        foreach ($_SESSION['sales_products'] as $product) {
            $pdf->Cell(30, 10, $product['quantity'], 1);
            $pdf->Cell(60, 10, $product['product_name'], 1);
            $pdf->Cell(40, 10, number_format($product['unit_price'], 2) . ' DH', 1);
            $pdf->Cell(40, 10, number_format($product['unit_price'] * $product['quantity'], 2) . ' DH', 1, 1);
        }

        // Totals
        $pdf->Ln(10);
        $pdf->Cell(130, 10, 'Total HT:', 0, 0, 'R');
        $pdf->Cell(40, 10, number_format($totalHT, 2) . ' DH', 0, 1);

        $pdf->Cell(130, 10, 'TVA (20%):', 0, 0, 'R');
        $pdf->Cell(40, 10, number_format($totalTVA, 2) . ' DH', 0, 1);

        $pdf->Cell(130, 10, 'Total TTC:', 0, 0, 'R');
        $pdf->Cell(40, 10, number_format($totalTTC, 2) . ' DH', 0, 1);

        // Save PDF
        $pdfPath = '../../deliveries/' . $_SESSION['sales_delivery']['delivery_number'] . '.pdf';
        $pdf->Output('F', $pdfPath);

        // Clear session data
        unset($_SESSION['sales_delivery']);
        unset($_SESSION['sales_products']);

        // Redirect to view page with download link
        $_SESSION['success_message'] = "Bon de livraison enregistré avec succès!";
        $_SESSION['delivery_pdf'] = $pdfPath;
        redirect('view.php');
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}

// Calculate totals
$totalHT = 0;
$totalTVA = 0;
$totalTTC = 0;

foreach ($_SESSION['sales_products'] as $product) {
    $productHT = $product['unit_price'] / 1.2;
    $productTVA = $productHT * 0.2;
    $productTotal = $product['unit_price'] * $product['quantity'];

    $totalHT += $productHT * $product['quantity'];
    $totalTVA += $productTVA * $product['quantity'];
    $totalTTC += $productTotal;
}
?>

<h1>Nouveau Bon de Livraison Vente - Étape 3/3</h1>

<div class="delivery-summary">
    <h3>Résumé du Bon de Livraison</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['sales_delivery']['client_name']; ?></p>
    <?php if ($_SESSION['sales_delivery']['delivery_type'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo $_SESSION['sales_delivery']['company_ice']; ?></p>
    <?php endif; ?>
    <p><strong>Méthode de Paiement:</strong> <?php echo $_SESSION['sales_delivery']['payment_method']; ?></p>
    <p><strong>Numéro:</strong> <?php echo $_SESSION['sales_delivery']['delivery_number']; ?></p>
    <p><strong>Date:</strong> <?php echo date('d/m/Y'); ?></p>
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
        <?php foreach ($_SESSION['sales_products'] as $product): ?>
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
            <th colspan="2">Totaux</th>
            <th>HT: <?php echo number_format($totalHT, 2); ?> DH</th>
            <th>TTC: <?php echo number_format($totalTTC, 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">TVA (20%)</th>
            <th><?php echo number_format($totalTVA, 2); ?> DH</th>
        </tr>
    </tfoot>
</table>

<form action="" method="post">
    <div class="form-actions">
        <a href="delivery_step2.php" class="btn btn-secondary">Retour</a>
        <button type="submit" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../../includes/footerin.php'; ?>