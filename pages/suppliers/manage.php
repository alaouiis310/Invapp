<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Gestion des Fournisseurs";
include '../../includes/header.php';

// Initialize filter variables
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sortField = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'SUPPLIERNAME';
$sortOrder = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'ASC';

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

// Get all suppliers with filters
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM BUY_INVOICE_HEADER WHERE SUPPLIER_NAME = s.SUPPLIERNAME) as invoice_count
        FROM SUPPLIER s WHERE 1=1";
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $sql .= " AND (s.SUPPLIERNAME LIKE ? OR s.ICE LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

// Validate sort field and order
$validSortFields = ['SUPPLIERNAME', 'ICE', 'invoice_count'];
$sortField = in_array($sortField, $validSortFields) ? $sortField : 'SUPPLIERNAME';
$sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';

$sql .= " ORDER BY $sortField $sortOrder";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}
$stmt->close();
?>

<style>
    .supplier-management {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .supplier-form {
        flex: 1;
        min-width: 300px;
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .suppliers-list {
        flex: 2;
        min-width: 300px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-group input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .form-actions {
        margin-top: 15px;
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
        position: relative;
    }

    table th a {
        color: white;
        text-decoration: none;
    }


    
    .sortable {
        cursor: pointer;
        padding-right: 20px;
    }
    
    .sortable:after {
        content: '';
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
    }
    
    .sortable.asc:after {
        border-bottom: 5px solid #333;
    }
    
    .sortable.desc:after {
        border-top: 5px solid #333;
    }
    
    .search-bar {
        margin-bottom: 20px;
    }
    
    .search-bar form {
        display: flex;
        gap: 10px;
    }
    
    .search-bar input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .alert {
        padding: 10px;
        margin-bottom: 20px;
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
    
    .btn-info {
        background-color: #17a2b8;
    }
    
    .btn-danger {
        background-color: #dc3545;
    }
</style>

<h1>Gestion des Fournisseurs</h1>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
<?php endif; ?>

<div class="supplier-management">
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
        <div class="search-bar">
            <form action="" method="get">
                <input type="text" name="search" placeholder="Rechercher par nom ou ICE..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit" class="btn btn-primary">Rechercher</button>
                <?php if (!empty($searchTerm)): ?>
                    <a href="manage.php" class="btn btn-secondary">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>
        
        <h2>Liste des Fournisseurs</h2>
        <table>
            <thead>
                <tr>
                    <th class="sortable <?php echo $sortField === 'SUPPLIERNAME' ? strtolower($sortOrder) : ''; ?>">
                        <a href="?search=<?php echo urlencode($searchTerm); ?>&sort=SUPPLIERNAME&order=<?php echo $sortField === 'SUPPLIERNAME' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>">
                            Nom
                        </a>
                    </th>
                    <th class="sortable <?php echo $sortField === 'ICE' ? strtolower($sortOrder) : ''; ?>">
                        <a href="?search=<?php echo urlencode($searchTerm); ?>&sort=ICE&order=<?php echo $sortField === 'ICE' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>">
                            ICE
                        </a>
                    </th>
                    <th class="sortable <?php echo $sortField === 'invoice_count' ? strtolower($sortOrder) : ''; ?>">
                        <a href="?search=<?php echo urlencode($searchTerm); ?>&sort=invoice_count&order=<?php echo $sortField === 'invoice_count' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>">
                            Factures
                        </a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supplier['SUPPLIERNAME']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['ICE'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['invoice_count']); ?></td>
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
                        <td colspan="4">Aucun fournisseur trouvé</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>