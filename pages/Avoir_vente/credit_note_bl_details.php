<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('credit_note_bl_view.php');
}

$creditNoteId = (int)$_GET['id'];
$deliveryId = isset($_GET['delivery_id']) ? (int)$_GET['delivery_id'] : null;

// Get credit note header
$stmt = $conn->prepare("SELECT * FROM DELIVERY_CREDIT_NOTE_HEADER WHERE ID_CREDIT_NOTE = ?");
$stmt->bind_param("i", $creditNoteId);
$stmt->execute();
$headerResult = $stmt->get_result();

if ($headerResult->num_rows === 0) {
    redirect('credit_note_bl_view.php');
}

$creditNoteHeader = $headerResult->fetch_assoc();
$stmt->close();

// Get credit note details
$stmt = $conn->prepare("SELECT * FROM DELIVERY_CREDIT_NOTE_DETAILS WHERE ID_CREDIT_NOTE = ?");
$stmt->bind_param("i", $creditNoteId);
$stmt->execute();
$detailsResult = $stmt->get_result();
$creditNoteDetails = [];

if ($detailsResult->num_rows > 0) {
    while($row = $detailsResult->fetch_assoc()) {
        $creditNoteDetails[] = $row;
    }
}
$stmt->close();

$title = "Détails Avoir #" . $creditNoteHeader['CREDIT_NOTE_NUMBER'];
include '../../includes/header.php';
?>

<h1>Détails Avoir #<?php echo $creditNoteHeader['CREDIT_NOTE_NUMBER']; ?></h1>

<div class="credit-note-header">
    <p><strong>Client:</strong> <?php echo $creditNoteHeader['CLIENT_NAME']; ?></p>
    <p><strong>Bon de livraison associé:</strong> <?php echo $creditNoteHeader['DELIVERY_NUMBER']; ?></p>
    <p><strong>Date:</strong> <?php echo $creditNoteHeader['DATE']; ?></p>
    <p><strong>Total HT:</strong> <?php echo number_format($creditNoteHeader['TOTAL_PRICE_HT'], 2); ?> DH</p>
    <p><strong>TVA:</strong> <?php echo number_format($creditNoteHeader['TVA'], 2); ?> DH</p>
    <p><strong>Total TTC:</strong> <?php echo number_format($creditNoteHeader['TOTAL_PRICE_TTC'], 2); ?> DH</p>
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
        <?php foreach ($creditNoteDetails as $detail): ?>
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

<?php if (!empty($creditNoteHeader['IMAGE'])): ?>
    <h3>Image de l'Avoir</h3>
    <div class="credit-note-image">
        <?php 
        if (isset($creditNoteHeader['IMAGE']) && !empty($creditNoteHeader['IMAGE'])) {
            $imageInfo = getimagesizefromstring($creditNoteHeader['IMAGE']);
            if ($imageInfo !== false) {
                $mime = $imageInfo['mime'];
                $base64 = base64_encode($creditNoteHeader['IMAGE']);
                echo '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Credit Note Image">';
            } else {
                echo '<p>Invalid image data</p>';
            }
        } else {
            echo '<p>No image available</p>';
        }
        ?>
        <div class="image-actions">
            <a href="credit_note_bl_download.php?id=<?php echo $creditNoteId; ?>" class="btn btn-primary">Télécharger</a>
            <button onclick="window.print()" class="btn btn-secondary">Imprimer</button>
        </div>
    </div>
<?php endif; ?>

<div class="form-actions">
    <?php if ($deliveryId): ?>
        <a href="../delivery/details.php?id=<?php echo $deliveryId; ?>" class="btn btn-secondary">Retour au bon de livraison</a>
    <?php endif; ?>
    <a href="credit_note_bl_view.php" class="btn btn-secondary">Retour à la liste</a>
</div>

<?php include '../../includes/footer.php'; ?>