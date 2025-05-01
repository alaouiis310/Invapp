<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireLogin(); // This will redirect to login if not authenticated

$title = "Tableau de Bord";
include '../includes/headerd.php';
?>

<h1>Tableau de Bord</h1>

<div class="dashboard-stats">
    <div class="stat-card">
        <h3>Produits en Stock</h3>
        <?php
        $sql = "SELECT SUM(QUANTITY) as total FROM PRODUCT";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        echo "<p>" . ($row['total'] ?? 0) . "</p>";
        ?>
    </div>

    <?php
    if (isAdmin()) {
    ?>
        <div class="stat-card">
            <h3>Valeur du Stock</h3>
            <?php
            $sql = "SELECT SUM(QUANTITY * PRICE) as total FROM PRODUCT";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            echo "<p>" . number_format($row['total'] ?? 0, 2) . " DH</p>";
            ?>
        </div>
    <?php
    }
    ?>


    <div class="stat-card">
        <h3>Fournisseurs</h3>
        <?php
        $sql = "SELECT COUNT(*) as total FROM SUPPLIER";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        echo "<p>" . ($row['total'] ?? 0) . "</p>";
        ?>
    </div>

    <div class="stat-card">
        <h3>Clients</h3>
        <?php
        $sql = "SELECT COUNT(*) as total FROM CLIENT";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        echo "<p>" . ($row['total'] ?? 0) . "</p>";
        ?>
    </div>
</div>

<div class="recent-activity">
    <h2>Activité Récente</h2>
    <div class="activity-grid">
        <div class="recent-invoices">
            <h3>Dernières Factures Achat</h3>
            <?php
            $sql = "SELECT * FROM BUY_INVOICE_HEADER ORDER BY DATE DESC LIMIT 5";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Numéro</th><th>Fournisseur</th><th>Date</th><th>Total</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['INVOICE_NUMBER'] . "</td>";
                    echo "<td>" . $row['SUPPLIER_NAME'] . "</td>";
                    echo "<td>" . $row['DATE'] . "</td>";
                    echo "<td>" . number_format($row['TOTAL_PRICE_TTC'], 2) . " DH</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>Aucune facture d'achat récente</p>";
            }
            ?>
        </div>

        <div class="recent-sales">
            <h3>Dernières Factures Vente</h3>
            <?php
            $sql = "SELECT * FROM SELL_INVOICE_HEADER ORDER BY DATE DESC LIMIT 5";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Numéro</th><th>Client</th><th>Date</th><th>Total</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['INVOICE_NUMBER'] . "</td>";
                    echo "<td>" . $row['CLIENT_NAME'] . "</td>";
                    echo "<td>" . $row['DATE'] . "</td>";
                    echo "<td>" . number_format($row['TOTAL_PRICE_TTC'], 2) . " DH</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>Aucune facture de vente récente</p>";
            }
            ?>
        </div>
    </div>
</div>

<?php include '../includes/footerd.php'; ?>