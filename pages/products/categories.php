<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Gestion des Catégories";
include '../../includes/header.php';

// Handle form submission for adding category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = sanitizeInput($_POST['category_name']);
    
    try {
        $stmt = $conn->prepare("INSERT INTO CATEGORY (CATEGORYNAME) VALUES (?)");
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "Catégorie ajoutée avec succès!";
        redirect('categories.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de l'ajout de la catégorie: " . $e->getMessage();
    }
}

// Handle category deletion
if (isset($_GET['delete']) && isAdmin()) {
    $categoryName = sanitizeInput($_GET['delete']);
    
    try {
        // Check if category has products
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM PRODUCT WHERE CATEGORY_NAME = ?");
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            $_SESSION['error_message'] = "Impossible de supprimer cette catégorie car elle contient des produits.";
        } else {
            $stmt = $conn->prepare("DELETE FROM CATEGORY WHERE CATEGORYNAME = ?");
            $stmt->bind_param("s", $categoryName);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = "Catégorie supprimée avec succès!";
        }
        
        redirect('categories.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de la suppression de la catégorie: " . $e->getMessage();
        redirect('categories.php');
    }
}

// Check for success/error messages
$successMessage = '';
$errorMessage = '';

if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get all categories
$categories = [];
$sql = "SELECT * FROM CATEGORY ORDER BY CATEGORYNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<h1>Gestion des Catégories</h1>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
<?php endif; ?>

<div class="category-form">
    <h2>Ajouter une Catégorie</h2>
    <form action="" method="post">
        <div class="form-group">
            <label for="category_name">Nom de la Catégorie:</label>
            <input type="text" name="category_name" id="category_name" required>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="add_category" class="btn btn-primary">Ajouter</button>
        </div>
    </form>
</div>

<div class="categories-list">
    <h2>Liste des Catégories</h2>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Nombre de Produits</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo $category['CATEGORYNAME']; ?></td>
                        <td><?php echo $category['NUMBER_OF_PRODUCTS']; ?></td>
                        <td>
                            <?php if ($category['CATEGORYNAME'] !== 'Uncategorized' && isAdmin()): ?>
                                <a href="?delete=<?php echo urlencode($category['CATEGORYNAME']); ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie?');">Supprimer</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Aucune catégorie enregistrée</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="form-actions">
    <a href="stock.php" class="btn btn-secondary">Retour au Stock</a>
</div>

<?php include '../../includes/footer.php'; ?>