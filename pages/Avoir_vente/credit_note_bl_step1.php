<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Avoir/Retour Bon de Livraison - Étape 1";
include '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['delivery_credit_note'] = [
        'client_name' => sanitizeInput($_POST['client_name']),
        'credit_note_number' => sanitizeInput($_POST['credit_note_number']),
        'delivery_number' => sanitizeInput($_POST['delivery_number']),
        'credit_note_date' => sanitizeInput($_POST['credit_note_date'])
    ];
    
    // Handle file upload
    if (isset($_FILES['credit_note_image']) && $_FILES['credit_note_image']['error'] === UPLOAD_ERR_OK) {
        $imageData = file_get_contents($_FILES['credit_note_image']['tmp_name']);
        $_SESSION['delivery_credit_note']['credit_note_image'] = $imageData;
    }
    
    redirect('credit_note_bl_step2.php');
}

// Get all clients
$clients = [];
$sql = "SELECT CLIENTNAME FROM CLIENT ORDER BY CLIENTNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $clients[] = $row['CLIENTNAME'];
    }
}

// Get all delivery numbers
$deliveries = [];
$sql = "SELECT ID_BON FROM BON_LIVRAISON_VENTE_HEADER ORDER BY DATE DESC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $deliveries[] = $row['ID_BON'];
    }
}
?>

<h1>Nouvel Avoir/Retour Bon de Livraison - Étape 1/3</h1>

<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="client_name">Client:</label>
        <input list="clients" name="client_name" id="client_name" required>
        <datalist id="clients">
            <?php foreach ($clients as $client): ?>
                <option value="<?php echo $client; ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
    
    <div class="form-group">
        <label for="delivery_number">Numéro de Bon de Livraison:</label>
        <input list="deliveries" name="delivery_number" id="delivery_number" required>
        <datalist id="deliveries">
            <?php foreach ($deliveries as $delivery): ?>
                <option value="<?php echo $delivery; ?>">
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