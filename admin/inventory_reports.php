
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function ir_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ir_money($value) {
    return '₹' . number_format((float)$value, 2);
}

function ir_valid_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$today = date('Y-m-d');
$defaultStart = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $defaultStart;
$endDate = $_GET['end_date'] ?? $today;

if (!ir_valid_date($startDate)) {
    $startDate = $defaultStart;
}

if (!ir_valid_date($endDate)) {
    $endDate = $today;
}

if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// =====================================
// MOVEMENT SUMMARY
// =====================================

$movementSummary = $conn->query("
    SELECT
        movement_type,
        COUNT(*) AS movement_count,
        COALESCE(SUM(ABS(quantity_change)), 0) AS total_quantity
    FROM stock_movements
    WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
    GROUP BY movement_type
");

$movementData = [];

$totalMovements = 0;
$totalMovementQuantity = 0;

if ($movementSummary) {
    while ($row = $movementSummary->fetch_assoc()) {
        $movementData[$row['movement_type']] = $row;
        $totalMovements += (int)$row['movement_count'];
        $totalMovementQuantity += (float)$row['total_quantity'];
    }
}

// =====================================
// PURCHASE SUMMARY
// =====================================

$purchaseSummary = $conn->query("
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(total_cost), 0) AS total_cost,
        COALESCE(SUM(quantity), 0) AS total_quantity
    FROM inventory_purchases
    WHERE DATE(purchased_at) BETWEEN '$startDate' AND '$endDate'
");

$purchaseData = $purchaseSummary
    ? $purchaseSummary->fetch_assoc()
    : [];

$purchaseCount = (int)($purchaseData['purchase_count'] ?? 0);
$purchaseAmount = (float)($purchaseData['total_cost'] ?? 0);
$purchaseQuantity = (float)($purchaseData['total_quantity'] ?? 0);

// =====================================
// WASTAGE COST
// =====================================

$wastageSummary = $conn->query("
    SELECT
        COALESCE(SUM(ABS(sm.quantity_change)), 0) AS total_wasted,
        COALESCE(
            SUM(ABS(sm.quantity_change) * i.cost_per_unit),
            0
        ) AS estimated_wastage_cost
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE sm.movement_type = 'Wastage'
      AND DATE(sm.created_at) BETWEEN '$startDate' AND '$endDate'
");

$wastageData = $wastageSummary
    ? $wastageSummary->fetch_assoc()
    : [];

$wastedQuantity = (float)($wastageData['total_wasted'] ?? 0);
$wastageCost = (float)($wastageData['estimated_wastage_cost'] ?? 0);

// =====================================
// TOP WASTED INGREDIENTS
// =====================================

$topWasted = $conn->query("
    SELECT
        i.name,
        i.unit,
        i.cost_per_unit,
        SUM(ABS(sm.quantity_change)) AS wasted_quantity,
        SUM(ABS(sm.quantity_change) * i.cost_per_unit) AS estimated_cost
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE sm.movement_type = 'Wastage'
      AND DATE(sm.created_at) BETWEEN '$startDate' AND '$endDate'
    GROUP BY i.id, i.name, i.unit, i.cost_per_unit
    ORDER BY estimated_cost DESC
    LIMIT 10
");

// =====================================
// STOCK MOVEMENT DETAILS
// =====================================

$movementDetails = $conn->query("
    SELECT
        sm.id,
        sm.movement_type,
        sm.quantity_change,
        sm.note,
        sm.created_at,
        i.name AS ingredient_name,
        i.unit
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE DATE(sm.created_at) BETWEEN '$startDate' AND '$endDate'
    ORDER BY sm.id DESC
    LIMIT 50
");

// =====================================
// PURCHASE DETAILS
// =====================================

$purchaseDetails = $conn->query("
    SELECT
        ip.id,
        ip.quantity,
        ip.total_cost,
        ip.purchased_at,
        s.supplier_name
    FROM inventory_purchases ip
    LEFT JOIN suppliers s ON s.id = ip.supplier_id
    WHERE DATE(ip.purchased_at) BETWEEN '$startDate' AND '$endDate'
    ORDER BY ip.id DESC
    LIMIT 50
");

// =====================================
// CURRENT INVENTORY VALUATION
// =====================================

$inventoryValueQuery = $conn->query("
    SELECT
        COUNT(*) AS ingredient_count,
        COALESCE(SUM(current_stock * cost_per_unit), 0) AS inventory_value
    FROM ingredients
    WHERE is_active = 1
");

$inventoryValueData = $inventoryValueQuery
    ? $inventoryValueQuery->fetch_assoc()
    : [];

$currentInventoryValue = (float)(
    $inventoryValueData['inventory_value'] ?? 0
);

$currentIngredientCount = (int)(
    $inventoryValueData['ingredient_count'] ?? 0
);

// =====================================
// MOVEMENT LABELS
// =====================================

$movementTypes = [
    'Purchase' => 'Purchases',
    'Usage' => 'Usage',
    'Wastage' => 'Wastage',
    'Adjustment' => 'Adjustments',
    'Restoration' => 'Restorations'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory Reports | CafeNest</title>

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
    flex-wrap: wrap;
    gap: 15px;
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
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    text-decoration: none;
    padding: 11px 15px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    font-size: 14px;
    font-weight: bold;
    border: none;
    cursor: pointer;
}

.btn.secondary {
    background: #475569;
}

.btn.green {
    background: #059669;
}

.filter-panel,
.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.filter-panel h2,
.panel h2 {
    font-size: 19px;
    margin: 0 0 16px;
}

.filter-form {
    display: flex;
    align-items: end;
    gap: 15px;
    flex-wrap: wrap;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    font-size: 13px;
    color: #475569;
    font-weight: bold;
}

.form-group input {
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 14px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, .04);
}

.card h3 {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 14px;
}

.card .value {
    font-size: 24px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.card .sub {
    color: #64748b;
    font-size: 13px;
    margin-top: 8px;
}

.blue {
    border-left: 4px solid #2563eb;
}

.green {
    border-left: 4px solid #10b981;
}

.orange {
    border-left: 4px solid #f97316;
}

.red {
    border-left: 4px solid #ef4444;
}

.purple {
    border-left: 4px solid #8b5cf6;
}

.movement-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.movement-card {
    background: white;
    padding: 16px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.movement-card h3 {
    font-size: 14px;
    margin: 0 0 10px;
}

.movement-card .count {
    font-size: 22px;
    font-weight: bold;
}

.movement-card p {
    font-size: 12px;
    color: #64748b;
    margin: 6px 0 0;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th,
td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

th {
    background: #f1f5f9;
    color: #475569;
    white-space: nowrap;
}

tr:last-child td {
    border-bottom: none;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.badge.Purchase {
    background: #dcfce7;
    color: #166534;
}

.badge.Usage {
    background: #dbeafe;
    color: #1d4ed8;
}

.badge.Wastage {
    background: #fee2e2;
    color: #991b1b;
}

.badge.Adjustment {
    background: #fef3c7;
    color: #92400e;
}

.badge.Restoration {
    background: #ede9fe;
    color: #6d28d9;
}

.empty {
    text-align: center;
    padding: 22px;
    color: #64748b;
}

.danger-text {
    color: #dc2626;
    font-weight: bold;
}

.footer-note {
    color: #64748b;
    font-size: 13px;
    margin-top: 12px;
}

@media(max-width: 1100px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .movement-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media(max-width: 700px) {
    .container {
        width: 94%;
        margin: 20px auto;
    }

    .header h1 {
        font-size: 23px;
    }

    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .card {
        padding: 15px;
    }

    .card .value {
        font-size: 20px;
    }

    .movement-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filter-form {
        align-items: stretch;
        flex-direction: column;
    }

    .form-group input {
        width: 100%;
    }

    .filter-form .btn {
        text-align: center;
    }
}

@media(max-width: 400px) {
    .grid,
    .movement-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    body {
        background: white;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .header-actions,
    .filter-panel {
        display: none;
    }

    .panel,
    .card,
    .movement-card {
        box-shadow: none;
        break-inside: avoid;
    }

    body {
        font-size: 12px;
    }
}

</style>
</head>

<body>

<div class="container">

<!-- HEADER -->

<div class="header">

    <div>
        <h1>Inventory Reports</h1>
        <p>Inventory performance, purchasing, stock movements and wastage analysis.</p>
    </div>

    <div class="header-actions">
        <a href="inventory_dashboard.php" class="btn secondary">Inventory Dashboard</a>
        <a href="index.php" class="btn secondary">Admin Home</a>
        <button class="btn green" onclick="window.print()">Print Report</button>
    </div>

</div>

<!-- FILTER -->

<div class="filter-panel">

    <h2>Filter Report Period</h2>

    <form method="GET" class="filter-form">

        <div class="form-group">
            <label for="start_date">From Date</label>
            <input
                type="date"
                name="start_date"
                id="start_date"
                value="<?= ir_escape($startDate) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="end_date">To Date</label>
            <input
                type="date"
                name="end_date"
                id="end_date"
                value="<?= ir_escape($endDate) ?>"
                required
            >
        </div>

        <button class="btn" type="submit">Generate Report</button>

        <a class="btn secondary" href="inventory_reports.php">Reset</a>

    </form>

</div>

<!-- SUMMARY CARDS -->

<h2>Report Summary</h2>

<div class="grid">

    <div class="card blue">
        <h3>Total Stock Movements</h3>
        <div class="value"><?= $totalMovements ?></div>
        <div class="sub">
            <?= number_format($totalMovementQuantity, 3) ?> units recorded
        </div>
    </div>

    <div class="card green">
        <h3>Recorded Purchases</h3>
        <div class="value"><?= ir_money($purchaseAmount) ?></div>
        <div class="sub"><?= $purchaseCount ?> purchase records</div>
    </div>

    <div class="card red">
        <h3>Estimated Wastage Cost</h3>
        <div class="value"><?= ir_money($wastageCost) ?></div>
        <div class="sub">
            <?= number_format($wastedQuantity, 3) ?> units wasted
        </div>
    </div>

    <div class="card purple">
        <h3>Current Inventory Value</h3>
        <div class="value"><?= ir_money($currentInventoryValue) ?></div>
        <div class="sub"><?= $currentIngredientCount ?> active ingredients</div>
    </div>

</div>

<!-- STOCK MOVEMENTS -->

<h2>Stock Movement Breakdown</h2>

<div class="movement-grid">

    <?php foreach ($movementTypes as $type => $label): ?>

        <?php
        $movement = $movementData[$type] ?? [];
        $count = (int)($movement['movement_count'] ?? 0);
        $quantity = (float)($movement['total_quantity'] ?? 0);
        ?>

        <div class="movement-card">

            <h3><?= ir_escape($label) ?></h3>

            <div class="count"><?= $count ?> records</div>

            <p><?= number_format($quantity, 3) ?> units</p>

        </div>

    <?php endforeach; ?>

</div>

<!-- WASTAGE ANALYSIS -->

<div class="panel">

    <h2>Wastage Analysis</h2>

    <p>
        Estimated wastage value is calculated using the ingredient's current
        cost per unit. This is an estimate, not historical purchase pricing.
    </p>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Quantity Wasted</th>
                    <th>Unit</th>
                    <th>Current Unit Cost</th>
                    <th>Estimated Cost</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($topWasted && $topWasted->num_rows > 0): ?>

                <?php while ($row = $topWasted->fetch_assoc()): ?>

                    <tr>
                        <td><?= ir_escape($row['name']) ?></td>

                        <td class="danger-text">
                            <?= number_format((float)$row['wasted_quantity'], 3) ?>
                        </td>

                        <td><?= ir_escape($row['unit']) ?></td>

                        <td><?= ir_money($row['cost_per_unit']) ?></td>

                        <td>
                            <strong><?= ir_money($row['estimated_cost']) ?></strong>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" class="empty">
                        No wastage recorded for this date range.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- PURCHASE REPORT -->

<div class="panel">

    <h2>Purchase Report</h2>

    <p>
        Total quantity purchased:
        <strong><?= number_format($purchaseQuantity, 3) ?></strong>
    </p>

    <p>
        Total purchase amount:
        <strong><?= ir_money($purchaseAmount) ?></strong>
    </p>

    <p>
        Number of purchase records:
        <strong><?= $purchaseCount ?></strong>
    </p>

</div>

<!-- STOCK MOVEMENT DETAILS -->

<div class="panel">

    <h2>Recent Stock Movements</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Movement</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($movementDetails && $movementDetails->num_rows > 0): ?>

                <?php while ($row = $movementDetails->fetch_assoc()): ?>

                    <tr>

                        <td><?= ir_escape($row['ingredient_name']) ?></td>

                        <td>
                            <span class="badge <?= ir_escape($row['movement_type']) ?>">
                                <?= ir_escape($row['movement_type']) ?>
                            </span>
                        </td>

                        <td>
                            <?= number_format((float)$row['quantity_change'], 3) ?>
                        </td>

                        <td><?= ir_escape($row['unit']) ?></td>

                        <td><?= ir_escape($row['note'] ?: '—') ?></td>

                        <td>
                            <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        No stock movements found for this date range.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <p class="footer-note">
        Showing up to 50 stock movement records for the selected period.
    </p>

</div>

<!-- PURCHASE HISTORY -->

<div class="panel">

    <h2>Purchase Records</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Supplier</th>
                    <th>Quantity</th>
                    <th>Total Cost</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($purchaseDetails && $purchaseDetails->num_rows > 0): ?>

                <?php while ($row = $purchaseDetails->fetch_assoc()): ?>

                    <tr>

                        <td>#<?= (int)$row['id'] ?></td>

                        <td><?= ir_escape($row['supplier_name'] ?? 'Not specified') ?></td>

                        <td><?= number_format((float)$row['quantity'], 3) ?></td>

                        <td><?= ir_money($row['total_cost']) ?></td>

                        <td>
                            <?= date('d M Y, h:i A', strtotime($row['purchased_at'])) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" class="empty">
                        No purchases found for this date range.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <p class="footer-note">
        Showing up to 50 purchase records for the selected period.
    </p>

</div>

</div>

</body>
</html>
