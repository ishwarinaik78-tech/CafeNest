
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function rr_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rr_money($value) {
    return '₹' . number_format((float)$value, 2);
}

// =====================================
// FETCH ACTIVE INGREDIENTS
// =====================================

$query = $conn->query("
    SELECT
        id,
        name,
        unit,
        current_stock,
        minimum_stock,
        cost_per_unit
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

$ingredients = [];

$totalIngredients = 0;
$lowStockCount = 0;
$outOfStockCount = 0;
$healthyStockCount = 0;

$totalRecommendedQuantity = 0;
$totalEstimatedCost = 0;

while ($row = $query->fetch_assoc()) {

    $currentStock = (float)$row['current_stock'];
    $minimumStock = (float)$row['minimum_stock'];
    $costPerUnit = (float)$row['cost_per_unit'];

    // =====================================
    // STOCK STATUS
    // =====================================

    if ($currentStock <= 0) {
        $status = 'Out of Stock';
        $priority = 1;
        $outOfStockCount++;
    } elseif ($currentStock <= $minimumStock) {
        $status = 'Low Stock';
        $priority = 2;
        $lowStockCount++;
    } else {
        $status = 'Healthy';
        $priority = 3;
        $healthyStockCount++;
    }

    // =====================================
    // RECOMMENDED RESTOCK QUANTITY
    // Buy enough to reach minimum stock
    // =====================================

    $recommendedQuantity = 0;

    if ($currentStock <= $minimumStock) {
        $recommendedQuantity = max(
            0,
            $minimumStock - $currentStock
        );
    }

    $estimatedCost = $recommendedQuantity * $costPerUnit;

    // =====================================
    // TOTALS
    // =====================================

    $totalIngredients++;
    $totalRecommendedQuantity += $recommendedQuantity;
    $totalEstimatedCost += $estimatedCost;

    $ingredients[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'unit' => $row['unit'],
        'current_stock' => $currentStock,
        'minimum_stock' => $minimumStock,
        'cost_per_unit' => $costPerUnit,
        'recommended_quantity' => $recommendedQuantity,
        'estimated_cost' => $estimatedCost,
        'status' => $status,
        'priority' => $priority
    ];
}

// =====================================
// SORT URGENT INGREDIENTS FIRST
// =====================================

usort($ingredients, function ($a, $b) {

    if ($a['priority'] !== $b['priority']) {
        return $a['priority'] <=> $b['priority'];
    }

    // For items with same priority, sort by shortage
    $shortageA = $a['minimum_stock'] - $a['current_stock'];
    $shortageB = $b['minimum_stock'] - $b['current_stock'];

    return $shortageB <=> $shortageA;
});

// =====================================
// FILTER
// =====================================

$filter = $_GET['filter'] ?? 'all';

$allowedFilters = ['all', 'urgent', 'low_stock'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$filteredIngredients = [];

foreach ($ingredients as $ingredient) {

    if ($filter === 'urgent' && $ingredient['status'] !== 'Out of Stock') {
        continue;
    }

    if ($filter === 'low_stock' && $ingredient['status'] !== 'Low Stock') {
        continue;
    }

    $filteredIngredients[] = $ingredient;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Restock Recommendations | CafeNest</title>

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
    max-width: 1400px;
    margin: 30px auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0 0 8px;
}

.header p {
    color: #64748b;
    margin: 0;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 11px 15px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    color: white;
    background: #2563eb;
    font-weight: bold;
    font-size: 14px;
}

.btn.gray {
    background: #475569;
}

.btn.green {
    background: #059669;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 3px 10px rgba(15,23,42,.04);
}

.card h3 {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 14px;
}

.value {
    font-size: 26px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.subtitle {
    margin-top: 8px;
    color: #64748b;
    font-size: 13px;
}

.card.red {
    border-left: 4px solid #dc2626;
}

.card.orange {
    border-left: 4px solid #ea580c;
}

.card.green {
    border-left: 4px solid #059669;
}

.card.blue {
    border-left: 4px solid #2563eb;
}

.panel {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    margin-top: 20px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.panel-header h2 {
    margin: 0;
    font-size: 19px;
}

.filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter {
    text-decoration: none;
    padding: 9px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 13px;
    color: #334155;
    background: white;
}

.filter.active {
    background: #1e293b;
    color: white;
    border-color: #1e293b;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

th, td {
    padding: 13px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #475569;
    white-space: nowrap;
}

td {
    vertical-align: middle;
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

.recommended {
    font-weight: bold;
    color: #047857;
}

.urgent-row {
    background: #fff7f7;
}

.low-row {
    background: #fffaf3;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #64748b;
}

tfoot td {
    font-weight: bold;
    background: #f8fafc;
}

.note {
    background: #eff6ff;
    color: #1d4ed8;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
    font-size: 14px;
    line-height: 1.6;
}

@media(max-width: 1000px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 600px) {
    .container {
        width: 94%;
        margin: 20px auto;
    }

    .summary-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .card {
        padding: 15px;
    }

    .value {
        font-size: 21px;
    }
}

@media print {

    body {
        background: white;
    }

    .actions,
    .filters,
    .note {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .panel,
    .card {
        box-shadow: none;
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
        <h1>Restock Recommendations</h1>
        <p>Inventory shortage analysis and estimated purchasing budget.</p>
    </div>

    <div class="actions">

        <a href="inventory_dashboard.php" class="btn gray">
            Inventory Dashboard
        </a>

        <a href="restock.php" class="btn green">
            Manual Restock
        </a>

        <button class="btn" onclick="window.print()">
            Print Recommendations
        </button>

    </div>

</div>

<!-- SUMMARY -->

<div class="summary-grid">

    <div class="card red">
        <h3>Out of Stock</h3>
        <div class="value"><?= $outOfStockCount ?></div>
        <div class="subtitle">Ingredients with zero stock</div>
    </div>

    <div class="card orange">
        <h3>Low Stock</h3>
        <div class="value"><?= $lowStockCount ?></div>
        <div class="subtitle">At or below minimum stock</div>
    </div>

    <div class="card green">
        <h3>Healthy Stock</h3>
        <div class="value"><?= $healthyStockCount ?></div>
        <div class="subtitle">Above minimum stock</div>
    </div>

    <div class="card blue">
        <h3>Estimated Restock Budget</h3>
        <div class="value"><?= rr_money($totalEstimatedCost) ?></div>
        <div class="subtitle">To replenish shortages to minimum level</div>
    </div>

</div>

<!-- RECOMMENDATIONS TABLE -->

<div class="panel">

    <div class="panel-header">

        <h2>Ingredient Restock Analysis</h2>

        <div class="filters">

            <a href="?filter=all"
               class="filter <?= $filter === 'all' ? 'active' : '' ?>">
                All Ingredients
            </a>

            <a href="?filter=urgent"
               class="filter <?= $filter === 'urgent' ? 'active' : '' ?>">
                Out of Stock
            </a>

            <a href="?filter=low_stock"
               class="filter <?= $filter === 'low_stock' ? 'active' : '' ?>">
                Low Stock
            </a>

        </div>

    </div>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Current Stock</th>
                    <th>Minimum Stock</th>
                    <th>Shortage</th>
                    <th>Unit Cost</th>
                    <th>Recommended Quantity</th>
                    <th>Estimated Cost</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($filteredIngredients)): ?>

                <tr>
                    <td colspan="8" class="empty">
                        No ingredients found for this filter.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($filteredIngredients as $ingredient): ?>

                    <?php
                    $rowClass = '';

                    if ($ingredient['status'] === 'Out of Stock') {
                        $rowClass = 'urgent-row';
                    } elseif ($ingredient['status'] === 'Low Stock') {
                        $rowClass = 'low-row';
                    }

                    $statusClass = 'healthy';

                    if ($ingredient['status'] === 'Out of Stock') {
                        $statusClass = 'out';
                    } elseif ($ingredient['status'] === 'Low Stock') {
                        $statusClass = 'low';
                    }
                    ?>

                    <tr class="<?= $rowClass ?>">

                        <td>
                            <strong><?= rr_escape($ingredient['name']) ?></strong>
                        </td>

                        <td>
                            <?= number_format($ingredient['current_stock'], 3) ?>
                            <?= rr_escape($ingredient['unit']) ?>
                        </td>

                        <td>
                            <?= number_format($ingredient['minimum_stock'], 3) ?>
                            <?= rr_escape($ingredient['unit']) ?>
                        </td>

                        <td>
                            <?php
                            $shortage = max(
                                0,
                                $ingredient['minimum_stock'] - $ingredient['current_stock']
                            );
                            ?>

                            <?= number_format($shortage, 3) ?>
                            <?= rr_escape($ingredient['unit']) ?>
                        </td>

                        <td>
                            <?= rr_money($ingredient['cost_per_unit']) ?>
                        </td>

                        <td class="recommended">
                            <?php if ($ingredient['recommended_quantity'] > 0): ?>

                                +<?= number_format($ingredient['recommended_quantity'], 3) ?>
                                <?= rr_escape($ingredient['unit']) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>
                        </td>

                        <td>
                            <strong>
                                <?= rr_money($ingredient['estimated_cost']) ?>
                            </strong>
                        </td>

                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= rr_escape($ingredient['status']) ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

            <tfoot>
                <tr>
                    <td colspan="5">Total Recommended Restock</td>
                    <td>
                        <?= number_format($totalRecommendedQuantity, 3) ?>
                    </td>
                    <td>
                        <?= rr_money($totalEstimatedCost) ?>
                    </td>
                    <td>—</td>
                </tr>
            </tfoot>

        </table>

    </div>

</div>

<!-- EXPLANATION -->

<div class="note">
    <strong>How recommendations are calculated:</strong><br>
    Ingredients at or below their minimum stock level are flagged.
    The recommended quantity is the difference between current stock
    and minimum stock. Estimated cost is calculated using the ingredient's
    current cost per unit. These are estimates, not supplier quotations.
</div>

</div>

</body>
</html>
