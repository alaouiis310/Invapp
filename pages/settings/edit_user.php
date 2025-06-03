<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch user data
$stmt = $conn->prepare("SELECT id, username, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "Utilisateur introuvable";
    redirect('settings.php');
}

// Initialize visibility settings with defaults
$visibilitySettings = [
    'stock_visibility_days' => 365,
    'sales_visibility_days' => 180,
    'sales_min_ttc' => 0,
    'product_types' => 'Facture,Stock,BL',
    'invoice_stock_visibility_days' => 30,
    'delivery_stock_visibility_days' => 30,
    'delivery_sales_visibility_days' => 30,
    'delivery_sales_min_ttc' => 0
];

// Load actual settings for non-admin users
if (!$user['is_admin']) {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $visibilitySettings)) {
            $visibilitySettings[$row['setting_key']] = $row['setting_value'];
        }
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // User info update
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Handle visibility settings update
    if (isset($_POST['save_visibility_settings']) && !$user['is_admin']) {
        $stockDays = isset($_POST['stock_visibility_days']) ? (int)$_POST['stock_visibility_days'] : 365;
        $salesDays = isset($_POST['sales_visibility_days']) ? (int)$_POST['sales_visibility_days'] : 180;
        $salesMinTTC = isset($_POST['sales_min_ttc']) ? (float)$_POST['sales_min_ttc'] : 0;
        $productTypes = isset($_POST['product_types']) ? implode(',', $_POST['product_types']) : '';
        $invoiceStockDays = isset($_POST['invoice_stock_visibility_days']) ? (int)$_POST['invoice_stock_visibility_days'] : 30;
        $deliveryStockDays = isset($_POST['delivery_stock_visibility_days']) ? (int)$_POST['delivery_stock_visibility_days'] : 30;
        $deliverySalesDays = isset($_POST['delivery_sales_visibility_days']) ? (int)$_POST['delivery_sales_visibility_days'] : 30;
        $deliverySalesMinTTC = isset($_POST['delivery_sales_min_ttc']) ? (float)$_POST['delivery_sales_min_ttc'] : 0;
        
        $conn->begin_transaction();
        try {
            // Clear existing settings
            $delete = $conn->prepare("DELETE FROM user_settings WHERE user_id = ?");
            $delete->bind_param("i", $user_id);
            $delete->execute();
            $delete->close();
            
            // Insert updated settings
            $insert = $conn->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
            
            $settingsToSave = [
                'stock_visibility_days' => $stockDays,
                'sales_visibility_days' => $salesDays,
                'sales_min_ttc' => $salesMinTTC,
                'product_types' => $productTypes,
                'invoice_stock_visibility_days' => $invoiceStockDays,
                'delivery_stock_visibility_days' => $deliveryStockDays,
                'delivery_sales_visibility_days' => $deliverySalesDays,
                'delivery_sales_min_ttc' => $deliverySalesMinTTC
            ];
            
            foreach ($settingsToSave as $key => $value) {
                $insert->bind_param("iss", $user_id, $key, $value);
                $insert->execute();
            }
            
            $insert->close();
            $conn->commit();
            $_SESSION['success'] = "Paramètres de visibilité mis à jour avec succès!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
    }
    // Handle user info update
    elseif (!isset($_POST['save_visibility_settings'])) {
        if (empty($username)) {
            $_SESSION['error'] = "Le nom d'utilisateur ne peut pas être vide";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $username, $user_id);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $_SESSION['error'] = "Ce nom d'utilisateur est déjà pris";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET username = ?, password = ?, is_admin = ? WHERE id = ?");
                    $update->bind_param("ssii", $username, $hashed_password, $is_admin, $user_id);
                } else {
                    $update = $conn->prepare("UPDATE users SET username = ?, is_admin = ? WHERE id = ?");
                    $update->bind_param("sii", $username, $is_admin, $user_id);
                }
                
                if ($update->execute()) {
                    $_SESSION['success'] = "Utilisateur modifié avec succès!";
                    $update->close();
                    redirect('index.php');
                } else {
                    $_SESSION['error'] = "Erreur lors de la modification: " . $conn->error;
                }
            }
            $check->close();
        }
    }
}

$title = "Modifier l'utilisateur";
include '../../includes/header.php';
?>

<div class="settings-container">
    <h1>Modifier l'utilisateur</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <form action="" method="post" class="user-form">
        <div class="form-group">
            <label for="username">Nom d'utilisateur:</label>
            <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        
        <div class="form-group checkbox-group">
            <input type="checkbox" name="is_admin" id="is_admin" <?= $user['is_admin'] ? 'checked' : '' ?>>
            <label for="is_admin">Administrateur</label>
        </div>
        
        <div class="form-group">
            <label for="password">Nouveau mot de passe (laisser vide pour ne pas changer):</label>
            <input type="password" name="password" id="password" minlength="8">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>

    <?php if (!$user['is_admin']): ?>
    <div class="settings-section">
        <h2>Contrôles de visibilité</h2>
        <form action="" method="post">
            <div class="form-group">
                <label for="stock_visibility_days">Visibilité du stock (jours):</label>
                <input type="number" name="stock_visibility_days" id="stock_visibility_days" min="1" 
                    value="<?= $visibilitySettings['stock_visibility_days'] ?>" required>
                <small>Nombre de jours d'historique de stock visible</small>
            </div>

            <div class="form-group">
                <label for="invoice_stock_visibility_days">Visibilité du stock pour factures (jours):</label>
                <input type="number" name="invoice_stock_visibility_days" id="invoice_stock_visibility_days" min="1" 
                    value="<?= $visibilitySettings['invoice_stock_visibility_days'] ?>" required>
                <small>Historique de stock visible lors de la création de factures</small>
            </div>

            <div class="form-group">
                <label for="delivery_stock_visibility_days">Visibilité du stock pour BL (jours):</label>
                <input type="number" name="delivery_stock_visibility_days" id="delivery_stock_visibility_days" min="1" 
                    value="<?= $visibilitySettings['delivery_stock_visibility_days'] ?>" required>
                <small>Historique de stock visible lors de la création de bons de livraison</small>
            </div>

            <div class="form-group">
                <label for="sales_visibility_days">Visibilité des factures de vente (jours):</label>
                <input type="number" name="sales_visibility_days" id="sales_visibility_days" min="1" 
                    value="<?= $visibilitySettings['sales_visibility_days'] ?>" required>
                <small>Nombre de jours d'historique des ventes visible</small>
            </div>

            <div class="form-group">
                <label for="sales_min_ttc">Montant minimum des factures (TTC):</label>
                <input type="number" name="sales_min_ttc" id="sales_min_ttc" min="0" step="0.01"
                    value="<?= $visibilitySettings['sales_min_ttc'] ?>" required>
                <small>Montant minimum pour qu'une facture soit visible</small>
            </div>

            <div class="form-group">
                <label for="delivery_sales_visibility_days">Visibilité des BL de vente (jours):</label>
                <input type="number" name="delivery_sales_visibility_days" id="delivery_sales_visibility_days" min="1" 
                    value="<?= $visibilitySettings['delivery_sales_visibility_days'] ?>" required>
                <small>Nombre de jours d'historique des BL visible</small>
            </div>

            <div class="form-group">
                <label for="delivery_sales_min_ttc">Montant minimum des BL (TTC):</label>
                <input type="number" name="delivery_sales_min_ttc" id="delivery_sales_min_ttc" min="0" step="0.01"
                    value="<?= $visibilitySettings['delivery_sales_min_ttc'] ?>" required>
                <small>Montant minimum pour qu'un BL soit visible</small>
            </div>

            <div class="form-group checkbox-group">
                <label>Types de produits visibles:</label><br>
                <?php 
                $visibleTypes = explode(',', $visibilitySettings['product_types']);
                $allTypes = ['Facture', 'Stock', 'BL'];
                
                foreach ($allTypes as $type): ?>
                    <input type="checkbox" name="product_types[]" id="type_<?= $type ?>" 
                        value="<?= $type ?>" <?= in_array($type, $visibleTypes) ? 'checked' : '' ?>>
                    <label for="type_<?= $type ?>"><?= $type ?></label><br>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_visibility_settings" class="btn btn-primary">Enregistrer les paramètres</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.user-form, .settings-section {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input[type="number"],
.form-group input[type="text"],
.form-group input[type="password"],
.form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.checkbox-group {
    margin-top: 10px;
}

.checkbox-group label {
    display: inline;
    font-weight: normal;
    margin-left: 5px;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 15px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0069d9;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

.alert {
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

small {
    display: block;
    margin-top: 5px;
    font-size: 0.8em;
    color: #6c757d;
}
</style>

<?php include '../../includes/footer.php'; ?>