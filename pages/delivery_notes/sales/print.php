<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$deliveryId = (int)$_GET['id'];

// Get delivery header
$stmt = $conn->prepare("SELECT * FROM BON_LIVRAISON_VENTE_HEADER WHERE ID = ?");
$stmt->bind_param("i", $deliveryId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('../view.php');
}

$deliveryHeader = $headerResult->fetch_assoc();
$stmt->close();

// Get delivery details
$stmt = $conn->prepare("SELECT * FROM BON_LIVRAISON_VENTE_DETAILS WHERE ID_BON = ?");
$stmt->bind_param("i", $deliveryId);
$stmt->execute();
$detailsResult = $stmt->get_result();
$deliveryDetails = [];

if ($detailsResult->num_rows > 0) {
    while($row = $detailsResult->fetch_assoc()) {
        $deliveryDetails[] = $row;
    }
}
$stmt->close();

// Calculate totals
$totalHT = $deliveryHeader['TOTAL_PRICE_HT'];
$totalTVA = $deliveryHeader['TVA'];
$totalTTC = $deliveryHeader['TOTAL_PRICE_TTC'];

require_once '../../../libs/fpdf/fpdf.php';

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

// Delivery header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'BON DE LIVRAISON VENTE', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Numero: ' . $deliveryHeader['ID_BON'], 0, 1);
$pdf->Cell(0, 10, 'Date: ' . date('d/m/Y', strtotime($deliveryHeader['DATE'])), 0, 1);
$pdf->Cell(0, 10, 'Client: ' . $deliveryHeader['CLIENT_NAME'], 0, 1);
$pdf->Cell(0, 10, 'Méthode de Paiement: ' . $deliveryHeader['PAYMENT_METHOD'], 0, 1);

if ($deliveryHeader['INVOICE_TYPE'] === 'Company') {
    $pdf->Cell(0, 10, 'ICE: ' . $deliveryHeader['COMPANY_ICE'], 0, 1);
}

$pdf->Ln(10);

// Products table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 10, 'Quantite', 1);
$pdf->Cell(60, 10, 'Designation', 1);
$pdf->Cell(40, 10, 'Prix Unitaire', 1);
$pdf->Cell(40, 10, 'Prix Total', 1, 1);

$pdf->SetFont('Arial', '', 12);
foreach ($deliveryDetails as $detail) {
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
$pdf->Output('I', 'bon_livraison_' . $deliveryHeader['ID_BON'] . '.pdf');
?>