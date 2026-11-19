<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function smart_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================
// FETCH ACTIVE INGREDIENTS
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
    ORDER BY
        CASE
            WHEN current_stock <= 0 THEN 1
            WHEN current_stock <= minimum_stock THEN 2
            ELSE 3
        END,
        name ASC
";

$result = $conn->query($sql);

$ingredients = [];

$totalLowStock = 0;
$totalOutOfStock = 0;
$totalSuggestedCost = 0;
$totalSuggestedQuantity = 0;

// =====================================
// CALCULATE SMART RESTOCK
// =====================================

while ($row = $result->fetch_assoc()) {

    $currentStock = (float)$row['current_stock'];
    $minimumStock = (float)$row['minimum_stock'];
    $costPerUnit = (float)$row['cost_per_unit'];

    // Target stock is 3 times the minimum stock
    $targetStock = $minimumStock * 3;

    // Recommended quantity
    $suggestedQuantity = max(0, $targetStock - $currentStock);

    // Estimated purchasing cost
    $estimatedCost = $suggestedQuantity * $costPerUnit;

    // Stock status
    if ($currentStock <= 0) {
        $status = "Out of Stock";
        $statusClass = "out";
        $totalOutOfStock++;
    } elseif ($currentStock <= $minimumStock) {
        $status = "Low Stock";
        $statusClass = "low";
        $totalLowStock++;
    } else {
        $status = "Healthy";
        $statusClass = "healthy";
    }

    // Ingredients without minimum stock set
    if ($minimumStock <= 0) {
        $status = "Minimum Not Set";
        $statusClass = "unset";
        $suggestedQuantity = 0;
        $estimatedCost = 0;
    }

    $row['target_stock'] = $targetStock;
    $row['suggested_quantity'] = $suggestedQuantity;
    $row['estimated_cost'] = $estimatedCost;
    $row['status'] = $status;
    $row['status_class'] = $statusClass;

    $ingredients[] = $row;

    if ($suggestedQuantity > 0) {
        $totalSuggestedQuantity += $suggestedQuantity;
        $totalSuggestedCost += $estimatedCost;
    }
}

// =====================================
// FILTERS
// =====================================

$filter = $_GET['filter'] ?? 'all';

$allowedFilters = ['all', 'low', 'out', 'healthy'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$filteredIngredients = [];

foreach ($ingredients as $item) {

    if ($filter === 'low' &&
        !in_array($item['status'], ['Low Stock', 'Out of Stock'], true)) {
        continue;
    }

    if ($filter === 'out' && $item['status'] !== 'Out of Stock') {
        continue;
    }

    if ($filter === 'healthy' && $item['status'] !== 'Healthy') {
        continue;
    }

    $filteredIngredients[] = $item;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Smart Restock | CafeNest</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f8fafc;
    color: #1e293b;
}

.container {
    width: 95%;
    max-width: 1450px;
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
    font-size: 28px;
}

.header p {
    margin: 0;
    color: #64748b;
}

.header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    background: #2563eb;
    color: white;
}

.btn.secondary {
    background: white;
    color: #334155;
    border: 1px solid #dbe2ea;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #edf1f5;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.card-label {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 12px;
}

.card-value {
    font-size: 27px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.blue {
    color: #2563eb;
}

.orange {
    color: #d97706;
}

.red {
    color: #dc2626;
}

.green {
    color: #16a34a;
}

.section {
    margin-top: 25px;
}

.table-card {
    background: white;
    border: 1px solid #edf1f5;
    border-radius: 14px;
    padding: 20px;
}

.filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filter-btn {
    padding: 9px 15px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    text-decoration: none;
    color: #334155;
    background: white;
    font-size: 13px;
    font-weight: bold;
}

.filter-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 14px 10px;
    border-bottom: 1px solid #edf1f5;
}

th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
}

td {
    font-size: 14px;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.status.out {
    background: #fee2e2;
    color: #b91c1c;
}

.status.low {
    background: #ffedd5;
    color: #c2410c;
}

.status.healthy {
    background: #dcfce7;
    color: #15803d;
}

.status.unset {
    background: #e2e8f0;
    color: #475569;
}

.restock-qty {
    color: #2563eb;
    font-weight: bold;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 35px;
}

.note {
    margin-top: 18px;
    padding: 15px;
    border-radius: 10px;
    background: #eff6ff;
    color: #1e40af;
    font-size: 14px;
    line-height: 1.6;
}

@media (max-width: 1000px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .container {
        width: 94%;
        margin: 20px auto;
    }

    .header h1 {
        font-size: 23px;
    }

    .summary-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .card {
        padding: 15px;
    }

    .card-value {
        font-size: 21px;
    }

    .table-card {
        padding: 12px;
    }
}

@media (max-width: 380px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body>

<div class="container">

<!-- HEADER -->

<div class="header">

    <div>
        <h1>Smart Restock</h1>
        <p>Intelligent inventory replenishment recommendations</p>
    </div>

    <div class="header-actions">
        <a href="inventory_dashboard.php" class="btn secondary">
            Inventory Dashboard
        </a>

        <a href="restock.php" class="btn">
            Open Restock
        </a>
    </div>

</div>

<!-- SUMMARY -->

<div class="summary-grid">

    <div class="card">
        <div class="card-label">Low Stock Ingredients</div>
        <div class="card-value orange">
            <?= $totalLowStock ?>
        </div>
    </div>

    <div class="card">
        <div class="card-label">Out of Stock</div>
        <div class="card-value red">
            <?= $totalOutOfStock ?>
        </div>
    </div>

    <div class="card">
        <div class="card-label">Suggested Purchase Quantity</div>
        <div class="card-value blue">
            <?= number_format($totalSuggestedQuantity, 3) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-label">Estimated Restock Cost</div>
        <div class="card-value green">
            ₹<?= number_format($totalSuggestedCost, 2) ?>
        </div>
    </div>

</div>

<!-- RESTOCK TABLE -->

<div class="section">

    <div class="table-card">

        <h2>Restock Recommendations</h2>

        <div class="filters">

            <a href="?filter=all"
               class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                All Ingredients
            </a>

            <a href="?filter=low"
               class="filter-btn <?= $filter === 'low' ? 'active' : '' ?>">
                Low Stock
            </a>

            <a href="?filter=out"
               class="filter-btn <?= $filter === 'out' ? 'active' : '' ?>">
                Out of Stock
            </a>

            <a href="?filter=healthy"
               class="filter-btn <?= $filter === 'healthy' ? 'active' : '' ?>">
                Healthy Stock
            </a>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Current</th>
                        <th>Minimum</th>
                        <th>Target</th>
                        <th>Suggested Purchase</th>
                        <th>Unit Cost</th>
                        <th>Est. Cost</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($filteredIngredients)): ?>

                    <?php foreach ($filteredIngredients as $item): ?>

                        <tr>

                            <td>
                                <strong><?= smart_escape($item['name']) ?></strong>
                            </td>

                            <td>
                                <?= number_format((float)$item['current_stock'], 3) ?>
                                <?= smart_escape($item['unit']) ?>
                            </td>

                            <td>
                                <?= number_format((float)$item['minimum_stock'], 3) ?>
                                <?= smart_escape($item['unit']) ?>
                            </td>

                            <td>
                                <?php if ((float)$item['minimum_stock'] > 0): ?>
                                    <?= number_format((float)$item['target_stock'], 3) ?>
                                    <?= smart_escape($item['unit']) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td class="restock-qty">
                                <?php if ((float)$item['suggested_quantity'] > 0): ?>

                                    +<?= number_format((float)$item['suggested_quantity'], 3) ?>
                                    <?= smart_escape($item['unit']) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>
                            </td>

                            <td>
                                ₹<?= number_format((float)$item['cost_per_unit'], 2) ?>
                            </td>

                            <td>
                                ₹<?= number_format((float)$item['estimated_cost'], 2) ?>
                            </td>

                            <td>
                                <span class="status <?= smart_escape($item['status_class']) ?>">
                                    <?= smart_escape($item['status']) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No ingredients found for this filter.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="note">
            <strong>How recommendations work:</strong>
            Target stock is calculated as 3 × minimum stock.
            Suggested purchase quantity is target stock minus current stock.
            Estimated cost is suggested quantity multiplied by the ingredient's cost per unit.
            Ingredients with no minimum stock configured will not receive purchase recommendations.
        </div>

    </div>

</div>

</div>

</body>
</html>