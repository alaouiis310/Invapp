<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('../view.php');
}

$quoteId = (int)$_GET['id'];

// Get quote header
$stmt = $conn->prepare("SELECT * FROM DEVIS_HEADER WHERE ID = ?");
$stmt->bind_param("i", $quoteId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('../view.php');
}

$quoteHeader = $headerResult->fetch_assoc();
$stmt->close();

// Get quote details
$stmt = $conn->prepare("SELECT * FROM DEVIS_DETAILS WHERE ID_DEVIS = ?");
$stmt->bind_param("i", $quoteId);
$stmt->execute();
$detailsResult = $stmt->get_result();
$quoteDetails = [];

if ($detailsResult->num_rows > 0) {
    while($row = $detailsResult->fetch_assoc()) {
        // Get product name
        $productStmt = $conn->prepare("SELECT PRODUCT_NAME FROM PRODUCT WHERE REFERENCE = ?");
        $productStmt->bind_param("s", $row['PRODUCT_ID']);
        $productStmt->execute();
        $productResult = $productStmt->get_result();
        
        if ($productResult->num_rows > 0) {
            $product = $productResult->fetch_assoc();
            $row['PRODUCT_NAME'] = $product['PRODUCT_NAME'];
        } else {
            $row['PRODUCT_NAME'] = 'Produit inconnu';
        }
        
        $productStmt->close();
        $quoteDetails[] = $row;
    }
}
$stmt->close();

// Calculate totals
$totalHT = $quoteHeader['TOTAL_PRICE_HT'];
$totalTVA = $quoteHeader['TVA'];
$totalTTC = $quoteHeader['TOTAL_PRICE_TTC'];

require_once '../../libs/fpdf/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Company Info
$pdf->Cell(0, 10, 'InvApp', 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, '123 Rue Principale', 0, 1, 'L');
$pdf->Cell(0, 10, 'Casablanca, Maroc', 0, 1, 'L');
$pdf->Cell(0, 10, 'ICE: 123456789', 0, 1, 'L');
$pdf->Ln(10);

// Quote header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'DEVIS', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Numero: ' . $quoteHeader['DEVIS_NUMBER'], 0, 1);
$pdf->Cell(0, 10, 'Date: ' . date('d/m/Y', strtotime($quoteHeader['DATE'])), 0, 1);
$pdf->Cell(0, 10, 'Client: ' . $quoteHeader['CLIENT_NAME'], 0, 1);

if (!empty($quoteHeader['COMPANY_ICE'])) {
    $pdf->Cell(0, 10, 'ICE: ' . $quoteHeader['COMPANY_ICE'], 0, 1);
}

$pdf->Ln(10);

// Products table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 10, 'Quantite', 1);
$pdf->Cell(60, 10, 'Designation', 1);
$pdf->Cell(40, 10, 'Prix Unitaire', 1);
$pdf->Cell(40, 10, 'Prix Total', 1, 1);

$pdf->SetFont('Arial', '', 12);
foreach ($quoteDetails as $detail) {
    $pdf->Cell(30, 10, $detail['QUANTITY'], 1);
    $pdf->Cell(60, 10, $detail['PRODUCT_NAME'], 1);
    $pdf->Cell(40, 10, number_format($detail['UNIT_PRICE_TTC'], 2) . ' DH', 1);
    $pdf->Cell(40, 10, number_format($detail['TOTAL_PRICE_TTC'], 2) . ' DH', 1, 1);
}

// Totals
$pdf->Ln(10);
$pdf->Cell(130, 10, 'Total HT:', 0, 0, 'R');
$pdf->Cell(40, 10, number_format($totalHT, 2) . ' DH', 0, 1);

$pdf->Cell(130, 10, 'TVA (20%):', 0, 0, 'R');
$pdf->Cell(40, 10, number_format($totalTVA, 2) . ' DH', 0, 1);

$pdf->Cell(130, 10, 'Total TTC:', 0, 0, 'R');
$pdf->Cell(40, 10, number_format($totalTTC, 2) . ' DH', 0, 1);

// Output PDF
$pdf->Output('I', 'devis_' . $quoteHeader['DEVIS_NUMBER'] . '.pdf');
?>