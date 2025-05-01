<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$invoiceId = (int)$_GET['id'];

// Récupérer les données
$stmt = $conn->prepare("SELECT * FROM SELL_INVOICE_HEADER WHERE ID_INVOICE = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('view.php');
}
$invoiceHeader = $headerResult->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM SELL_INVOICE_DETAILS WHERE ID_INVOICE = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$detailsResult = $stmt->get_result();
$invoiceDetails = [];
while ($row = $detailsResult->fetch_assoc()) {
    $invoiceDetails[] = $row;
}
$stmt->close();

$totalHT = $invoiceHeader['TOTAL_PRICE_HT'];
$totalTVA = $invoiceHeader['TVA'];
$totalTTC = $invoiceHeader['TOTAL_PRICE_TTC'];




require_once '../../libs/fpdf/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);


// Client Name
// Move inside the box
    
// Increase box width from 80 to 100mm and adjust position
// In the client information section of print.php:

// Client information with address
$pdf->SetFont('Arial', '', 10);
$boxWidth = 100;
$boxX = 110;

// Calculate box height based on address lines
$addressLines = explode("\n", wordwrap($invoiceHeader['CLIENT_ADDRESS'], 40, "\n"));
$boxHeight = 20 + (count($addressLines) * 5);

$pdf->Rect($boxX, 20, $boxWidth, $boxHeight);

// Client Name
$pdf->SetXY($boxX + 5, 22);
$pdf->Cell(15, 6, 'Nom :', 0, 0);
$pdf->MultiCell($boxWidth - 20, 6, $invoiceHeader['CLIENT_NAME'], 0);

// Client Address
$currentY = $pdf->GetY();
$pdf->SetXY($boxX + 5, $currentY);
$pdf->Cell(15, 6, 'Adresse :', 0, 0);
$pdf->MultiCell($boxWidth - 20, 5, $invoiceHeader['CLIENT_ADDRESS'], 0);

// ICE Number
$currentY = $pdf->GetY();
$pdf->SetXY($boxX + 5, $currentY);
$pdf->Cell(15, 6, 'ICE :', 0, 0);
$pdf->Cell(30, 6, $invoiceHeader['COMPANY_ICE'], 0, 1);

// Reset font size
$pdf->SetFont('Arial', '', 12);
// Encadré client
//$pdf->Rect(122, 20, 80, 20);


// Titre facture
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetXY(10, 46);
$pdf->Cell(0, 10, 'FACTURE', 0, 1);

// Bloc numéro/date
$pdf->SetFont('Arial', '', 12);

// First row (headers)
$pdf->SetXY(10, 58); // Set starting position
$pdf->Cell(30, 8, 'Numero', 1, 0, 'C');
$pdf->Cell(30, 8, 'Date', 1, 1, 'C');

// Second row (data)
$pdf->SetX(10); // Reset X position for the new row
$pdf->Cell(30, 8, $invoiceHeader['INVOICE_NUMBER'], 1, 0, 'C');
$pdf->Cell(30, 8, date('d/m/Y', strtotime($invoiceHeader['DATE'])), 1, 1, 'C');


// Espace avant tableau
$pdf->Ln(10);

// En-tête du tableau des produits
// Define fixed table height (e.g., 5 rows = 50mm if each row is 10mm tall)
$maxRows = 17;
$rowHeight = 8; // Height per row in mm

// Products table header (with borders)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(20, $rowHeight, 'QTE', 'LTRB', 0, 'C');
$pdf->Cell(100, $rowHeight, 'DESIGNATION', 'TRB', 0, 'C');
$pdf->Cell(35, $rowHeight, 'P.U TTC', 'TRB', 0, 'C');
$pdf->Cell(35, $rowHeight, 'TOTAL TTC', 'TRB', 1, 'C');

// Products rows (with vertical borders only)
$pdf->SetFont('Arial', '', 12);
$rowCount = 0;

// Fill actual products

foreach ($invoiceDetails as $detail) {
    if ($rowCount >= $maxRows) break; // Stop if exceeding max rows
    $pdf->Cell(20, $rowHeight, $detail['QUANTITY'], 'LR', 0, 'C');
    $pdf->Cell(100, $rowHeight, $detail['PRODUCT_NAME'], 'R', 0, 'L');
    $pdf->Cell(35, $rowHeight, number_format($detail['UNIT_PRICE_TTC'], 2) . ' DH', 'R', 0, 'R');
    $pdf->Cell(35, $rowHeight, number_format($detail['TOTAL_PRICE_TTC'], 2) . ' DH', 'R', 1, 'R');
    $rowCount++;
}


// Fill remaining empty rows (if needed)
while ($rowCount < $maxRows) {
    $pdf->Cell(20, $rowHeight, '', 'LR', 0, 'C');
    $pdf->Cell(100, $rowHeight, '', 'R', 0, 'L');
    $pdf->Cell(35, $rowHeight, '', 'R', 0, 'R');
    $pdf->Cell(35, $rowHeight, '', 'R', 1, 'R');
    $rowCount++;
}

// Bottom border to close the table
$pdf->Cell(20, 0, '', 'LBR');
$pdf->Cell(100, 0, '', 'BR');
$pdf->Cell(35, 0, '', 'BR');
$pdf->Cell(35, 0, '', 'BR', 1);

// Espace avant totaux
$pdf->Ln(10);

// Texte final
$pdf->SetFont('Arial', 'I', 11);
$pdf->SetTextColor(0, 0, 255);
$pdf->Cell(130, 6, ("La presente facture est arretee a la somme de :"), 0, 1);

// Totaux alignés à droite
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->SetXY(145, 242);
$pdf->Cell(30, 8, 'TOTAL HT', 1, 0, 'C');
$pdf->Cell(30, 8, number_format($totalHT, 2) . ' DH', 1, 1, 'R');
$pdf->SetX(145);
$pdf->Cell(30, 8, 'TVA', 1, 0, 'C');
$pdf->Cell(30, 8, number_format($totalTVA, 2) . ' DH', 1, 1, 'R');
$pdf->SetX(145);
$pdf->Cell(30, 8, 'TOTAL TTC', 1, 0, 'C');
$pdf->Cell(30, 8, number_format($totalTTC, 2) . ' DH', 1, 1, 'R');

$pdf->Output('I', 'facture_' . $invoiceHeader['INVOICE_NUMBER'] . '.pdf');
