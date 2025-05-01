<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

$title = "Gestion des Fournisseurs";
include '../../includes/header.php';

// Handle form submission for adding supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplierName = sanitizeInput($_POST['supplier_name']);
    $ice = sanitizeInput($_POST['ice']);
    
    try {
        $stmt = $conn->prepare("INSERT INTO SUPPLIER (SUPPLIERNAME, ICE) VALUES (?, ?)");
        $stmt->bind_param("ss", $supplierName, $ice);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "Fournisseur ajouté avec succès!";
        redirect('manage.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de l'ajout du fournisseur: " . $e->getMessage();
    }
}

// Handle supplier deletion
if (isset($_GET['delete']) && isAdmin()) {
    $supplierId = (int)$_GET['delete'];
    
    try {
        // Check if supplier has invoices
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM BUY_INVOICE_HEADER WHERE SUPPLIER_NAME = (SELECT SUPPLIERNAME FROM SUPPLIER WHERE ID = ?)");
        $stmt->bind_param("i", $supplierId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] > 0) {
            $_SESSION['error_message'] = "Impossible de supprimer ce fournisseur car il a des factures associées.";
        } else {
            $stmt = $conn->prepare("DELETE FROM SUPPLIER WHERE ID = ?");
            $stmt->bind_param("i", $supplierId);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = "Fournisseur supprimé avec succès!";
        }
        
        redirect('manage.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de la suppression du fournisseur: " . $e->getMessage();
        redirect('manage.php');
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

// Get all suppliers
$suppliers = [];
$sql = "SELECT * FROM SUPPLIER ORDER BY SUPPLIERNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}
?>

<h1>Gestion des Fournisseurs</h1>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
<?php endif; ?>

<div class="supplier-form">
    <h2>Ajouter un Fournisseur</h2>
    <form action="" method="post">
        <div class="form-group">
            <label for="supplier_name">Nom du Fournisseur:</label>
            <input type="text" name="supplier_name" id="supplier_name" required>
        </div>
        
        <div class="form-group">
            <label for="ice">ICE:</label>
            <input type="text" name="ice" id="ice">
        </div>
        
        <div class="form-actions">
            <button type="submit" name="add_supplier" class="btn btn-primary">Ajouter</button>
        </div>
    </form>
</div>

<div class="suppliers-list">
    <h2>Liste des Fournisseurs</h2>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>ICE</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($suppliers)): ?>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><?php echo $supplier['SUPPLIERNAME']; ?></td>
                        <td><?php echo $supplier['ICE'] ?? '-'; ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $supplier['ID']; ?>" class="btn btn-info">Voir Factures</a>
                            <?php if (isAdmin()): ?>
                                <a href="?delete=<?php echo $supplier['ID']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur?');">Supprimer</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Aucun fournisseur enregistré</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer.php'; ?>