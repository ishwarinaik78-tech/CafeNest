
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function ic_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================
// INVENTORY SUMMARY
// =====================================

$totalIngredients = 0;
$lowStockCount = 0;
$outOfStockCount = 0;
$healthyCount = 0;
$totalInventoryValue = 0;

$inventoryQuery = $conn->query("
    SELECT
        COUNT(*) AS total_ingredients,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(
            CASE
                WHEN current_stock > 0
                AND current_stock <= minimum_stock
                THEN 1 ELSE 0
            END
        ) AS low_stock,
        SUM(
            CASE
                WHEN current_stock > minimum_stock
                THEN 1 ELSE 0
            END
        ) AS healthy,
        SUM(current_stock * cost_per_unit) AS inventory_value
    FROM ingredients
    WHERE is_active = 1
");

$inventorySummary = $inventoryQuery->fetch_assoc();

$totalIngredients = (int)($inventorySummary['total_ingredients'] ?? 0);
$outOfStockCount = (int)($inventorySummary['out_of_stock'] ?? 0);
$lowStockCount = (int)($inventorySummary['low_stock'] ?? 0);
$healthyCount = (int)($inventorySummary['healthy'] ?? 0);
$totalInventoryValue = (float)($inventorySummary['inventory_value'] ?? 0);

// =====================================
// RECENT STOCK MOVEMENTS
// =====================================

$recentMovements = $conn->query("
    SELECT
        sm.id,
        i.name AS ingredient_name,
        sm.movement_type,
        sm.quantity_change,
        sm.created_at
    FROM stock_movements sm
    INNER JOIN ingredients i
        ON i.id = sm.ingredient_id
    ORDER BY sm.id DESC
    LIMIT 6
");

// =====================================
// LOW STOCK ITEMS
// =====================================

$lowStockItems = $conn->query("
    SELECT
        id,
        name,
        current_stock,
        minimum_stock,
        unit
    FROM ingredients
    WHERE is_active = 1
    AND current_stock <= minimum_stock
    ORDER BY current_stock ASC
    LIMIT 8
");

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory Control Center | CafeNest</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f1f5f9;
    color: #1e293b;
    font-family: Arial, Helvetica, sans-serif;
}

.container {
    width: 95%;
    max-width: 1500px;
    margin: 30px auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0 0 8px;
}

.header p {
    color: #64748b;
    margin: 0;
}

.btn {
    display: inline-block;
    padding: 11px 15px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
}

.btn.gray {
    background: #475569;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.card h3 {
    margin: 0 0 12px;
    font-size: 13px;
    color: #64748b;
}

.card .value {
    font-size: 25px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.card .sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 8px;
}

.card.red {
    border-top: 4px solid #dc2626;
}

.card.orange {
    border-top: 4px solid #ea580c;
}

.card.green {
    border-top: 4px solid #16a34a;
}

.card.blue {
    border-top: 4px solid #2563eb;
}

.card.purple {
    border-top: 4px solid #7c3aed;
}

.section {
    margin-bottom: 25px;
}

.section-title {
    margin: 0 0 15px;
    font-size: 21px;
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.module {
    display: flex;
    flex-direction: column;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    text-decoration: none;
    color: #1e293b;
    transition: transform .15s, box-shadow .15s;
}

.module:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(15,23,42,.08);
}

.module-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.module-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.module h3 {
    margin: 0;
    font-size: 16px;
}

.module p {
    margin: 0;
    color: #64748b;
    line-height: 1.6;
    font-size: 13px;
    flex: 1;
}

.module-link {
    margin-top: 15px;
    color: #2563eb;
    font-size: 13px;
    font-weight: bold;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.panel h2 {
    margin: 0 0 18px;
    font-size: 19px;
}

.content-grid {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 20px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 500px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge.low {
    background: #ffedd5;
    color: #c2410c;
}

.badge.out {
    background: #fee2e2;
    color: #b91c1c;
}

.badge.healthy {
    background: #dcfce7;
    color: #15803d;
}

.movement {
    font-weight: bold;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 25px;
}

.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.quick-links a {
    background: #f1f5f9;
    color: #334155;
    text-decoration: none;
    padding: 10px 13px;
    border-radius: 8px;
    font-size: 13px;
}

.footer {
    text-align: center;
    color: #64748b;
    font-size: 12px;
    padding: 20px 0;
}

@media(max-width: 1200px) {
    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .module-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 850px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 550px) {
    .container {
        width: 94%;
        margin: 20px auto;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .module-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .card {
        padding: 15px;
    }

    .card .value {
        font-size: 21px;
    }

    .module {
        padding: 16px;
    }
}

@media print {
    body {
        background: white;
    }

    .container {
        width: 100%;
    }

    .module-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .module {
        break-inside: avoid;
    }
}

</style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">
        <div>
            <h1>Inventory Control Center</h1>
            <p>Central access to inventory operations, suppliers, purchasing and analytics.</p>
        </div>

        <div>
            <a href="index.php" class="btn gray">Admin Home</a>
        </div>
    </div>

    <!-- SUMMARY -->

    <div class="summary-grid">

        <div class="card blue">
            <h3>Active Ingredients</h3>
            <div class="value"><?= $totalIngredients ?></div>
            <div class="sub">Currently active</div>
        </div>

        <div class="card red">
            <h3>Out of Stock</h3>
            <div class="value"><?= $outOfStockCount ?></div>
            <div class="sub">Immediate attention</div>
        </div>

        <div class="card orange">
            <h3>Low Stock</h3>
            <div class="value"><?= $lowStockCount ?></div>
            <div class="sub">Needs replenishment</div>
        </div>

        <div class="card green">
            <h3>Healthy Stock</h3>
            <div class="value"><?= $healthyCount ?></div>
            <div class="sub">Above minimum level</div>
        </div>

        <div class="card purple">
            <h3>Stock Value</h3>
            <div class="value">
                ₹<?= number_format($totalInventoryValue, 2) ?>
            </div>
            <div class="sub">Estimated current value</div>
        </div>

    </div>

    <!-- STOCK OPERATIONS -->

    <div class="section">

        <h2 class="section-title">Stock Operations</h2>

        <div class="module-grid">

            <a href="inventory_dashboard.php" class="module">
                <div class="module-top">
                    <div class="module-icon">📦</div>
                    <h3>Inventory Dashboard</h3>
                </div>
                <p>Monitor inventory value, stock levels, low-stock items and inventory KPIs.</p>
                <div class="module-link">Open Dashboard →</div>
            </a>

            <a href="restock.php" class="module">
                <div class="module-top">
                    <div class="module-icon">🔄</div>
                    <h3>Stock Restocking</h3>
                </div>
                <p>Record stock purchases and replenish ingredients manually.</p>
                <div class="module-link">Manage Restocking →</div>
            </a>

            <a href="stock_reconciliation.php" class="module">
                <div class="module-top">
                    <div class="module-icon">⚖️</div>
                    <h3>Stock Reconciliation</h3>
                </div>
                <p>Compare actual physical quantities against system stock and record adjustments.</p>
                <div class="module-link">Reconcile Stock →</div>
            </a>

        </div>

    </div>

    <!-- SUPPLIERS AND PURCHASING -->

    <div class="section">

        <h2 class="section-title">Suppliers & Purchasing</h2>

        <div class="module-grid">

            <a href="suppliers.php" class="module">
                <div class="module-top">
                    <div class="module-icon">🏢</div>
                    <h3>Supplier Management</h3>
                </div>
                <p>Manage supplier profiles, contacts, active status and ingredient assignments.</p>
                <div class="module-link">Manage Suppliers →</div>
            </a>

            <a href="purchase_orders.php" class="module">
                <div class="module-top">
                    <div class="module-icon">🧾</div>
                    <h3>Purchase Orders</h3>
                </div>
                <p>Create orders, track statuses and receive supplier deliveries.</p>
                <div class="module-link">Open Purchase Orders →</div>
            </a>

            <a href="supplier_restock_planner.php" class="module">
                <div class="module-top">
                    <div class="module-icon">🛒</div>
                    <h3>Supplier Restock Planner</h3>
                </div>
                <p>Review shortages, supplier mappings and estimated restocking costs.</p>
                <div class="module-link">Plan Restocking →</div>
            </a>

            <a href="supplier_performance.php" class="module">
                <div class="module-top">
                    <div class="module-icon">📈</div>
                    <h3>Supplier Performance</h3>
                </div>
                <p>Review supplier spending, purchase history and purchasing performance.</p>
                <div class="module-link">View Performance →</div>
            </a>

        </div>

    </div>

    <!-- REPORTS -->

    <div class="section">

        <h2 class="section-title">Reports & Analytics</h2>

        <div class="module-grid">

            <a href="inventory_reports.php" class="module">
                <div class="module-top">
                    <div class="module-icon">📑</div>
                    <h3>Inventory Reports</h3>
                </div>
                <p>View inventory summaries and stock-related reporting.</p>
                <div class="module-link">Open Reports →</div>
            </a>

            <a href="inventory_monthly_analytics.php" class="module">
                <div class="module-top">
                    <div class="module-icon">📊</div>
                    <h3>Monthly Analytics</h3>
                </div>
                <p>Analyze monthly purchase spending and wastage trends.</p>
                <div class="module-link">View Analytics →</div>
            </a>

            <a href="inventory_restock_recommendations.php" class="module">
                <div class="module-top">
                    <div class="module-icon">💡</div>
                    <h3>Restock Recommendations</h3>
                </div>
                <p>Identify shortages and review recommended replenishment quantities.</p>
                <div class="module-link">View Recommendations →</div>
            </a>

            <a href="export_inventory.php" class="module">
                <div class="module-top">
                    <div class="module-icon">📤</div>
                    <h3>Export Inventory</h3>
                </div>
                <p>Download inventory data and low-stock lists as CSV files.</p>
                <div class="module-link">Export Data →</div>
            </a>

        </div>

    </div>

    <!-- AUDIT -->

    <div class="section">

        <h2 class="section-title">Audit & Monitoring</h2>

        <div class="module-grid">

            <a href="inventory_activity_log.php" class="module">
                <div class="module-top">
                    <div class="module-icon">📝</div>
                    <h3>Inventory Activity Log</h3>
                </div>
                <p>Track purchases, usage, wastage, adjustments and stock restorations.</p>
                <div class="module-link">View Activity →</div>
            </a>

            <a href="inventory_integrity_audit.php" class="module">
                <div class="module-top">
                    <div class="module-icon">🔍</div>
                    <h3>Integrity Audit</h3>
                </div>
                <p>Check stock anomalies, supplier mapping problems and suspicious movements.</p>
                <div class="module-link">Run Audit →</div>
            </a>

        </div>

    </div>

    <!-- LIVE TABLES -->

    <div class="content-grid">

        <div class="panel">

            <h2>Stock Requiring Attention</h2>

            <div class="table-wrap">
                <table>

                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Stock</th>
                            <th>Minimum</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($lowStockItems->num_rows === 0): ?>

                        <tr>
                            <td colspan="4" class="empty">
                                All active ingredients are above minimum stock.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php while ($item = $lowStockItems->fetch_assoc()): ?>

                            <?php
                            $isOut = (float)$item['current_stock'] <= 0;
                            ?>

                            <tr>
                                <td><?= ic_escape($item['name']) ?></td>

                                <td>
                                    <?= number_format((float)$item['current_stock'], 3) ?>
                                    <?= ic_escape($item['unit']) ?>
                                </td>

                                <td>
                                    <?= number_format((float)$item['minimum_stock'], 3) ?>
                                    <?= ic_escape($item['unit']) ?>
                                </td>

                                <td>
                                    <span class="badge <?= $isOut ? 'out' : 'low' ?>">
                                        <?= $isOut ? 'Out of Stock' : 'Low Stock' ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php endif; ?>

                    </tbody>

                </table>
            </div>

            <div class="quick-links">
                <a href="supplier_restock_planner.php">Supplier Restock Planner</a>
                <a href="stock_reconciliation.php">Stock Reconciliation</a>
                <a href="inventory_restock_recommendations.php">Restock Recommendations</a>
            </div>

        </div>

        <div class="panel">

            <h2>Recent Stock Movements</h2>

            <div class="table-wrap">
                <table>

                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Type</th>
                            <th>Qty</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($recentMovements->num_rows === 0): ?>

                        <tr>
                            <td colspan="3" class="empty">
                                No stock movements recorded.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php while ($movement = $recentMovements->fetch_assoc()): ?>

                            <tr>
                                <td><?= ic_escape($movement['ingredient_name']) ?></td>

                                <td><?= ic_escape($movement['movement_type']) ?></td>

                                <td class="movement">
                                    <?= number_format((float)$movement['quantity_change'], 3) ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php endif; ?>

                    </tbody>

                </table>
            </div>

            <div class="quick-links">
                <a href="inventory_activity_log.php">Full Activity Log</a>
                <a href="inventory_reports.php">Inventory Reports</a>
            </div>

        </div>

    </div>

    <div class="footer">
        CafeNest Inventory Control Center
    </div>

</div>

</body>
</html>
