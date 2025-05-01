<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Avoir/Retour d'Achat - Étape 1";
include '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['credit_note'] = [
        'supplier_name' => sanitizeInput($_POST['supplier_name']),
        'credit_note_number' => sanitizeInput($_POST['credit_note_number']),
        'invoice_number' => sanitizeInput($_POST['invoice_number']),
        'credit_note_date' => sanitizeInput($_POST['credit_note_date'])
    ];
    
    // Handle file upload
    if (isset($_FILES['credit_note_image']) && $_FILES['credit_note_image']['error'] === UPLOAD_ERR_OK) {
        $imageData = file_get_contents($_FILES['credit_note_image']['tmp_name']);
        $_SESSION['credit_note']['credit_note_image'] = $imageData;
    }
    
    redirect('credit_note_step2.php');
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

// Get all invoice numbers
$invoices = [];
$sql = "SELECT INVOICE_NUMBER FROM BUY_INVOICE_HEADER ORDER BY DATE DESC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $invoices[] = $row['INVOICE_NUMBER'];
    }
}
?>

<h1>Nouvel Avoir/Retour d'Achat - Étape 1/3</h1>

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
        <label for="invoice_number">Numéro de Facture Originale:</label>
        <input list="invoices" name="invoice_number" id="invoice_number" required>
        <datalist id="invoices">
            <?php foreach ($invoices as $invoice): ?>
                <option value="<?php echo $invoice; ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
    
    <div class="form-group">
        <label for="credit_note_number">Numéro de l'Avoir:</label>
        <input type="text" name="credit_note_number" id="credit_note_number" required>
    </div>
    
    <div class="form-group">
        <label for="credit_note_date">Date de l'Avoir:</label>
        <input type="date" name="credit_note_date" id="credit_note_date" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
    
    <div class="form-group">
        <label for="credit_note_image">Image de l'Avoir:</label>
        <input type="file" name="credit_note_image" id="credit_note_image" accept="image/*">
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Suivant</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>