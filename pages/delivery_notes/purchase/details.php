<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('view.php');
}

$bonId = (int)$_GET['id'];

// Get bon header
$stmt = $conn->prepare("SELECT * FROM BON_LIVRAISON_ACHAT_HEADER WHERE ID = ?");
$stmt->bind_param("i", $bonId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('view.php');
}

$bonHeader = $headerResult->fetch_assoc();
$stmt->close();

// Get bon details
$stmt = $conn->prepare("SELECT * FROM BON_LIVRAISON_ACHAT_DETAILS WHERE ID_BON = ?");
$stmt->bind_param("i", $bonId);
$stmt->execute();
$detailsResult = $stmt->get_result();
$bonDetails = [];

if ($detailsResult->num_rows > 0) {
    while($row = $detailsResult->fetch_assoc()) {
        $bonDetails[] = $row;
    }
}
$stmt->close();

$title = "Détails Bon de Livraison #" . $bonHeader['ID_BON'];
include '../../../includes/headerin.php';
?>

<h1>Détails Bon de Livraison #<?php echo $bonHeader['ID_BON']; ?></h1>

<div class="invoice-header">
    <p><strong>Fournisseur:</strong> <?php echo $bonHeader['SUPPLIER_NAME']; ?></p>
    <p><strong>Date:</strong> <?php echo $bonHeader['DATE']; ?></p>
    <p><strong>Total HT:</strong> <?php echo number_format($bonHeader['TOTAL_PRICE_HT'], 2); ?> DH</p>
    <p><strong>TVA:</strong> <?php echo number_format($bonHeader['TVA'], 2); ?> DH</p>
    <p><strong>Total TTC:</strong> <?php echo number_format($bonHeader['TOTAL_PRICE_TTC'], 2); ?> DH</p>
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
        <?php foreach ($bonDetails as $detail): ?>
            <tr>
                <td><?php echo $detail['PRODUCT_ID']; ?></td>
                <td><?php echo $detail['PRODUCT_NAME']; ?></td>
                <td><?php echo $detail['QUANTITY']; ?></td>
                <td><?php echo number_format($detail['UNIT_PRICE_TTC'], 2); ?> DH</td>
                <td><?php echo number_format($detail['TOTAL_PRICE_TTC'], 2); ?> DH</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($bonHeader['IMAGE'])): ?>
    <h3>Image du Bon</h3>
    <div class="invoice-image">
        <?php 
// In your details.php file, replace line 83 with something like:
if (isset($invoice['IMAGE']) && !empty($invoice['IMAGE'])) {
    $imageInfo = getimagesizefromstring($invoice['IMAGE']);
    if ($imageInfo !== false) {
        $mime = $imageInfo['mime'];
        $base64 = base64_encode($invoice['IMAGE']);
        echo '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Invoice Image">';
    } else {
        echo '<p>Invalid image data</p>';
    }
} else {
    echo '<p>No image available</p>';
}
        ?>
        <div class="image-actions">
            <a href="download.php?id=<?php echo $bonId; ?>" class="btn btn-primary">Télécharger</a>
            <button onclick="window.print()" class="btn btn-secondary">Imprimer</button>
        </div>
    </div>
<?php endif; ?>

<div class="form-actions">
    <a href="view.php" class="btn btn-secondary">Retour</a>
</div>

<?php include '../../../includes/footerin.php'; ?>