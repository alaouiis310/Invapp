<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

$title = "Bons de Livraison";
include '../../../includes/headerin.php';

// Handle search
$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
}

// Get all purchase bons
$sql = "SELECT * FROM BON_LIVRAISON_ACHAT_HEADER";
if (!empty($searchTerm)) {
    $sql .= " WHERE SUPPLIER_NAME LIKE ? OR ID_BON LIKE ?";
    $searchParam = "%$searchTerm%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<h1>Bons de Livraison</h1>

<div class="search-bar">
    <form action="" method="get">
        <input type="text" name="search" placeholder="Rechercher par fournisseur ou numéro..." value="<?php echo $searchTerm; ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
        <a href="create_step1.php" class="btn btn-success ml-2">
            <i class="fas fa-plus"></i> Nouveau BL d'achat
        </a>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Numéro</th>
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
                    <td><?php echo $row['ID_BON']; ?></td>
                    <td><?php echo $row['SUPPLIER_NAME']; ?></td>
                    <td><?php echo $row['DATE']; ?></td>
                    <td><?php echo number_format($row['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="details.php?id=<?php echo $row['ID']; ?>" class="btn btn-info">Détails</a>
                        <?php if (isAdmin()): ?>
                            <a href="delete.php?id=<?php echo $row['ID']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce bon?');">Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">Aucun bon de livraison trouvé</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../../../includes/footerin.php'; ?>