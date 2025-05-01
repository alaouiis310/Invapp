<?php
require_once 'includes/config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create default admin if no users exist
$result = $conn->query("SELECT id FROM users LIMIT 1");
if ($result->num_rows === 0) {
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, is_admin) VALUES ('admin', '$default_password', 1)");
    $first_run = true;
}
$result->close();

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $errorMessage = "Veuillez remplir tous les champs!";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // SECURITY: Regenerate session ID to prevent session fixation
                session_regenerate_id(true);  // <-- ADD THIS LINE
                
                // Then set your session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Redirect to dashboard
                header('Location: index.php');
                exit();
            } else {
                $errorMessage = "Nom d'utilisateur ou mot de passe incorrect!";
            }
        } else {
            $errorMessage = "Nom d'utilisateur ou mot de passe incorrect!";
        }
        
        $stmt->close();
    }
}

$title = "Connexion";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - InvApp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>InvApp</h1>
            <h2>Connexion</h2>
            
            <?php if (isset($first_run)): ?>
                <div class="alert alert-info">
                    <strong>Première utilisation:</strong> Un compte admin a été créé.<br>
                    <strong>Identifiants:</strong> admin / admin123
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur:</label>
                    <input type="text" name="username" id="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <input type="password" name="password" id="password" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Se Connecter</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>