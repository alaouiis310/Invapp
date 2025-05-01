<?php
// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    redirect('../login.php');
}
?>

<nav class="sidebar">
    <div class="logo">
        <img src="../assets/images/logo.png" alt="InvApp Logo">
        <h1>InvApp</h1>
    </div>
    <ul class="nav-links">
        <li><a href="../pages/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="../pages/purchase/view.php"><i class="fas fa-cart-plus"></i> Facture d'Achat</a></li>
        <li><a href="../pages/sales/view.php"><i class="fas fa-cart-arrow-down"></i> Facture de Vente</a></li>
        <li><a href="../pages/delivery_notes/purchase/view.php"><i class="fas fa-truck-loading"></i> Bons Livraison Achat</a></li>
        <li><a href="../pages/delivery_notes/sales/view.php"><i class="fas fa-shipping-fast"></i> Bons Livraison Vente</a></li>
        <li><a href="../pages/products/stock.php"><i class="fas fa-boxes"></i> Stock</a></li>
        <li><a href="../pages/suppliers/manage.php"><i class="fas fa-truck"></i> Fournisseurs</a></li>
        <li><a href="../pages/settings/index.php"><i class="fas fa-cog"></i> Paramètres</a></li>
        <li>
            <form action="" method="post" class="logout-form" style="display: flex; align-items: center; border: none; background: none; padding: 0; margin: 0;">
                <button type="submit" name="logout" style="display: flex; align-items: center; gap: 10px; background: none; border: none; color: inherit; font: inherit; cursor: pointer; width: 100%; padding: 10px 20px; text-align: left;">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </button>
            </form>
        </li>

    </ul>
</nav>