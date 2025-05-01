<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Créer un Devis";
include '../../includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['quote'] = [
        'client_id' => (int)$_POST['client_id'],
        'client_name' => sanitizeInput($_POST['client_name']),
        'company_ice' => $_POST['client_type'] === 'Company' ? sanitizeInput($_POST['company_ice']) : null,
        'quote_number' => generateInvoiceNumber('DEV')
    ];
    
    redirect('add_products.php');
}

// Get all clients
$clients = [];
$sql = "SELECT ID, CLIENTNAME, ICE FROM CLIENT ORDER BY CLIENTNAME";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
}

// Get all existing quotes
$quotes = [];
$sql = "SELECT q.ID, q.DEVIS_NUMBER, q.DATE, q.TOTAL_PRICE_TTC, 
               c.CLIENTNAME, c.ICE 
        FROM DEVIS_HEADER q
        LEFT JOIN CLIENT c ON q.CLIENT_ID = c.ID
        ORDER BY q.DATE DESC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $quotes[] = $row;
    }
}
?>

<h1>Gestion des Devis</h1>

<div class="quote-management">
    <div class="create-quote-section">
        <h2>Créer un Nouveau Devis</h2>
        <form action="" method="post">
            <div class="form-group">
                <label for="client_id">Client:</label>
                <select name="client_id" id="client_id" required>
                    <option value="">Sélectionner un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['ID']; ?>" data-ice="<?php echo $client['ICE'] ?? ''; ?>">
                            <?php echo $client['CLIENTNAME']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="client_name">Nom du Client:</label>
                <input type="text" name="client_name" id="client_name" required>
            </div>
            
            <div class="form-group">
                <label for="client_type">Type de Client:</label>
                <select name="client_type" id="client_type" required>
                    <option value="Personal">Personnel</option>
                    <option value="Company">Entreprise</option>
                </select>
            </div>
            
            <div class="form-group" id="company-ice-group" style="display: none;">
                <label for="company_ice">ICE de l'Entreprise:</label>
                <input type="text" name="company_ice" id="company_ice">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Créer le Devis</button>
            </div>
        </form>
    </div>

    <div class="existing-quotes-section">
        <h2>Devis Existants</h2>
        <?php if (!empty($quotes)): ?>
            <table class="quote-table">
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Montant TTC</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($quote['DEVIS_NUMBER']); ?></td>
                            <td><?php echo htmlspecialchars($quote['CLIENTNAME']); ?></td>
                            <td><?php echo htmlspecialchars($quote['DATE']); ?></td>
                            <td><?php echo number_format($quote['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                            <td class="actions">
                                <a href="details.php?id=<?php echo $quote['ID']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                                <a href="delete.php?id=<?php echo $quote['ID']; ?>" class="btn btn-danger" 
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce devis?');">
                                    <i class="fas fa-trash"></i> Supprimer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun devis existant.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.quote-management {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}
.create-quote-section, .existing-quotes-section {
    flex: 1;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
.quote-table {
    width: 100%;
    border-collapse: collapse;
}
.quote-table th, .quote-table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}
.quote-table th {
    background-color: #f2f2f2;
}
.actions {
    white-space: nowrap;
}
.btn {
    padding: 5px 10px;
    margin: 0 3px;
}
</style>

<script>
document.getElementById('client_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const clientName = document.getElementById('client_name');
    const clientType = document.getElementById('client_type');
    const companyIce = document.getElementById('company_ice');
    const iceGroup = document.getElementById('company-ice-group');
    
    if (selectedOption.value) {
        clientName.value = selectedOption.text;
        
        if (selectedOption.getAttribute('data-ice')) {
            clientType.value = 'Company';
            iceGroup.style.display = 'block';
            companyIce.value = selectedOption.getAttribute('data-ice');
        } else {
            clientType.value = 'Personal';
            iceGroup.style.display = 'none';
            companyIce.value = '';
        }
    } else {
        clientName.value = '';
        clientType.value = 'Personal';
        iceGroup.style.display = 'none';
        companyIce.value = '';
    }
});

document.getElementById('client_type').addEventListener('change', function() {
    const iceGroup = document.getElementById('company-ice-group');
    iceGroup.style.display = this.value === 'Company' ? 'block' : 'none';
});
</script>

<?php include '../../includes/footer.php'; ?>