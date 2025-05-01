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
    redirect('view.php');
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

$title = "Détails Bon de Livraison #" . $deliveryHeader['ID_BON'];
include '../../../includes/headerin.php';
?>

<h1>Détails Bon de Livraison #<?php echo $deliveryHeader['ID_BON']; ?></h1>

<div class="delivery-header">
    <p><strong>Client:</strong> <?php echo $deliveryHeader['CLIENT_NAME']; ?></p>
    <?php if ($deliveryHeader['INVOICE_TYPE'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo $deliveryHeader['COMPANY_ICE']; ?></p>
    <?php endif; ?>
    <p><strong>Type:</strong> <?php echo $deliveryHeader['INVOICE_TYPE'] === 'Company' ? 'Entreprise' : 'Personnelle'; ?></p>
    <p><strong>Méthode de Paiement:</strong> <?php echo $deliveryHeader['PAYMENT_METHOD']; ?></p>
    <p><strong>Date:</strong> <?php echo $deliveryHeader['DATE']; ?></p>
    <p><strong>Total HT:</strong> <?php echo number_format($deliveryHeader['TOTAL_PRICE_HT'], 2); ?> DH</p>
    <p><strong>TVA:</strong> <?php echo number_format($deliveryHeader['TVA'], 2); ?> DH</p>
    <p><strong>Total TTC:</strong> <?php echo number_format($deliveryHeader['TOTAL_PRICE_TTC'], 2); ?> DH</p>
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
        <?php foreach ($deliveryDetails as $detail): ?>
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
            <th><?php echo number_format($deliveryHeader['TOTAL_PRICE_HT'], 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">TVA (20%)</th>
            <th><?php echo number_format($deliveryHeader['TVA'], 2); ?> DH</th>
        </tr>
        <tr>
            <th colspan="3">Total TTC</th>
            <th><?php echo number_format($deliveryHeader['TOTAL_PRICE_TTC'], 2); ?> DH</th>
        </tr>
    </tfoot>
</table>

<div class="form-actions">
    <a href="view.php" class="btn btn-secondary">Retour</a>
    <a href="print.php?id=<?php echo $deliveryId; ?>" class="btn btn-primary" target="_blank">Imprimer</a>
    <?php if (isAdmin()): ?>
        <a href="delete.php?id=<?php echo $deliveryId; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce bon de livraison?');">Supprimer</a>
    <?php endif; ?>
</div>

<?php include '../../../includes/footerin.php'; ?>