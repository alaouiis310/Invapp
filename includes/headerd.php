<!DOCTYPE html>
<html lang="fr" data-theme="<?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - InvApp</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/icon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // Initialize theme from session/localStorage
        document.documentElement.setAttribute('data-theme', 
            localStorage.getItem('darkMode') === 'true' || 
            (<?php echo isset($_SESSION['dark_mode']) ? 'true' : 'false'; ?> && <?php echo $_SESSION['dark_mode'] ? 'true' : 'false'; ?>) ? 'dark' : 'light'
        );
    </script>
</head>
<body>
    <div class="container">
        <?php include 'navigationd.php'; ?>
        <main class="content">