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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username)) {
        $_SESSION['error'] = "Le nom d'utilisateur ne peut pas être vide";
    } else {
        // Check if username exists for another user
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $_SESSION['error'] = "Ce nom d'utilisateur est déjà pris";
        } else {
            // Update user
            if (!empty($password)) {
                // Update with password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET username = ?, password = ?, is_admin = ? WHERE id = ?");
                $update->bind_param("ssii", $username, $hashed_password, $is_admin, $user_id);
            } else {
                // Update without password
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

$title = "Modifier l'utilisateur";
include '../../includes/header.php';
?>

<div class="settings-container">
    <h1>Modifier l'utilisateur</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
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
</div>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.user-form {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 8px;
    box-shadow: var(--card-shadow);
}

.form-group {
    margin-bottom: 15px;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.btn-secondary {
    background-color: #7f8c8d;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    background-color: #6c7a7d;
}
</style>

<?php include '../../includes/footer.php'; ?>