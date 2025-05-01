<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect('manage.php');
}

$supplierId = (int)$_GET['id'];

// Get supplier info
$stmt = $conn->prepare("SELECT * FROM SUPPLIER WHERE ID = ?");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('manage.php');
}

$supplier = $result->fetch_assoc();
$stmt->close();

// Get supplier invoices
$stmt = $conn->prepare("SELECT * FROM BUY_INVOICE_HEADER WHERE SUPPLIER_NAME = ? ORDER BY DATE DESC");
$stmt->bind_param("s", $supplier['SUPPLIERNAME']);
$stmt->execute();
$invoicesResult = $stmt->get_result();
$invoices = [];

if ($invoicesResult->num_rows > 0) {
    while($row = $invoicesResult->fetch_assoc()) {
        $invoices[] = $row;
    }
}
$stmt->close();

$title = "Factures du Fournisseur: " . $supplier['SUPPLIERNAME'];
include '../../includes/header.php';
?>

<h1>Factures du Fournisseur: <?php echo $supplier['SUPPLIERNAME']; ?></h1>

<div class="supplier-info">
    <p><strong>ICE:</strong> <?php echo $supplier['ICE'] ?? '-'; ?></p>
</div>

<h2>Historique des Factures</h2>
<table>
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Date</th>
            <th>Total TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($invoices)): ?>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><?php echo $invoice['INVOICE_NUMBER']; ?></td>
                    <td><?php echo $invoice['DATE']; ?></td>
                    <td><?php echo number_format($invoice['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="../purchase/details.php?id=<?php echo $invoice['ID_INVOICE']; ?>" class="btn btn-info">Détails</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">Aucune facture trouvée pour ce fournisseur</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="form-actions">
    <a href="manage.php" class="btn btn-secondary">Retour</a>
</div>

<?php include '../../includes/footer.php'; ?>