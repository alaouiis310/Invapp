<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Facture d'Achat - Étape 1";
include '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['purchase_invoice'] = [
        'supplier_name' => sanitizeInput($_POST['supplier_name']),
        'invoice_number' => sanitizeInput($_POST['invoice_number']),
        'invoice_date' => sanitizeInput($_POST['invoice_date'])
    ];
    
    // Handle file upload
    if (isset($_FILES['invoice_image']) && $_FILES['invoice_image']['error'] === UPLOAD_ERR_OK) {
        $imageData = file_get_contents($_FILES['invoice_image']['tmp_name']);
        $_SESSION['purchase_invoice']['invoice_image'] = $imageData;
    }
    
    redirect('add_step2.php');
}

// Get all suppliers
$suppliers = [];
$sql = "SELECT SUPPLIERNAME FROM SUPPLIER ORDER BY SUPPLIERNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row['SUPPLIERNAME'];
    }
}
?>

<h1>Nouvelle Facture d'Achat - Étape 1/3</h1>

<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="supplier_name">Fournisseur:</label>
        <input list="suppliers" name="supplier_name" id="supplier_name" required>
        <datalist id="suppliers">
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo $supplier; ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
    
    <div class="form-group">
        <label for="invoice_number">Numéro de Facture:</label>
        <input type="text" name="invoice_number" id="invoice_number" required>
    </div>
    
    <div class="form-group">
        <label for="invoice_date">Date de Facture:</label>
        <input type="date" name="invoice_date" id="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
    
    <div class="form-group">
        <label for="invoice_image">Image de la Facture:</label>
        <input type="file" name="invoice_image" id="invoice_image" accept="image/*">
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Suivant</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>