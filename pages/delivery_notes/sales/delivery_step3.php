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
    $clientName = $_SESSION['sales_delivery']['client_name'];
    $verifyClient->bind_param("s", $clientName);
    $verifyClient->execute();

    if ($verifyClient->get_result()->num_rows === 0) {
        $createClient = $conn->prepare("INSERT INTO CLIENT (CLIENTNAME, ICE) VALUES (?, ?)");
        $companyIce = $_SESSION['sales_delivery']['company_ice'] ?? '';
        $createClient->bind_param("ss", $clientName, $companyIce);
        $createClient->execute();
        $createClient->close();
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

        $deliveryDate = date('Y-m-d');

        // Create variables from session data
        $deliveryNumber = $_SESSION['sales_delivery']['delivery_number'];
        $clientName = $_SESSION['sales_delivery']['client_name'];
        $companyIce = $_SESSION['sales_delivery']['company_ice'] ?? '';
        $deliveryType = $_SESSION['sales_delivery']['delivery_type'];
        $paymentMethod = $_SESSION['sales_delivery']['payment_method'];

        $stmt->bind_param(
            "sssssddds",
            $deliveryNumber,
            $clientName,
            $companyIce,
            $deliveryType,
            $paymentMethod,
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

        // Fetch the inserted delivery data for PDF generation
        $deliveryHeaderQuery = $conn->prepare("SELECT * FROM BON_LIVRAISON_VENTE_HEADER WHERE ID = ?");
        $deliveryHeaderQuery->bind_param("i", $deliveryId);
        $deliveryHeaderQuery->execute();
        $deliveryHeader = $deliveryHeaderQuery->get_result()->fetch_assoc();
        $deliveryHeaderQuery->close();

        $deliveryDetailsQuery = $conn->prepare("SELECT * FROM BON_LIVRAISON_VENTE_DETAILS WHERE ID_BON = ?");
        $deliveryDetailsQuery->bind_param("i", $deliveryId);
        $deliveryDetailsQuery->execute();
        $deliveryDetails = $deliveryDetailsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $deliveryDetailsQuery->close();

        // Generate PDF
        require_once '../../../libs/fpdf/fpdf.php';

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);

        // Client information
        if (isset($deliveryHeader['CLIENT_NAME'])) {
            $pdf->SetFont('Arial', '', 12);
            $boxHeight = 32;
            $boxWidth = 100;
            $boxX = 100;
            $pdf->Rect($boxX, 20, $boxWidth, $boxHeight);

            // Client Name
            $pdf->SetXY($boxX + 5, 22);
            $pdf->Cell(13, 6, 'Nom :', 0, 0);
            $pdf->MultiCell($boxWidth - 20, 6, $deliveryHeader['CLIENT_NAME'], 0);

            // ICE Number
            $pdf->SetFont('Arial', '', 10);
            $currentY = $pdf->GetY();
            $pdf->SetXY($boxX + 5, $currentY);
            $pdf->Cell(10, 6, 'ICE :', 0, 0);
            $pdf->Cell(22, 6, $deliveryHeader['COMPANY_ICE'], 0, 1);

            // Reset font size
            $pdf->SetFont('Arial', '', 11);
        }

        // Delivery title
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetXY(10, 46);
        $pdf->Cell(0, 10, 'BON DE LIVRAISON', 0, 1);

        // Number/date block
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetXY(10, 58);
        $pdf->Cell(30, 8, 'Numero', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Date', 1, 1, 'C');
        $pdf->SetFont('Arial', '', 10);

        $pdf->SetX(10);
        $pdf->Cell(30, 8, $deliveryHeader['ID_BON'], 1, 0, 'C');
        $pdf->Cell(30, 8, date('d/m/Y', strtotime($deliveryHeader['DATE'])), 1, 1, 'C');

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

        if (is_array($deliveryDetails)) {
            foreach ($deliveryDetails as $detail) {
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

        $pdf->Ln(10);
        $startY = $pdf->GetY();

        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 11);
        $pdf->SetTextColor(0, 0, 255);






        // Save Y position after explanatory text
        $afterTextY = $pdf->GetY();

        // Numeric totals on the right (fixed position)
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



        // Save PDF to file in ../../BL directory
        $pdfPath = '../../BL/' . $deliveryHeader['ID_BON'] . '.pdf';
        if (!file_exists('../../BL')) {
            if (!mkdir('../../BL', 0777, true)) {
                throw new Exception("Could not create BL directory");
            }
        }

        $pdf->Output('F', $pdfPath);

        if (!file_exists($pdfPath)) {
            throw new Exception("PDF file was not created successfully");
        }

        // Clear session data
        unset($_SESSION['sales_delivery']);
        unset($_SESSION['sales_products']);

        // Set success message and redirect
        $_SESSION['success_message'] = "Bon de livraison enregistré avec succès!";
        $_SESSION['delivery_pdf'] = $pdfPath;
        redirect('view.php');
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
        error_log("Delivery Error: " . $e->getMessage());
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

<h1>Nouveau Bon de Livraison Vente - Étape 3/3</h1>

<div class="delivery-summary">
    <h3>Résumé du Bon de Livraison</h3>
    <p><strong>Client:</strong> <?php echo htmlspecialchars($_SESSION['sales_delivery']['client_name']); ?></p>
    <?php if ($_SESSION['sales_delivery']['delivery_type'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo htmlspecialchars($_SESSION['sales_delivery']['company_ice']); ?></p>
    <?php endif; ?>
    <p><strong>Méthode de Paiement:</strong> <?php echo ucfirst($_SESSION['sales_delivery']['payment_method']); ?></p>
    <p><strong>Numéro:</strong> <?php echo htmlspecialchars($_SESSION['sales_delivery']['delivery_number']); ?></p>
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
        <a href="delivery_step2.php" class="btn btn-secondary">Retour</a>
        <button type="submit" class="btn btn-success">Confirmer et Enregistrer</button>
    </div>
</form>

<?php include '../../../includes/footerin.php'; ?>