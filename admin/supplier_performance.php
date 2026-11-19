
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sp_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sp_money($value) {
    return '₹' . number_format((float)$value, 2);
}

// =====================================
// SEARCH
// =====================================

$search = trim($_GET['search'] ?? '');

// =====================================
// SUPPLIER PERFORMANCE
// =====================================

$sql = "
    SELECT
        s.id,
        s.supplier_name,
        s.company_name,
        s.phone,
        s.email,
        COALESCE(p.total_spent, 0) AS total_spent,
        COALESCE(p.purchase_count, 0) AS purchase_count,
        COALESCE(p.total_quantity, 0) AS total_quantity,
        p.last_purchase_date
    FROM suppliers s
    LEFT JOIN (
        SELECT
            supplier_id,
            SUM(total_cost) AS total_spent,
            COUNT(*) AS purchase_count,
            SUM(quantity) AS total_quantity,
            MAX(purchased_at) AS last_purchase_date
        FROM inventory_purchases
        GROUP BY supplier_id
    ) p ON p.supplier_id = s.id
    WHERE s.is_active = 1
    ORDER BY total_spent DESC, s.supplier_name ASC
";

$result = $conn->query($sql);

$suppliers = [];

$totalSupplierSpend = 0;
$totalPurchaseCount = 0;
$totalPurchaseQuantity = 0;
$supplierCount = 0;

while ($row = $result->fetch_assoc()) {

    $row['total_spent'] = (float)$row['total_spent'];
    $row['purchase_count'] = (int)$row['purchase_count'];
    $row['total_quantity'] = (float)$row['total_quantity'];

    $row['average_purchase'] = $row['purchase_count'] > 0
        ? $row['total_spent'] / $row['purchase_count']
        : 0;

    $totalSupplierSpend += $row['total_spent'];
    $totalPurchaseCount += $row['purchase_count'];
    $totalPurchaseQuantity += $row['total_quantity'];

    $supplierCount++;

    $suppliers[] = $row;
}

// =====================================
// SEARCH FILTER
// =====================================

$filteredSuppliers = [];

foreach ($suppliers as $supplier) {

    $searchText = strtolower(
        $supplier['supplier_name'] . ' ' .
        $supplier['company_name'] . ' ' .
        $supplier['phone'] . ' ' .
        $supplier['email']
    );

    if ($search !== '' && strpos($searchText, strtolower($search)) === false) {
        continue;
    }

    $filteredSuppliers[] = $supplier;
}

// =====================================
// TOP SUPPLIER
// =====================================

$topSupplier = null;

foreach ($suppliers as $supplier) {
    if ($supplier['purchase_count'] > 0) {
        $topSupplier = $supplier;
        break;
    }
}

// =====================================
// RECENT PURCHASES
// =====================================

$recentQuery = $conn->query("
    SELECT
        ip.id,
        ip.supplier_id,
        ip.quantity,
        ip.total_cost,
        ip.purchased_at,
        s.supplier_name
    FROM inventory_purchases ip
    LEFT JOIN suppliers s ON s.id = ip.supplier_id
    ORDER BY ip.purchased_at DESC, ip.id DESC
    LIMIT 10
");

$recentPurchases = [];

while ($row = $recentQuery->fetch_assoc()) {
    $recentPurchases[] = $row;
}

// =====================================
// CHART DATA
// =====================================

$chartLabels = [];
$chartSpending = [];

foreach ($suppliers as $supplier) {
    if ($supplier['total_spent'] > 0) {
        $chartLabels[] = $supplier['supplier_name'];
        $chartSpending[] = $supplier['total_spent'];
    }
}

// =====================================
// FILTERED TOTALS
// =====================================

$filteredSpend = 0;
$filteredTransactions = 0;

foreach ($filteredSuppliers as $supplier) {
    $filteredSpend += $supplier['total_spent'];
    $filteredTransactions += $supplier['purchase_count'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Supplier Performance | CafeNest</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    flex-wrap: wrap;
    gap: 10px;
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
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.panel h2 {
    font-size: 19px;
    margin: 0 0 18px;
}

.chart-box {
    position: relative;
    height: 360px;
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.search-form input {
    flex: 1;
    min-width: 220px;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
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
    text-align: left;
    padding: 13px 12px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #475569;
    white-space: nowrap;
}

.rank {
    font-weight: bold;
    color: #64748b;
}

.supplier-name {
    font-weight: bold;
}

.company {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}

.spending {
    font-weight: bold;
    color: #047857;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

tfoot td {
    font-weight: bold;
    background: #f8fafc;
}

.top-supplier {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
}

.top-supplier h3 {
    margin: 0 0 8px;
    color: #1d4ed8;
}

.top-supplier p {
    margin: 5px 0;
}

.contact {
    font-size: 13px;
    color: #64748b;
    overflow-wrap: anywhere;
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
        font-size: 20px;
    }

    .chart-box {
        height: 280px;
    }
}

@media print {
    body {
        background: white;
    }

    .actions,
    .search-form {
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
        <h1>Supplier Performance</h1>
        <p>Supplier spending, purchasing activity and comparisons.</p>
    </div>

    <div class="actions">
        <a href="inventory_dashboard.php" class="btn gray">
            Inventory Dashboard
        </a>

        <a href="suppliers.php" class="btn green">
            Manage Suppliers
        </a>

        <button onclick="window.print()" class="btn">
            Print Report
        </button>
    </div>

</div>

<!-- SUMMARY -->

<div class="summary-grid">

    <div class="card blue">
        <h3>Active Suppliers</h3>
        <div class="value"><?= number_format($supplierCount) ?></div>
        <div class="subtitle">Currently active suppliers</div>
    </div>

    <div class="card green">
        <h3>Total Purchase Spending</h3>
        <div class="value"><?= sp_money($totalSupplierSpend) ?></div>
        <div class="subtitle">Recorded supplier purchases</div>
    </div>

    <div class="card orange">
        <h3>Purchase Transactions</h3>
        <div class="value"><?= number_format($totalPurchaseCount) ?></div>
        <div class="subtitle">Recorded purchases</div>
    </div>

    <div class="card purple">
        <h3>Average Purchase</h3>
        <div class="value">
            <?= sp_money(
                $totalPurchaseCount > 0
                ? $totalSupplierSpend / $totalPurchaseCount
                : 0
            ) ?>
        </div>
        <div class="subtitle">Per transaction</div>
    </div>

</div>

<!-- TOP SUPPLIER -->

<div class="panel">

    <h2>Top Supplier</h2>

    <?php if ($topSupplier): ?>

        <div class="top-supplier">

            <h3><?= sp_escape($topSupplier['supplier_name']) ?></h3>

            <?php if (!empty($topSupplier['company_name'])): ?>
                <p><?= sp_escape($topSupplier['company_name']) ?></p>
            <?php endif; ?>

            <p>
                <strong>Total Spending:</strong>
                <?= sp_money($topSupplier['total_spent']) ?>
            </p>

            <p>
                <strong>Purchase Transactions:</strong>
                <?= number_format($topSupplier['purchase_count']) ?>
            </p>

            <p>
                <strong>Average Purchase:</strong>
                <?= sp_money($topSupplier['average_purchase']) ?>
            </p>

            <?php if (!empty($topSupplier['last_purchase_date'])): ?>
                <p>
                    <strong>Last Purchase:</strong>
                    <?= sp_escape(date(
                        'd M Y',
                        strtotime($topSupplier['last_purchase_date'])
                    )) ?>
                </p>
            <?php endif; ?>

        </div>

    <?php else: ?>

        <p class="empty">No supplier purchases have been recorded yet.</p>

    <?php endif; ?>

</div>

<!-- SPENDING CHART -->

<div class="panel">

    <h2>Supplier Spending Comparison</h2>

    <div class="chart-box">
        <canvas id="supplierChart"></canvas>
    </div>

</div>

<!-- SUPPLIER TABLE -->

<div class="panel">

    <h2>Supplier Comparison</h2>

    <form method="GET" class="search-form">

        <input
            type="text"
            name="search"
            placeholder="Search supplier, company, phone or email"
            value="<?= sp_escape($search) ?>"
        >

        <button type="submit" class="btn">Search</button>

        <a href="supplier_performance.php" class="btn gray">Reset</a>

    </form>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Supplier</th>
                    <th>Contact</th>
                    <th>Total Spent</th>
                    <th>Transactions</th>
                    <th>Average Purchase</th>
                    <th>Last Purchase</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($filteredSuppliers)): ?>

                <tr>
                    <td colspan="7" class="empty">
                        No suppliers found.
                    </td>
                </tr>

            <?php else: ?>

                <?php $rank = 0; ?>

                <?php foreach ($filteredSuppliers as $supplier): ?>

                    <?php $rank++; ?>

                    <tr>

                        <td class="rank">#<?= $rank ?></td>

                        <td>
                            <div class="supplier-name">
                                <?= sp_escape($supplier['supplier_name']) ?>
                            </div>

                            <?php if (!empty($supplier['company_name'])): ?>
                                <div class="company">
                                    <?= sp_escape($supplier['company_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="contact">

                            <?php if (!empty($supplier['phone'])): ?>
                                <?= sp_escape($supplier['phone']) ?><br>
                            <?php endif; ?>

                            <?php if (!empty($supplier['email'])): ?>
                                <?= sp_escape($supplier['email']) ?>
                            <?php endif; ?>

                            <?php if (empty($supplier['phone']) && empty($supplier['email'])): ?>
                                —
                            <?php endif; ?>

                        </td>

                        <td class="spending">
                            <?= sp_money($supplier['total_spent']) ?>
                        </td>

                        <td>
                            <?= number_format($supplier['purchase_count']) ?>
                        </td>

                        <td>
                            <?= sp_money($supplier['average_purchase']) ?>
                        </td>

                        <td>
                            <?php if ($supplier['last_purchase_date']): ?>

                                <?= sp_escape(date(
                                    'd M Y',
                                    strtotime($supplier['last_purchase_date'])
                                )) ?>

                            <?php else: ?>

                                <span class="company">No purchases</span>

                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

            <tfoot>
                <tr>
                    <td colspan="3">All Active Suppliers</td>
                    <td><?= sp_money($totalSupplierSpend) ?></td>
                    <td><?= number_format($totalPurchaseCount) ?></td>
                    <td>
                        <?= sp_money(
                            $totalPurchaseCount > 0
                            ? $totalSupplierSpend / $totalPurchaseCount
                            : 0
                        ) ?>
                    </td>
                    <td>—</td>
                </tr>
            </tfoot>

        </table>

    </div>

</div>

<!-- RECENT PURCHASES -->

<div class="panel">

    <h2>Recent Supplier Purchases</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Supplier</th>
                    <th>Quantity</th>
                    <th>Purchase Amount</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($recentPurchases)): ?>

                <tr>
                    <td colspan="5" class="empty">
                        No purchases recorded yet.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($recentPurchases as $purchase): ?>

                    <tr>

                        <td>#<?= (int)$purchase['id'] ?></td>

                        <td>
                            <?= sp_escape(
                                $purchase['supplier_name'] ?: 'Unassigned Supplier'
                            ) ?>
                        </td>

                        <td>
                            <?= number_format((float)$purchase['quantity'], 3) ?>
                        </td>

                        <td class="spending">
                            <?= sp_money($purchase['total_cost']) ?>
                        </td>

                        <td>
                            <?= sp_escape(date(
                                'd M Y, h:i A',
                                strtotime($purchase['purchased_at'])
                            )) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>

<script>

// =====================================
// SUPPLIER SPENDING CHART
// =====================================

const supplierLabels = <?= json_encode($chartLabels) ?>;
const supplierSpending = <?= json_encode($chartSpending) ?>;

new Chart(document.getElementById('supplierChart'), {
    type: 'bar',

    data: {
        labels: supplierLabels,
        datasets: [{
            label: 'Total Purchase Spending (INR)',
            data: supplierSpending,
            backgroundColor: '#2563eb',
            borderRadius: 6
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                display: false
            }
        },

        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' + value;
                    }
                }
            }
        }
    }
});

</script>

</body>
</html>
