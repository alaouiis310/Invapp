<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

$title = "Bon de Livraison Vente - Étape 1";
include '../../../includes/headerin.php';

// Add this to your form processing code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName = trim($_POST['client_name']);
    $clientType = $_POST['delivery_type'];
    $companyICE = ($clientType === 'Company') ? trim($_POST['company_ice']) : null;
    $paymentMethod = $_POST['payment_method'];

    // Check if client exists or create new one
    $checkClient = $conn->prepare("SELECT ID FROM CLIENT WHERE CLIENTNAME = ?");
    $checkClient->bind_param("s", $clientName);
    $checkClient->execute();
    
    if ($checkClient->get_result()->num_rows === 0) {
        // Client doesn't exist - create new
        $insertClient = $conn->prepare("INSERT INTO CLIENT (CLIENTNAME, ICE) VALUES (?, ?)");
        $insertClient->bind_param("ss", $clientName, $companyICE);
        $insertClient->execute();
        $insertClient->close();
    }
    $checkClient->close();

// Store in session
$_SESSION['sales_delivery'] = [
    'client_name' => $clientName,
    'delivery_type' => $clientType,
    'company_ice' => $companyICE,
    'payment_method' => $paymentMethod,
    'delivery_number' => generateDeliveryNumber() // This will now use the settings
];
    
    redirect('delivery_step2.php');
}

// Get all clients
$clients = [];
$sql = "SELECT CLIENTNAME, ICE FROM CLIENT ORDER BY CLIENTNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $clients[$row['CLIENTNAME']] = $row['ICE'];
    }
}
?>

<h1>Nouveau Bon de Livraison Vente - Étape 1/3</h1>

<form action="" method="post">
    <div class="form-group">
        <label for="client_name">Client:</label>
        <input list="clients" name="client_name" id="client_name" required>
        <datalist id="clients">
            <?php foreach ($clients as $name => $ice): ?>
                <option value="<?php echo $name; ?>" data-ice="<?php echo $ice; ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
    
    <div class="form-group">
        <label for="delivery_type">Type de Client:</label>
        <select name="delivery_type" id="delivery_type" required>
            <option value="Personal">Personnelle</option>
            <option value="Company">Entreprise</option>
        </select>
    </div>
    
    <div class="form-group" id="company-ice-group" style="display: none;">
        <label for="company_ice">ICE de l'Entreprise:</label>
        <input type="text" name="company_ice" id="company_ice">
    </div>
    
    <div class="form-group">
        <label for="payment_method">Méthode de Paiement:</label>
        <select name="payment_method" id="payment_method" required>
            <option value="Cash">Espèces</option>
            <option value="Credit">Crédit</option>
            <option value="Check">Chèque</option>
            <option value="Bank Transfer">Virement Bancaire</option>
        </select>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Suivant</button>
    </div>
</form>

<script>
document.getElementById('delivery_type').addEventListener('change', function() {
    const iceGroup = document.getElementById('company-ice-group');
    iceGroup.style.display = this.value === 'Company' ? 'block' : 'none';
});

document.getElementById('client_name').addEventListener('input', function() {
    const selectedOption = document.querySelector(`#clients option[value="${this.value}"]`);
    const deliveryType = document.getElementById('delivery_type');
    const companyIce = document.getElementById('company_ice');
    
    if (selectedOption) {
        const ice = selectedOption.getAttribute('data-ice');
        if (ice) {
            deliveryType.value = 'Company';
            document.getElementById('company-ice-group').style.display = 'block';
            companyIce.value = ice;
        } else {
            deliveryType.value = 'Personal';
            document.getElementById('company-ice-group').style.display = 'none';
            companyIce.value = '';
        }
    }
});
</script>

<?php include '../../../includes/footerin.php'; ?>