<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

if (!isset($_GET['id'])) {
    redirect('manage.php');
}

$supplierId = (int)$_GET['id'];

// Get supplier info
$stmt = $conn->prepare("SELECT * FROM SUPPLIER WHERE ID = ?");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('manage.php');
}

$supplier = $result->fetch_assoc();
$stmt->close();

// Initialize filter variables
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';
$minTotal = isset($_GET['min_total']) ? (float)$_GET['min_total'] : null;
$maxTotal = isset($_GET['max_total']) ? (float)$_GET['max_total'] : null;

// Build SQL query with filters
$sql = "SELECT * FROM BUY_INVOICE_HEADER WHERE SUPPLIER_NAME = ?";
$params = [$supplier['SUPPLIERNAME']];
$types = "s";

// Add search term filter
if (!empty($searchTerm)) {
    $sql .= " AND INVOICE_NUMBER LIKE ?";
    $params[] = "%$searchTerm%";
    $types .= "s";
}

// Add date range filter
if (!empty($startDate)) {
    $sql .= " AND DATE >= ?";
    $params[] = $startDate;
    $types .= "s";
}
if (!empty($endDate)) {
    $sql .= " AND DATE <= ?";
    $params[] = $endDate;
    $types .= "s";
}

// Add total amount range filter
if ($minTotal !== null && $minTotal !== '') {
    $sql .= " AND TOTAL_PRICE_TTC >= ?";
    $params[] = $minTotal;
    $types .= "d";
}
if ($maxTotal !== null && $maxTotal !== '') {
    $sql .= " AND TOTAL_PRICE_TTC <= ?";
    $params[] = $maxTotal;
    $types .= "d";
}

$sql .= " ORDER BY DATE DESC";

// Get supplier invoices with filters
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$invoicesResult = $stmt->get_result();
$invoices = [];

if ($invoicesResult->num_rows > 0) {
    while($row = $invoicesResult->fetch_assoc()) {
        $invoices[] = $row;
    }
}
$stmt->close();

$title = "Factures du Fournisseur: " . $supplier['SUPPLIERNAME'];
include '../../includes/header.php';
?>

<style>
    .supplier-info {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .filters-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }
    
    .filter-group label {
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .filter-group input {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .date-range,
    .total-range {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .date-range input,
    .total-range input {
        flex: 1;
    }
    
    .filter-actions {
        display: flex;
        gap: 10px;
        margin-left: auto;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    table th, table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    table th {
        background-color: rgb(22, 106, 189);
    }
    
    .btn {
        padding: 8px 12px;
        border-radius: 4px;
        text-decoration: none;
        color: white;
        font-size: 14px;
        display: inline-block;
    }
    
    .btn-secondary {
        background-color: #6c757d;
    }
    
    .btn-info {
        background-color: #17a2b8;
    }
    
    .form-actions {
        margin-top: 20px;
    }
</style>

<h1>Factures du Fournisseur: <?php echo htmlspecialchars($supplier['SUPPLIERNAME']); ?></h1>

<div class="supplier-info">
    <p><strong>ICE:</strong> <?php echo htmlspecialchars($supplier['ICE'] ?? '-'); ?></p>
</div>

<div class="filters-container">
    <form action="" method="get">
        <input type="hidden" name="id" value="<?php echo $supplierId; ?>">
        
        <div class="filter-group">
            <label for="search">Recherche:</label>
            <input type="text" name="search" id="search" placeholder="Numéro de facture..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        
        <div class="filter-group">
            <label>Date:</label>
            <div class="date-range">
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                <span>à</span>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
        </div>
        
        <div class="filter-group">
            <label>Montant TTC:</label>
            <div class="total-range">
                <input type="number" name="min_total" placeholder="Min" step="0.01" min="0" value="<?php echo $minTotal !== null ? htmlspecialchars($minTotal) : ''; ?>">
                <span>à</span>
                <input type="number" name="max_total" placeholder="Max" step="0.01" min="0" value="<?php echo $maxTotal !== null ? htmlspecialchars($maxTotal) : ''; ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="view.php?id=<?php echo $supplierId; ?>" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<h2>Historique des Factures</h2>
<table>
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Date</th>
            <th>Total TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($invoices)): ?>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><?php echo htmlspecialchars($invoice['INVOICE_NUMBER']); ?></td>
                    <td><?php echo htmlspecialchars($invoice['DATE']); ?></td>
                    <td><?php echo number_format($invoice['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="../purchase/details.php?id=<?php echo $invoice['ID_INVOICE']; ?>" class="btn btn-info">Détails</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">Aucune facture trouvée pour ce fournisseur</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="form-actions">
    <a href="manage.php" class="btn btn-secondary">Retour à la liste des fournisseurs</a>
</div>

<?php include '../../includes/footer.php'; ?>