<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('stock.php');
}

$productId = (int)$_GET['id'];

// Get product info
$stmt = $conn->prepare("SELECT * FROM PRODUCT WHERE ID = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('stock.php');
}

$product = $result->fetch_assoc();
$stmt->close();

// Get all categories
$categories = [];
$sql = "SELECT CATEGORYNAME FROM CATEGORY ORDER BY CATEGORYNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row['CATEGORYNAME'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = sanitizeInput($_POST['reference']);
    $productName = sanitizeInput($_POST['product_name']);
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $category = sanitizeInput($_POST['category']);
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get old category for update
        $oldCategory = $product['CATEGORY_NAME'];
        
        // Update product
        $stmt = $conn->prepare("UPDATE PRODUCT SET 
            REFERENCE = ?,
            PRODUCT_NAME = ?,
            PRICE = ?,
            QUANTITY = ?,
            CATEGORY_NAME = ?
            WHERE ID = ?");
        $stmt->bind_param("ssdiss", 
            $reference,
            $productName,
            $price,
            $quantity,
            $category,
            $productId
        );
        $stmt->execute();
        $stmt->close();
        
        // Handle image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $imageData = file_get_contents($_FILES['product_image']['tmp_name']);
            
            $stmt = $conn->prepare("UPDATE PRODUCT SET IMAGE = ? WHERE ID = ?");
            $stmt->bind_param("bi", $imageData, $productId);
            $stmt->send_long_data(0, $imageData);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update category counts if category changed
        if ($oldCategory !== $category) {
            // Decrement old category if not Uncategorized
            if ($oldCategory !== 'Uncategorized') {
                $stmt = $conn->prepare("UPDATE CATEGORY SET 
                    NUMBER_OF_PRODUCTS = NUMBER_OF_PRODUCTS - 1 
                    WHERE CATEGORYNAME = ?");
                $stmt->bind_param("s", $oldCategory);
                $stmt->execute();
                $stmt->close();
            }
            
            // Increment new category if not Uncategorized
            if ($category !== 'Uncategorized') {
                $stmt = $conn->prepare("UPDATE CATEGORY SET 
                    NUMBER_OF_PRODUCTS = NUMBER_OF_PRODUCTS + 1 
                    WHERE CATEGORYNAME = ?");
                $stmt->bind_param("s", $category);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Produit mis à jour avec succès!";
        redirect('stock.php');
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error_message'] = "Erreur lors de la mise à jour du produit: " . $e->getMessage();
    }
}

$title = "Modifier Produit: " . $product['PRODUCT_NAME'];
include '../../includes/header.php';
?>

<h1>Modifier Produit: <?php echo $product['PRODUCT_NAME']; ?></h1>

<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="reference">Référence:</label>
        <input type="text" name="reference" id="reference" value="<?php echo $product['REFERENCE']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="product_name">Désignation:</label>
        <input type="text" name="product_name" id="product_name" value="<?php echo $product['PRODUCT_NAME']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="price">Prix:</label>
        <input type="number" name="price" id="price" step="0.01" min="0" value="<?php echo $product['PRICE']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="quantity">Quantité:</label>
        <input type="number" name="quantity" id="quantity" min="0" value="<?php echo $product['QUANTITY']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="category">Catégorie:</label>
        <select name="category" id="category">
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat; ?>" <?php echo $cat === $product['CATEGORY_NAME'] ? 'selected' : ''; ?>>
                    <?php echo $cat; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="product_image">Image du Produit:</label>
        <input type="file" name="product_image" id="product_image" accept="image/*">
        <?php if (!empty($product['IMAGE'])): ?>
            <div class="current-image">
                <p>Image actuelle:</p>
                <?php 
                $imageData = $product['IMAGE'];
                $imageInfo = getimagesizefromstring($imageData);
                $mime = $imageInfo['mime'];
                $base64 = base64_encode($imageData);
                echo '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Product Image" style="max-width: 100px; max-height: 100px;">';
                ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="stock.php" class="btn btn-secondary">Annuler</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>