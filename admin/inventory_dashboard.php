
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function dashboard_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dashboard_money($value) {
    return '₹' . number_format((float)$value, 2);
}

function dashboard_query($conn, $sql) {
    try {
        return $conn->query($sql);
    } catch (Throwable $e) {
        return false;
    }
}

// =====================================
// STOCK OVERVIEW
// =====================================

$stockOverview = dashboard_query($conn, "
    SELECT
        COUNT(*) AS total_ingredients,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN current_stock > 0 AND current_stock <= minimum_stock THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN current_stock > minimum_stock THEN 1 ELSE 0 END) AS healthy_stock,
        COALESCE(SUM(current_stock * cost_per_unit), 0) AS stock_value
    FROM ingredients
    WHERE is_active = 1
");

$stockData = $stockOverview ? $stockOverview->fetch_assoc() : [];

$totalIngredients = (int)($stockData['total_ingredients'] ?? 0);
$outOfStock = (int)($stockData['out_of_stock'] ?? 0);
$lowStock = (int)($stockData['low_stock'] ?? 0);
$healthyStock = (int)($stockData['healthy_stock'] ?? 0);
$stockValue = (float)($stockData['stock_value'] ?? 0);

// =====================================
// MANUAL PURCHASES THIS MONTH
// =====================================

$monthlyPurchasesQuery = dashboard_query($conn, "
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(total_cost), 0) AS total_spending,
        COALESCE(SUM(quantity), 0) AS total_quantity
    FROM inventory_purchases
    WHERE MONTH(purchased_at) = MONTH(CURRENT_DATE())
      AND YEAR(purchased_at) = YEAR(CURRENT_DATE())
");

$monthlyPurchases = $monthlyPurchasesQuery
    ? $monthlyPurchasesQuery->fetch_assoc()
    : [];

$monthlyPurchaseCount = (int)($monthlyPurchases['purchase_count'] ?? 0);
$monthlyPurchaseAmount = (float)($monthlyPurchases['total_spending'] ?? 0);
$monthlyPurchaseQuantity = (float)($monthlyPurchases['total_quantity'] ?? 0);

// =====================================
// WASTAGE THIS MONTH
// =====================================

$wastageQuery = dashboard_query($conn, "
    SELECT
        COUNT(*) AS wastage_count,
        COALESCE(SUM(ABS(quantity_change)), 0) AS wastage_quantity
    FROM stock_movements
    WHERE movement_type = 'Wastage'
      AND MONTH(created_at) = MONTH(CURRENT_DATE())
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
");

$wastageData = $wastageQuery ? $wastageQuery->fetch_assoc() : [];

$monthlyWastageCount = (int)($wastageData['wastage_count'] ?? 0);
$monthlyWastageQuantity = (float)($wastageData['wastage_quantity'] ?? 0);

// =====================================
// TOP WASTED INGREDIENTS THIS MONTH
// =====================================

$topWastageQuery = dashboard_query($conn, "
    SELECT
        i.name,
        i.unit,
        SUM(ABS(sm.quantity_change)) AS wasted_quantity
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE sm.movement_type = 'Wastage'
      AND MONTH(sm.created_at) = MONTH(CURRENT_DATE())
      AND YEAR(sm.created_at) = YEAR(CURRENT_DATE())
    GROUP BY i.id, i.name, i.unit
    ORDER BY wasted_quantity DESC
    LIMIT 5
");

// =====================================
// SUPPLIER SPENDING THIS MONTH
// =====================================

$supplierSpendingQuery = dashboard_query($conn, "
    SELECT
        s.supplier_name,
        COALESCE(SUM(ip.total_cost), 0) AS total_spending,
        COUNT(ip.id) AS purchase_count
    FROM inventory_purchases ip
    INNER JOIN suppliers s ON s.id = ip.supplier_id
    WHERE MONTH(ip.purchased_at) = MONTH(CURRENT_DATE())
      AND YEAR(ip.purchased_at) = YEAR(CURRENT_DATE())
    GROUP BY s.id, s.supplier_name
    ORDER BY total_spending DESC
    LIMIT 5
");

// =====================================
// RECENT WASTAGE
// =====================================

$recentWastageQuery = dashboard_query($conn, "
    SELECT
        sm.quantity_change,
        sm.note,
        sm.created_at,
        i.name,
        i.unit
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE sm.movement_type = 'Wastage'
    ORDER BY sm.id DESC
    LIMIT 8
");

// =====================================
// LOW STOCK INGREDIENTS
// =====================================

$lowStockQuery = dashboard_query($conn, "
    SELECT
        id,
        name,
        current_stock,
        minimum_stock,
        unit,
        cost_per_unit
    FROM ingredients
    WHERE is_active = 1
      AND current_stock <= minimum_stock
    ORDER BY current_stock ASC
    LIMIT 10
");

// =====================================
// TOP INGREDIENTS BY STOCK VALUE
// =====================================

$topCostlyQuery = dashboard_query($conn, "
    SELECT
        name,
        current_stock,
        unit,
        cost_per_unit,
        (current_stock * cost_per_unit) AS stock_value
    FROM ingredients
    WHERE is_active = 1
      AND current_stock > 0
    ORDER BY stock_value DESC
    LIMIT 5
");

// =====================================
// PURCHASE ORDER SUMMARY
// =====================================

$poSummaryQuery = dashboard_query($conn, "
    SELECT
        COUNT(*) AS total_orders,

        COALESCE(SUM(
            CASE WHEN status = 'Received'
            THEN total_amount ELSE 0 END
        ), 0) AS received_amount,

        COALESCE(SUM(
            CASE WHEN status = 'Ordered'
            THEN total_amount ELSE 0 END
        ), 0) AS pending_amount,

        SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END) AS received_count,
        SUM(CASE WHEN status = 'Ordered' THEN 1 ELSE 0 END) AS ordered_count,
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) AS draft_count

    FROM purchase_orders
");

$poSummary = $poSummaryQuery ? $poSummaryQuery->fetch_assoc() : [];

$totalPOs = (int)($poSummary['total_orders'] ?? 0);
$receivedPOAmount = (float)($poSummary['received_amount'] ?? 0);
$pendingPOAmount = (float)($poSummary['pending_amount'] ?? 0);
$receivedPOCount = (int)($poSummary['received_count'] ?? 0);
$orderedPOCount = (int)($poSummary['ordered_count'] ?? 0);
$draftPOCount = (int)($poSummary['draft_count'] ?? 0);

// =====================================
// RECENT PURCHASE ORDERS
// =====================================

$recentPOQuery = dashboard_query($conn, "
    SELECT
        po.id,
        po.po_number,
        po.status,
        po.total_amount,
        po.created_at,
        s.supplier_name
    FROM purchase_orders po
    INNER JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.id DESC
    LIMIT 8
");

$recentPOResult = $recentPOQuery;

// =====================================
// PAGE
// =====================================
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory Dashboard | CafeNest</title>

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
    border-radius: 8px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    transition: 0.2s;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn.secondary {
    background: #475569;
}

.btn.green {
    background: #059669;
}

.btn.orange {
    background: #ea580c;
}

.btn.export {
    background: #0f766e;
}

.section-title {
    margin: 28px 0 15px;
    font-size: 20px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
}

.card h3 {
    margin: 0 0 12px;
    font-size: 14px;
    color: #64748b;
}

.card .value {
    font-size: 25px;
    font-weight: bold;
    margin-bottom: 8px;
    overflow-wrap: anywhere;
}

.card .sub {
    font-size: 13px;
    color: #64748b;
}

.blue { border-left: 4px solid #2563eb; }
.green-border { border-left: 4px solid #10b981; }
.orange-border { border-left: 4px solid #f97316; }
.red-border { border-left: 4px solid #ef4444; }
.purple-border { border-left: 4px solid #8b5cf6; }

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 18px;
    overflow: hidden;
}

.panel h2 {
    margin: 0 0 16px;
    font-size: 19px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    text-align: left;
    padding: 12px;
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

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status.Received {
    background: #dcfce7;
    color: #166534;
}

.status.Ordered {
    background: #dbeafe;
    color: #1d4ed8;
}

.status.Draft {
    background: #fef3c7;
    color: #92400e;
}

.status.Cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.small-btn {
    display: inline-block;
    padding: 7px 10px;
    background: #2563eb;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.quick-link {
    display: block;
    padding: 16px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 10px;
    text-decoration: none;
    color: #1e293b;
    transition: 0.2s;
}

.quick-link:hover {
    background: #eff6ff;
    border-color: #93c5fd;
}

.quick-link strong {
    display: block;
    margin-bottom: 6px;
}

.quick-link span {
    font-size: 13px;
    color: #64748b;
}

.empty {
    text-align: center;
    padding: 20px;
    color: #64748b;
}

.alert-text {
    color: #dc2626;
    font-weight: bold;
}

.good-text {
    color: #059669;
    font-weight: bold;
}

.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

@media(max-width: 1100px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .quick-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
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
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .card {
        padding: 15px;
    }

    .card .value {
        font-size: 20px;
    }

    .two-column {
        grid-template-columns: 1fr;
    }

    .quick-grid {
        grid-template-columns: 1fr 1fr;
    }

    .btn {
        padding: 10px 12px;
        font-size: 13px;
    }
}

@media(max-width: 400px) {
    .grid,
    .quick-grid {
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
        <h1>Inventory Dashboard</h1>
        <p>Monitor stock, purchases, wastage and supplier activity.</p>
    </div>

    <div class="header-actions">
        <a class="btn secondary" href="index.php">Admin Home</a>
        <a class="btn green" href="purchase_orders.php">Purchase Orders</a>
        <a class="btn orange" href="restock.php">Restock</a>

        <!-- EXPORT BUTTONS -->
        <a class="btn export" href="export_inventory.php">
            Export Full Inventory
        </a>

        <a class="btn orange" href="export_inventory.php?filter=low_stock">
            Export Low Stock
        </a>
    </div>

</div>

<!-- STOCK OVERVIEW -->

<h2 class="section-title">Stock Overview</h2>

<div class="grid">

    <div class="card blue">
        <h3>Total Active Ingredients</h3>
        <div class="value"><?= $totalIngredients ?></div>
        <div class="sub">Active inventory items</div>
    </div>

    <div class="card red-border">
        <h3>Out of Stock</h3>
        <div class="value"><?= $outOfStock ?></div>
        <div class="sub">Ingredients with zero stock</div>
    </div>

    <div class="card orange-border">
        <h3>Low Stock</h3>
        <div class="value"><?= $lowStock ?></div>
        <div class="sub">At or below minimum level</div>
    </div>

    <div class="card green-border">
        <h3>Stock Value</h3>
        <div class="value"><?= dashboard_money($stockValue) ?></div>
        <div class="sub">Current estimated inventory value</div>
    </div>

</div>

<!-- FINANCIAL OVERVIEW -->

<h2 class="section-title">Financial & Inventory Overview</h2>

<div class="grid">

    <div class="card green-border">
        <h3>Manual Purchases This Month</h3>
        <div class="value"><?= dashboard_money($monthlyPurchaseAmount) ?></div>
        <div class="sub"><?= $monthlyPurchaseCount ?> purchase records</div>
    </div>

    <div class="card blue">
        <h3>Quantity Purchased</h3>
        <div class="value"><?= number_format($monthlyPurchaseQuantity, 3) ?></div>
        <div class="sub">Recorded purchase quantity</div>
    </div>

    <div class="card orange-border">
        <h3>Wastage This Month</h3>
        <div class="value"><?= number_format($monthlyWastageQuantity, 3) ?></div>
        <div class="sub"><?= $monthlyWastageCount ?> wastage records</div>
    </div>

    <div class="card red-border">
        <h3>Out of Stock Items</h3>
        <div class="value"><?= $outOfStock ?></div>
        <div class="sub">Require replenishment</div>
    </div>

</div>

<!-- PURCHASE ORDER ANALYTICS -->

<h2 class="section-title">Purchase Order Analytics</h2>

<div class="grid">

    <div class="card blue">
        <h3>Total Purchase Orders</h3>
        <div class="value"><?= $totalPOs ?></div>
        <div class="sub">All purchase orders</div>
    </div>

    <div class="card green-border">
        <h3>Received Orders</h3>
        <div class="value"><?= $receivedPOCount ?></div>
        <div class="sub">Value: <?= dashboard_money($receivedPOAmount) ?></div>
    </div>

    <div class="card orange-border">
        <h3>Pending Orders</h3>
        <div class="value"><?= $orderedPOCount ?></div>
        <div class="sub">Value: <?= dashboard_money($pendingPOAmount) ?></div>
    </div>

    <div class="card purple-border">
        <h3>Draft Orders</h3>
        <div class="value"><?= $draftPOCount ?></div>
        <div class="sub">Awaiting confirmation</div>
    </div>

</div>

<!-- QUICK LINKS -->

<div class="panel">

    <h2>Inventory Management</h2>

    <div class="quick-grid">

        <a class="quick-link" href="ingredients.php">
            <strong>Ingredients</strong>
            <span>Manage inventory items</span>
        </a>

        <a class="quick-link" href="restock.php">
            <strong>Restock</strong>
            <span>Add stock to ingredients</span>
        </a>

        <a class="quick-link" href="wastage.php">
            <strong>Wastage</strong>
            <span>Record wasted inventory</span>
        </a>

        <a class="quick-link" href="stock_history.php">
            <strong>Stock History</strong>
            <span>Track inventory movements</span>
        </a>

        <a class="quick-link" href="suppliers.php">
            <strong>Suppliers</strong>
            <span>Manage supplier information</span>
        </a>

        <a class="quick-link" href="purchase_history.php">
            <strong>Purchase History</strong>
            <span>View manual purchase records</span>
        </a>

        <a class="quick-link" href="manage_supplier_ingredients.php">
            <strong>Supplier Mapping</strong>
            <span>Manage supplier ingredients</span>
        </a>

        <a class="quick-link" href="purchase_orders.php">
            <strong>Purchase Orders</strong>
            <span>Create and manage POs</span>
        </a>

    </div>

</div>

<!-- LOW STOCK ALERTS -->

<div class="panel">

    <h2>Stock Alerts</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Current Stock</th>
                    <th>Minimum Stock</th>
                    <th>Unit</th>
                    <th>Unit Cost</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($lowStockQuery && $lowStockQuery->num_rows > 0): ?>

                <?php while ($row = $lowStockQuery->fetch_assoc()): ?>

                    <tr>
                        <td><?= dashboard_escape($row['name']) ?></td>

                        <td class="alert-text">
                            <?= number_format((float)$row['current_stock'], 3) ?>
                        </td>

                        <td>
                            <?= number_format((float)$row['minimum_stock'], 3) ?>
                        </td>

                        <td><?= dashboard_escape($row['unit']) ?></td>

                        <td><?= dashboard_money($row['cost_per_unit']) ?></td>

                        <td>
                            <?php if ((float)$row['current_stock'] <= 0): ?>
                                <span class="status Cancelled">Out of Stock</span>
                            <?php else: ?>
                                <span class="status Draft">Low Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        All ingredients are adequately stocked.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- TOP INGREDIENTS BY STOCK VALUE -->

<div class="panel">

    <h2>Top Ingredients by Stock Value</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Current Stock</th>
                    <th>Unit Cost</th>
                    <th>Stock Value</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($topCostlyQuery && $topCostlyQuery->num_rows > 0): ?>

                <?php while ($row = $topCostlyQuery->fetch_assoc()): ?>

                    <tr>
                        <td><?= dashboard_escape($row['name']) ?></td>

                        <td>
                            <?= number_format((float)$row['current_stock'], 3) ?>
                            <?= dashboard_escape($row['unit']) ?>
                        </td>

                        <td><?= dashboard_money($row['cost_per_unit']) ?></td>

                        <td>
                            <strong><?= dashboard_money($row['stock_value']) ?></strong>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4" class="empty">No stock value data available.</td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- RECENT PURCHASE ORDERS -->

<div class="panel">

    <h2>Recent Purchase Orders</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($recentPOResult && $recentPOResult->num_rows > 0): ?>

                <?php while ($po = $recentPOResult->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <strong><?= dashboard_escape($po['po_number']) ?></strong>
                        </td>

                        <td><?= dashboard_escape($po['supplier_name']) ?></td>

                        <td>
                            <span class="status <?= dashboard_escape($po['status']) ?>">
                                <?= dashboard_escape($po['status']) ?>
                            </span>
                        </td>

                        <td><?= dashboard_money($po['total_amount']) ?></td>

                        <td>
                            <?= date('d M Y', strtotime($po['created_at'])) ?>
                        </td>

                        <td>
                            <a class="small-btn"
                               href="view_purchase_order.php?id=<?= (int)$po['id'] ?>">
                                View
                            </a>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        No purchase orders created yet.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- TOP WASTAGE -->

<div class="panel">

    <h2>Top Wasted Ingredients This Month</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Wasted Quantity</th>
                    <th>Unit</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($topWastageQuery && $topWastageQuery->num_rows > 0): ?>

                <?php while ($row = $topWastageQuery->fetch_assoc()): ?>

                    <tr>
                        <td><?= dashboard_escape($row['name']) ?></td>

                        <td class="alert-text">
                            <?= number_format((float)$row['wasted_quantity'], 3) ?>
                        </td>

                        <td><?= dashboard_escape($row['unit']) ?></td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="3" class="empty">
                        No wastage recorded this month.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- SUPPLIER SPENDING -->

<div class="panel">

    <h2>Top Suppliers This Month</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Purchase Records</th>
                    <th>Total Spending</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($supplierSpendingQuery && $supplierSpendingQuery->num_rows > 0): ?>

                <?php while ($row = $supplierSpendingQuery->fetch_assoc()): ?>

                    <tr>
                        <td><?= dashboard_escape($row['supplier_name']) ?></td>
                        <td><?= (int)$row['purchase_count'] ?></td>
                        <td><strong><?= dashboard_money($row['total_spending']) ?></strong></td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="3" class="empty">
                        No supplier purchases recorded this month.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- RECENT WASTAGE ACTIVITY -->

<div class="panel">

    <h2>Recent Wastage Activity</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Quantity Wasted</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($recentWastageQuery && $recentWastageQuery->num_rows > 0): ?>

                <?php while ($row = $recentWastageQuery->fetch_assoc()): ?>

                    <tr>
                        <td><?= dashboard_escape($row['name']) ?></td>

                        <td class="alert-text">
                            <?= number_format(abs((float)$row['quantity_change']), 3) ?>
                            <?= dashboard_escape($row['unit']) ?>
                        </td>

                        <td>
                            <?= dashboard_escape($row['note'] ?: '—') ?>
                        </td>

                        <td>
                            <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4" class="empty">
                        No recent wastage records.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>

</body>
</html>
