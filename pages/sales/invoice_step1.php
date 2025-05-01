<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Facture de Vente - Étape 1";
include '../../includes/header.php';

// Get TVA min/max from settings
$minTVA = 13;
$maxTVA = 27;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName = trim($_POST['client_name']);
    $clientType = $_POST['invoice_type'];
    $companyICE = ($clientType === 'Company') ? trim($_POST['company_ice']) : null;
    $clientAddress = trim($_POST['client_address']);
    $totalTTC = (float)$_POST['total_ttc'];

    if ($totalTTC <= 0) {
        $_SESSION['error_message'] = "Le montant total TTC doit être supérieur à 0";
        redirect('invoice_step1.php');
    }

    // Check if client exists or create new one with address
    $checkClient = $conn->prepare("SELECT ID FROM CLIENT WHERE CLIENTNAME = ?");
    $checkClient->bind_param("s", $clientName);
    $checkClient->execute();

    if ($checkClient->get_result()->num_rows === 0) {
        $insertClient = $conn->prepare("INSERT INTO CLIENT (CLIENTNAME, ICE, ADDRESS) VALUES (?, ?, ?)");
        $insertClient->bind_param("sss", $clientName, $companyICE, $clientAddress);
        $insertClient->execute();
        $insertClient->close();
    } else {
        // Update address if client exists
        $updateClient = $conn->prepare("UPDATE CLIENT SET ADDRESS = ? WHERE CLIENTNAME = ?");
        $updateClient->bind_param("ss", $clientAddress, $clientName);
        $updateClient->execute();
        $updateClient->close();
    }
    $checkClient->close();

    // Store in session with address
    $_SESSION['sales_invoice'] = [
        'client_name' => $clientName,
        'invoice_type' => $clientType,
        'company_ice' => $companyICE,
        'client_address' => $clientAddress,
        'invoice_number' => generateInvoiceNumber('FAC'),
        'total_ttc' => $totalTTC
    ];

    // In the POST handling section, add these lines before redirect
    $_SESSION['sales_invoice']['payment_type'] = $_POST['payment_type'];
    if ($_POST['payment_type'] === 'cheque' || $_POST['payment_type'] === 'effet') {
        $_SESSION['sales_invoice']['payment_reference'] = trim($_POST['payment_reference']);
    } else {
        $_SESSION['sales_invoice']['payment_reference'] = null;
    }

    redirect('invoice_step2.php');
}

// Get all clients with addresses
$clients = [];
$sql = "SELECT CLIENTNAME, ICE, ADDRESS FROM CLIENT ORDER BY CLIENTNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clients[$row['CLIENTNAME']] = [
            'ice' => $row['ICE'],
            'address' => $row['ADDRESS']
        ];
    }
}
?>

<h1>Nouvelle Facture de Vente - Étape 1/3</h1>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error_message'];
                                    unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<form action="" method="post">
    <div class="form-group">
        <label for="client_name">Client:</label>
        <input list="clients" name="client_name" id="client_name" required>
        <datalist id="clients">
            <?php foreach ($clients as $name => $data): ?>
                <option value="<?php echo $name; ?>"
                    data-ice="<?php echo $data['ice']; ?>"
                    data-address="<?php echo $data['address']; ?>">
                <?php endforeach; ?>
        </datalist>
    </div>

    <div class="form-group">
        <label for="client_address">Adresse:</label>
        <textarea name="client_address" id="client_address" rows="1"></textarea>
    </div>

    <div class="form-group">
        <label for="invoice_type">Type de Facture:</label>
        <select name="invoice_type" id="invoice_type" required>
            <option value="Personal">Personnelle</option>
            <option value="Company">Entreprise</option>
        </select>
    </div>

    <div class="form-group" id="company-ice-group" style="display: none;">
        <label for="company_ice">ICE de l'Entreprise:</label>
        <input type="text" name="company_ice" id="company_ice">
    </div>

    <div class="form-group">
        <label for="payment_type">Type de Paiement:</label>
        <select name="payment_type" id="payment_type" required>
            <option value="espece">Espèce</option>
            <option value="cheque">Chèque</option>
            <option value="effet">Effet</option>
        </select>
    </div>

    <div class="form-group" id="payment_reference_group" style="display: none;">
        <label for="payment_reference">Référence:</label>
        <input type="text" name="payment_reference" id="payment_reference">
    </div>

    <div class="form-group">
        <label for="total_ttc">Montant Total TTC (DH):</label>
        <input type="number" name="total_ttc" id="total_ttc" step="0.01" min="0.01" required>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Suivant</button>
    </div>
</form>

<script>
    document.getElementById('payment_type').addEventListener('change', function() {
        const refGroup = document.getElementById('payment_reference_group');
        if (this.value === 'cheque' || this.value === 'effet') {
            refGroup.style.display = 'block';
            document.getElementById('payment_reference').required = true;
        } else {
            refGroup.style.display = 'none';
            document.getElementById('payment_reference').required = false;
        }
    });

    document.getElementById('invoice_type').addEventListener('change', function() {
        const iceGroup = document.getElementById('company-ice-group');
        iceGroup.style.display = this.value === 'Company' ? 'block' : 'none';
    });

    document.getElementById('client_name').addEventListener('input', function() {
        const selectedOption = document.querySelector(`#clients option[value="${this.value}"]`);
        const invoiceType = document.getElementById('invoice_type');
        const companyIce = document.getElementById('company_ice');
        const clientAddress = document.getElementById('client_address');

        if (selectedOption) {
            const ice = selectedOption.getAttribute('data-ice');
            const address = selectedOption.getAttribute('data-address');

            if (ice) {
                invoiceType.value = 'Company';
                document.getElementById('company-ice-group').style.display = 'block';
                companyIce.value = ice;
            } else {
                invoiceType.value = 'Personal';
                document.getElementById('company-ice-group').style.display = 'none';
                companyIce.value = '';
            }

            clientAddress.value = address || '';
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const totalTTCField = document.getElementById('total_ttc');
        if (totalTTCField && !totalTTCField.value) {
            totalTTCField.focus();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>