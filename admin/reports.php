<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Default report period: first day of this month to today.
$today = date("Y-m-d");
$defaultStart = date("Y-m-01");

$startDate = $_GET['start_date'] ?? $defaultStart;
$endDate = $_GET['end_date'] ?? $today;

$error = "";

function validDate($date) {
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
}

if (!validDate($startDate) || !validDate($endDate)) {
    $startDate = $defaultStart;
    $endDate = $today;
    $error = "Invalid date selected. Showing the current month's report.";
}

if ($startDate > $endDate) {
    $startDate = $defaultStart;
    $endDate = $today;
    $error = "Start date cannot be after the end date.";
}

// 1. Summary statistics.
$summarySql = "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(status = 'Completed'), 0) AS completed_orders,
        COALESCE(SUM(status = 'Pending'), 0) AS pending_orders,
        COALESCE(
            SUM(
                CASE
                    WHEN status != 'Cancelled' THEN total_amount
                    ELSE 0
                END
            ), 0
        ) AS total_revenue
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $summarySql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);

$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

mysqli_stmt_close($stmt);

// 2. Daily sales.
$dailySql = "
    SELECT
        DATE(created_at) AS sale_date,
        COUNT(*) AS order_count,
        SUM(total_amount) AS revenue
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
      AND status != 'Cancelled'
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
";

$stmt = mysqli_prepare($conn, $dailySql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);

$dailyResult = mysqli_stmt_get_result($stmt);

$dailySales = [];
while ($row = mysqli_fetch_assoc($dailyResult)) {
    $dailySales[] = $row;
}

mysqli_stmt_close($stmt);

// 3. Best-selling products.
$productsSql = "
    SELECT
        p.name,
        SUM(oi.quantity) AS units_sold,
        SUM(oi.quantity * oi.price) AS product_revenue
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
      AND o.status != 'Cancelled'
    GROUP BY p.id, p.name
    ORDER BY units_sold DESC
    LIMIT 5
";

$stmt = mysqli_prepare($conn, $productsSql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);

$productResult = mysqli_stmt_get_result($stmt);

$bestProducts = [];
while ($row = mysqli_fetch_assoc($productResult)) {
    $bestProducts[] = $row;
}

mysqli_stmt_close($stmt);

// Find the largest daily revenue for chart scaling.
$maxRevenue = 0;

foreach ($dailySales as $sale) {
    $maxRevenue = max($maxRevenue, (float)$sale['revenue']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales Reports | CafeNest Admin</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f2ee;
            color: #35251d;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        h1, h2 {
            color: #70452c;
        }

        .back-link {
            color: #70452c;
            text-decoration: none;
            font-weight: bold;
        }

        .filter-card,
        .report-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 15px;
            margin-top: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #70452c;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 11px 20px;
            cursor: pointer;
        }

        button:hover {
            background: #51311f;
        }

        .error {
            background: #ffe8e8;
            color: #a00000;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .stat-card p {
            color: #777;
            margin-bottom: 12px;
        }

        .stat-card h2 {
            font-size: 25px;
        }

        .report-card h2 {
            margin-bottom: 18px;
            font-size: 21px;
        }

        .chart-row {
            display: grid;
            grid-template-columns: 110px 1fr 110px;
            align-items: center;
            gap: 12px;
            margin: 17px 0;
        }

        .bar-background {
            height: 19px;
            background: #eee8e2;
            border-radius: 20px;
            overflow: hidden;
        }

        .bar {
            height: 100%;
            background: #a66b42;
            border-radius: 20px;
            min-width: 2px;
        }

        .chart-value {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f7f2eb;
        }

        .empty {
            color: #777;
            padding: 15px 0;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 550px) {
            body {
                padding: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .chart-row {
                grid-template-columns: 85px 1fr;
            }

            .chart-value {
                grid-column: 2;
                text-align: left;
            }

            table {
                font-size: 13px;
            }
        }
    </style>

</head>

<body>

<div class="container">

    <div class="topbar">
        <div>
            <h1>Sales Reports</h1>
            <p>Analyze CafeNest sales and performance.</p>
        </div>

        <a href="dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>
    </div>

    <div class="filter-card">

        <h2>Choose Report Period</h2>

        <form method="GET" class="filter-form">

            <div class="filter-group">
                <label for="start_date">From Date</label>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?php echo e($startDate); ?>"
                    required
                >
            </div>

            <div class="filter-group">
                <label for="end_date">To Date</label>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?php echo e($endDate); ?>"
                    required
                >
            </div>

            <button type="submit">Generate Report</button>

        </form>

        <?php if ($error !== ""): ?>
            <div class="error"><?php echo e($error); ?></div>
        <?php endif; ?>

    </div>

    <div class="stats">

        <div class="stat-card">
            <p>Total Orders</p>
            <h2><?php echo (int)$summary['total_orders']; ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Revenue</p>
            <h2>₹<?php echo number_format((float)$summary['total_revenue'], 2); ?></h2>
        </div>

        <div class="stat-card">
            <p>Completed Orders</p>
            <h2><?php echo (int)$summary['completed_orders']; ?></h2>
        </div>

        <div class="stat-card">
            <p>Pending Orders</p>
            <h2><?php echo (int)$summary['pending_orders']; ?></h2>
        </div>

    </div>

    <div class="report-card">

        <h2>Daily Sales</h2>

        <p>
            Sales from <?php echo e($startDate); ?>
            to <?php echo e($endDate); ?>
        </p>

        <?php if (empty($dailySales)): ?>

            <p class="empty">No sales found for this period.</p>

        <?php else: ?>

            <?php foreach ($dailySales as $sale): ?>

                <?php
                    $percentage = $maxRevenue > 0
                        ? ((float)$sale['revenue'] / $maxRevenue) * 100
                        : 0;
                ?>

                <div class="chart-row">

                    <div>
                        <?php echo e(date("d M", strtotime($sale['sale_date']))); ?>
                    </div>

                    <div class="bar-background">
                        <div
                            class="bar"
                            style="width: <?php echo $percentage; ?>%;"
                        ></div>
                    </div>

                    <div class="chart-value">
                        ₹<?php echo number_format((float)$sale['revenue'], 2); ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <div class="report-card">

        <h2>Best-Selling Products</h2>

        <?php if (empty($bestProducts)): ?>

            <p class="empty">No product sales found for this period.</p>

        <?php else: ?>

            <table>

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($bestProducts as $product): ?>

                        <tr>
                            <td><?php echo e($product['name']); ?></td>

                            <td><?php echo (int)$product['units_sold']; ?></td>

                            <td>
                                ₹<?php echo number_format((float)$product['product_revenue'], 2); ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>