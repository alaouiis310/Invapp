<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['quote'])) {
    redirect('create.php');
}

$title = "Ajouter des Produits au Devis";
include '../../includes/header.php';

// Handle adding products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_products'])) {
    if (!isset($_SESSION['quote_products'])) {
        $_SESSION['quote_products'] = [];
    }
    
    if (isset($_POST['selected_products'])) {
        foreach ($_POST['selected_products'] as $productId) {
            $quantity = (int)$_POST['quantity'][$productId];
            $unitPrice = (float)$_POST['price'][$productId];
            
            // Get product details
            $stmt = $conn->prepare("SELECT ID, REFERENCE, PRODUCT_NAME FROM PRODUCT WHERE ID = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                $_SESSION['quote_products'][$productId] = [
                    'reference' => $product['REFERENCE'],
                    'product_name' => $product['PRODUCT_NAME'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice
                ];
            }
            $stmt->close();
        }
    }
}

// Handle product removal
if (isset($_GET['remove']) && isset($_SESSION['quote_products'])) {
    $productId = (int)$_GET['remove'];
    if (isset($_SESSION['quote_products'][$productId])) {
        unset($_SESSION['quote_products'][$productId]);
    }
    redirect('add_products.php');
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

// Get products based on view mode
$products = [];
if ($viewMode === 'category' && !empty($categories)) {
    $currentCategory = isset($_GET['category']) ? sanitizeInput($_GET['category']) : $categories[0];
    
    $sql = "SELECT ID, REFERENCE, PRODUCT_NAME, PRICE FROM PRODUCT 
            WHERE CATEGORY_NAME = ? 
            ORDER BY PRODUCT_NAME";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $currentCategory);
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
    $sql = "SELECT ID, REFERENCE, PRODUCT_NAME, PRICE FROM PRODUCT";
    
    if (!empty($searchTerm)) {
        $sql .= " WHERE PRODUCT_NAME LIKE ? OR REFERENCE LIKE ?";
        $searchParam = "%$searchTerm%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $searchParam, $searchParam);
    } else {
        $stmt = $conn->prepare($sql);
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

<h1>Ajouter des Produits au Devis</h1>

<div class="quote-summary">
    <h3>Résumé du Devis</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['quote']['client_name']; ?></p>
    <?php if (!empty($_SESSION['quote']['company_ice'])): ?>
        <p><strong>ICE:</strong> <?php echo $_SESSION['quote']['company_ice']; ?></p>
    <?php endif; ?>
    <p><strong>Numéro:</strong> <?php echo $_SESSION['quote']['quote_number']; ?></p>
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
                    <option value="<?php echo $category; ?>" <?php echo (isset($currentCategory) && $currentCategory === $category ? 'selected' : ''); ?>>
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
                        <td>
                            <input type="number" name="quantity[<?php echo $product['ID']; ?>]" 
                                   min="1" value="1">
                        </td>
                        <td>
                            <input type="number" name="price[<?php echo $product['ID']; ?>]" 
                                   step="0.01" min="0" value="<?php echo number_format($product['PRICE'], 2); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Aucun produit trouvé</td>
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

<?php if (!empty($_SESSION['quote_products'])): ?>
    <h3>Produits Ajoutés au Devis</h3>
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
            
            foreach ($_SESSION['quote_products'] as $productId => $product): 
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
        <a href="create.php" class="btn btn-secondary">Retour</a>
        <a href="confirm.php" class="btn btn-primary">Confirmer</a>
    </div>
<?php else: ?>
    <p>Aucun produit ajouté pour le moment.</p>
    <div class="form-actions">
        <a href="create.php" class="btn btn-secondary">Retour</a>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>