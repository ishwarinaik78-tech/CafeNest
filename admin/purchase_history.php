
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================
// FILTERS
// =====================================

$supplierId = (int)($_GET["supplier"] ?? 0);
$ingredientId = (int)($_GET["ingredient"] ?? 0);

$fromDate = trim($_GET["from"] ?? "");
$toDate = trim($_GET["to"] ?? "");
$search = trim($_GET["search"] ?? "");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function validDate($date) {
    if ($date === "") {
        return true;
    }

    $d = DateTime::createFromFormat("Y-m-d", $date);

    return $d && $d->format("Y-m-d") === $date;
}

if (!validDate($fromDate)) {
    $fromDate = "";
}

if (!validDate($toDate)) {
    $toDate = "";
}

// =====================================
// FETCH SUPPLIERS
// =====================================

$suppliers = [];

$result = $conn->query("
    SELECT id, supplier_name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY supplier_name
");

while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

// =====================================
// FETCH INGREDIENTS
// =====================================

$ingredients = [];

$result = $conn->query("
    SELECT id, name
    FROM ingredients
    ORDER BY name
");

while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

// =====================================
// BUILD FILTER CONDITIONS
// =====================================

$where = [];
$params = [];
$types = "";

if ($supplierId > 0) {
    $where[] = "ip.supplier_id = ?";
    $params[] = $supplierId;
    $types .= "i";
}

if ($ingredientId > 0) {
    $where[] = "ip.ingredient_id = ?";
    $params[] = $ingredientId;
    $types .= "i";
}

if ($fromDate !== "") {
    $where[] = "DATE(ip.purchased_at) >= ?";
    $params[] = $fromDate;
    $types .= "s";
}

if ($toDate !== "") {
    $where[] = "DATE(ip.purchased_at) <= ?";
    $params[] = $toDate;
    $types .= "s";
}

if ($search !== "") {
    $where[] = "(ip.note LIKE ? OR s.supplier_name LIKE ? OR i.name LIKE ?)";
    $searchTerm = "%" . $search . "%";

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $types .= "sss";
}

$whereSql = "";

if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

// =====================================
// PURCHASE SUMMARY
// =====================================

$summarySql = "
    SELECT
        COUNT(ip.id) AS total_purchases,
        COALESCE(SUM(ip.total_cost), 0) AS total_spent,
        COUNT(DISTINCT ip.supplier_id) AS suppliers_used,
        COUNT(DISTINCT ip.ingredient_id) AS ingredients_purchased
    FROM inventory_purchases ip
    JOIN suppliers s ON s.id = ip.supplier_id
    JOIN ingredients i ON i.id = ip.ingredient_id
    $whereSql
";

$stmt = $conn->prepare($summarySql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$summary = $stmt->get_result()->fetch_assoc();

$stmt->close();

// =====================================
// FETCH PURCHASE HISTORY
// =====================================

$purchaseSql = "
    SELECT
        ip.id,
        ip.quantity,
        ip.total_cost,
        ip.note,
        ip.purchased_at,
        s.supplier_name,
        s.company_name,
        i.name AS ingredient_name,
        i.unit
    FROM inventory_purchases ip
    JOIN suppliers s ON s.id = ip.supplier_id
    JOIN ingredients i ON i.id = ip.ingredient_id
    $whereSql
    ORDER BY ip.purchased_at DESC
";

$stmt = $conn->prepare($purchaseSql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$purchaseResult = $stmt->get_result();

$purchases = [];

while ($row = $purchaseResult->fetch_assoc()) {
    $purchases[] = $row;
}

$stmt->close();

// =====================================
// SUPPLIER EXPENDITURE
// =====================================

$supplierSql = "
    SELECT
        s.supplier_name,
        COUNT(ip.id) AS purchase_count,
        COALESCE(SUM(ip.total_cost), 0) AS total_spent
    FROM inventory_purchases ip
    JOIN suppliers s ON s.id = ip.supplier_id
    JOIN ingredients i ON i.id = ip.ingredient_id
    $whereSql
    GROUP BY s.id, s.supplier_name
    ORDER BY total_spent DESC
";

$stmt = $conn->prepare($supplierSql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$supplierResult = $stmt->get_result();

$supplierStats = [];

while ($row = $supplierResult->fetch_assoc()) {
    $supplierStats[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Purchase History | CafeNest</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

.purchase-page {
    max-width: 1400px;
    margin: auto;
    padding: 25px 20px;
}

.purchase-header {
    margin-bottom: 25px;
}

.purchase-header h1 {
    font-size: 28px;
    margin-bottom: 8px;
}

.purchase-header p {
    color: #64748b;
}

.purchase-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.purchase-stat {
    background: #ffffff;
    padding: 22px;
    border-radius: 13px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}

.purchase-stat span {
    display: block;
    color: #64748b;
    font-size: 13px;
    margin-bottom: 10px;
}

.purchase-stat h2 {
    margin: 0;
    font-size: 24px;
    color: #1e293b;
}

.purchase-card {
    background: white;
    padding: 22px;
    border-radius: 13px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    margin-bottom: 25px;
}

.purchase-card h2 {
    margin: 0 0 20px;
    font-size: 20px;
}

.purchase-filters {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
    align-items: end;
}

.purchase-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.purchase-field label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}

.purchase-field input,
.purchase-field select {
    width: 100%;
    padding: 11px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    box-sizing: border-box;
    outline: none;
    background: white;
}

.purchase-field input:focus,
.purchase-field select:focus {
    border-color: #2563eb;
}

.purchase-btn {
    border: none;
    padding: 12px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    font-weight: 600;
    cursor: pointer;
}

.purchase-btn:hover {
    background: #1d4ed8;
}

.purchase-reset {
    display: inline-block;
    padding: 12px;
    text-align: center;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    text-decoration: none;
    color: #475569;
    font-weight: 600;
}

.purchase-table-wrapper {
    overflow-x: auto;
}

.purchase-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.purchase-table th,
.purchase-table td {
    padding: 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 14px;
}

.purchase-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
}

.purchase-table tbody tr:hover {
    background: #fafcff;
}

.purchase-amount {
    color: #15803d;
    font-weight: 700;
}

.purchase-empty {
    text-align: center;
    color: #64748b;
    padding: 35px !important;
}

.supplier-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.supplier-stat {
    border: 1px solid #e5e7eb;
    padding: 17px;
    border-radius: 10px;
}

.supplier-stat h3 {
    margin: 0 0 10px;
    font-size: 16px;
}

.supplier-stat p {
    margin: 5px 0;
    color: #64748b;
    font-size: 14px;
}

.supplier-stat strong {
    color: #15803d;
    font-size: 18px;
}

@media(max-width: 1000px) {
    .purchase-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .purchase-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .supplier-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 600px) {
    .purchase-page {
        padding: 15px 10px;
    }

    .purchase-header h1 {
        font-size: 23px;
    }

    .purchase-cards {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .purchase-stat {
        padding: 16px;
    }

    .purchase-stat h2 {
        font-size: 20px;
    }

    .purchase-filters {
        grid-template-columns: 1fr;
    }

    .supplier-grid {
        grid-template-columns: 1fr;
    }

    .purchase-card {
        padding: 16px;
    }
}

</style>

</head>

<body>

<div class="purchase-page">

    <!-- ================================= -->
    <!-- HEADER -->
    <!-- ================================= -->

    <div class="purchase-header">

        <h1>Purchase History</h1>

        <p>Track inventory purchases, supplier spending and purchase records.</p>

    </div>

    <!-- ================================= -->
    <!-- SUMMARY CARDS -->
    <!-- ================================= -->

    <div class="purchase-cards">

        <div class="purchase-stat">
            <span>Total Purchase Spending</span>
            <h2>₹<?= number_format((float)$summary["total_spent"], 2) ?></h2>
        </div>

        <div class="purchase-stat">
            <span>Total Purchase Records</span>
            <h2><?= (int)$summary["total_purchases"] ?></h2>
        </div>

        <div class="purchase-stat">
            <span>Suppliers Used</span>
            <h2><?= (int)$summary["suppliers_used"] ?></h2>
        </div>

        <div class="purchase-stat">
            <span>Ingredients Purchased</span>
            <h2><?= (int)$summary["ingredients_purchased"] ?></h2>
        </div>

    </div>

    <!-- ================================= -->
    <!-- FILTERS -->
    <!-- ================================= -->

    <div class="purchase-card">

        <h2>Filter Purchases</h2>

        <form method="GET">

            <div class="purchase-filters">

                <div class="purchase-field">

                    <label>Supplier</label>

                    <select name="supplier">

                        <option value="0">All suppliers</option>

                        <?php foreach ($suppliers as $supplier): ?>

                            <option
                                value="<?= (int)$supplier["id"] ?>"
                                <?= $supplierId == $supplier["id"] ? "selected" : "" ?>
                            >
                                <?= e($supplier["supplier_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="purchase-field">

                    <label>Ingredient</label>

                    <select name="ingredient">

                        <option value="0">All ingredients</option>

                        <?php foreach ($ingredients as $ingredient): ?>

                            <option
                                value="<?= (int)$ingredient["id"] ?>"
                                <?= $ingredientId == $ingredient["id"] ? "selected" : "" ?>
                            >
                                <?= e($ingredient["name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="purchase-field">

                    <label>From Date</label>

                    <input
                        type="date"
                        name="from"
                        value="<?= e($fromDate) ?>"
                    >

                </div>

                <div class="purchase-field">

                    <label>To Date</label>

                    <input
                        type="date"
                        name="to"
                        value="<?= e($toDate) ?>"
                    >

                </div>

                <div class="purchase-field">

                    <label>Search</label>

                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Ingredient, supplier, note..."
                    >

                </div>

                <div class="purchase-field">

                    <button type="submit" class="purchase-btn">
                        Apply Filters
                    </button>

                </div>

                <div class="purchase-field">

                    <a href="purchase_history.php" class="purchase-reset">
                        Reset Filters
                    </a>

                </div>

            </div>

        </form>

    </div>

    <!-- ================================= -->
    <!-- SUPPLIER SPENDING -->
    <!-- ================================= -->

    <div class="purchase-card">

        <h2>Supplier Spending Breakdown</h2>

        <?php if (!empty($supplierStats)): ?>

            <div class="supplier-grid">

                <?php foreach ($supplierStats as $supplier): ?>

                    <div class="supplier-stat">

                        <h3><?= e($supplier["supplier_name"]) ?></h3>

                        <p>
                            Purchase Records:
                            <?= (int)$supplier["purchase_count"] ?>
                        </p>

                        <p>Total Spent</p>

                        <strong>
                            ₹<?= number_format((float)$supplier["total_spent"], 2) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="purchase-empty">No supplier purchases found.</p>

        <?php endif; ?>

    </div>

    <!-- ================================= -->
    <!-- PURCHASE TABLE -->
    <!-- ================================= -->

    <div class="purchase-card">

        <h2>Purchase Records (<?= count($purchases) ?>)</h2>

        <div class="purchase-table-wrapper">

            <table class="purchase-table">

                <thead>

                    <tr>
                        <th>Ingredient</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Total Cost</th>
                        <th>Note</th>
                        <th>Date</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($purchases)): ?>

                    <?php foreach ($purchases as $purchase): ?>

                        <tr>

                            <td><?= e($purchase["ingredient_name"]) ?></td>

                            <td>
                                <?= e($purchase["supplier_name"]) ?>
                                <?php if (!empty($purchase["company_name"])): ?>
                                    <small style="display:block;color:#64748b">
                                        <?= e($purchase["company_name"]) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($purchase["quantity"]) ?>
                                <?= e($purchase["unit"]) ?>
                            </td>

                            <td class="purchase-amount">
                                ₹<?= number_format((float)$purchase["total_cost"], 2) ?>
                            </td>

                            <td><?= e($purchase["note"] ?: "—") ?></td>

                            <td>
                                <?= e(date("d M Y, h:i A", strtotime($purchase["purchased_at"]))) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="purchase-empty">
                            No purchase records match the selected filters.
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
