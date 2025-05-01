<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

$title = "Liste des Devis";
include '../../includes/header.php';

// Get all quotes
$sql = "SELECT * FROM DEVIS_HEADER ORDER BY DATE DESC";
$result = $conn->query($sql);
$quotes = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $quotes[] = $row;
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

// Check for PDF download
if (isset($_SESSION['quote_pdf'])) {
    $pdfPath = $_SESSION['quote_pdf'];
    unset($_SESSION['quote_pdf']);
    
    // Force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
    readfile($pdfPath);
    exit();
}
?>

<h1>Liste des Devis</h1>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success"><?php echo $successMessage; ?></div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Numéro</th>
            <th>Client</th>
            <th>ICE</th>
            <th>Date</th>
            <th>Montant TTC</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($quotes)): ?>
            <?php foreach ($quotes as $quote): ?>
                <tr>
                    <td><?php echo $quote['DEVIS_NUMBER']; ?></td>
                    <td><?php echo $quote['CLIENT_NAME']; ?></td>
                    <td><?php echo $quote['COMPANY_ICE'] ?? '-'; ?></td>
                    <td><?php echo $quote['DATE']; ?></td>
                    <td><?php echo number_format($quote['TOTAL_PRICE_TTC'], 2); ?> DH</td>
                    <td>
                        <a href="details.php?id=<?php echo $quote['ID']; ?>" class="btn btn-info">Détails</a>
                        <?php if (isAdmin()): ?>
                            <a href="delete.php?id=<?php echo $quote['ID']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce devis?');">Supprimer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Aucun devis trouvé</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="form-actions">
    <a href="create.php" class="btn btn-primary">Créer un Nouveau Devis</a>
</div>

<?php include '../../includes/footer.php'; ?>