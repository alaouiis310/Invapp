<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['quote']) || empty($_SESSION['quote_products'])) {
    redirect('create.php');
}

$title = "Confirmer le Devis";
include '../../includes/header.php';

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert quote header
        $stmt = $conn->prepare("INSERT INTO DEVIS_HEADER (
            DEVIS_NUMBER, 
            CLIENT_ID, 
            CLIENT_NAME, 
            COMPANY_ICE, 
            TOTAL_PRICE_TTC, 
            TOTAL_PRICE_HT, 
            TVA, 
            DATE
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;
        
        foreach ($_SESSION['quote_products'] as $product) {
            $productHT = $product['unit_price'] / 1.2;
            $productTVA = $productHT * 0.2;
            $productTotal = $product['unit_price'] * $product['quantity'];
            
            $totalHT += $productHT * $product['quantity'];
            $totalTVA += $productTVA * $product['quantity'];
            $totalTTC += $productTotal;
        }

         // Create a date variable
         $invoiceDate = date('Y-m-d');
        
        $stmt->bind_param("sissddds", 
            $_SESSION['quote']['quote_number'],
            $_SESSION['quote']['client_id'],
            $_SESSION['quote']['client_name'],
            $_SESSION['quote']['company_ice'],
            $totalTTC,
            $totalHT,
            $totalTVA,
            $invoiceDate  // ← Now using a variable
        );
        $stmt->execute();
        $quoteId = $conn->insert_id;
        $stmt->close();
        
        // Insert quote details
        foreach ($_SESSION['quote_products'] as $productId => $product) {
            $detailStmt = $conn->prepare("INSERT INTO DEVIS_DETAILS (
                ID_DEVIS,
                PRODUCT_ID,
                QUANTITY,
                UNIT_PRICE_TTC,
                TOTAL_PRICE_TTC
            ) VALUES (?, ?, ?, ?, ?)");
            
            $totalPrice = $product['unit_price'] * $product['quantity'];
            
            $detailStmt->bind_param("isidd", 
                $quoteId,
                $product['reference'],
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
        require_once '../../libs/fpdf/fpdf.php';
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Quote header
        $pdf->Cell(0, 10, 'DEVIS', 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Numero: ' . $_SESSION['quote']['quote_number'], 0, 1);
        $pdf->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 1);
        $pdf->Cell(0, 10, 'Client: ' . $_SESSION['quote']['client_name'], 0, 1);
        
        if (!empty($_SESSION['quote']['company_ice'])) {
            $pdf->Cell(0, 10, 'ICE: ' . $_SESSION['quote']['company_ice'], 0, 1);
        }
        
        $pdf->Ln(10);
        
        // Products table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 10, 'Quantite', 1);
        $pdf->Cell(60, 10, 'Designation', 1);
        $pdf->Cell(40, 10, 'Prix Unitaire', 1);
        $pdf->Cell(40, 10, 'Prix Total', 1, 1);
        
        $pdf->SetFont('Arial', '', 12);
        foreach ($_SESSION['quote_products'] as $product) {
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
        $pdfPath = '../../quotes/' . $_SESSION['quote']['quote_number'] . '.pdf';
        $pdf->Output('F', $pdfPath);
        
        // Clear session data
        unset($_SESSION['quote']);
        unset($_SESSION['quote_products']);
        
        // Redirect to view page with download link
        $_SESSION['success_message'] = "Devis créé avec succès!";
        $_SESSION['quote_pdf'] = $pdfPath;
        redirect('view.php');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Erreur lors de la création du devis: " . $e->getMessage();
    }
}

// Calculate totals
$totalHT = 0;
$totalTVA = 0;
$totalTTC = 0;

foreach ($_SESSION['quote_products'] as $product) {
    $productHT = $product['unit_price'] / 1.2;
    $productTVA = $productHT * 0.2;
    $productTotal = $product['unit_price'] * $product['quantity'];
    
    $totalHT += $productHT * $product['quantity'];
    $totalTVA += $productTVA * $product['quantity'];
    $totalTTC += $productTotal;
}
?>

<h1>Confirmer le Devis</h1>

<div class="quote-summary">
    <h3>Résumé du Devis</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['quote']['client_name']; ?></p>
    <?php if (!empty($_SESSION['quote']['company_ice'])): ?>
        <p><strong>ICE:</strong> <?php echo $_SESSION['quote']['company_ice']; ?></p>
    <?php endif; ?>
    <p><strong>Numéro:</strong> <?php echo $_SESSION['quote']['quote_number']; ?></p>
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
        <?php foreach ($_SESSION['quote_products'] as $product): ?>
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
        <a href="add_products.php" class="btn btn-secondary">Retour</a>
        <button type="submit" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>