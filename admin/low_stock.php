<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

// =====================================
// FETCH LOW STOCK INGREDIENTS
// =====================================

$sql = "
    SELECT
        id,
        name,
        unit,
        current_stock,
        minimum_stock,
        cost_per_unit
    FROM ingredients
    WHERE is_active = 1
      AND current_stock <= minimum_stock
    ORDER BY current_stock ASC, name ASC
";

$result = $conn->query($sql);

$lowStockItems = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}

// =====================================
// STATISTICS
// =====================================

$totalLowStock = count($lowStockItems);
$outOfStock = 0;
$criticalStock = 0;

foreach ($lowStockItems as $item) {

    $stock = (float)$item['current_stock'];

    if ($stock <= 0) {
        $outOfStock++;
    } else {
        $criticalStock++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Low Stock Alerts | CafeNest</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8fafc;
    color: #1e293b;
}

.container {
    max-width: 1300px;
    margin: auto;
    padding: 30px 20px;
}

/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
}

.page-header p {
    margin-top: 8px;
    color: #64748b;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    border: none;
}

.btn-blue {
    background: #2563eb;
    color: white;
}

.btn-blue:hover {
    background: #1d4ed8;
}

.btn-light {
    background: #e2e8f0;
    color: #334155;
}

/* SUMMARY */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.stat-card p {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 10px;
}

.stat-card h2 {
    margin: 0;
    font-size: 28px;
}

.stat-total {
    border-top: 3px solid #2563eb;
}

.stat-critical {
    border-top: 3px solid #f59e0b;
}

.stat-out {
    border-top: 3px solid #dc2626;
}

/* ALERT */

.alert-box {
    padding: 16px 18px;
    border-radius: 12px;
    margin-bottom: 22px;
    background: #fff7ed;
    color: #9a3412;
    border: 1px solid #fed7aa;
}

.alert-box strong {
    display: block;
    margin-bottom: 5px;
}

.alert-box p {
    margin: 0;
    font-size: 14px;
}

/* TABLE */

.table-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.table-header h3 {
    margin: 0;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #64748b;
    text-transform: uppercase;
    font-size: 12px;
}

tbody tr:hover td {
    background: #fafcff;
}

/* STOCK STATUS */

.stock-badge {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.badge-out {
    background: #fee2e2;
    color: #991b1b;
}

.badge-low {
    background: #ffedd5;
    color: #9a3412;
}

.stock-value {
    font-weight: 700;
}

.stock-zero {
    color: #dc2626;
}

.stock-low {
    color: #ea580c;
}

.stock-normal {
    color: #16a34a;
}

.progress-container {
    width: 120px;
    height: 8px;
    background: #f1f5f9;
    border-radius: 20px;
    overflow: hidden;
    margin-top: 8px;
}

.progress-bar {
    height: 100%;
    border-radius: 20px;
}

.progress-red {
    background: #dc2626;
}

.progress-orange {
    background: #f59e0b;
}

.restock-btn {
    display: inline-block;
    padding: 8px 13px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
}

.restock-btn:hover {
    background: #1d4ed8;
}

/* EMPTY STATE */

.empty-state {
    padding: 55px 20px;
    text-align: center;
}

.empty-icon {
    font-size: 42px;
    margin-bottom: 12px;
}

.empty-state h3 {
    margin: 0 0 10px;
}

.empty-state p {
    color: #64748b;
    margin: 0 0 20px;
}

/* RESPONSIVE */

@media (max-width: 750px) {

    .container {
        padding: 20px 14px;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .stats {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stat-card {
        padding: 18px;
    }

    .stat-card h2 {
        font-size: 24px;
    }
}

@media (max-width: 500px) {

    .container {
        padding: 15px 10px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .header-actions {
        width: 100%;
    }

    .header-actions .btn {
        flex: 1;
        text-align: center;
    }
}
</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1>Low Stock Alerts</h1>
            <p>Monitor ingredients that need replenishment.</p>
        </div>

        <div class="header-actions">

            <a href="ingredients.php" class="btn btn-light">
                ← Inventory
            </a>

            <a href="restock.php" class="btn btn-blue">
                + Restock
            </a>

        </div>

    </div>

    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat-card stat-total">
            <p>Total Low Stock Items</p>
            <h2><?= $totalLowStock ?></h2>
        </div>

        <div class="stat-card stat-critical">
            <p>Running Low</p>
            <h2><?= $criticalStock ?></h2>
        </div>

        <div class="stat-card stat-out">
            <p>Out of Stock</p>
            <h2><?= $outOfStock ?></h2>
        </div>

    </div>

    <!-- ALERT MESSAGE -->

    <?php if ($outOfStock > 0): ?>

        <div class="alert-box">
            <strong>⚠ Immediate attention required</strong>
            <p>
                <?= $outOfStock ?> ingredient(s) currently have zero stock.
                Restock them as soon as possible.
            </p>
        </div>

    <?php elseif ($totalLowStock > 0): ?>

        <div class="alert-box">
            <strong>⚠ Stock replenishment required</strong>
            <p>
                Some ingredients have reached their minimum stock level.
                Consider replenishing them soon.
            </p>
        </div>

    <?php endif; ?>

    <!-- LOW STOCK TABLE -->

    <div class="table-card">

        <div class="table-header">
            <h3>Ingredients Requiring Attention (<?= $totalLowStock ?>)</h3>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Stock Level</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($lowStockItems)): ?>

                    <tr>
                        <td colspan="6">

                            <div class="empty-state">

                                <div class="empty-icon">✓</div>

                                <h3>All Stock Levels Are Healthy</h3>

                                <p>
                                    No ingredients are currently below
                                    their minimum stock level.
                                </p>

                                <a href="ingredients.php" class="btn btn-blue">
                                    View Inventory
                                </a>

                            </div>

                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($lowStockItems as $item): ?>

                        <?php
                        $current = (float)$item['current_stock'];
                        $minimum = (float)$item['minimum_stock'];

                        $isOut = $current <= 0;

                        $percentage = $minimum > 0
                            ? min(($current / $minimum) * 100, 100)
                            : 0;

                        $percentage = max(0, $percentage);

                        $stockClass = $isOut
                            ? 'stock-zero'
                            : 'stock-low';

                        $progressClass = $isOut
                            ? 'progress-red'
                            : 'progress-orange';
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($item['name']) ?>
                                </strong>
                            </td>

                            <td>

                                <span class="stock-value <?= $stockClass ?>">
                                    <?= number_format($current, 2) ?>
                                    <?= htmlspecialchars($item['unit']) ?>
                                </span>

                                <div class="progress-container">
                                    <div
                                        class="progress-bar <?= $progressClass ?>"
                                        style="width: <?= $percentage ?>%;"
                                    ></div>
                                </div>

                            </td>

                            <td>
                                <?= number_format($minimum, 2) ?>
                                <?= htmlspecialchars($item['unit']) ?>
                            </td>

                            <td>
                                <?= number_format($percentage, 0) ?>%
                            </td>

                            <td>

                                <?php if ($isOut): ?>

                                    <span class="stock-badge badge-out">
                                        Out of Stock
                                    </span>

                                <?php else: ?>

                                    <span class="stock-badge badge-low">
                                        Low Stock
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <a href="restock.php" class="restock-btn">
                                    Restock
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>