<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

// Initialize view mode and search term at the beginning
$viewMode = isset($_GET['view']) ? sanitizeInput($_GET['view']) : 'category';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Check if current user is admin
$isAdmin = isAdmin();
$userId = $_SESSION['user_id'];

// Get user-specific visibility settings (only for non-admin users)
$visibilitySettings = [
    'delivery_stock_visibility_days' => 30  // Default value
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
if (!isset($_SESSION['sales_delivery'])) {
    redirect('delivery_step1.php');
}

$title = "Bon de Livraison Vente - Étape 2/3";
include '../../../includes/headerin.php';

// Handle adding products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_products'])) {
    if (!isset($_SESSION['sales_products'])) {
        $_SESSION['sales_products'] = [];
    }
    
    if (isset($_POST['selected_products'])) {
        foreach ($_POST['selected_products'] as $productId) {
            $quantity = (int)$_POST['quantity'][$productId];
            $unitPrice = (float)$_POST['price'][$productId];
            
            // Get product details
            $stmt = $conn->prepare("SELECT REFERENCE, PRODUCT_NAME, QUANTITY FROM PRODUCT WHERE ID = ?");
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
                        'unit_price' => $unitPrice
                    ];
                } else {
                    $_SESSION['error_message'] = "La quantité demandée pour " . $product['PRODUCT_NAME'] . " dépasse le stock disponible.";
                }
            }
            $stmt->close();
        }
    }
}

// Handle product removal
if (isset($_GET['remove']) && isset($_SESSION['sales_products'])) {
    $productId = (int)$_GET['remove'];
    if (isset($_SESSION['sales_products'][$productId])) {
        unset($_SESSION['sales_products'][$productId]);
    }
    redirect('delivery_step2.php');
}

// Get all categories
$categories = [];
$sql = "SELECT CATEGORYNAME FROM CATEGORY ORDER BY CATEGORYNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row['CATEGORYNAME'];
    }
}

// Get products based on view mode
$products = [];
if ($viewMode === 'category' && !empty($categories)) {
    $currentCategory = isset($_GET['category']) ? sanitizeInput($_GET['category']) : $categories[0];
    
    $sql = "SELECT p.ID, p.REFERENCE, p.PRODUCT_NAME, p.PRICE, p.QUANTITY 
            FROM PRODUCT p
            LEFT JOIN BON_LIVRAISON_VENTE_DETAILS bld ON p.REFERENCE = bld.PRODUCT_ID
            LEFT JOIN BON_LIVRAISON_VENTE_HEADER blh ON bld.ID_BON = blh.ID
            WHERE p.CATEGORY_NAME = ?";
    
    // Add date visibility filter for non-admin users
    if (!$isAdmin) {
        $cutoffDate = date('Y-m-d', strtotime("-".$visibilitySettings['delivery_stock_visibility_days']." days"));
        $sql .= " AND (blh.DATE >= ? OR blh.DATE IS NULL)";
    }
    
    $sql .= " GROUP BY p.ID ORDER BY p.PRODUCT_NAME";
    
    $stmt = $conn->prepare($sql);
    
    if (!$isAdmin) {
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
// View all products with optional search
$sql = "SELECT p.ID, p.REFERENCE, p.PRODUCT_NAME, p.PRICE, p.QUANTITY, p.TYPE
        FROM PRODUCT p
        LEFT JOIN (
            SELECT PRODUCT_ID, MAX(bih.DATE) as LAST_DATE 
            FROM BUY_INVOICE_DETAILS bid
            JOIN BUY_INVOICE_HEADER bih ON bid.ID_INVOICE = bih.ID_INVOICE
            GROUP BY PRODUCT_ID
        ) invoice_dates ON p.REFERENCE = invoice_dates.PRODUCT_ID AND p.TYPE = 'Facture'
        LEFT JOIN (
            SELECT PRODUCT_ID, MAX(blh.DATE) as LAST_DATE 
            FROM BON_LIVRAISON_ACHAT_DETAILS bld
            JOIN BON_LIVRAISON_ACHAT_HEADER blh ON bld.ID_BON = blh.ID
            GROUP BY PRODUCT_ID
        ) purchase_delivery_dates ON p.REFERENCE = purchase_delivery_dates.PRODUCT_ID AND p.TYPE = 'BL'
        LEFT JOIN (
            SELECT PRODUCT_ID, MAX(blh.DATE) as LAST_DATE 
            FROM BON_LIVRAISON_VENTE_DETAILS bld
            JOIN BON_LIVRAISON_VENTE_HEADER blh ON bld.ID_BON = blh.ID
            GROUP BY PRODUCT_ID
        ) sales_delivery_dates ON p.REFERENCE = sales_delivery_dates.PRODUCT_ID AND p.TYPE = 'BL'
        WHERE 1=1";

$params = [];
$types = "";

// Add date visibility filter for non-admin users
if (!$isAdmin) {
    $cutoffDate = date('Y-m-d', strtotime("-".$visibilitySettings['delivery_stock_visibility_days']." days"));
    $sql .= " AND (
                (p.TYPE = 'Facture' AND (invoice_dates.LAST_DATE >= ? OR invoice_dates.LAST_DATE IS NULL)) OR
                (p.TYPE = 'BL' AND (sales_delivery_dates.LAST_DATE >= ? OR sales_delivery_dates.LAST_DATE IS NULL)) OR
                (p.TYPE = 'Stock')
              )";
    $params[] = $cutoffDate;
    $params[] = $cutoffDate;
    $types .= "ss";
}

if (!empty($searchTerm)) {
    $sql .= " AND (p.PRODUCT_NAME LIKE ? OR p.REFERENCE LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
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

<!-- Rest of your HTML remains the same -->

<h1>Nouveau Bon de Livraison Vente - Étape 2/3</h1>

<div class="delivery-summary">
    <h3>Résumé du Bon de Livraison</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['sales_delivery']['client_name']; ?></p>
    <?php if ($_SESSION['sales_delivery']['delivery_type'] === 'Company'): ?>
        <p><strong>ICE:</strong> <?php echo $_SESSION['sales_delivery']['company_ice']; ?></p>
    <?php endif; ?>
    <p><strong>Méthode de Paiement:</strong> <?php echo $_SESSION['sales_delivery']['payment_method']; ?></p>
    <p><strong>Numéro:</strong> <?php echo $_SESSION['sales_delivery']['delivery_number']; ?></p>
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
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
<?php endif; ?>

<?php if ($viewMode === 'category'): ?>
    <div class="category-selector">
        <form action="" method="get">
            <input type="hidden" name="view" value="category">
            <select name="category" onchange="this.form.submit()">
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category; ?>" <?php echo (isset($currentCategory) && $currentCategory === $category) ? 'selected' : ''; ?>>
                        <?php echo $category; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
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
                        <td><?php echo $product['QUANTITY']; ?></td>
                        <td>
                            <input type="number" name="quantity[<?php echo $product['ID']; ?>]" 
                                   min="1" max="<?php echo $product['QUANTITY']; ?>" value="1">
                        </td>
                        <td>
                            <input type="number" name="price[<?php echo $product['ID']; ?>]" 
                                   step="0.01" min="0" value="<?php echo number_format($product['PRICE'], 2); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">Aucun produit trouvé</td>
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
    <h3>Produits Ajoutés au Bon de Livraison</h3>
    <table>
        <thead>
            <tr>
                <th>Référence</th>
                <th>Désignation</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalHT = 0;
            $totalTVA = 0;
            $totalTTC = 0;
            
            foreach ($_SESSION['sales_products'] as $productId => $product): 
                $productHT = $product['unit_price'] / 1.2;
                $productTVA = $productHT * 0.2;
                $productTotal = $product['unit_price'] * $product['quantity'];
                
                $totalHT += $productHT * $product['quantity'];
                $totalTVA += $productTVA * $product['quantity'];
                $totalTTC += $productTotal;
            ?>
                <tr>
                    <td><?php echo $product['reference']; ?></td>
                    <td><?php echo $product['product_name']; ?></td>
                    <td><?php echo $product['quantity']; ?></td>
                    <td><?php echo number_format($product['unit_price'], 2); ?> DH</td>
                    <td><?php echo number_format($productTotal, 2); ?> DH</td>
                    <td>
                        <a href="?remove=<?php echo $productId; ?>" class="btn btn-danger">Supprimer</a>
                        <a href="?edit=<?php echo $productId; ?>&view=all" class="btn btn-warning">Modifier</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Totaux</th>
                <th>HT: <?php echo number_format($totalHT, 2); ?> DH</th>
                <th>TVA: <?php echo number_format($totalTVA, 2); ?> DH</th>
                <th>TTC: <?php echo number_format($totalTTC, 2); ?> DH</th>
            </tr>
        </tfoot>
    </table>
    
    <div class="form-actions">
        <a href="delivery_step1.php" class="btn btn-secondary">Retour</a>
        <a href="delivery_step3.php" class="btn btn-primary">Suivant</a>
    </div>
<?php else: ?>
    <p>Aucun produit ajouté pour le moment.</p>
    <div class="form-actions">
        <a href="delivery_step1.php" class="btn btn-secondary">Retour</a>
    </div>
<?php endif; ?>

<?php include '../../../includes/footerin.php'; ?>