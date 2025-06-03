<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Check if we have the necessary session data
if (!isset($_SESSION['delivery_credit_note'])) {
    redirect('credit_note_bl_step1.php');
}

$title = "Avoir/Retour Bon de Livraison - Étape 2/3";
include '../../includes/header.php';

// Handle adding products
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    if (!isset($_SESSION['delivery_credit_note_products'])) {
        $_SESSION['delivery_credit_note_products'] = [];
    }
    
    $product = [
        'reference' => sanitizeInput($_POST['reference']),
        'product_name' => sanitizeInput($_POST['product_name']),
        'quantity' => (int)$_POST['quantity'],
        'unit_price' => (float)$_POST['unit_price'],
        'category' => sanitizeInput($_POST['category']),
        'image' => null
    ];
    
    // Handle product image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $product['image'] = file_get_contents($_FILES['product_image']['tmp_name']);
    }
    
    $_SESSION['delivery_credit_note_products'][] = $product;
}

// Handle product removal
if (isset($_GET['remove']) && isset($_SESSION['delivery_credit_note_products'])) {
    $index = (int)$_GET['remove'];
    if (isset($_SESSION['delivery_credit_note_products'][$index])) {
        array_splice($_SESSION['delivery_credit_note_products'], $index, 1);
    }
    redirect('credit_note_bl_step2.php');
}

// Get products from the original delivery
$originalProducts = [];
$sql = "SELECT PRODUCT_ID, PRODUCT_NAME FROM BON_LIVRAISON_VENTE_DETAILS WHERE ID_BON = 
        (SELECT ID FROM BON_LIVRAISON_VENTE_HEADER WHERE ID_BON = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $_SESSION['delivery_credit_note']['delivery_number']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $originalProducts[] = $row;
    }
}
$stmt->close();

// Get all categories
$categories = ['Uncategorized'];
$sql = "SELECT CATEGORYNAME FROM CATEGORY WHERE CATEGORYNAME != 'Uncategorized' ORDER BY CATEGORYNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row['CATEGORYNAME'];
    }
}
?>

<h1>Nouvel Avoir/Retour Bon de Livraison - Étape 2/3</h1>

<div class="invoice-summary">
    <h3>Résumé de l'Avoir</h3>
    <p><strong>Client:</strong> <?php echo $_SESSION['delivery_credit_note']['client_name']; ?></p>
    <p><strong>Numéro Bon de Livraison:</strong> <?php echo $_SESSION['delivery_credit_note']['delivery_number']; ?></p>
    <p><strong>Numéro Avoir:</strong> <?php echo $_SESSION['delivery_credit_note']['credit_note_number']; ?></p>
    <p><strong>Date:</strong> <?php echo $_SESSION['delivery_credit_note']['credit_note_date']; ?></p>
</div>

<h3>Ajouter un Produit</h3>
<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="reference">Référence:</label>
        <input list="originalProducts" name="reference" id="reference" required>
        <datalist id="originalProducts">
            <?php foreach ($originalProducts as $product): ?>
                <option value="<?php echo $product['PRODUCT_ID']; ?>"><?php echo $product['PRODUCT_NAME']; ?></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    
    <div class="form-group">
        <label for="product_name">Désignation:</label>
        <input type="text" name="product_name" id="product_name" required>
    </div>
    
    <div class="form-group">
        <label for="quantity">Quantité:</label>
        <input type="number" name="quantity" id="quantity" min="1" required>
    </div>
    
    <div class="form-group">
        <label for="unit_price">Prix Unitaire (TTC):</label>
        <input type="number" name="unit_price" id="unit_price" step="0.01" min="0" required>
    </div>
    
    <div class="form-group">
        <label for="category">Catégorie:</label>
        <select name="category" id="category">
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category; ?>" <?php echo $category === 'Uncategorized' ? 'selected' : ''; ?>>
                    <?php echo $category; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="product_image">Image du Produit:</label>
        <input type="file" name="product_image" id="product_image" accept="image/*">
    </div>
    
    <div class="form-actions">
        <button type="submit" name="add_product" class="btn btn-primary">Ajouter Produit</button>
    </div>
</form>

<?php if (!empty($_SESSION['delivery_credit_note_products'])): ?>
    <h3>Produits Ajoutés</h3>
    <table>
        <thead>
            <tr>
                <th>Référence</th>
                <th>Désignation</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>Total</th>
                <th>Catégorie</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalHT = 0;
            $totalTVA = 0;
            $totalTTC = 0;
            
            foreach ($_SESSION['delivery_credit_note_products'] as $index => $product): 
                $productHT = $product['unit_price'] / 1.2; // Remove 20% TVA
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
                    <td><?php echo $product['category']; ?></td>
                    <td>
                        <a href="?remove=<?php echo $index; ?>" class="btn btn-danger">Supprimer</a>
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
                <th></th>
            </tr>
        </tfoot>
    </table>
    
    <div class="form-actions">
        <a href="credit_note_bl_step1.php" class="btn btn-secondary">Retour</a>
        <a href="credit_note_bl_step3.php" class="btn btn-primary">Suivant</a>
    </div>
<?php else: ?>
    <p>Aucun produit ajouté pour le moment.</p>
    <div class="form-actions">
        <a href="credit_note_bl_step1.php" class="btn btn-secondary">Retour</a>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>