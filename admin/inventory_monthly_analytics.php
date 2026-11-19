
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function analytics_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function analytics_money($value) {
    return '₹' . number_format((float)$value, 2);
}

// =====================================
// GENERATE LAST 6 MONTHS
// =====================================

$months = [];
$monthKeys = [];

for ($i = 5; $i >= 0; $i--) {
    $date = strtotime("first day of -" . $i . " month");

    $key = date("Y-m", $date);
    $monthKeys[] = $key;

    $months[$key] = [
        'label' => date("M Y", $date),
        'purchase_amount' => 0,
        'purchase_quantity' => 0,
        'wastage_quantity' => 0,
        'wastage_records' => 0
    ];
}

// =====================================
// MONTHLY PURCHASE ANALYTICS
// =====================================

$purchaseSql = "
    SELECT
        DATE_FORMAT(purchased_at, '%Y-%m') AS month_key,
        COALESCE(SUM(total_cost), 0) AS total_amount,
        COALESCE(SUM(quantity), 0) AS total_quantity,
        COUNT(*) AS purchase_count
    FROM inventory_purchases
    WHERE purchased_at >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH), '%Y-%m-01')
      AND purchased_at < DATE_FORMAT(DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(purchased_at, '%Y-%m')
    ORDER BY month_key
";

$purchaseResult = $conn->query($purchaseSql);

while ($row = $purchaseResult->fetch_assoc()) {
    $key = $row['month_key'];

    if (isset($months[$key])) {
        $months[$key]['purchase_amount'] = (float)$row['total_amount'];
        $months[$key]['purchase_quantity'] = (float)$row['total_quantity'];
    }
}

// =====================================
// MONTHLY WASTAGE ANALYTICS
// =====================================

$wastageSql = "
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        COALESCE(SUM(ABS(quantity_change)), 0) AS wasted_quantity,
        COUNT(*) AS wastage_records
    FROM stock_movements
    WHERE movement_type = 'Wastage'
      AND created_at >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH), '%Y-%m-01')
      AND created_at < DATE_FORMAT(DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key
";

$wastageResult = $conn->query($wastageSql);

while ($row = $wastageResult->fetch_assoc()) {
    $key = $row['month_key'];

    if (isset($months[$key])) {
        $months[$key]['wastage_quantity'] = (float)$row['wasted_quantity'];
        $months[$key]['wastage_records'] = (int)$row['wastage_records'];
    }
}

// =====================================
// TOTALS
// =====================================

$totalPurchaseAmount = 0;
$totalPurchaseQuantity = 0;
$totalWastageQuantity = 0;
$totalWastageRecords = 0;

foreach ($months as $month) {
    $totalPurchaseAmount += $month['purchase_amount'];
    $totalPurchaseQuantity += $month['purchase_quantity'];
    $totalWastageQuantity += $month['wastage_quantity'];
    $totalWastageRecords += $month['wastage_records'];
}

$averageMonthlyPurchase = $totalPurchaseAmount / 6;

// =====================================
// CHART DATA
// =====================================

$chartLabels = [];
$purchaseAmounts = [];
$purchaseQuantities = [];
$wastageQuantities = [];

foreach ($months as $month) {
    $chartLabels[] = $month['label'];
    $purchaseAmounts[] = $month['purchase_amount'];
    $purchaseQuantities[] = $month['purchase_quantity'];
    $wastageQuantities[] = $month['wastage_quantity'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Monthly Inventory Analytics | CafeNest</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f8fafc;
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
    border-radius: 8px;
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
    box-shadow: 0 3px 10px rgba(15,23,42,0.04);
}

.card h3 {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 12px;
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

.purchase {
    border-left: 4px solid #2563eb;
}

.quantity {
    border-left: 4px solid #059669;
}

.wastage {
    border-left: 4px solid #ea580c;
}

.records {
    border-left: 4px solid #8b5cf6;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    margin-top: 20px;
}

.panel h2 {
    margin: 0 0 20px;
    font-size: 19px;
}

.chart-box {
    position: relative;
    width: 100%;
    height: 350px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 14px;
}

th {
    background: #f1f5f9;
    color: #475569;
    white-space: nowrap;
}

tfoot td {
    font-weight: bold;
    background: #f8fafc;
}

.empty {
    text-align: center;
    padding: 20px;
    color: #64748b;
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

    .actions {
        display: none;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .panel, .card {
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
        <h1>Monthly Inventory Analytics</h1>
        <p>Purchases and wastage trends for the last six months.</p>
    </div>

    <div class="actions">
        <a href="inventory_dashboard.php" class="btn gray">
            Inventory Dashboard
        </a>

        <a href="inventory_reports.php" class="btn green">
            Inventory Reports
        </a>

        <button class="btn" onclick="window.print()">
            Print Report
        </button>
    </div>

</div>

<!-- SUMMARY CARDS -->

<div class="summary-grid">

    <div class="card purchase">
        <h3>Total Purchase Spending</h3>
        <div class="value"><?= analytics_money($totalPurchaseAmount) ?></div>
        <div class="subtitle">Last 6 months</div>
    </div>

    <div class="card quantity">
        <h3>Total Quantity Purchased</h3>
        <div class="value"><?= number_format($totalPurchaseQuantity, 3) ?></div>
        <div class="subtitle">Recorded purchase quantity</div>
    </div>

    <div class="card wastage">
        <h3>Total Wastage</h3>
        <div class="value"><?= number_format($totalWastageQuantity, 3) ?></div>
        <div class="subtitle">Recorded wastage quantity</div>
    </div>

    <div class="card records">
        <h3>Wastage Records</h3>
        <div class="value"><?= $totalWastageRecords ?></div>
        <div class="subtitle">Last 6 months</div>
    </div>

</div>

<!-- PURCHASE SPENDING CHART -->

<div class="panel">

    <h2>Monthly Purchase Spending</h2>

    <div class="chart-box">
        <canvas id="purchaseChart"></canvas>
    </div>

</div>

<!-- PURCHASE VS WASTAGE -->

<div class="panel">

    <h2>Purchase Quantity vs Wastage</h2>

    <div class="chart-box">
        <canvas id="quantityChart"></canvas>
    </div>

</div>

<!-- MONTHLY DATA TABLE -->

<div class="panel">

    <h2>Monthly Breakdown</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Month</th>
                    <th>Purchase Spending</th>
                    <th>Quantity Purchased</th>
                    <th>Quantity Wasted</th>
                    <th>Wastage Records</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($months as $month): ?>

                <tr>
                    <td><?= analytics_escape($month['label']) ?></td>

                    <td>
                        <?= analytics_money($month['purchase_amount']) ?>
                    </td>

                    <td>
                        <?= number_format($month['purchase_quantity'], 3) ?>
                    </td>

                    <td>
                        <?= number_format($month['wastage_quantity'], 3) ?>
                    </td>

                    <td>
                        <?= (int)$month['wastage_records'] ?>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>

            <tfoot>
                <tr>
                    <td>6-Month Total</td>
                    <td><?= analytics_money($totalPurchaseAmount) ?></td>
                    <td><?= number_format($totalPurchaseQuantity, 3) ?></td>
                    <td><?= number_format($totalWastageQuantity, 3) ?></td>
                    <td><?= $totalWastageRecords ?></td>
                </tr>
            </tfoot>

        </table>

    </div>

</div>

</div>

<script>

// =====================================
// MONTHLY PURCHASE SPENDING CHART
// =====================================

const labels = <?= json_encode($chartLabels) ?>;
const purchaseAmounts = <?= json_encode($purchaseAmounts) ?>;
const purchaseQuantities = <?= json_encode($purchaseQuantities) ?>;
const wastageQuantities = <?= json_encode($wastageQuantities) ?>;

new Chart(document.getElementById('purchaseChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Purchase Spending (INR)',
            data: purchaseAmounts,
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

// =====================================
// PURCHASE VS WASTAGE CHART
// =====================================

new Chart(document.getElementById('quantityChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Quantity Purchased',
                data: purchaseQuantities,
                borderColor: '#059669',
                backgroundColor: '#059669',
                tension: 0.3
            },
            {
                label: 'Quantity Wasted',
                data: wastageQuantities,
                borderColor: '#ea580c',
                backgroundColor: '#ea580c',
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

</script>

</body>
</html>
