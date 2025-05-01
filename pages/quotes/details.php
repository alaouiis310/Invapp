<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$quoteId = (int)$_GET['id'];

// Get quote header
$stmt = $conn->prepare("SELECT * FROM DEVIS_HEADER WHERE ID = ?");
$stmt->bind_param("i", $quoteId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('view.php');
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

$title = "Détails du Devis #" . $quoteHeader['DEVIS_NUMBER'];
include '../../includes/header.php';
?>

<h1>Détails du Devis #<?php echo $quoteHeader['DEVIS_NUMBER']; ?></h1>

<div class="quote-header">
    <p><strong>Client:</strong> <?php echo $quoteHeader['CLIENT_NAME']; ?></p>
    <?php if (!empty($quoteHeader['COMPANY_ICE'])): ?>
        <p><strong>ICE:</strong> <?php echo $quoteHeader['COMPANY_ICE']; ?></p>
    <?php endif; ?>
    <p><strong>Date:</strong> <?php echo $quoteHeader['DATE']; ?></p>
    <p><strong>Total HT:</strong> <?php echo number_format($quoteHeader['TOTAL_PRICE_HT'], 2); ?> DH</p>
    <p><strong>TVA:</strong> <?php echo number_format($quoteHeader['TVA'], 2); ?> DH</p>
    <p><strong>Total TTC:</strong> <?php echo number_format($quoteHeader['TOTAL_PRICE_TTC'], 2); ?> DH</p>
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
        <?php foreach ($quoteDetails as $detail): ?>
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
            <th><?php echo number_format($quoteHeader['TOTAL_PRICE_HT'], 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">TVA (20%)</th>
            <th><?php echo number_format($quoteHeader['TVA'], 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">Total TTC</th>
            <th><?php echo number_format($quoteHeader['TOTAL_PRICE_TTC'], 2); ?> DH</th>
        </tr>
    </tfoot>
</table>

<div class="form-actions">
    <a href="view.php" class="btn btn-secondary">Retour</a>
    <a href="print.php?id=<?php echo $quoteId; ?>" class="btn btn-primary" target="_blank">Imprimer</a>
</div>

<?php include '../../includes/footer.php'; ?>