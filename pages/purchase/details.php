<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$invoiceId = (int)$_GET['id'];
$fromStock = isset($_GET['from']) && $_GET['from'] === 'stock';

// Get invoice header
$stmt = $conn->prepare("SELECT * FROM BUY_INVOICE_HEADER WHERE ID_INVOICE = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('view.php');
}

$invoiceHeader = $headerResult->fetch_assoc();
$stmt->close();

// Get invoice details
$stmt = $conn->prepare("SELECT * FROM BUY_INVOICE_DETAILS WHERE ID_INVOICE = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$detailsResult = $stmt->get_result();
$invoiceDetails = [];

if ($detailsResult->num_rows > 0) {
    while($row = $detailsResult->fetch_assoc()) {
        $invoiceDetails[] = $row;
    }
}
$stmt->close();

// Check if there are any credit notes for this invoice
$stmt = $conn->prepare("SELECT * FROM BUY_CREDIT_NOTE_HEADER WHERE INVOICE_NUMBER = ?");
$stmt->bind_param("s", $invoiceHeader['INVOICE_NUMBER']);
$stmt->execute();
$creditNotesResult = $stmt->get_result();
$creditNotes = [];

if ($creditNotesResult->num_rows > 0) {
    while($row = $creditNotesResult->fetch_assoc()) {
        $creditNotes[] = $row;
    }
}
$stmt->close();

$title = "Détails Facture d'Achat #" . $invoiceHeader['INVOICE_NUMBER'];
include '../../includes/header.php';
?>

<h1>Détails Facture d'Achat #<?php echo $invoiceHeader['INVOICE_NUMBER']; ?></h1>

<div class="invoice-header">
    <p><strong>Fournisseur:</strong> <?php echo $invoiceHeader['SUPPLIER_NAME']; ?></p>
    <p><strong>Date:</strong> <?php echo $invoiceHeader['DATE']; ?></p>
    <p><strong>Total HT:</strong> <?php echo number_format($invoiceHeader['TOTAL_PRICE_HT'], 2); ?> DH</p>
    <p><strong>TVA:</strong> <?php echo number_format($invoiceHeader['TOTAL_PRICE_TVA'], 2); ?> DH</p>
    <p><strong>Total TTC:</strong> <?php echo number_format($invoiceHeader['TOTAL_PRICE_TTC'], 2); ?> DH</p>
    
    <?php if (!empty($creditNotes)): ?>
        <p><strong>Avoirs associés:</strong> 
            <?php foreach ($creditNotes as $creditNote): ?>
                <a href="../Avoir_achat/details.php?id=<?php echo $creditNote['ID_CREDIT_NOTE']; ?>&invoice_id=<?php echo $invoiceId; ?>">
                    <?php echo $creditNote['CREDIT_NOTE_NUMBER']; ?> (<?php echo number_format($creditNote['TOTAL_PRICE_TTC'], 2); ?> DH)
                </a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</div>

<h3>Produits</h3>
<table>
    <thead>
        <tr>
            <th>Référence</th>
            <th>Désignation</th>
            <th>Quantité</th>
            <th>Prix Unitaire</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($invoiceDetails as $detail): ?>
            <tr>
                <td><?php echo $detail['PRODUCT_ID']; ?></td>
                <td><?php echo $detail['PRODUCT_NAME']; ?></td>
                <td><?php echo $detail['QUANTITY']; ?></td>
                <td><?php echo number_format($detail['UNIT_PRICE_TTC'], 2); ?> DH</td>
                <td><?php echo number_format($detail['TOTAL_PRICE'], 2); ?> DH</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($invoiceHeader['IMAGE'])): ?>
    <h3>Image de la Facture</h3>
    <div class="invoice-image">
        <?php 
        if (isset($invoiceHeader['IMAGE']) && !empty($invoiceHeader['IMAGE'])) {
            $imageInfo = getimagesizefromstring($invoiceHeader['IMAGE']);
            if ($imageInfo !== false) {
                $mime = $imageInfo['mime'];
                $base64 = base64_encode($invoiceHeader['IMAGE']);
                echo '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Invoice Image">';
            } else {
                echo '<p>Invalid image data</p>';
            }
        } else {
            echo '<p>No image available</p>';
        }
        ?>
        <div class="image-actions">
            <a href="download.php?id=<?php echo $invoiceId; ?>" class="btn btn-primary">Télécharger</a>
            <button onclick="window.print()" class="btn btn-secondary">Imprimer</button>
        </div>
    </div>
<?php endif; ?>

<div class="form-actions">
    <?php if ($fromStock): ?>
        <a href="../products/stock.php" class="btn btn-secondary">Retour au stock</a>
    <?php endif; ?>
    <a href="view.php" class="btn btn-secondary">Retour à la liste</a>
</div>

<?php include '../../includes/footer.php'; ?>