
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sph_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sph_money($value) {
    return '₹' . number_format((float)$value, 2);
}

// =====================================
// FILTERS
// =====================================

$supplierId = (int)($_GET['supplier_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

function sph_valid_date($date) {
    if ($date === '') {
        return true;
    }

    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (!sph_valid_date($dateFrom)) {
    $dateFrom = '';
}

if (!sph_valid_date($dateTo)) {
    $dateTo = '';
}

if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    $dateTo = '';
}

// =====================================
// SUPPLIER DROPDOWN
// =====================================

$supplierQuery = $conn->query("
    SELECT id, supplier_name, company_name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY supplier_name ASC
");

$suppliers = [];

while ($supplier = $supplierQuery->fetch_assoc()) {
    $suppliers[] = $supplier;
}

// =====================================
// FILTER CONDITIONS
// =====================================

$conditions = [];
$params = [];
$types = '';

if ($supplierId > 0) {
    $conditions[] = "ip.supplier_id = ?";
    $params[] = $supplierId;
    $types .= 'i';
}

if ($search !== '') {
    $conditions[] = "(
        i.name LIKE ?
        OR s.supplier_name LIKE ?
        OR s.company_name LIKE ?
        OR ip.note LIKE ?
    )";

    $searchTerm = '%' . $search . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $types .= 'ssss';
}

if ($dateFrom !== '') {
    $conditions[] = "DATE(ip.purchased_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $conditions[] = "DATE(ip.purchased_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereSQL = '';

if (!empty($conditions)) {
    $whereSQL = ' WHERE ' . implode(' AND ', $conditions);
}

// =====================================
// BIND PARAMETER HELPER
// =====================================

function sph_bind_params($stmt, $types, &$params) {

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
// SUMMARY
// =====================================

$summarySQL = "
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(ip.quantity), 0) AS total_quantity,
        COALESCE(SUM(ip.total_cost), 0) AS total_spending,
        COALESCE(AVG(ip.total_cost), 0) AS average_purchase
    FROM inventory_purchases ip
    INNER JOIN suppliers s ON s.id = ip.supplier_id
    INNER JOIN ingredients i ON i.id = ip.ingredient_id
    $whereSQL
";

$summaryStmt = $conn->prepare($summarySQL);

$summaryParams = $params;
sph_bind_params($summaryStmt, $types, $summaryParams);

$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();

$totalPurchases = (int)$summary['purchase_count'];
$totalQuantity = (float)$summary['total_quantity'];
$totalSpending = (float)$summary['total_spending'];
$averagePurchase = (float)$summary['average_purchase'];

// =====================================
// PAGINATION
// =====================================

$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalPurchases / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// =====================================
// PURCHASE HISTORY
// =====================================

$historySQL = "
    SELECT
        ip.id,
        ip.supplier_id,
        ip.ingredient_id,
        ip.quantity,
        ip.total_cost,
        ip.note,
        ip.purchased_at,
        s.supplier_name,
        s.company_name,
        i.name AS ingredient_name,
        i.unit
    FROM inventory_purchases ip
    INNER JOIN suppliers s ON s.id = ip.supplier_id
    INNER JOIN ingredients i ON i.id = ip.ingredient_id
    $whereSQL
    ORDER BY ip.purchased_at DESC, ip.id DESC
    LIMIT $perPage OFFSET $offset
";

$historyStmt = $conn->prepare($historySQL);

$historyParams = $params;
sph_bind_params($historyStmt, $types, $historyParams);

$historyStmt->execute();
$historyResult = $historyStmt->get_result();

// =====================================
// PAGINATION URL
// =====================================

function sph_page_url($pageNumber) {
    $query = $_GET;
    $query['page'] = $pageNumber;

    return '?' . http_build_query($query);
}

// =====================================
// SELECTED SUPPLIER LABEL
// =====================================

$selectedSupplierName = 'All Suppliers';

foreach ($suppliers as $supplier) {
    if ((int)$supplier['id'] === $supplierId) {
        $selectedSupplierName = $supplier['supplier_name'];
        break;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Supplier Purchase History | CafeNest</title>

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
    background: #2563eb;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    cursor: pointer;
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
    border: 1px solid #e2e8f0;
    border-radius: 12px;
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
    color: #64748b;
    font-size: 13px;
    margin-top: 8px;
}

.card.blue {
    border-left: 4px solid #2563eb;
}

.card.green {
    border-left: 4px solid #059669;
}

.card.orange {
    border-left: 4px solid #ea580c;
}

.card.purple {
    border-left: 4px solid #8b5cf6;
}

.panel {
    background: white;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-top: 20px;
}

.panel h2 {
    font-size: 19px;
    margin: 0 0 18px;
}

.filters {
    display: grid;
    grid-template-columns: 1.5fr 2fr 1fr 1fr auto auto;
    gap: 12px;
    align-items: end;
}

.field label {
    display: block;
    margin-bottom: 7px;
    color: #475569;
    font-size: 13px;
    font-weight: bold;
}

.field input,
.field select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    background: white;
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
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #475569;
    white-space: nowrap;
}

.supplier {
    font-weight: bold;
}

.company {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}

.amount {
    font-weight: bold;
    color: #047857;
}

.note {
    color: #64748b;
    max-width: 250px;
    overflow-wrap: anywhere;
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
    opacity: .4;
}

.result-info {
    color: #64748b;
    font-size: 13px;
    margin-top: 15px;
}

@media(max-width: 1100px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filters {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media(max-width: 650px) {
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

    .card,
    .panel {
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
        <h1>Supplier Purchase History</h1>
        <p>
            Purchase records for:
            <strong><?= sph_escape($selectedSupplierName) ?></strong>
        </p>
    </div>

    <div class="actions">

        <a href="supplier_performance.php" class="btn gray">
            Supplier Performance
        </a>

        <a href="inventory_dashboard.php" class="btn green">
            Inventory Dashboard
        </a>

        <button onclick="window.print()" class="btn">
            Print History
        </button>

    </div>

</div>

<!-- SUMMARY -->

<div class="summary-grid">

    <div class="card blue">
        <h3>Total Purchases</h3>
        <div class="value"><?= number_format($totalPurchases) ?></div>
        <div class="subtitle">Matching purchase records</div>
    </div>

    <div class="card green">
        <h3>Total Spending</h3>
        <div class="value"><?= sph_money($totalSpending) ?></div>
        <div class="subtitle">Purchase amount</div>
    </div>

    <div class="card orange">
        <h3>Total Quantity</h3>
        <div class="value"><?= number_format($totalQuantity, 3) ?></div>
        <div class="subtitle">Combined recorded quantity</div>
    </div>

    <div class="card purple">
        <h3>Average Purchase</h3>
        <div class="value"><?= sph_money($averagePurchase) ?></div>
        <div class="subtitle">Average cost per purchase record</div>
    </div>

</div>

<!-- FILTERS -->

<div class="panel">

    <form method="GET" class="filters">

        <div class="field">
            <label>Supplier</label>

            <select name="supplier_id">

                <option value="0">All Suppliers</option>

                <?php foreach ($suppliers as $supplier): ?>

                    <option
                        value="<?= (int)$supplier['id'] ?>"
                        <?= $supplierId === (int)$supplier['id'] ? 'selected' : '' ?>
                    >
                        <?= sph_escape($supplier['supplier_name']) ?>
                        <?= !empty($supplier['company_name'])
                            ? ' — ' . sph_escape($supplier['company_name'])
                            : '' ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <div class="field">
            <label>Search</label>

            <input
                type="text"
                name="search"
                placeholder="Ingredient, supplier or note"
                value="<?= sph_escape($search) ?>"
            >
        </div>

        <div class="field">
            <label>From Date</label>

            <input
                type="date"
                name="date_from"
                value="<?= sph_escape($dateFrom) ?>"
            >
        </div>

        <div class="field">
            <label>To Date</label>

            <input
                type="date"
                name="date_to"
                value="<?= sph_escape($dateTo) ?>"
            >
        </div>

        <button type="submit" class="btn">
            Filter
        </button>

        <a href="supplier_purchase_history.php" class="btn gray">
            Reset
        </a>

    </form>

</div>

<!-- PURCHASE TABLE -->

<div class="panel">

    <h2>Purchase Records</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Ingredient</th>
                    <th>Quantity</th>
                    <th>Total Cost</th>
                    <th>Unit Cost</th>
                    <th>Note</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($historyResult->num_rows === 0): ?>

                <tr>
                    <td colspan="8" class="empty">
                        No purchase records found for the selected filters.
                    </td>
                </tr>

            <?php else: ?>

                <?php while ($row = $historyResult->fetch_assoc()): ?>

                    <?php
                    $quantity = (float)$row['quantity'];
                    $cost = (float)$row['total_cost'];

                    $unitCost = $quantity > 0 ? $cost / $quantity : 0;
                    ?>

                    <tr>

                        <td>#<?= (int)$row['id'] ?></td>

                        <td>
                            <?= sph_escape(date(
                                'd M Y, h:i A',
                                strtotime($row['purchased_at'])
                            )) ?>
                        </td>

                        <td>
                            <div class="supplier">
                                <?= sph_escape($row['supplier_name']) ?>
                            </div>

                            <?php if (!empty($row['company_name'])): ?>
                                <div class="company">
                                    <?= sph_escape($row['company_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= sph_escape($row['ingredient_name']) ?>
                        </td>

                        <td>
                            <?= number_format($quantity, 3) ?>
                            <?= sph_escape($row['unit']) ?>
                        </td>

                        <td class="amount">
                            <?= sph_money($cost) ?>
                        </td>

                        <td>
                            <?= sph_money($unitCost) ?>
                            / <?= sph_escape($row['unit']) ?>
                        </td>

                        <td class="note">
                            <?= sph_escape($row['note'] ?: '—') ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php endif; ?>

            </tbody>

            <tfoot>
                <tr>
                    <td colspan="4">Filtered Total</td>
                    <td><?= number_format($totalQuantity, 3) ?></td>
                    <td><?= sph_money($totalSpending) ?></td>
                    <td colspan="2">—</td>
                </tr>
            </tfoot>

        </table>

    </div>

    <div class="result-info">
        Showing page <?= $page ?> of <?= $totalPages ?> |
        <?= number_format($totalPurchases) ?> matching records
    </div>

    <!-- PAGINATION -->

    <?php if ($totalPages > 1): ?>

        <div class="pagination">

            <a
                href="<?= sph_escape(sph_page_url(1)) ?>"
                class="<?= $page <= 1 ? 'disabled' : '' ?>"
            >
                First
            </a>

            <a
                href="<?= sph_escape(sph_page_url(max(1, $page - 1))) ?>"
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
                    href="<?= sph_escape(sph_page_url($p)) ?>"
                    class="<?= $p === $page ? 'active' : '' ?>"
                >
                    <?= $p ?>
                </a>

            <?php endfor; ?>

            <a
                href="<?= sph_escape(sph_page_url(min($totalPages, $page + 1))) ?>"
                class="<?= $page >= $totalPages ? 'disabled' : '' ?>"
            >
                Next
            </a>

            <a
                href="<?= sph_escape(sph_page_url($totalPages)) ?>"
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
