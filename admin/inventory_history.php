<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

// =====================================
// FILTERS
// =====================================

$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$allowedTypes = ['Purchase', 'Usage', 'Wastage', 'Adjustment', 'Restoration'];

if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = '';
}

// =====================================
// FETCH MOVEMENTS
// =====================================

$sql = "
    SELECT
        sm.id,
        sm.order_id,
        sm.movement_type,
        sm.quantity_change,
        sm.note,
        sm.created_at,
        i.name AS ingredient_name,
        i.unit
    FROM stock_movements sm
    JOIN ingredients i ON sm.ingredient_id = i.id
    LEFT JOIN orders o ON sm.order_id = o.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($type !== '') {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($search !== '') {
    $sql .= " AND (
        i.name LIKE ?
        OR sm.note LIKE ?
        OR CAST(sm.order_id AS CHAR) LIKE ?
    )";

    $searchTerm = "%" . $search . "%";

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $types .= "sss";
}

if ($fromDate !== '') {
    $sql .= " AND DATE(sm.created_at) >= ?";
    $params[] = $fromDate;
    $types .= "s";
}

if ($toDate !== '') {
    $sql .= " AND DATE(sm.created_at) <= ?";
    $params[] = $toDate;
    $types .= "s";
}

$sql .= " ORDER BY sm.created_at DESC, sm.id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$movements = [];

while ($row = $result->fetch_assoc()) {
    $movements[] = $row;
}

$stmt->close();

// =====================================
// STATISTICS
// =====================================

$totalMovements = count($movements);

$totalUsage = 0;
$totalRestoration = 0;
$totalPurchase = 0;
$totalWastage = 0;

$movementCounts = [
    'Purchase' => 0,
    'Usage' => 0,
    'Restoration' => 0,
    'Wastage' => 0,
    'Adjustment' => 0
];

foreach ($movements as $movement) {

    $qty = (float)$movement['quantity_change'];
    $movementType = $movement['movement_type'];

    if (isset($movementCounts[$movementType])) {
        $movementCounts[$movementType]++;
    }

    if ($movementType === 'Usage') {
        $totalUsage += abs($qty);
    } elseif ($movementType === 'Restoration') {
        $totalRestoration += abs($qty);
    } elseif ($movementType === 'Purchase') {
        $totalPurchase += abs($qty);
    } elseif ($movementType === 'Wastage') {
        $totalWastage += abs($qty);
    }
}

// =====================================
// CHART DATA
// =====================================

$maxCount = max($movementCounts);
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory History | CafeNest</title>

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
    max-width: 1400px;
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
    color: #64748b;
    margin-top: 8px;
}

.back-btn {
    text-decoration: none;
    background: #2563eb;
    color: white;
    padding: 11px 18px;
    border-radius: 9px;
    font-weight: 600;
}

.back-btn:hover {
    background: #1d4ed8;
}

/* SUMMARY CARDS */

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
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
    margin: 0 0 10px;
    font-size: 14px;
}

.stat-card h2 {
    font-size: 24px;
    margin: 0;
    overflow-wrap: anywhere;
}

.stat-blue {
    border-top: 3px solid #2563eb;
}

.stat-green {
    border-top: 3px solid #16a34a;
}

.stat-red {
    border-top: 3px solid #dc2626;
}

.stat-purple {
    border-top: 3px solid #7c3aed;
}

.stat-orange {
    border-top: 3px solid #ea580c;
}

/* ANALYTICS */

.analytics-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.04);
}

.analytics-header {
    margin-bottom: 24px;
}

.analytics-header h3 {
    margin: 0 0 8px;
    font-size: 19px;
}

.analytics-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.chart-row {
    margin-bottom: 20px;
}

.chart-row:last-child {
    margin-bottom: 0;
}

.chart-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    gap: 10px;
    font-size: 14px;
}

.chart-label strong {
    font-size: 14px;
}

.chart-track {
    height: 12px;
    background: #f1f5f9;
    border-radius: 20px;
    overflow: hidden;
}

.chart-bar {
    height: 100%;
    border-radius: 20px;
    min-width: 0;
    transition: width 0.4s ease;
}

.bar-purchase {
    background: #16a34a;
}

.bar-usage {
    background: #2563eb;
}

.bar-restoration {
    background: #7c3aed;
}

.bar-wastage {
    background: #ea580c;
}

.bar-adjustment {
    background: #64748b;
}

/* FILTERS */

.filters {
    background: white;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    margin-bottom: 20px;
}

.filters form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filters input,
.filters select {
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font-size: 14px;
    outline: none;
    background: white;
    color: #1e293b;
}

.filters input:focus,
.filters select:focus {
    border-color: #2563eb;
}

.search-input {
    flex: 1;
    min-width: 220px;
}

.date-input {
    min-width: 155px;
}

.filters select {
    min-width: 180px;
}

.filters button {
    border: none;
    background: #2563eb;
    color: white;
    padding: 12px 20px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: 600;
}

.filters button:hover {
    background: #1d4ed8;
}

.reset-btn {
    text-decoration: none;
    padding: 12px 18px;
    border-radius: 9px;
    background: #f1f5f9;
    color: #475569;
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
    min-width: 900px;
}

th, td {
    padding: 15px 18px;
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .4px;
    white-space: nowrap;
}

tbody tr:hover td {
    background: #fafcff;
}

/* BADGES */

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.purchase {
    background: #dcfce7;
    color: #166534;
}

.usage {
    background: #fee2e2;
    color: #991b1b;
}

.wastage {
    background: #ffedd5;
    color: #9a3412;
}

.adjustment {
    background: #dbeafe;
    color: #1d4ed8;
}

.restoration {
    background: #ede9fe;
    color: #6d28d9;
}

.quantity-positive {
    color: #16a34a;
    font-weight: 700;
}

.quantity-negative {
    color: #dc2626;
    font-weight: 700;
}

.empty {
    padding: 45px 20px;
    text-align: center;
    color: #64748b;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 750px) {
    .container {
        padding: 20px 14px;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .stat-card {
        padding: 17px;
    }

    .stat-card h2 {
        font-size: 21px;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .analytics-card {
        padding: 18px;
    }
}

@media (max-width: 500px) {
    .container {
        padding: 16px 10px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .stats {
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h2 {
        font-size: 19px;
    }

    .filters form {
        flex-direction: column;
        align-items: stretch;
    }

    .filters input,
    .filters select,
    .filters button,
    .reset-btn {
        width: 100%;
        min-width: 0;
    }
}
</style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1>Inventory Movement History</h1>
            <p>Track and analyse every stock movement across your cafe.</p>
        </div>

        <a href="ingredients.php" class="back-btn">
            ← Back to Inventory
        </a>

    </div>

    <!-- SUMMARY CARDS -->

    <div class="stats">

        <div class="stat-card stat-blue">
            <p>Total Movements</p>
            <h2><?= $totalMovements ?></h2>
        </div>

        <div class="stat-card stat-green">
            <p>Stock Purchased</p>
            <h2><?= number_format($totalPurchase, 2) ?></h2>
        </div>

        <div class="stat-card stat-red">
            <p>Stock Used</p>
            <h2><?= number_format($totalUsage, 2) ?></h2>
        </div>

        <div class="stat-card stat-purple">
            <p>Stock Restored</p>
            <h2><?= number_format($totalRestoration, 2) ?></h2>
        </div>

        <div class="stat-card stat-orange">
            <p>Stock Wasted</p>
            <h2><?= number_format($totalWastage, 2) ?></h2>
        </div>

    </div>

    <!-- MOVEMENT ANALYTICS -->

    <div class="analytics-card">

        <div class="analytics-header">
            <h3>Inventory Activity Overview</h3>
            <p>Number of recorded movements by type</p>
        </div>

        <?php
        $chartItems = [
            'Purchase' => 'bar-purchase',
            'Usage' => 'bar-usage',
            'Restoration' => 'bar-restoration',
            'Wastage' => 'bar-wastage',
            'Adjustment' => 'bar-adjustment'
        ];
        ?>

        <?php foreach ($chartItems as $chartType => $barClass): ?>

            <?php
            $count = $movementCounts[$chartType];

            $width = $maxCount > 0
                ? ($count / $maxCount) * 100
                : 0;
            ?>

            <div class="chart-row">

                <div class="chart-label">
                    <span><?= htmlspecialchars($chartType) ?></span>
                    <strong><?= $count ?> movements</strong>
                </div>

                <div class="chart-track">

                    <div
                        class="chart-bar <?= $barClass ?>"
                        style="width: <?= $width ?>%;"
                    ></div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <!-- FILTERS -->

    <div class="filters">

        <form method="GET">

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search ingredient, order ID or note..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <input
                type="date"
                name="from_date"
                class="date-input"
                value="<?= htmlspecialchars($fromDate) ?>"
                title="From date"
            >

            <input
                type="date"
                name="to_date"
                class="date-input"
                value="<?= htmlspecialchars($toDate) ?>"
                title="To date"
            >

            <select name="type">

                <option value="">All Movement Types</option>

                <?php foreach ($allowedTypes as $movementType): ?>

                    <option
                        value="<?= htmlspecialchars($movementType) ?>"
                        <?= $type === $movementType ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($movementType) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit">Apply Filters</button>

            <a href="inventory_history.php" class="reset-btn">Reset</a>

        </form>

    </div>

    <!-- MOVEMENT TABLE -->

    <div class="table-card">

        <div class="table-header">
            <h3>Movement Records (<?= $totalMovements ?>)</h3>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Ingredient</th>
                        <th>Movement</th>
                        <th>Quantity</th>
                        <th>Order</th>
                        <th>Notes</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($movements)): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No inventory movements found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($movements as $movement): ?>

                        <?php
                        $qty = (float)$movement['quantity_change'];
                        $movementClass = strtolower($movement['movement_type']);
                        ?>

                        <tr>

                            <td>
                                <?= date('d M Y, h:i A', strtotime($movement['created_at'])) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($movement['ingredient_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="badge <?= htmlspecialchars($movementClass) ?>">
                                    <?= htmlspecialchars($movement['movement_type']) ?>
                                </span>
                            </td>

                            <td class="<?= $qty >= 0 ? 'quantity-positive' : 'quantity-negative' ?>">
                                <?= $qty > 0 ? '+' : '' ?>
                                <?= number_format($qty, 2) ?>
                                <?= htmlspecialchars($movement['unit']) ?>
                            </td>

                            <td>
                                <?php if (!empty($movement['order_id'])): ?>
                                    #<?= (int)$movement['order_id'] ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($movement['note'] ?? '—') ?>
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