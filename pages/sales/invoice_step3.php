<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['sales_invoice']) || empty($_SESSION['sales_products'])) {
    redirect('invoice_step1.php');
}

$title = "Facture de Vente - Étape 3/3";
include '../../includes/header.php';

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();










    function numberToFrenchWords($number)
    {
        $units = array('', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf');
        $teens = array('dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf');
        $tens = array('', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix');
        $thousands = array('', 'mille', 'million', 'milliard');

        $words = array();
        $number = str_replace(',', '', $number);
        $parts = explode('.', $number);
        $whole = (int)$parts[0];
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 2) : '00';

        if ($whole == 0) {
            $words[] = 'zéro';
        } else {
            $chunks = array_reverse(str_split(str_pad($whole, 12, '0', STR_PAD_LEFT), 3));

            foreach ($chunks as $i => $chunk) {
                if ($chunk != '000') {
                    $chunkWords = array();
                    $hundreds = (int)substr($chunk, 0, 1);
                    $tensUnits = (int)substr($chunk, 1, 2);

                    if ($hundreds > 0) {
                        $chunkWords[] = ($hundreds == 1 ? '' : $units[$hundreds]) . ' cent';
                    }

                    if ($tensUnits > 0) {
                        if ($tensUnits < 10) {
                            $chunkWords[] = $units[$tensUnits];
                        } elseif ($tensUnits < 20) {
                            $chunkWords[] = $teens[$tensUnits - 10];
                        } else {
                            $ten = (int)($tensUnits / 10);
                            $unit = $tensUnits % 10;

                            if ($ten == 7 || $ten == 9) {
                                $ten--; // Soixante-dix becomes soixante, quatre-vingt-dix becomes quatre-vingt
                                $unit += 10;
                            }

                            if ($unit == 0) {
                                $chunkWords[] = $tens[$ten];
                            } elseif ($unit == 1 && $ten != 8) {
                                $chunkWords[] = $tens[$ten] . '-et-un';
                            } else {
                                $chunkWords[] = $tens[$ten] . '-' . $units[$unit];
                            }
                        }
                    }

                    if ($i > 0) {
                        $chunkWords[] = $thousands[$i] . ($i > 1 && $chunk > 1 ? 's' : '');
                    }

                    $words[] = implode(' ', $chunkWords);
                }
            }
        }

        $result = implode(' ', array_reverse($words));
        $result = str_replace('  ', ' ', $result);

        if ($decimal != '00') {
            $result .= ' virgule ' . numberToFrenchWords($decimal);
        }

        return $result . ' dirhams';
    }


































    // Verify client exists (should exist from step1, but double-check)
    $verifyClient = $conn->prepare("SELECT ID FROM CLIENT WHERE CLIENTNAME = ?");
    $clientName = $_SESSION['sales_invoice']['client_name'];
    $verifyClient->bind_param("s", $clientName);
    $verifyClient->execute();

    if ($verifyClient->get_result()->num_rows === 0) {
        $createClient = $conn->prepare("INSERT INTO CLIENT (CLIENTNAME, ICE, ADDRESS) VALUES (?, ?, ?)");
        $companyIce = $_SESSION['sales_invoice']['company_ice'] ?? '';
        $clientAddress = $_SESSION['sales_invoice']['client_address'] ?? '';
        $createClient->bind_param("sss", $clientName, $companyIce, $clientAddress);
        $createClient->execute();
        $createClient->close();
    } else {
        // Update existing client's address if needed
        $updateAddress = $conn->prepare("UPDATE CLIENT SET ADDRESS = ? WHERE CLIENTNAME = ?");
        $clientAddress = $_SESSION['sales_invoice']['client_address'] ?? '';
        $clientName = $_SESSION['sales_invoice']['client_name'];
        $updateAddress->bind_param("ss", $clientAddress, $clientName);
        $updateAddress->execute();
        $updateAddress->close();
    }
    $verifyClient->close();

    try {
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


        // Update the INSERT statement for SELL_INVOICE_HEADER
        $stmt = $conn->prepare("INSERT INTO SELL_INVOICE_HEADER (
    INVOICE_NUMBER, 
    CLIENT_NAME, 
    CLIENT_ADDRESS,
    COMPANY_ICE, 
    INVOICE_TYPE, 
    TOTAL_PRICE_TTC, 
    TOTAL_PRICE_HT, 
    TVA, 
    DATE,
    PAYMENT_TYPE,
    PAYMENT_REFERENCE
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $invoiceDate = date('Y-m-d');

        // Create variables from session data
        $invoiceNumber = $_SESSION['sales_invoice']['invoice_number'];
        $clientName = $_SESSION['sales_invoice']['client_name'];
        $clientAddress = $_SESSION['sales_invoice']['client_address'] ?? ''; // Default empty string if null
        $companyIce = $_SESSION['sales_invoice']['company_ice'] ?? '';
        $invoiceType = $_SESSION['sales_invoice']['invoice_type'];

        $stmt->bind_param(
            "sssssdddsss",  // Notice the extra 'ss' for payment type and reference
            $invoiceNumber,        // s
            $clientName,           // s
            $clientAddress,        // s
            $companyIce,           // s
            $invoiceType,          // s
            $totalTTC,             // d
            $totalHT,              // d
            $totalTVA,             // d
            $invoiceDate,          // s
            $_SESSION['sales_invoice']['payment_type'], // s
            $_SESSION['sales_invoice']['payment_reference'] // s
        );
        $stmt->execute();
        $invoiceId = $conn->insert_id;
        $stmt->close();

        // Insert invoice details and update stock
        foreach ($_SESSION['sales_products'] as $productId => $product) {
            // Update product quantity
            $updateStmt = $conn->prepare("UPDATE PRODUCT SET QUANTITY = QUANTITY - ? WHERE ID = ?");
            $updateStmt->bind_param("ii", $product['quantity'], $productId);
            $updateStmt->execute();
            $updateStmt->close();

            // Insert invoice detail
            $detailStmt = $conn->prepare("INSERT INTO SELL_INVOICE_DETAILS (
                ID_INVOICE,
                PRODUCT_ID,
                PRODUCT_NAME,
                QUANTITY,
                UNIT_PRICE_TTC,
                TOTAL_PRICE_TTC
            ) VALUES (?, ?, ?, ?, ?, ?)");

            $totalPrice = $product['unit_price'] * $product['quantity'];

            $detailStmt->bind_param(
                "issidd",
                $invoiceId,
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

        // Fetch the inserted invoice data for PDF generation
        $invoiceHeaderQuery = $conn->prepare("SELECT * FROM SELL_INVOICE_HEADER WHERE ID_INVOICE = ?");
        $invoiceHeaderQuery->bind_param("i", $invoiceId);
        $invoiceHeaderQuery->execute();
        $invoiceHeader = $invoiceHeaderQuery->get_result()->fetch_assoc();
        $invoiceHeaderQuery->close();

        $invoiceDetailsQuery = $conn->prepare("SELECT * FROM SELL_INVOICE_DETAILS WHERE ID_INVOICE = ?");
        $invoiceDetailsQuery->bind_param("i", $invoiceId);
        $invoiceDetailsQuery->execute();
        $invoiceDetails = $invoiceDetailsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $invoiceDetailsQuery->close();

        // Generate PDF
        require_once '../../libs/fpdf/fpdf.php';

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);


        // Client information with address
        if (isset($invoiceHeader['CLIENT_NAME'])) {
            $pdf->SetFont('Arial', '', 12); // Smaller font for address

            // Only include address section if not empty
            $address = !empty($invoiceHeader['CLIENT_ADDRESS']) ? $invoiceHeader['CLIENT_ADDRESS'] : false;
            $boxHeight = $address ? (20 + (count(explode("\n", wordwrap($address, 40, "\n"))) * 5)) : 32;

            // Draw client info box
            $boxWidth = 100;
            $boxX = 100;
            $pdf->Rect($boxX, 20, $boxWidth, $boxHeight);

            // Client Name
            $pdf->SetXY($boxX + 5, 22);
            $pdf->Cell(13, 6, 'Nom :', 0, 0);
            $pdf->MultiCell($boxWidth - 20, 6, $invoiceHeader['CLIENT_NAME'], 0);

            $pdf->Ln(2);

            // Client Address (only if exists)
            if ($address) {
                $pdf->SetFont('Arial', '', 10);
                $currentY = $pdf->GetY();
                $pdf->SetXY($boxX + 5, $currentY);
                $pdf->Cell(18, 6, 'Adresse :', 0, 0);
                $pdf->MultiCell($boxWidth - 20, 5, $address, 0);
            }

            $pdf->Ln(2);

            // ICE Number
            $pdf->SetFont('Arial', '', 10);
            $currentY = $pdf->GetY();
            $pdf->SetXY($boxX + 5, $currentY);
            $pdf->Cell(10, 6, 'ICE :', 0, 0);
            $pdf->Cell(22, 6, $invoiceHeader['COMPANY_ICE'], 0, 1);

            // Reset font size
            $pdf->SetFont('Arial', '', 11);
        }

        // Invoice title
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetXY(10, 46);
        $pdf->Cell(0, 10, 'FACTURE', 0, 1);

        // Number/date block
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetXY(10, 58);
        $pdf->Cell(30, 8, 'Numero', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Date', 1, 1, 'C');
        $pdf->SetFont('Arial', '', 10);

        $pdf->SetX(10);
        $pdf->Cell(30, 8, $invoiceHeader['INVOICE_NUMBER'], 1, 0, 'C');
        $pdf->Cell(30, 8, date('d/m/Y', strtotime($invoiceHeader['DATE'])), 1, 1, 'C');

        // Products table
        $pdf->Ln(10);
        $maxRows = 17;
        $rowHeight = 8;

        // Table header
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(20, $rowHeight, 'QTE', 'LTRB', 0, 'C');
        $pdf->Cell(115, $rowHeight, 'DESIGNATION', 'TRB', 0, 'C');
        $pdf->Cell(20, $rowHeight, 'P.U TTC', 'TRB', 0, 'C');
        $pdf->Cell(35, $rowHeight, 'TOTAL TTC', 'TRB', 1, 'C');

        // Table rows
        $pdf->SetFont('Arial', '', 11);
        $rowCount = 0;

        if (is_array($invoiceDetails)) {
            foreach ($invoiceDetails as $detail) {
                if ($rowCount >= $maxRows) break;
                $pdf->Cell(20, $rowHeight, $detail['QUANTITY'], 'LR', 0, 'C');
                $pdf->Cell(115, $rowHeight, $detail['PRODUCT_NAME'], 'R', 0, 'L');
                $pdf->Cell(20, $rowHeight, number_format($detail['UNIT_PRICE_TTC'], 2), 'R', 0, 'R');
                $pdf->Cell(35, $rowHeight, number_format($detail['TOTAL_PRICE_TTC'], 2), 'R', 1, 'R');
                $rowCount++;
            }
        }

        // Fill empty rows
        while ($rowCount < $maxRows) {
            $pdf->Cell(20, $rowHeight, '', 'LR', 0, 'C');
            $pdf->Cell(115, $rowHeight, '', 'R', 0, 'L');
            $pdf->Cell(20, $rowHeight, '', 'R', 0, 'R');
            $pdf->Cell(35, $rowHeight, '', 'R', 1, 'R');
            $rowCount++;
        }

        // Bottom border
        $pdf->Cell(20, 0, '', 'LBR');
        $pdf->Cell(115, 0, '', 'BR');
        $pdf->Cell(20, 0, '', 'BR');
        $pdf->Cell(35, 0, '', 'BR', 1);






        $pdf->Ln(10); // ← Adds space between product table and this block
        $startY = $pdf->GetY();

        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 11);
        $pdf->SetTextColor(0, 0, 255);

        // Convertir total en lettres
        $totalInWords = numberToFrenchWords(number_format($totalTTC, 2));

        // Texte explicatif à gauche
        $pdf->SetXY(10, $startY);
        $pdf->MultiCell(120, 6, "La presente facture est arretee a la somme de : \n" . $totalInWords);

        // Enregistrer la position Y après le texte explicatif
        $afterTextY = $pdf->GetY();

        // Totaux numériques à droite (position fixe)
        $pdf->SetXY(140, $startY);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(30, 8, 'TOTAL HT', 1, 0, 'C');
        $pdf->Cell(30, 8, number_format($totalHT, 2), 1, 1, 'R');

        $pdf->SetX(140);
        $pdf->Cell(30, 8, 'TVA', 1, 0, 'C');
        $pdf->Cell(30, 8, number_format($totalTVA, 2), 1, 1, 'R');

        $pdf->SetX(140);
        $pdf->Cell(30, 8, 'TOTAL TTC', 1, 0, 'C');
        $pdf->Cell(30, 8, number_format($totalTTC, 2), 1, 1, 'R');

        // Bloc "Paye par" en dessous du texte explicatif
        $pdf->SetY($afterTextY + 5);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(40, 8, 'Paye par:', 0, 0);
        $pdf->Cell(0, 8, ucfirst($_SESSION['sales_invoice']['payment_type']), 0, 1);

        if ($_SESSION['sales_invoice']['payment_type'] === 'cheque' || $_SESSION['sales_invoice']['payment_type'] === 'effet') {
            $pdf->Cell(40, 8, 'Numero:', 0, 0);
            $pdf->Cell(0, 8, $_SESSION['sales_invoice']['payment_reference'], 0, 1);
        }


        // Save PDF to file
        $pdfPath = '../../invoices/' . $invoiceHeader['INVOICE_NUMBER'] . '.pdf';
        if (!file_exists('../../invoices')) {
            if (!mkdir('../../invoices', 0777, true)) {
                throw new Exception("Could not create invoices directory");
            }
        }

        $pdf->Output('F', $pdfPath);

        if (!file_exists($pdfPath)) {
            throw new Exception("PDF file was not created successfully");
        }

        // Clear session data
        unset($_SESSION['sales_invoice']);
        unset($_SESSION['sales_products']);

        // Set success message and redirect
        $_SESSION['success_message'] = "Facture de vente enregistrée avec succès!";
        $_SESSION['invoice_pdf'] = $pdfPath;
        redirect('view.php');
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
        error_log("Invoice Error: " . $e->getMessage());
        header("Location: view.php");
        exit();
    }
}

// Calculate totals for display
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

<!-- Rest of your HTML remains the same -->

<h1>Nouvelle Facture de Vente - Étape 3/3</h1>

<div class="invoice-summary">
    <h3>Résumé de la Facture</h3>
    <p><strong>Client:</strong> <?php echo htmlspecialchars($_SESSION['sales_invoice']['client_name']); ?></p>
    <?php if ($_SESSION['sales_invoice']['invoice_type'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo htmlspecialchars($_SESSION['sales_invoice']['company_ice']); ?></p>
    <?php endif; ?>
    <p><strong>Mode de Paiement:</strong> <?php echo ucfirst($_SESSION['sales_invoice']['payment_type']); ?></p>
    <?php if ($_SESSION['sales_invoice']['payment_type'] === 'cheque' || $_SESSION['sales_invoice']['payment_type'] === 'effet'): ?>
        <p><strong>Référence:</strong> <?php echo htmlspecialchars($_SESSION['sales_invoice']['payment_reference']); ?></p>
    <?php endif; ?>
    <p><strong>Numéro:</strong> <?php echo htmlspecialchars($_SESSION['sales_invoice']['invoice_number']); ?></p>
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
                <td><?php echo htmlspecialchars($product['quantity']); ?></td>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
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
        <a href="invoice_step2.php" class="btn btn-secondary">Retour</a>
        <button type="submit" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>