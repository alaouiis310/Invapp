<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Gestion du Stock";
include '../../includes/header.php';

// Check if current user is admin
$isAdmin = isAdmin();
$userId = $_SESSION['user_id'];

// Initialize filter variables
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$supplierFilter = isset($_GET['supplier']) ? sanitizeInput($_GET['supplier']) : '';
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$minQuantity = isset($_GET['min_quantity']) ? (int)$_GET['min_quantity'] : null;
$maxQuantity = isset($_GET['max_quantity']) ? (int)$_GET['max_quantity'] : null;
$productType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'Facture';

// Get user-specific visibility settings (only for non-admin users)
$visibilitySettings = [
    'stock_visibility_days' => 365,
    'purchase_visibility_days' => 180,
    'product_types' => 'Facture,Stock,BL'
];

if (!$isAdmin) {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $visibilitySettings)) {
            $visibilitySettings[$row['setting_key']] = $row['setting_value'];
        }
    }
    $stmt->close();
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

// Get all suppliers for filter (from both invoices and delivery notes)
$suppliers = [];
$supplierQuery = $conn->query("
    SELECT DISTINCT SUPPLIER_NAME FROM BUY_INVOICE_HEADER 
    UNION 
    SELECT DISTINCT SUPPLIER_NAME FROM BON_LIVRAISON_ACHAT_HEADER 
    ORDER BY SUPPLIER_NAME
");
if ($supplierQuery->num_rows > 0) {
    while ($row = $supplierQuery->fetch_assoc()) {
        $suppliers[] = $row['SUPPLIER_NAME'];
    }
}

// Build SQL query with filters
$sql = "SELECT p.*, 
        COALESCE(bih.SUPPLIER_NAME, blh.SUPPLIER_NAME) AS SUPPLIER_NAME,
        COALESCE(bid.ID_INVOICE, bld.ID_BON) AS SOURCE_ID,
        CASE 
            WHEN p.TYPE = 'Facture' THEN 'invoice'
            WHEN p.TYPE = 'BL' THEN 'delivery'
            ELSE 'other'
        END AS SOURCE_TYPE
        FROM PRODUCT p
        LEFT JOIN BUY_INVOICE_DETAILS bid ON p.REFERENCE = bid.PRODUCT_ID AND p.TYPE = 'Facture'
        LEFT JOIN BUY_INVOICE_HEADER bih ON bid.ID_INVOICE = bih.ID_INVOICE
        LEFT JOIN BON_LIVRAISON_ACHAT_DETAILS bld ON p.REFERENCE = bld.PRODUCT_ID AND p.TYPE = 'BL'
        LEFT JOIN BON_LIVRAISON_ACHAT_HEADER blh ON bld.ID_BON = blh.ID";

// Initialize conditions array
$conditions = [];
$params = [];
$types = '';

// Add product type filter
$conditions[] = "p.TYPE = ?";
$params[] = $productType;
$types .= 's';

// Only apply visibility restrictions for non-admin users
if (!$isAdmin) {
    // Add product type visibility filter from user settings
    $visibleTypes = explode(',', $visibilitySettings['product_types']);
    if (!in_array($productType, $visibleTypes)) {
        $conditions[] = "1 = 0"; // Force empty results if type not visible
    }

    // Add date visibility filter for both invoice and BL products
    $visibilityDays = (int)$visibilitySettings['stock_visibility_days'];
    $cutoffDate = date('Y-m-d', strtotime("-$visibilityDays days"));
    $conditions[] = "(bih.DATE >= ? OR blh.DATE >= ? OR (bih.DATE IS NULL AND blh.DATE IS NULL))";
    $params[] = $cutoffDate;
    $params[] = $cutoffDate;
    $types .= 'ss';
}

// Rest of your existing filter conditions remain the same...
// [Keep all the existing filter conditions as they are]

// Combine all conditions
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY p.ID ORDER BY p.PRODUCT_NAME";

// Prepare and execute the query
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

<!-- The rest of your HTML/CSS remains exactly the same -->

<style>
    .product-type-switcher {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .stock-filters {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }
    
    .filter-group label {
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .filter-group input,
    .filter-group select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .price-range,
    .quantity-range {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .price-range input,
    .quantity-range input {
        flex: 1;
    }
    
    .filter-actions {
        display: flex;
        gap: 10px;
        margin-left: auto;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    table th, table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    table th {
        background-color: rgb(22, 106, 189);
        color: white;
    }
    
    .btn {
        padding: 8px 12px;
        border-radius: 4px;
        text-decoration: none;
        color: white;
        font-size: 14px;
        display: inline-block;
        margin-right: 5px;
    }
    
    .btn-primary {
        background-color: #007bff;
    }
    
    .btn-secondary {
        background-color: #6c757d;
    }
    
    .btn-success {
        background-color: #28a745;
    }
    
    .btn-info {
        background-color: #17a2b8;
    }
    
    .btn-warning {
        background-color: #ffc107;
        color: #212529;
    }
    
    .btn-danger {
        background-color: #dc3545;
    }
    
    img.product-image {
        max-width: 50px;
        max-height: 50px;
    }
</style>

<h1>Gestion du Stock</h1>

<!-- Product type switcher buttons -->
<div class="product-type-switcher">
    <a href="?type=Facture" class="btn <?= $productType === 'Facture' ? 'btn-primary' : 'btn-secondary' ?>">Facture</a>
    <a href="?type=BL" class="btn <?= $productType === 'BL' ? 'btn-primary' : 'btn-secondary' ?>">Bon de Livraison</a>
    <a href="?type=Stock" class="btn <?= $productType === 'Stock' ? 'btn-primary' : 'btn-secondary' ?>">Stock</a>

    <a href="add_product.php" class="btn btn-success">Ajouter Produit</a>
    <a href="categories.php" class="btn btn-info">Gérer les Catégories</a>
    <a href="../quotes/view.php" class="btn btn-info">Devis</a>
</div>

<div class="stock-filters">
    <form action="" method="get">
        <input type="hidden" name="type" value="<?= $productType ?>">

        <div class="filter-group">
            <label for="search">Recherche:</label>
            <input type="text" name="search" id="search" placeholder="Référence ou désignation" value="<?= htmlspecialchars($searchTerm) ?>">
        </div>

        <div class="filter-group">
            <label for="category">Catégorie:</label>
            <select name="category" id="category">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>" <?= $category === $categoryFilter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="supplier">Fournisseur:</label>
            <select name="supplier" id="supplier">
                <option value="">Tous fournisseurs</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= htmlspecialchars($supplier) ?>" <?= $supplier === $supplierFilter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($supplier) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Prix:</label>
            <div class="price-range">
                <input type="number" name="min_price" placeholder="Min" step="0.01" min="0" value="<?= $minPrice !== null ? htmlspecialchars($minPrice) : '' ?>">
                <span>à</span>
                <input type="number" name="max_price" placeholder="Max" step="0.01" min="0" value="<?= $maxPrice !== null ? htmlspecialchars($maxPrice) : '' ?>">
            </div>
        </div>

        <div class="filter-group">
            <label>Quantité:</label>
            <div class="quantity-range">
                <input type="number" name="min_quantity" placeholder="Min" min="0" value="<?= $minQuantity !== null ? htmlspecialchars($minQuantity) : '' ?>">
                <span>à</span>
                <input type="number" name="max_quantity" placeholder="Max" min="0" value="<?= $maxQuantity !== null ? htmlspecialchars($maxQuantity) : '' ?>">
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="stock.php?type=<?= $productType ?>" class="btn btn-secondary">Réinitialiser</a>
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
            <th>Fournisseur</th>
            <th>Type</th>
            <th>Image</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['REFERENCE']) ?></td>
                    <td><?= htmlspecialchars($product['PRODUCT_NAME']) ?></td>
                    <td><?= number_format($product['PRICE'], 2) ?> DH</td>
                    <td><?= htmlspecialchars($product['QUANTITY']) ?></td>
                    <td><?= htmlspecialchars($product['CATEGORY_NAME']) ?></td>
                    <td><?= htmlspecialchars($product['SUPPLIER_NAME'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($product['TYPE']) ?></td>
                    <td>
                        <?php if (!empty($product['IMAGE'])): ?>
                            <?php
                            $imageData = $product['IMAGE'];
                            $imageInfo = getimagesizefromstring($imageData);
                            $mime = $imageInfo['mime'];
                            $base64 = base64_encode($imageData);
                            echo '<img src="data:' . $mime . ';base64,' . $base64 . '" class="product-image" alt="Product Image">';
                            ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit.php?id=<?= $product['ID'] ?>" class="btn btn-warning">Modifier</a>
                        <?php if (!empty($product['SOURCE_ID'])): ?>
                            <?php if ($product['SOURCE_TYPE'] === 'invoice'): ?>
                                <a href="../purchase/details.php?id=<?= $product['SOURCE_ID'] ?>&from=stock" class="btn btn-info">Détails Facture</a>
                            <?php else: ?>
                                <a href="../purchase/delivery_details.php?id=<?= $product['SOURCE_ID'] ?>&from=stock" class="btn btn-info">Détails BL</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="9">Aucun produit trouvé</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../../includes/footer.php'; ?>