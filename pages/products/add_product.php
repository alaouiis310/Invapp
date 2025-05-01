<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Ajouter un Produit";
include '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = sanitizeInput($_POST['reference']);
    $productName = sanitizeInput($_POST['product_name']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $category = sanitizeInput($_POST['category']);
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = file_get_contents($_FILES['image']['tmp_name']);
    }

    // Insert product with type 'Stock'
    $sql = "INSERT INTO PRODUCT (REFERENCE, PRODUCT_NAME, PRICE, QUANTITY, CATEGORY_NAME, IMAGE, TYPE) 
            VALUES (?, ?, ?, ?, ?, ?, 'Stock')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssdiss', $reference, $productName, $price, $quantity, $category, $image);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Produit ajouté avec succès!";
        header("Location: stock.php?type=Stock");
        exit();
    } else {
        $error = "Erreur lors de l'ajout du produit: " . $conn->error;
    }
    $stmt->close();
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
?>

<h1>Ajouter un Produit (Stock)</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form action="add_product.php" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="reference">Référence:</label>
        <input type="text" name="reference" id="reference" class="form-control" required>
    </div>
    
    <div class="form-group">
        <label for="product_name">Désignation:</label>
        <input type="text" name="product_name" id="product_name" class="form-control" required>
    </div>
    
    <div class="form-group">
        <label for="price">Prix (DH):</label>
        <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" required>
    </div>
    
    <div class="form-group">
        <label for="quantity">Quantité:</label>
        <input type="number" name="quantity" id="quantity" class="form-control" min="0" required>
    </div>
    
    <div class="form-group">
        <label for="category">Catégorie:</label>
        <select name="category" id="category" class="form-control" required>
            <option value="">Sélectionner une catégorie</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="image">Image:</label>
        <input type="file" name="image" id="image" class="form-control" accept="image/*">
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="stock.php?type=Stock" class="btn btn-secondary">Annuler</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>