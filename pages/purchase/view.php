<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Factures d'Achat";
include '../../includes/header.php';

// Initialize filter variables
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$supplierFilter = isset($_GET['supplier']) ? sanitizeInput($_GET['supplier']) : '';
$minTotal = isset($_GET['min_total']) ? (float)$_GET['min_total'] : null;
$maxTotal = isset($_GET['max_total']) ? (float)$_GET['max_total'] : null;
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';

// Get all distinct suppliers for the filter dropdown
$suppliers = [];
$supplierQuery = $conn->query("SELECT DISTINCT SUPPLIER_NAME FROM BUY_INVOICE_HEADER ORDER BY SUPPLIER_NAME");
if ($supplierQuery->num_rows > 0) {
    while ($row = $supplierQuery->fetch_assoc()) {
        $suppliers[] = $row['SUPPLIER_NAME'];
    }
}

// Build the SQL query with filters
$sql = "SELECT * FROM BUY_INVOICE_HEADER WHERE 1=1";
$params = [];
$types = '';

// Add search term filter
if (!empty($searchTerm)) {
    $sql .= " AND (SUPPLIER_NAME LIKE ? OR INVOICE_NUMBER LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= 'ss';
}

// Add supplier filter
if (!empty($supplierFilter)) {
    $sql .= " AND SUPPLIER_NAME = ?";
    $params[] = $supplierFilter;
    $types .= 's';
}

// Add total TTC range filter
if (!empty($minTotal) || $minTotal === 0) {
    $sql .= " AND TOTAL_PRICE_TTC >= ?";
    $params[] = $minTotal;
    $types .= 'd';
}
if (!empty($maxTotal) || $maxTotal === 0) {
    $sql .= " AND TOTAL_PRICE_TTC <= ?";
    $params[] = $maxTotal;
    $types .= 'd';
}

// Add date range filter
if (!empty($startDate)) {
    $sql .= " AND DATE >= ?";
    $params[] = $startDate;
    $types .= 's';
}
if (!empty($endDate)) {
    $sql .= " AND DATE <= ?";
    $params[] = $endDate;
    $types .= 's';
}

// Debug output
error_log("SQL: $sql");
error_log("Params: " . print_r($params, true));
error_log("Types: $types");

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Check for errors
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Debugging: Uncomment to see the generated SQL
//echo "SQL: $sql<br>";
//echo "Params: "; print_r($params);
?>

<h1>Factures d'Achat</h1>

<div class="filters-container">
    <form action="" method="get" class="filter-form">
        <!-- Search by supplier or invoice number -->
        <div class="filter-group">
            <label for="search">Recherche:</label>
            <input type="text" name="search" id="search" placeholder="Fournisseur ou numéro..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        
        <!-- Supplier dropdown filter -->
        <div class="filter-group">
            <label for="supplier">Fournisseur:</label>
            <select name="supplier" id="supplier">
                <option value="">Tous les fournisseurs</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo htmlspecialchars($supplier); ?>" <?php echo $supplier === $supplierFilter ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Date range filter -->
        <div class="filter-group">
            <label>Date:</label>
            <div class="date-range">
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                <span>à</span>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
        </div>
        
        <!-- Total TTC range filter -->
        <div class="filter-group">
            <label>Montant TTC:</label>
            <div class="total-range">
                <input type="number" name="min_total" placeholder="Min" step="0.01" min="0" value="<?php echo $minTotal !== null && $minTotal !== '' ? htmlspecialchars($minTotal) : ''; ?>">
                <span>à</span>
                <input type="number" name="max_total" placeholder="Max" step="0.01" min="0" value="<?php echo $maxTotal !== null && $maxTotal !== '' ? htmlspecialchars($maxTotal) : ''; ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="view.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<div class="action-buttons">
    <a href="add_step1.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Nouvelle Facture d'achat
    </a>
    <a href="../Avoir_achat/credit_note_view.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Avoir
    </a>
</div>

<table>
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Fournisseur</th>
            <th>Date</th>
            <th>Montant TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['INVOICE_NUMBER']); ?></td>
                    <td><?php echo htmlspecialchars($row['SUPPLIER_NAME']); ?></td>
                    <td><?php echo htmlspecialchars($row['DATE']); ?></td>
                    <td><?php echo number_format($row['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="details.php?id=<?php echo $row['ID_INVOICE']; ?>" class="btn btn-info">Détails</a>
                        <?php if (isAdmin()): ?>
                            <a href="delete.php?id=<?php echo $row['ID_INVOICE']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette facture?');">Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">Aucune facture d'achat trouvée</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<style>
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
    
    .filter-group input,
    .filter-group select {
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
    
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
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
    }
    
    .btn-primary {
        background-color: #007bff;
    }
    
    .btn-secondary {
        background-color: #6c757d;
    }
    
    .btn-success {
        background-color: #28a745;
    }
    
    .btn-info {
        background-color: #17a2b8;
    }
    
    .btn-danger {
        background-color: #dc3545;
    }
    
    .btn:hover {
        opacity: 0.9;
    }
<?php include '../../includes/footer.php'; ?>