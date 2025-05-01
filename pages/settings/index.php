<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Only allow admin to access settings
if (!isAdmin()) {
    // Debug output
    error_log("Admin access denied. Session data: " . print_r($_SESSION, true));
    redirect('../dashboard.php');
}


$minTVA = 13;
$maxTVA = 27;

$result = $conn->query("SELECT SETTING_VALUE FROM APP_SETTINGS WHERE SETTING_KEY = 'MIN_TVA_RATE'");
if ($result->num_rows > 0) {
    $minTVA = (float)$result->fetch_assoc()['SETTING_VALUE'];
}

$result = $conn->query("SELECT SETTING_VALUE FROM APP_SETTINGS WHERE SETTING_KEY = 'MAX_TVA_RATE'");
if ($result->num_rows > 0) {
    $maxTVA = (float)$result->fetch_assoc()['SETTING_VALUE'];
}


// Handle theme toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    redirect('../../login.php');
}

// Handle data export


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_data'])) {
    // Get all data tables
    $tables = [
        'users', 'APP_SETTINGS', 'CATEGORY', 'SUPPLIER', 'CLIENT', 'PRODUCT',
        'BUY_INVOICE_HEADER', 'BUY_INVOICE_DETAILS',
        'SELL_INVOICE_HEADER', 'SELL_INVOICE_DETAILS',
        'DEVIS_HEADER', 'DEVIS_DETAILS',
        'BON_LIVRAISON_ACHAT_HEADER', 'BON_LIVRAISON_ACHAT_DETAILS',
        'BON_LIVRAISON_VENTE_HEADER', 'BON_LIVRAISON_VENTE_DETAILS'
    ];
    
    $backupData = [
        'tables' => [],
        'images' => []
    ];

    // Backup all table data
    $conn->set_charset("utf8mb4"); // très important

    foreach ($tables as $table) {
        $result = $conn->query("SELECT * FROM $table");
        if ($result) {
            $backupData['tables'][$table] = [];
            while ($row = $result->fetch_assoc()) {
                foreach ($row as $key => $value) {
                    if (is_string($value)) {
                        $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }
                }
                $backupData['tables'][$table][] = $row;
            }
        } else {
            $_SESSION['error'] = "Error fetching data from table $table: " . $conn->error;
            redirect('index.php');
        }
    }
    

    // Backup product images
    $products = $conn->query("SELECT ID, IMAGE FROM PRODUCT WHERE IMAGE IS NOT NULL");
    while ($product = $products->fetch_assoc()) {
        $backupData['images']['product_' . $product['ID']] = base64_encode($product['IMAGE']);
    }

    // Backup purchase invoice images
    $buyInvoices = $conn->query("SELECT ID_INVOICE, IMAGE FROM BUY_INVOICE_HEADER WHERE IMAGE IS NOT NULL");
    while ($invoice = $buyInvoices->fetch_assoc()) {
        $backupData['images']['buy_invoice_' . $invoice['ID_INVOICE']] = base64_encode($invoice['IMAGE']);
    }

    // Backup delivery note images (purchase)
    $deliveryNotes = $conn->query("SELECT ID_BON, IMAGE FROM BON_LIVRAISON_ACHAT_HEADER WHERE IMAGE IS NOT NULL");
    while ($note = $deliveryNotes->fetch_assoc()) {
        $backupData['images']['delivery_note_' . $note['ID_BON']] = base64_encode($note['IMAGE']);
    }


    // Create JSON file
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
    $fileContent = json_encode($backupData, JSON_PRETTY_PRINT);

    // Verify JSON encoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['error'] = "JSON encoding error: " . json_last_error_msg();
        redirect('index.php');
    }

    // Force download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $fileContent;
    exit();
}

// Handle data import
// Handle data import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_data']) && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "File upload error: " . $file['error'];
        redirect('index.php');
    }

    // Check file type
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($fileExt !== 'json') {
        $_SESSION['error'] = "Only JSON files are allowed";
        redirect('index.php');
    }

    // Read file content
    $fileContent = file_get_contents($file['tmp_name']);
    $backupData = json_decode($fileContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['error'] = "Invalid JSON file";
        redirect('index.php');
    }

    // Import data
    $conn->autocommit(FALSE); // Start transaction
    $success = true;

    try {
        // First import all table data
        foreach ($backupData['tables'] as $table => $records) {
            // Empty the table first
            $conn->query("DELETE FROM $table");

            // Insert records
            if (!empty($records)) {
                $columns = array_keys($records[0]);
                $columnsStr = implode(',', $columns);

                foreach ($records as $record) {
                    $values = array_map(function ($value) use ($conn) {
                        if (is_array($value)) {
                            return "'" . $conn->real_escape_string(json_encode($value)) . "'";
                        }
                        return "'" . $conn->real_escape_string($value) . "'";
                    }, array_values($record));

                    $valuesStr = implode(',', $values);
                    $sql = "INSERT INTO $table ($columnsStr) VALUES ($valuesStr)";

                    if (!$conn->query($sql)) {
                        throw new Exception("Error inserting into $table: " . $conn->error);
                    }
                }
            }
        }

        // Then restore all images - CORRECTED TO MATCH YOUR SCHEMA
        if (isset($backupData['images'])) {
            foreach ($backupData['images'] as $key => $imageData) {
                $parts = explode('_', $key);
                $type = $parts[0];
                $id = $parts[1];
                $imageBinary = base64_decode($imageData);

                switch ($type) {
                    case 'product':
                        $stmt = $conn->prepare("UPDATE PRODUCT SET IMAGE = ? WHERE ID = ?");
                        $stmt->bind_param("si", $null, $id);
                        break;
                    case 'buy':
                        $stmt = $conn->prepare("UPDATE BUY_INVOICE_HEADER SET IMAGE = ? WHERE ID_INVOICE = ?");
                        $stmt->bind_param("si", $null, $id);
                        break;
                    case 'delivery':
                        // Match your bon_livraison_achat_header table structure
                        $stmt = $conn->prepare("UPDATE BON_LIVRAISON_ACHAT_HEADER SET IMAGE = ? WHERE ID = ?");
                        $stmt->bind_param("si", $null, $id);
                        break;
                    case 'sell':
                        $stmt = $conn->prepare("UPDATE SELL_INVOICE_HEADER SET IMAGE = ? WHERE ID_INVOICE = ?");
                        $stmt->bind_param("si", $null, $id);
                        break;
                    default:
                        continue 2; // Skip unknown types
                }

                $null = null;
                $stmt->send_long_data(0, $imageBinary);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error restoring image for $type ID $id: " . $conn->error);
                }
                $stmt->close();
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Data and images imported successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Import failed: " . $e->getMessage();
    }

    $conn->autocommit(TRUE); // Restore autocommit
    redirect('index.php');
}

// Handle app reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_app'])) {
    $tables = [
        'BUY_INVOICE_HEADER',
        'BUY_INVOICE_DETAILS',
        'SELL_INVOICE_HEADER',
        'SELL_INVOICE_DETAILS',
        'DEVIS_HEADER',
        'DEVIS_DETAILS',
        'CLIENT',
        'SUPPLIER',
        'CATEGORY'
    ];

    $conn->autocommit(FALSE); // Start transaction
    $success = true;

    try {
        foreach ($tables as $table) {
            if (!$conn->query("DELETE FROM $table")) {
                throw new Exception("Error resetting table $table: " . $conn->error);
            }
        }
        $conn->commit();
        $_SESSION['success'] = "Application data reset successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Reset failed: " . $e->getMessage();
    }

    $conn->autocommit(TRUE); // Restore autocommit
    redirect('index.php');
}

// Handle user addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['new_username']);
    $password = $_POST['new_password'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Validate inputs
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Veuillez remplir tous les champs!";
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = "Le mot de passe doit contenir au moins 8 caractères";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "Ce nom d'utilisateur existe déjà";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $insert = $conn->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
            $insert->bind_param("ssi", $username, $hashed_password, $is_admin);

            if ($insert->execute()) {
                $_SESSION['success'] = "Utilisateur ajouté avec succès!";
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout de l'utilisateur: " . $conn->error;
            }
            $insert->close();
        }
        $stmt->close();
    }
    redirect('index.php');
}

// Handle user edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $new_username = trim($_POST['edit_username']);
    $is_admin = isset($_POST['edit_is_admin']) ? 1 : 0;

    // Validate inputs
    if (empty($new_username)) {
        $_SESSION['error'] = "Le nom d'utilisateur ne peut pas être vide";
    } else {
        // Check if username exists for another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "Ce nom d'utilisateur est déjà pris";
        } else {
            // Update user
            $update = $conn->prepare("UPDATE users SET username = ?, is_admin = ? WHERE id = ?");
            $update->bind_param("sii", $new_username, $is_admin, $user_id);

            if ($update->execute()) {
                $_SESSION['success'] = "Utilisateur modifié avec succès!";
            } else {
                $_SESSION['error'] = "Erreur lors de la modification: " . $conn->error;
            }
            $update->close();
        }
        $stmt->close();
    }
    redirect('index.php');
}


// Add this to the POST handling section:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tva_settings'])) {
    $minTVA = (float)$_POST['min_tva'];
    $maxTVA = (float)$_POST['max_tva'];
    
    if ($minTVA >= 0 && $maxTVA > $minTVA && $maxTVA <= 100) {
        $stmt = $conn->prepare("INSERT INTO APP_SETTINGS (SETTING_KEY, SETTING_VALUE) VALUES 
                              ('MIN_TVA_RATE', ?), ('MAX_TVA_RATE', ?)
                              ON DUPLICATE KEY UPDATE SETTING_VALUE = VALUES(SETTING_VALUE)");
        $stmt->bind_param("dd", $minTVA, $maxTVA);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Paramètres TVA mis à jour avec succès!";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Valeurs TVA invalides (min doit être < max et entre 0-100)";
    }
    redirect('index.php');
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_user_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];

    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Le mot de passe doit contenir au moins 8 caractères";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed_password, $user_id);

        if ($update->execute()) {
            $_SESSION['success'] = "Mot de passe modifié avec succès!";
        } else {
            $_SESSION['error'] = "Erreur lors du changement de mot de passe: " . $conn->error;
        }
        $update->close();
    }
    redirect('index.php');
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];

    // Prevent deleting yourself
    if ($user_id === $_SESSION['user_id']) {
        $_SESSION['error'] = "Vous ne pouvez pas supprimer votre propre compte";
    } else {
        $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete->bind_param("i", $user_id);

        if ($delete->execute()) {
            $_SESSION['success'] = "Utilisateur supprimé avec succès!";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression: " . $conn->error;
        }
        $delete->close();
    }
    redirect('index.php');
}

$title = "Paramètres";
include '../../includes/header.php';

// Display success/error messages
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>

<!-- REST OF YOUR HTML REMAINS THE SAME -->

<h1>Paramètres</h1>

<div class="settings-sections">
    <div class="settings-section">
        <h2>Sauvegarde des Données</h2>
        <form action="" method="post" class="backup-form">
            <div class="form-group">
                <p>Sauvegarder toutes les données de l'application dans un fichier JSON.</p>
                <button type="submit" name="export_data" class="btn btn-backup">
                    <i class="fas fa-download"></i> Exporter les Données
                </button>
            </div>
        </form>

        <form action="" method="post" enctype="multipart/form-data" class="restore-form">
            <div class="form-group">
                <p>Restaurer les données à partir d'un fichier de sauvegarde.</p>
                <input type="file" name="backup_file" id="backup_file" accept=".json" required>
                <button type="submit" name="import_data" class="btn btn-restore">
                    <i class="fas fa-upload"></i> Importer les Données
                </button>
            </div>
        </form>

        <form action="" method="post" class="reset-form" onsubmit="return confirm('⚠️ ATTENTION! Ceci effacera TOUTES les données. Continuer?');">
            <div class="form-group">
                <p>Réinitialiser complètement l'application (supprime toutes les données)</p>
                <button type="submit" name="reset_app" class="btn btn-reset">
                    <i class="fas fa-trash-alt"></i> Réinitialiser l'Application
                </button>
            </div>
        </form>
    </div>

    <div class="settings-section">
        <h2>Paramètres Administrateur</h2>
        <form action="" method="post">
            <div class="form-group">
                <label for="new_password">Nouveau Mot de Passe:</label>
                <input type="password" name="new_password" id="new_password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmer le Mot de Passe:</label>
                <input type="password" name="confirm_password" id="confirm_password">
            </div>

            <div class="form-actions">
                <button type="submit" name="change_password" class="btn btn-primary">Changer le Mot de Passe</button>
            </div>
        </form>

        <!-- Add logout button here -->
        <form action="" method="post" class="logout-form">
            <button type="submit" name="logout" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </button>
        </form>
    </div>

    <div class="settings-section">
        <h2>Gestion des Utilisateurs</h2>
        <form action="" method="post" class="user-form">
            <div class="form-group">
                <label for="new_username">Nouvel Utilisateur:</label>
                <input type="text" name="new_username" id="new_username" required>
            </div>

            <div class="form-group">
                <label for="new_password">Mot de Passe:</label>
                <input type="password" name="new_password" id="new_password" required minlength="8">
                <small>Minimum 8 caractères</small>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_admin" id="is_admin">
                <label for="is_admin">Administrateur</label>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Ajouter Utilisateur
                </button>
            </div>
        </form>

        <!-- User list table -->
        <div class="user-list">
            <h3>Utilisateurs Existants</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Rôle</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $conn->query("SELECT id, username, is_admin, created_at FROM users ORDER BY created_at DESC");
                    while ($user = $users->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= $user['is_admin'] ? 'Admin' : 'Utilisateur' ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                            <td class="actions">
                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <form action="" method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="settings-section">
            <h2>Paramètres TVA</h2>
            <form action="" method="post">
                <div class="form-group">
                    <label for="min_tva">TVA Minimum (%):</label>
                    <input type="number" name="min_tva" id="min_tva" step="0.1" min="0" max="100"
                        value="<?php echo $minTVA; ?>" required>
                </div>

                <div class="form-group">
                    <label for="max_tva">TVA Maximum (%):</label>
                    <input type="number" name="max_tva" id="max_tva" step="0.1" min="0" max="100"
                        value="<?php echo $maxTVA; ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" name="save_tva_settings" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h3>Modifier l'Utilisateur</h3>
                <form action="" method="post">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="form-group">
                        <label for="edit_username">Nom d'utilisateur:</label>
                        <input type="text" name="edit_username" id="edit_username" required>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="edit_is_admin" id="edit_is_admin">
                        <label for="edit_is_admin">Administrateur</label>
                    </div>

                    <div class="form-group">
                        <label for="edit_password">Nouveau mot de passe (laisser vide pour ne pas changer):</label>
                        <input type="password" name="new_password" id="edit_password" minlength="8">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="edit_user" class="btn btn-primary">Enregistrer</button>
                        <button type="submit" name="change_user_password" class="btn btn-secondary">Changer seulement le mot de passe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="settings-section">
        <h2>Apparence</h2>
        <form action="" method="post">
            <div class="form-group">
                <label>Thème:</label>
                <div class="theme-toggle">
                    <button type="submit" name="toggle_theme" class="btn btn-theme-toggle">
                        <i class="fas fa-moon"></i> Mode <?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'Clair' : 'Sombre'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    /* User Actions */
    .actions {
        display: flex;
        gap: 8px;
    }

    .btn-edit,
    .btn-delete {
        padding: 6px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-edit {
        background-color: #3498db;
        color: white;
    }

    .btn-delete {
        background-color: #e74c3c;
        color: white;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: var(--card-bg);
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 500px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: var(--text-color);
    }

    .btn-secondary {
        background-color: #7f8c8d;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        border: none;
        cursor: pointer;
    }

    .btn-secondary:hover {
        background-color: #6c7a7d;
    }

    .user-form {
        margin-bottom: 30px;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 15px 0;
    }

    .checkbox-group input[type="checkbox"] {
        width: auto;
    }

    .user-list table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .user-list th,
    .user-list td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .user-list th {
        background-color: var(--primary-color);
        color: white;
    }

    .user-list tr:nth-child(even) {
        background-color: rgba(0, 0, 0, 0.05);
    }

    [data-theme="dark"] .user-list tr:nth-child(even) {
        background-color: rgba(255, 255, 255, 0.05);
    }

    small {
        display: block;
        margin-top: 5px;
        font-size: 0.8em;
        color: #666;
    }

    [data-theme="dark"] small {
        color: #aaa;
    }

    .btn-reset {
        background: #e74c3c;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-reset:hover {
        background: #c0392b;
    }

    .reset-form {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }

    .settings-section {
        background: var(--card-bg);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: var(--card-shadow);
    }

    .btn-theme-toggle {
        background: var(--primary-color);
        color: var(--text-color);
        padding: 10px 15px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-backup {
        background: #2ecc71;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-restore {
        background: #f39c12;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-logout {
        background: #e74c3c;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 20px;
    }

    .btn-logout:hover {
        background: #c0392b;
    }

    .theme-toggle {
        margin: 15px 0;
    }

    .backup-form,
    .restore-form {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .logout-form {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }

    .alert {
        padding: 15px;
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

    :root {
        --primary-color: #3498db;
        --text-color: #333;
        --bg-color: #f5f5f5;
        --card-bg: #fff;
        --card-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        --border-color: #ddd;
    }

    [data-theme="dark"] {
        --primary-color: #2980b9;
        --text-color: #f5f5f5;
        --bg-color: #121212;
        --card-bg: #1e1e1e;
        --card-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        --border-color: #444;
    }

    body {
        background: var(--bg-color);
        color: var(--text-color);
        transition: background 0.3s, color 0.3s;
    }

    input[type="file"] {
        margin: 10px 0;
        display: block;
        color: var(--text-color);
    }
</style>

<script>
    // Apply theme on page load
    document.addEventListener('DOMContentLoaded', function() {
        const darkMode = <?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'true' : 'false'; ?>;
        if (darkMode) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    });

    // Toggle icon on button
    const themeButton = document.querySelector('.btn-theme-toggle');
    if (themeButton) {
        themeButton.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-moon')) {
                icon.classList.replace('fa-moon', 'fa-sun');
            } else {
                icon.classList.replace('fa-sun', 'fa-moon');
            }
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>