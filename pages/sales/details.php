<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$invoiceId = (int)$_GET['id'];

// Get invoice header
$stmt = $conn->prepare("SELECT * FROM SELL_INVOICE_HEADER WHERE ID_INVOICE = ?");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('view.php');
}

$invoiceHeader = $headerResult->fetch_assoc();
$stmt->close();

// Get invoice details
$stmt = $conn->prepare("SELECT * FROM SELL_INVOICE_DETAILS WHERE ID_INVOICE = ?");
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

$title = "Détails Facture de Vente #" . $invoiceHeader['INVOICE_NUMBER'];
include '../../includes/header.php';
?>

<h1>Détails Facture de Vente #<?php echo $invoiceHeader['INVOICE_NUMBER']; ?></h1>

<div class="invoice-header">
    <p><strong>Client:</strong> <?php echo $invoiceHeader['CLIENT_NAME']; ?></p>
    <?php if ($invoiceHeader['INVOICE_TYPE'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo $invoiceHeader['COMPANY_ICE']; ?></p>
    <?php endif; ?>
    <p><strong>Type:</strong> <?php echo $invoiceHeader['INVOICE_TYPE'] === 'Company' ? 'Entreprise' : 'Personnelle'; ?></p>
    <p><strong>Date:</strong> <?php echo $invoiceHeader['DATE']; ?></p>
    <p><strong>Total HT:</strong> <?php echo number_format($invoiceHeader['TOTAL_PRICE_HT'], 2); ?> DH</p>
    <p><strong>TVA:</strong> <?php echo number_format($invoiceHeader['TVA'], 2); ?> DH</p>
    <p><strong>Total TTC:</strong> <?php echo number_format($invoiceHeader['TOTAL_PRICE_TTC'], 2); ?> DH</p>
</div>

<h3>Produits</h3>
<table>
    <thead>
        <tr>
            <th>Quantité</th>
            <th>Désignation</th>
            <th>Prix Unitaire</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($invoiceDetails as $detail): ?>
            <tr>
                <td><?php echo $detail['QUANTITY']; ?></td>
                <td><?php echo $detail['PRODUCT_NAME']; ?></td>
                <td><?php echo number_format($detail['UNIT_PRICE_TTC'], 2); ?> DH</td>
                <td><?php echo number_format($detail['TOTAL_PRICE_TTC'], 2); ?> DH</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">Total HT</th>
            <th><?php echo number_format($invoiceHeader['TOTAL_PRICE_HT'], 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">TVA (20%)</th>
            <th><?php echo number_format($invoiceHeader['TVA'], 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">Total TTC</th>
            <th><?php echo number_format($invoiceHeader['TOTAL_PRICE_TTC'], 2); ?> DH</th>
        </tr>
    </tfoot>
</table>

<div class="form-actions">
    <a href="view.php" class="btn btn-secondary">Retour</a>
    <a href="print.php?id=<?php echo $invoiceId; ?>" class="btn btn-primary" target="_blank">Imprimer</a>
</div>

<?php include '../../includes/footer.php'; ?>