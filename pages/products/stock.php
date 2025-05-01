<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Gestion du Stock";
include '../../includes/header.php';

// Handle search
$searchTerm = '';
$categoryFilter = '';
$productType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'Facture'; // Default to Facture

if (isset($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
}

if (isset($_GET['category'])) {
    $categoryFilter = sanitizeInput($_GET['category']);
}

// Get all categories for filter
$categories = [];
$sql = "SELECT CATEGORYNAME FROM CATEGORY ORDER BY CATEGORYNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['CATEGORYNAME'];
    }
}

// Get products with filters and their invoice information
$sql = "SELECT p.*, bid.ID_INVOICE 
        FROM PRODUCT p
        LEFT JOIN BUY_INVOICE_DETAILS bid ON p.REFERENCE = bid.PRODUCT_ID
        WHERE p.TYPE = ?";
$conditions = [];
$params = [$productType];
$types = 's';

if (!empty($searchTerm)) {
    $conditions[] = "(p.PRODUCT_NAME LIKE ? OR p.REFERENCE LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($categoryFilter)) {
    $conditions[] = "p.CATEGORY_NAME = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY p.ID ORDER BY p.PRODUCT_NAME";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();
?>

<h1>Gestion du Stock</h1>

<!-- Product type switcher buttons -->
<div class="product-type-switcher">
    <a href="?type=Facture" class="btn <?php echo $productType === 'Facture' ? 'btn-primary' : 'btn-secondary'; ?>">Facture</a>
    <a href="?type=BL" class="btn <?php echo $productType === 'BL' ? 'btn-primary' : 'btn-secondary'; ?>">Bon de Livraison</a>
    <a href="?type=Stock" class="btn <?php echo $productType === 'Stock' ? 'btn-primary' : 'btn-secondary'; ?>">Stock</a>

    <a href="add_product.php" class="btn btn-success">Ajouter Produit</a>
    <a href="categories.php" class="btn btn-info">Gérer les Catégories</a>
    <a href="../quotes/create.php" class="btn btn-info">Devis</a>
</div>

<div class="stock-filters">
    <form action="" method="get">
        <input type="hidden" name="type" value="<?php echo $productType; ?>">

        <div class="form-group">
            <label for="search">Recherche:</label>
            <input type="text" name="search" id="search" placeholder="Rechercher un produit..." value="<?php echo $searchTerm; ?>">
        </div>

        <div class="form-group">
            <label for="category">Catégorie:</label>
            <select name="category" id="category">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category; ?>" <?php echo $category === $categoryFilter ? 'selected' : ''; ?>>
                        <?php echo $category; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="stock.php?type=<?php echo $productType; ?>" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Référence</th>
            <th>Désignation</th>
            <th>Prix</th>
            <th>Quantité</th>
            <th>Catégorie</th>
            <th>Type</th>
            <th>Image</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['REFERENCE']; ?></td>
                    <td><?php echo $product['PRODUCT_NAME']; ?></td>
                    <td><?php echo number_format($product['PRICE'], 2); ?> DH</td>
                    <td><?php echo $product['QUANTITY']; ?></td>
                    <td><?php echo $product['CATEGORY_NAME']; ?></td>
                    <td><?php echo $product['TYPE']; ?></td>
                    <td>
                        <?php if (!empty($product['IMAGE'])): ?>
                            <?php
                            $imageData = $product['IMAGE'];
                            $imageInfo = getimagesizefromstring($imageData);
                            $mime = $imageInfo['mime'];
                            $base64 = base64_encode($imageData);
                            echo '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Product Image" style="max-width: 50px; max-height: 50px;">';
                            ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit.php?id=<?php echo $product['ID']; ?>" class="btn btn-warning">Modifier</a>
                        <?php if (!empty($product['ID_INVOICE'])): ?>
                            <a href="../purchase/details.php?id=<?php echo $product['ID_INVOICE']; ?>&from=stock" class="btn btn-info">Détails</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">Aucun produit trouvé</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../../includes/footer.php'; ?>