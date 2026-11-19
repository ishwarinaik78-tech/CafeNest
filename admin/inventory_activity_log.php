
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function activity_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================
// FILTERS
// =====================================

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$allowedTypes = [
    'Purchase',
    'Usage',
    'Wastage',
    'Adjustment',
    'Restoration'
];

if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}

function activity_valid_date($date) {
    if ($date === '') {
        return true;
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);

    return $d && $d->format('Y-m-d') === $date;
}

if (!activity_valid_date($dateFrom)) {
    $dateFrom = '';
}

if (!activity_valid_date($dateTo)) {
    $dateTo = '';
}

if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    $dateTo = '';
}

// =====================================
// BUILD FILTER CONDITIONS
// =====================================

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "i.name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= 's';
}

if ($type !== '') {
    $conditions[] = "sm.movement_type = ?";
    $params[] = $type;
    $types .= 's';
}

if ($dateFrom !== '') {
    $conditions[] = "DATE(sm.created_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $conditions[] = "DATE(sm.created_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereSQL = '';

if (!empty($conditions)) {
    $whereSQL = ' WHERE ' . implode(' AND ', $conditions);
}

// =====================================
// DYNAMIC BIND HELPER
// =====================================

function activity_bind_params($stmt, $types, &$params) {
    if (empty($params)) {
        return;
    }

    $bindValues = [];
    $bindValues[] = $types;

    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

// =====================================
// PAGINATION
// =====================================

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

// =====================================
// COUNT MOVEMENTS
// =====================================

$countSQL = "
    SELECT COUNT(*) AS total
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    $whereSQL
";

$countStmt = $conn->prepare($countSQL);

$countParams = $params;
activity_bind_params($countStmt, $types, $countParams);

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = (int)$countResult->fetch_assoc()['total'];

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// =====================================
// FETCH MOVEMENT HISTORY
// =====================================

$sql = "
    SELECT
        sm.id,
        sm.ingredient_id,
        sm.movement_type,
        sm.quantity_change,
        sm.note,
        sm.created_at,
        i.name AS ingredient_name,
        i.unit,
        i.current_stock
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    $whereSQL
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $conn->prepare($sql);

$queryParams = $params;
activity_bind_params($stmt, $types, $queryParams);

$stmt->execute();
$result = $stmt->get_result();

// =====================================
// MOVEMENT TYPE COUNTS
// =====================================

$movementCounts = [];

$countTypesQuery = $conn->query("
    SELECT
        movement_type,
        COUNT(*) AS total
    FROM stock_movements
    GROUP BY movement_type
");

while ($row = $countTypesQuery->fetch_assoc()) {
    $movementCounts[$row['movement_type']] = (int)$row['total'];
}

// =====================================
// FILTER URL HELPER
// =====================================

function activity_url($pageNumber) {
    $params = $_GET;
    $params['page'] = $pageNumber;

    return '?' . http_build_query($params);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory Activity Log | CafeNest</title>

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
    flex-wrap: wrap;
    gap: 15px;
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
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.btn.gray {
    background: #475569;
}

.btn.green {
    background: #059669;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 15px;
}

.summary-card {
    background: white;
    padding: 18px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.summary-card h3 {
    color: #64748b;
    font-size: 13px;
    margin: 0 0 10px;
}

.summary-value {
    font-size: 25px;
    font-weight: bold;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.filters {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
    gap: 12px;
    align-items: end;
}

.field label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: bold;
    color: #475569;
}

.field input,
.field select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    outline: none;
    font-size: 14px;
}

.field input:focus,
.field select:focus {
    border-color: #2563eb;
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

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.purchase {
    background: #dcfce7;
    color: #166534;
}

.usage {
    background: #dbeafe;
    color: #1d4ed8;
}

.wastage {
    background: #fee2e2;
    color: #b91c1c;
}

.adjustment {
    background: #fef3c7;
    color: #92400e;
}

.restoration {
    background: #ede9fe;
    color: #6d28d9;
}

.positive {
    color: #059669;
    font-weight: bold;
}

.negative {
    color: #dc2626;
    font-weight: bold;
}

.note-cell {
    max-width: 260px;
    overflow-wrap: anywhere;
    color: #64748b;
}

.empty {
    padding: 35px;
    text-align: center;
    color: #64748b;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.pagination a {
    padding: 9px 13px;
    background: white;
    border: 1px solid #cbd5e1;
    color: #334155;
    text-decoration: none;
    border-radius: 7px;
}

.pagination a.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.pagination a.disabled {
    pointer-events: none;
    opacity: .45;
}

.result-info {
    color: #64748b;
    font-size: 13px;
    margin-top: 15px;
}

@media(max-width: 1100px) {
    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .filters {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media(max-width: 700px) {
    .container {
        width: 94%;
        margin: 20px auto;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .summary-value {
        font-size: 21px;
    }

    .filters {
        grid-template-columns: 1fr 1fr;
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
    .pagination {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .panel,
    .summary-card {
        box-shadow: none;
        break-inside: avoid;
    }

    table {
        min-width: 0;
    }
}

</style>
</head>

<body>

<div class="container">

<!-- HEADER -->

<div class="header">

    <div>
        <h1>Inventory Activity Log</h1>
        <p>Track every recorded inventory movement in CafeNest.</p>
    </div>

    <div class="actions">
        <a href="inventory_dashboard.php" class="btn gray">
            Inventory Dashboard
        </a>

        <a href="inventory_reports.php" class="btn green">
            Inventory Reports
        </a>

        <button class="btn" onclick="window.print()">
            Print Log
        </button>
    </div>

</div>

<!-- MOVEMENT SUMMARY -->

<div class="summary-grid">

    <div class="summary-card">
        <h3>All Movements</h3>
        <div class="summary-value">
            <?= number_format(array_sum($movementCounts)) ?>
        </div>
    </div>

    <div class="summary-card">
        <h3>Purchases</h3>
        <div class="summary-value">
            <?= number_format($movementCounts['Purchase'] ?? 0) ?>
        </div>
    </div>

    <div class="summary-card">
        <h3>Usage</h3>
        <div class="summary-value">
            <?= number_format($movementCounts['Usage'] ?? 0) ?>
        </div>
    </div>

    <div class="summary-card">
        <h3>Wastage</h3>
        <div class="summary-value">
            <?= number_format($movementCounts['Wastage'] ?? 0) ?>
        </div>
    </div>

    <div class="summary-card">
        <h3>Adjustments</h3>
        <div class="summary-value">
            <?= number_format($movementCounts['Adjustment'] ?? 0) ?>
        </div>
    </div>

</div>

<!-- FILTERS -->

<div class="panel">

    <form method="GET" class="filters">

        <div class="field">
            <label>Search Ingredient</label>
            <input
                type="text"
                name="search"
                placeholder="Enter ingredient name"
                value="<?= activity_escape($search) ?>"
            >
        </div>

        <div class="field">
            <label>Movement Type</label>

            <select name="type">
                <option value="">All Types</option>

                <?php foreach ($allowedTypes as $movementType): ?>
                    <option
                        value="<?= activity_escape($movementType) ?>"
                        <?= $type === $movementType ? 'selected' : '' ?>
                    >
                        <?= activity_escape($movementType) ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </div>

        <div class="field">
            <label>From Date</label>
            <input
                type="date"
                name="date_from"
                value="<?= activity_escape($dateFrom) ?>"
            >
        </div>

        <div class="field">
            <label>To Date</label>
            <input
                type="date"
                name="date_to"
                value="<?= activity_escape($dateTo) ?>"
            >
        </div>

        <button type="submit" class="btn">
            Apply Filters
        </button>

        <a href="inventory_activity_log.php" class="btn gray">
            Reset
        </a>

    </form>

</div>

<!-- ACTIVITY TABLE -->

<div class="panel">

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Movement ID</th>
                    <th>Date & Time</th>
                    <th>Ingredient</th>
                    <th>Movement Type</th>
                    <th>Quantity Change</th>
                    <th>Current Stock</th>
                    <th>Note</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows === 0): ?>

                <tr>
                    <td colspan="7" class="empty">
                        No inventory movements found.
                    </td>
                </tr>

            <?php else: ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                    $movementClass = strtolower($row['movement_type']);

                    $quantity = (float)$row['quantity_change'];

                    $quantityClass = $quantity >= 0 ? 'positive' : 'negative';

                    $displayQuantity = ($quantity > 0 ? '+' : '') .
                        number_format($quantity, 3) .
                        ' ' . $row['unit'];
                    ?>

                    <tr>

                        <td>#<?= (int)$row['id'] ?></td>

                        <td>
                            <?= activity_escape(date(
                                'd M Y, h:i A',
                                strtotime($row['created_at'])
                            )) ?>
                        </td>

                        <td>
                            <strong><?= activity_escape($row['ingredient_name']) ?></strong>
                        </td>

                        <td>
                            <span class="badge <?= activity_escape($movementClass) ?>">
                                <?= activity_escape($row['movement_type']) ?>
                            </span>
                        </td>

                        <td class="<?= $quantityClass ?>">
                            <?= activity_escape($displayQuantity) ?>
                        </td>

                        <td>
                            <?= number_format((float)$row['current_stock'], 3) ?>
                            <?= activity_escape($row['unit']) ?>
                        </td>

                        <td class="note-cell">
                            <?= activity_escape($row['note'] ?: '—') ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- RESULT COUNT -->

    <div class="result-info">
        Total matching movements: <strong><?= number_format($totalRecords) ?></strong>
        &nbsp;|&nbsp;
        Page <?= $page ?> of <?= $totalPages ?>
    </div>

    <!-- PAGINATION -->

    <?php if ($totalPages > 1): ?>

        <div class="pagination">

            <a
                href="<?= activity_escape(activity_url(1)) ?>"
                class="<?= $page <= 1 ? 'disabled' : '' ?>"
            >
                First
            </a>

            <a
                href="<?= activity_escape(activity_url(max(1, $page - 1))) ?>"
                class="<?= $page <= 1 ? 'disabled' : '' ?>"
            >
                Previous
            </a>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>

                <a
                    href="<?= activity_escape(activity_url($p)) ?>"
                    class="<?= $p === $page ? 'active' : '' ?>"
                >
                    <?= $p ?>
                </a>

            <?php endfor; ?>

            <a
                href="<?= activity_escape(activity_url(min($totalPages, $page + 1))) ?>"
                class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
            >
                Next
            </a>

            <a
                href="<?= activity_escape(activity_url($totalPages)) ?>"
                class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
            >
                Last
            </a>

        </div>

    <?php endif; ?>

</div>

</div>

</body>
</html>
