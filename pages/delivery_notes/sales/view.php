<?php
require_once '../../../includes/config.php';
require_once '../../../includes/functionsin.php';
requireLogin();

$title = "Bons de Livraison Vente";
include '../../../includes/headerin.php';

// Initialize filter variables
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$clientFilter = isset($_GET['client']) ? sanitizeInput($_GET['client']) : '';
$paymentFilter = isset($_GET['payment']) ? sanitizeInput($_GET['payment']) : '';
$minTotal = isset($_GET['min_total']) ? (float)$_GET['min_total'] : null;
$maxTotal = isset($_GET['max_total']) ? (float)$_GET['max_total'] : null;
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';



// Get all distinct clients for the filter dropdown
$clients = [];
$clientQuery = $conn->query("SELECT DISTINCT CLIENT_NAME FROM BON_LIVRAISON_VENTE_HEADER ORDER BY CLIENT_NAME");
if ($clientQuery->num_rows > 0) {
    while ($row = $clientQuery->fetch_assoc()) {
        $clients[] = $row['CLIENT_NAME'];
    }
}

// Get all payment methods
$paymentMethods = [];
$paymentQuery = $conn->query("SELECT DISTINCT PAYMENT_METHOD FROM BON_LIVRAISON_VENTE_HEADER WHERE PAYMENT_METHOD IS NOT NULL");
if ($paymentQuery->num_rows > 0) {
    while ($row = $paymentQuery->fetch_assoc()) {
        $paymentMethods[] = $row['PAYMENT_METHOD'];
    }
}

// Build the SQL query with filters
$sql = "SELECT * FROM BON_LIVRAISON_VENTE_HEADER WHERE 1=1";
$params = [];
$types = '';

// Debug SQL query building
$debugSql = $sql;

// Add search term filter
if (!empty($searchTerm)) {
    $sql .= " AND (CLIENT_NAME LIKE ? OR ID_BON LIKE ?)";
    $debugSql .= " AND (CLIENT_NAME LIKE '%$searchTerm%' OR ID_BON LIKE '%$searchTerm%')";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= 'ss';
}

// Add client filter
if (!empty($clientFilter)) {
    $sql .= " AND CLIENT_NAME = ?";
    $debugSql .= " AND CLIENT_NAME = '$clientFilter'";
    $params[] = $clientFilter;
    $types .= 's';
}

// Add payment method filter
if (!empty($paymentFilter)) {
    $sql .= " AND PAYMENT_METHOD = ?";
    $debugSql .= " AND PAYMENT_METHOD = '$paymentFilter'";
    $params[] = $paymentFilter;
    $types .= 's';
}

// Add total TTC range filter
// To this (only apply the filter if not both are 0):
    if (($minTotal !== null && $minTotal !== '') && !($minTotal == 0 && $maxTotal == 0)) {
        $sql .= " AND TOTAL_PRICE_TTC >= ?";
        $params[] = $minTotal;
        $types .= 'd';
    }
    if (($maxTotal !== null && $maxTotal !== '') && !($minTotal == 0 && $maxTotal == 0)) {
        $sql .= " AND TOTAL_PRICE_TTC <= ?";
        $params[] = $maxTotal;
        $types .= 'd';
    }

// Add date range filter
if (!empty($startDate)) {
    $sql .= " AND DATE >= ?";
    $debugSql .= " AND DATE >= '$startDate'";
    $params[] = $startDate;
    $types .= 's';
}
if (!empty($endDate)) {
    $sql .= " AND DATE <= ?";
    $debugSql .= " AND DATE <= '$endDate'";
    $params[] = $endDate;
    $types .= 's';
}



// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

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

// Check for PDF download
if (isset($_SESSION['delivery_pdf'])) {
    $pdfPath = $_SESSION['delivery_pdf'];
    unset($_SESSION['delivery_pdf']);

    // Force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
    readfile($pdfPath);
    exit();
}
?>

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
    
    .alert {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .btn {
        padding: 8px 12px;
        border-radius: 4px;
        text-decoration: none;
        color: white;
        font-size: 14px;
        display: inline-block;
        margin-right: 5px;
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
    
    .search-bar {
        margin-bottom: 20px;
    }
</style>

<h1>Bons de Livraison Vente</h1>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
<?php endif; ?>

<div class="filters-container">
    <form action="" method="get" class="filter-form">
        <!-- Search by client or delivery note number -->
        <div class="filter-group">
            <label for="search">Recherche:</label>
            <input type="text" name="search" id="search" placeholder="Client ou numéro..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        
        <!-- Client dropdown filter -->
        <div class="filter-group">
            <label for="client">Client:</label>
            <select name="client" id="client">
                <option value="">Tous les clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo htmlspecialchars($client); ?>" <?php echo $client === $clientFilter ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($client); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Payment method filter -->
        <div class="filter-group">
            <label for="payment">Méthode Paiement:</label>
            <select name="payment" id="payment">
                <option value="">Toutes méthodes</option>
                <?php foreach ($paymentMethods as $method): ?>
                    <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $method === $paymentFilter ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($method); ?>
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
                <input type="number" name="min_total" placeholder="Min" step="0.01" min="0" value="<?php echo $minTotal !== null ? htmlspecialchars($minTotal) : ''; ?>">
                <span>à</span>
                <input type="number" name="max_total" placeholder="Max" step="0.01" min="0" value="<?php echo $maxTotal !== null ? htmlspecialchars($maxTotal) : ''; ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="view.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<div class="search-bar">
    <a href="delivery_step1.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Nouveau BL de vente
    </a>
        <a href="../../Avoir_vente/credit_note_bl_view.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Avoir
    </a>
</div>

<table>
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Client</th>
            <th>ICE</th>
            <th>Méthode Paiement</th>
            <th>Date</th>
            <th>Montant TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['ID_BON']); ?></td>
                    <td><?php echo htmlspecialchars($row['CLIENT_NAME']); ?></td>
                    <td><?php echo htmlspecialchars($row['COMPANY_ICE'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['PAYMENT_METHOD']); ?></td>
                    <td><?php echo htmlspecialchars($row['DATE']); ?></td>
                    <td><?php echo number_format($row['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="details.php?id=<?php echo $row['ID']; ?>" class="btn btn-info">Détails</a>
                        <?php if (isAdmin()): ?>
                            <a href="delete.php?id=<?php echo $row['ID']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce bon de livraison?');">Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7">Aucun bon de livraison trouvé</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../../../includes/footerin.php'; ?>