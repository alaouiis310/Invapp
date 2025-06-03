<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Initialize view mode and search term at the beginning
$viewMode = isset($_GET['view']) ? sanitizeInput($_GET['view']) : 'category';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Check if current user is admin
$isAdmin = isAdmin();
$userId = $_SESSION['user_id'];

// Get user-specific visibility settings (only for non-admin users)
$visibilitySettings = [
    'invoice_stock_visibility_days' => 30  // Default value
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

// Check if we have the necessary session data
if (!isset($_SESSION['sales_invoice'])) {
    redirect('invoice_step1.php');
}

// Get TVA min/max from settings (default to 13% min, 27% max)
$minTVA = 13; // Should be fetched from settings table
$maxTVA = 27; // Should be fetched from settings table

$title = "Facture de Vente - Étape 2/3";
include '../../includes/header.php';

// Handle adding products
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_products'])) {
        if (!isset($_SESSION['sales_products'])) {
            $_SESSION['sales_products'] = [];
        }
        
        if (isset($_POST['selected_products'])) {
            foreach ($_POST['selected_products'] as $productId) {
                $quantity = (int)$_POST['quantity'][$productId];
                $unitPrice = (float)$_POST['price'][$productId];
                $tvaRate = isset($_POST['tva_rate'][$productId]) ? (float)$_POST['tva_rate'][$productId] : 20;
                
                // Get product details (only for Facture type products)
                $stmt = $conn->prepare("SELECT REFERENCE, PRODUCT_NAME, QUANTITY FROM PRODUCT WHERE ID = ? AND TYPE = 'Facture'");
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $product = $result->fetch_assoc();
                    
                    // Check if quantity is available
                    if ($quantity <= $product['QUANTITY']) {
                        $_SESSION['sales_products'][$productId] = [
                            'reference' => $product['REFERENCE'],
                            'product_name' => $product['PRODUCT_NAME'],
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'tva_rate' => $tvaRate
                        ];
                    } else {
                        $_SESSION['error_message'] = "La quantité demandée pour " . $product['PRODUCT_NAME'] . " dépasse le stock disponible.";
                    }
                }
                $stmt->close();
            }
        }
    }
    elseif (isset($_POST['distribute_total'])) {
        // Distribute the total TTC amount across products by adjusting prices
        $totalTTC = (float)$_SESSION['sales_invoice']['total_ttc'];
        $productsCount = count($_SESSION['sales_products']);
        
        if ($productsCount > 0) {
            $totalHT = 0;
            $totalTVA = 0;
            
            // First calculate current totals
            foreach ($_SESSION['sales_products'] as $productId => $product) {
                $productHT = $product['unit_price'] * $product['quantity'] / (1 + ($product['tva_rate'] / 100));
                $productTVA = $productHT * ($product['tva_rate'] / 100);
                
                $totalHT += $productHT;
                $totalTVA += $productTVA;
            }
            
            // Calculate adjustment factor
            $currentTTC = $totalHT + $totalTVA;
            if ($currentTTC > 0) {
                $adjustmentFactor = $totalTTC / $currentTTC;
                
                // Apply adjustment to each product
                foreach ($_SESSION['sales_products'] as $productId => &$product) {
                    $newHT = ($product['unit_price'] * $product['quantity'] / (1 + ($product['tva_rate'] / 100))) * $adjustmentFactor;
                    $product['unit_price'] = $newHT * (1 + ($product['tva_rate'] / 100)) / $product['quantity'];
                }
                unset($product); // Break the reference
            }
        }
    }
    elseif (isset($_POST['auto_adjust'])) {
        // Auto adjust TVA rates to match total TTC
        $totalTTC = (float)$_SESSION['sales_invoice']['total_ttc'];
        $productsCount = count($_SESSION['sales_products']);
        
        if ($productsCount > 0) {
            // Calculate total quantity and average price
            $totalQuantity = 0;
            $totalPrice = 0;
            
            foreach ($_SESSION['sales_products'] as $product) {
                $totalQuantity += $product['quantity'];
                $totalPrice += $product['unit_price'] * $product['quantity'];
            }
            
            // Calculate average price per unit
            $averagePricePerUnit = $totalPrice / $totalQuantity;
            
            // Calculate required average TVA rate to reach total TTC
            $requiredAverageTVA = (($totalTTC / $totalPrice) - 1) * 100;
            
            // Ensure TVA stays within min/max limits
            $requiredAverageTVA = max($minTVA, min($maxTVA, $requiredAverageTVA));
            
            // Apply adjusted TVA rate to all products
            foreach ($_SESSION['sales_products'] as $productId => &$product) {
                // Calculate new unit price with adjusted TVA
                $productHT = $product['unit_price'] / (1 + ($product['tva_rate'] / 100));
                $newUnitPrice = $productHT * (1 + ($requiredAverageTVA / 100));
                
                $product['unit_price'] = $newUnitPrice;
                $product['tva_rate'] = $requiredAverageTVA;
            }
            unset($product); // Break the reference
        }
    }
}

// Handle product removal
if (isset($_GET['remove']) && isset($_SESSION['sales_products'])) {
    $productId = (int)$_GET['remove'];
    if (isset($_SESSION['sales_products'][$productId])) {
        unset($_SESSION['sales_products'][$productId]);
    }
    redirect('invoice_step2.php');
}

// Get view mode (category or all)
$viewMode = isset($_GET['view']) ? sanitizeInput($_GET['view']) : 'category';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Get all categories
$categories = [];
$sql = "SELECT CATEGORYNAME FROM CATEGORY ORDER BY CATEGORYNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row['CATEGORYNAME'];
    }
}

// Get all suppliers (fournisseurs)
$suppliers = [];
$sql = "SELECT DISTINCT SUPPLIER_NAME FROM BUY_INVOICE_HEADER ORDER BY SUPPLIER_NAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row['SUPPLIER_NAME'];
    }
}

// Get products based on view mode (only Facture type products with quantity > 0)
$products = [];
if ($viewMode === 'category' && !empty($categories)) {
    $currentCategory = isset($_GET['category']) ? sanitizeInput($_GET['category']) : $categories[0];
    $currentSupplier = isset($_GET['supplier']) ? sanitizeInput($_GET['supplier']) : '';
    
    $sql = "SELECT p.ID, p.REFERENCE, p.PRODUCT_NAME, p.PRICE, p.QUANTITY 
            FROM PRODUCT p
            LEFT JOIN BUY_INVOICE_DETAILS bid ON p.REFERENCE = bid.PRODUCT_ID
            LEFT JOIN BUY_INVOICE_HEADER bih ON bid.ID_INVOICE = bih.ID_INVOICE
            WHERE p.CATEGORY_NAME = ? 
            AND p.TYPE = 'Facture'
            AND p.QUANTITY > 0";  // Only show products with available stock
    
    // Add date visibility filter for non-admin users
    if (!$isAdmin) {
        $cutoffDate = date('Y-m-d', strtotime("-".$visibilitySettings['invoice_stock_visibility_days']." days"));
        $sql .= " AND (bih.DATE >= ? OR bih.DATE IS NULL)";
    }
    
    $params = [$currentCategory];
    $types = "s";
    
    if (!$isAdmin) {
        $params[] = $cutoffDate;
        $types .= "s";
    }
    
    if (!empty($currentSupplier)) {
        $sql .= " AND bih.SUPPLIER_NAME = ?";
        $params[] = $currentSupplier;
        $types .= "s";
    }
    
    $sql .= " GROUP BY p.ID ORDER BY p.PRODUCT_NAME";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($currentSupplier) && $isAdmin) {
        $stmt->bind_param("ss", $currentCategory, $currentSupplier);
    } elseif (!empty($currentSupplier) && !$isAdmin) {
        $stmt->bind_param("sss", $currentCategory, $cutoffDate, $currentSupplier);
    } elseif (!$isAdmin) {
        $stmt->bind_param("ss", $currentCategory, $cutoffDate);
    } else {
        $stmt->bind_param("s", $currentCategory);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt->close();
} else {
    // View all Facture type products with quantity > 0 and optional search/supplier filter
    $currentSupplier = isset($_GET['supplier']) ? sanitizeInput($_GET['supplier']) : '';
    
    $sql = "SELECT p.ID, p.REFERENCE, p.PRODUCT_NAME, p.PRICE, p.QUANTITY 
            FROM PRODUCT p
            LEFT JOIN BUY_INVOICE_DETAILS bid ON p.REFERENCE = bid.PRODUCT_ID
            LEFT JOIN BUY_INVOICE_HEADER bih ON bid.ID_INVOICE = bih.ID_INVOICE
            WHERE p.TYPE = 'Facture'
            AND p.QUANTITY > 0";  // Only show products with available stock
    
    $params = [];
    $types = "";
    
    // Add date visibility filter for non-admin users
    if (!$isAdmin) {
        $cutoffDate = date('Y-m-d', strtotime("-".$visibilitySettings['invoice_stock_visibility_days']." days"));
        $sql .= " AND (bih.DATE >= ? OR bih.DATE IS NULL)";
        $params[] = $cutoffDate;
        $types .= "s";
    }
    
    if (!empty($searchTerm)) {
        $sql .= " AND (p.PRODUCT_NAME LIKE ? OR p.REFERENCE LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }
    
    if (!empty($currentSupplier)) {
        $sql .= " AND bih.SUPPLIER_NAME = ?";
        $params[] = $currentSupplier;
        $types .= "s";
    }
    
    $sql .= " GROUP BY p.ID ORDER BY p.PRODUCT_NAME";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt->close();
}
?>

<h1>Nouvelle Facture de Vente - Étape 2/3</h1>

<div class="invoice-summary">
    <h3>Résumé de la Facture</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['sales_invoice']['client_name']; ?></p>
    <?php if ($_SESSION['sales_invoice']['invoice_type'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo $_SESSION['sales_invoice']['company_ice']; ?></p>
    <?php endif; ?>
    <p><strong>Adresse:</strong> <?php echo nl2br(htmlspecialchars($_SESSION['sales_invoice']['client_address'] ?? '')); ?></p>
    <p><strong>Numéro:</strong> <?php echo $_SESSION['sales_invoice']['invoice_number']; ?></p>
    <p><strong>Total TTC Cible:</strong> <?php echo number_format($_SESSION['sales_invoice']['total_ttc'], 2); ?> DH</p>
</div>

<div class="view-mode-switcher">
    <a href="?view=category" class="<?php echo $viewMode === 'category' ? 'active' : ''; ?>">Par Catégorie</a>
    <a href="?view=all" class="<?php echo $viewMode === 'all' ? 'active' : ''; ?>">Tous les Produits</a>
</div>

<?php if ($viewMode === 'all'): ?>
    <div class="search-bar">
        <form action="" method="get">
            <input type="hidden" name="view" value="all">
            <input type="text" name="search" placeholder="Rechercher un produit..." value="<?php echo $searchTerm; ?>">
            <div class="filter-dropdown">
                <select name="supplier">
                    <option value="">Tous les fournisseurs</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier; ?>" <?php echo (isset($currentSupplier) && $currentSupplier === $supplier) ? 'selected' : ''; ?>>
                            <?php echo $supplier; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
<?php endif; ?>

<?php if ($viewMode === 'category'): ?>
    <div class="filter-container">
        <form action="" method="get">
            <input type="hidden" name="view" value="category">
            <div class="filter-dropdown">
                <select name="category" onchange="this.form.submit()">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category; ?>" <?php echo (isset($currentCategory) && $currentCategory === $category) ? 'selected' : ''; ?>>
                            <?php echo $category; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-dropdown">
                <select name="supplier" onchange="this.form.submit()">
                    <option value="">Tous les fournisseurs</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier; ?>" <?php echo (isset($currentSupplier) && $currentSupplier === $supplier) ? 'selected' : ''; ?>>
                            <?php echo $supplier; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<form action="" method="post">
    <table class="product-selection">
        <thead>
            <tr>
                <th>Sélection</th>
                <th>Référence</th>
                <th>Désignation</th>
                <th>Prix Unitaire</th>
                <th>Stock</th>
                <th>Quantité</th>
                <th>TVA (%)</th>
                <th>Prix Vente</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><input type="checkbox" name="selected_products[]" value="<?php echo $product['ID']; ?>"></td>
                        <td><?php echo $product['REFERENCE']; ?></td>
                        <td><?php echo $product['PRODUCT_NAME']; ?></td>
                        <td><?php echo number_format($product['PRICE'], 2); ?> DH</td>
                        <td>
                            <?php 
                            echo $product['QUANTITY']; 
                            if ($product['QUANTITY'] < 5): ?>
                                <span class="low-stock-warning">(Stock faible)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" name="quantity[<?php echo $product['ID']; ?>]" 
                                   min="1" max="<?php echo $product['QUANTITY']; ?>" value="1">
                        </td>
                        <td>
                            <input type="number" name="tva_rate[<?php echo $product['ID']; ?>]" 
                                   step="0.1" min="<?php echo $minTVA; ?>" max="<?php echo $maxTVA; ?>" value="20">
                        </td>
                        <td>
                            <input type="number" name="price[<?php echo $product['ID']; ?>]" 
                                   step="0.01" min="0" value="<?php echo number_format($product['PRICE'], 2); ?>">
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
    
    <?php if (!empty($products)): ?>
        <div class="form-actions">
            <button type="submit" name="add_products" class="btn btn-primary">Ajouter les Produits Sélectionnés</button>
        </div>
    <?php endif; ?>
</form>

<?php if (!empty($_SESSION['sales_products'])): ?>
    <h3>Produits Ajoutés à la Facture</h3>
    
    <div class="adjustment-buttons">
        <form action="" method="post" style="display: inline-block; margin-right: 10px;">
            <button type="submit" name="auto_adjust" class="btn btn-warning">
                <i class="fas fa-magic"></i> Ajustement Automatique TVA
            </button>
        </form>
        
        <form action="" method="post" style="display: inline-block;">
            <button type="submit" name="distribute_total" class="btn btn-info">
                <i class="fas fa-calculator"></i> Ajustement par Prix
            </button>
        </form>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Référence</th>
                <th>Désignation</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>TVA (%)</th>
                <th>Total HT</th>
                <th>Total TVA</th>
                <th>Total TTC</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalHT = 0;
            $totalTVA = 0;
            $totalTTC = 0;
            
            foreach ($_SESSION['sales_products'] as $productId => $product): 
                $productHT = $product['unit_price'] * $product['quantity'] / (1 + ($product['tva_rate'] / 100));
                $productTVA = $productHT * ($product['tva_rate'] / 100);
                $productTotal = $product['unit_price'] * $product['quantity'];
                
                $totalHT += $productHT;
                $totalTVA += $productTVA;
                $totalTTC += $productTotal;
            ?>
                <tr>
                    <td><?php echo $product['reference']; ?></td>
                    <td><?php echo $product['product_name']; ?></td>
                    <td><?php echo $product['quantity']; ?></td>
                    <td><?php echo number_format($product['unit_price'], 2); ?> DH</td>
                    <td><?php echo number_format($product['tva_rate'], 2); ?>%</td>
                    <td><?php echo number_format($productHT, 2); ?> DH</td>
                    <td><?php echo number_format($productTVA, 2); ?> DH</td>
                    <td><?php echo number_format($productTotal, 2); ?> DH</td>
                    <td>
                        <a href="?remove=<?php echo $productId; ?>" class="btn btn-danger">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5">Totaux</th>
                <th><?php echo number_format($totalHT, 2); ?> DH</th>
                <th><?php echo number_format($totalTVA, 2); ?> DH</th>
                <th><?php echo number_format($totalTTC, 2); ?> DH</th>
                <th></th>
            </tr>
            <tr>
                <th colspan="7">Total TTC Cible:</th>
                <th colspan="2"><?php echo number_format($_SESSION['sales_invoice']['total_ttc'], 2); ?> DH</th>
            </tr>
            <tr>
                <th colspan="7">Différence:</th>
                <th colspan="2"><?php echo number_format($_SESSION['sales_invoice']['total_ttc'] - $totalTTC, 2); ?> DH</th>
            </tr>
        </tfoot>
    </table>
    
    <div class="form-actions">
        <a href="invoice_step1.php" class="btn btn-secondary">Retour</a>
        <a href="invoice_step3.php" class="btn btn-primary">Suivant</a>
    </div>
<?php else: ?>
    <p>Aucun produit ajouté pour le moment.</p>
    <div class="form-actions">
        <a href="invoice_step1.php" class="btn btn-secondary">Retour</a>
    </div>
<?php endif; ?>

<style>
    .filter-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .filter-dropdown {
        position: relative;
    }
    
    .filter-dropdown select {
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #ddd;
        background-color: white;
        cursor: pointer;
    }
    
    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .search-bar input[type="text"] {
        flex: 1;
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .adjustment-buttons {
        margin-bottom: 20px;
    }
    
    .btn-warning {
        background-color: #ffc107;
        color: #212529;
    }
    
    .btn-warning:hover {
        background-color: #e0a800;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .table th, .table td {
        padding: 8px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    .table th {
        background-color: rgb(22, 106, 189);
        color: white;
    }
    
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
    
    .form-actions {
        margin-top: 20px;
    }
    
    .view-mode-switcher {
        margin-bottom: 20px;
    }
    
    .view-mode-switcher a {
        padding: 8px 16px;
        background-color: #f0f0f0;
        border-radius: 4px;
        text-decoration: none;
        color: #333;
        margin-right: 10px;
    }
    
    .view-mode-switcher a.active {
        background-color: rgb(22, 106, 189);
        color: white;
    }
    
    .product-selection {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .product-selection th, .product-selection td {
        padding: 8px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    .product-selection th {
        background-color: rgb(22, 106, 189);
    }
    
    .invoice-summary {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .invoice-summary h3 {
        margin-top: 0;
    }
    
    .low-stock-warning {
        color: #dc3545;
        font-size: 0.8em;
        margin-left: 5px;
    }
</style>

<?php include '../../includes/footer.php'; ?>