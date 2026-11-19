
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function srp_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function srp_money($value) {
    return '₹' . number_format((float)$value, 2);
}

// =====================================
// FILTER
// =====================================

$filter = $_GET['filter'] ?? 'all';

$allowedFilters = ['all', 'urgent', 'low', 'unmapped'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

// =====================================
// FETCH INGREDIENTS + SUPPLIERS
// =====================================

$sql = "
    SELECT
        i.id,
        i.name,
        i.unit,
        i.current_stock,
        i.minimum_stock,
        i.cost_per_unit,

        COALESCE(supplier_data.supplier_count, 0) AS supplier_count,
        COALESCE(supplier_data.supplier_names, '') AS supplier_names

    FROM ingredients i

    LEFT JOIN (
        SELECT
            si.ingredient_id,
            COUNT(DISTINCT s.id) AS supplier_count,
            GROUP_CONCAT(
                DISTINCT s.supplier_name
                ORDER BY s.supplier_name
                SEPARATOR ', '
            ) AS supplier_names
        FROM supplier_ingredients si
        INNER JOIN suppliers s
            ON s.id = si.supplier_id
        WHERE s.is_active = 1
        GROUP BY si.ingredient_id
    ) supplier_data
        ON supplier_data.ingredient_id = i.id

    WHERE i.is_active = 1

    ORDER BY i.current_stock ASC, i.name ASC
";

$result = $conn->query($sql);

$ingredients = [];

$totalIngredients = 0;
$outOfStock = 0;
$lowStock = 0;
$unmappedCount = 0;
$totalSuggestedCost = 0;

while ($row = $result->fetch_assoc()) {

    $stock = (float)$row['current_stock'];
    $minimum = (float)$row['minimum_stock'];
    $cost = (float)$row['cost_per_unit'];
    $supplierCount = (int)$row['supplier_count'];

    // Stock status
    if ($stock <= 0) {
        $status = 'Out of Stock';
        $priority = 1;
        $outOfStock++;
    } elseif ($stock <= $minimum) {
        $status = 'Low Stock';
        $priority = 2;
        $lowStock++;
    } else {
        $status = 'Healthy';
        $priority = 3;
    }

    // Suggested amount to restore stock to minimum
    $suggestedQuantity = max(0, $minimum - $stock);

    $estimatedCost = $suggestedQuantity * $cost;

    if ($supplierCount === 0 && $suggestedQuantity > 0) {
        $unmappedCount++;
    }

    $totalIngredients++;

    $totalSuggestedCost += $estimatedCost;

    $ingredients[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'unit' => $row['unit'],
        'stock' => $stock,
        'minimum' => $minimum,
        'cost' => $cost,
        'suggested_quantity' => $suggestedQuantity,
        'estimated_cost' => $estimatedCost,
        'supplier_count' => $supplierCount,
        'supplier_names' => $row['supplier_names'],
        'status' => $status,
        'priority' => $priority
    ];
}

// =====================================
// SORT
// =====================================

usort($ingredients, function ($a, $b) {

    if ($a['priority'] !== $b['priority']) {
        return $a['priority'] <=> $b['priority'];
    }

    return $a['stock'] <=> $b['stock'];
});

// =====================================
// APPLY FILTER
// =====================================

$filteredIngredients = [];

foreach ($ingredients as $ingredient) {

    if (
        $filter === 'urgent' &&
        $ingredient['status'] !== 'Out of Stock'
    ) {
        continue;
    }

    if (
        $filter === 'low' &&
        $ingredient['status'] !== 'Low Stock'
    ) {
        continue;
    }

    if (
        $filter === 'unmapped' &&
        (
            $ingredient['suggested_quantity'] <= 0 ||
            $ingredient['supplier_count'] > 0
        )
    ) {
        continue;
    }

    $filteredIngredients[] = $ingredient;
}

// =====================================
// SUMMARY
// =====================================

$mappedCount = 0;
$healthyCount = 0;

foreach ($ingredients as $ingredient) {

    if ($ingredient['supplier_count'] > 0) {
        $mappedCount++;
    }

    if ($ingredient['status'] === 'Healthy') {
        $healthyCount++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Supplier Restock Planner | CafeNest</title>

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
}

.header p {
    margin: 0;
    color: #64748b;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 11px 15px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
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
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(15,23,42,.04);
}

.card h3 {
    margin: 0 0 12px;
    font-size: 14px;
    color: #64748b;
}

.value {
    font-size: 25px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.subtitle {
    font-size: 13px;
    color: #64748b;
    margin-top: 8px;
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
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
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
    padding: 9px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    text-decoration: none;
    color: #334155;
    font-size: 13px;
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
    min-width: 1050px;
    border-collapse: collapse;
}

th, td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
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

.ingredient {
    font-weight: bold;
}

.supplier {
    max-width: 260px;
    overflow-wrap: anywhere;
}

.no-supplier {
    color: #dc2626;
    font-weight: bold;
}

.muted {
    color: #64748b;
    font-size: 12px;
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

.restock {
    color: #047857;
    font-weight: bold;
}

.cost {
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #64748b;
}

.notice {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
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
        gap: 10px;
    }

    .card {
        padding: 15px;
    }

    .value {
        font-size: 20px;
    }

    .panel {
        padding: 15px;
    }
}

@media print {
    body {
        background: white;
    }

    .actions,
    .filters,
    .notice {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .card,
    .panel {
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
        <h1>Supplier Restock Planner</h1>
        <p>Low-stock analysis with supplier mapping and estimated order costs.</p>
    </div>

    <div class="actions">
        <a href="inventory_dashboard.php" class="btn gray">Dashboard</a>
        <a href="suppliers.php" class="btn green">Manage Suppliers</a>
        <a href="purchase_orders.php" class="btn">Purchase Orders</a>
        <button onclick="window.print()" class="btn gray">Print</button>
    </div>

</div>

<!-- SUMMARY CARDS -->

<div class="summary-grid">

    <div class="card red">
        <h3>Out of Stock</h3>
        <div class="value"><?= $outOfStock ?></div>
        <div class="subtitle">Requires urgent attention</div>
    </div>

    <div class="card orange">
        <h3>Low Stock</h3>
        <div class="value"><?= $lowStock ?></div>
        <div class="subtitle">At or below minimum level</div>
    </div>

    <div class="card green">
        <h3>Supplier Mapped</h3>
        <div class="value"><?= $mappedCount ?></div>
        <div class="subtitle">Ingredients with active suppliers</div>
    </div>

    <div class="card blue">
        <h3>Estimated Restock Cost</h3>
        <div class="value"><?= srp_money($totalSuggestedCost) ?></div>
        <div class="subtitle">All ingredient shortages combined</div>
    </div>

</div>

<!-- TABLE -->

<div class="panel">

    <div class="panel-header">

        <h2>Restocking Overview</h2>

        <div class="filters">
            <a href="?filter=all"
               class="filter <?= $filter === 'all' ? 'active' : '' ?>">
                All
            </a>

            <a href="?filter=urgent"
               class="filter <?= $filter === 'urgent' ? 'active' : '' ?>">
                Out of Stock
            </a>

            <a href="?filter=low"
               class="filter <?= $filter === 'low' ? 'active' : '' ?>">
                Low Stock
            </a>

            <a href="?filter=unmapped"
               class="filter <?= $filter === 'unmapped' ? 'active' : '' ?>">
                Missing Supplier
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
                    <th>Recommended Order</th>
                    <th>Unit Cost</th>
                    <th>Est. Cost</th>
                    <th>Supplier(s)</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($filteredIngredients)): ?>

                <tr>
                    <td colspan="8" class="empty">
                        No ingredients match this filter.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($filteredIngredients as $item): ?>

                    <?php
                    $statusClass = 'healthy';

                    if ($item['status'] === 'Out of Stock') {
                        $statusClass = 'out';
                    } elseif ($item['status'] === 'Low Stock') {
                        $statusClass = 'low';
                    }
                    ?>

                    <tr>

                        <td class="ingredient">
                            <?= srp_escape($item['name']) ?>
                        </td>

                        <td>
                            <?= number_format($item['stock'], 3) ?>
                            <?= srp_escape($item['unit']) ?>
                        </td>

                        <td>
                            <?= number_format($item['minimum'], 3) ?>
                            <?= srp_escape($item['unit']) ?>
                        </td>

                        <td class="restock">
                            <?php if ($item['suggested_quantity'] > 0): ?>
                                +<?= number_format($item['suggested_quantity'], 3) ?>
                                <?= srp_escape($item['unit']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= srp_money($item['cost']) ?>
                        </td>

                        <td class="cost">
                            <?= srp_money($item['estimated_cost']) ?>
                        </td>

                        <td class="supplier">

                            <?php if ($item['supplier_count'] > 0): ?>

                                <?= srp_escape($item['supplier_names']) ?>

                                <div class="muted">
                                    <?= $item['supplier_count'] ?> supplier(s)
                                </div>

                            <?php elseif ($item['suggested_quantity'] > 0): ?>

                                <span class="no-supplier">
                                    No supplier mapped
                                </span>

                            <?php else: ?>

                                <span class="muted">Not assigned</span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= srp_escape($item['status']) ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- EXPLANATION -->

<div class="notice">
    <strong>Recommendation logic:</strong>
    Ingredients at or below minimum stock are flagged for replenishment.
    The suggested quantity brings stock up to the configured minimum.
    Estimated costs use the current ingredient cost and are not supplier quotations.
    Supplier mapping is informational; no orders or stock changes are created by this page.
</div>

</div>

</body>
</html>
