<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Avoirs/Retours d'Achat";
include '../../includes/header.php';

// Handle search
$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
}

// Get all credit notes
$sql = "SELECT * FROM BUY_CREDIT_NOTE_HEADER";
if (!empty($searchTerm)) {
    $sql .= " WHERE SUPPLIER_NAME LIKE ? OR CREDIT_NOTE_NUMBER LIKE ? OR INVOICE_NUMBER LIKE ?";
    $searchParam = "%$searchTerm%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<h1>Avoirs/Retours d'Achat</h1>

<div class="search-bar">
    <form action="" method="get">
        <input type="text" name="search" placeholder="Rechercher par fournisseur, numéro d'avoir ou facture..." value="<?php echo $searchTerm; ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
        <a href="credit_note_step1.php" class="btn btn-success ml-2">
            <i class="fas fa-plus"></i> Nouvel Avoir/Retour
        </a>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Numéro Avoir</th>
            <th>Numéro Facture</th>
            <th>Fournisseur</th>
            <th>Date</th>
            <th>Montant TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['CREDIT_NOTE_NUMBER']; ?></td>
                    <td><?php echo $row['INVOICE_NUMBER']; ?></td>
                    <td><?php echo $row['SUPPLIER_NAME']; ?></td>
                    <td><?php echo $row['DATE']; ?></td>
                    <td><?php echo number_format($row['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="details.php?id=<?php echo $row['ID_CREDIT_NOTE']; ?>" class="btn btn-info">Détails</a>
                        <?php if (isAdmin()): ?>
                            <a href="delete.php?id=<?php echo $row['ID_CREDIT_NOTE']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet avoir?');">Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Aucun avoir/retour trouvé</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../../includes/footer.php'; ?>