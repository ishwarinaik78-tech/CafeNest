
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function iia_escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function iia_fetch($conn, $sql) {
    $result = $conn->query($sql);
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function iia_count($conn, $sql) {
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return (int)($row['total'] ?? 0);
}

function iia_status($count) {
    return $count === 0 ? 'clean' : 'issue';
}

// ==========================================
// AUDIT QUERIES
// ==========================================

// 1. Negative stock
$negativeStock = iia_fetch($conn, "
    SELECT id, name, current_stock, unit, minimum_stock
    FROM ingredients
    WHERE current_stock < 0
    ORDER BY current_stock ASC
");

// 2. Negative minimum stock
$invalidMinimum = iia_fetch($conn, "
    SELECT id, name, current_stock, minimum_stock, unit
    FROM ingredients
    WHERE minimum_stock < 0
    ORDER BY name ASC
");

// 3. Negative ingredient cost
$negativeCost = iia_fetch($conn, "
    SELECT id, name, cost_per_unit, unit
    FROM ingredients
    WHERE cost_per_unit < 0
    ORDER BY name ASC
");

// 4. Missing active supplier
$missingSupplier = iia_fetch($conn, "
    SELECT
        i.id,
        i.name,
        i.current_stock,
        i.minimum_stock,
        i.unit
    FROM ingredients i
    LEFT JOIN supplier_ingredients si
        ON si.ingredient_id = i.id
    LEFT JOIN suppliers s
        ON s.id = si.supplier_id
        AND s.is_active = 1
    WHERE i.is_active = 1
    GROUP BY
        i.id, i.name, i.current_stock, i.minimum_stock, i.unit
    HAVING COUNT(s.id) = 0
    ORDER BY i.name ASC
");

// 5. Mappings to inactive suppliers
$inactiveSupplierMappings = iia_fetch($conn, "
    SELECT
        si.id AS mapping_id,
        i.name AS ingredient_name,
        s.supplier_name,
        s.is_active
    FROM supplier_ingredients si
    INNER JOIN ingredients i
        ON i.id = si.ingredient_id
    INNER JOIN suppliers s
        ON s.id = si.supplier_id
    WHERE s.is_active = 0
    ORDER BY s.supplier_name, i.name
");

// 6. Duplicate supplier mappings
$duplicateMappings = iia_fetch($conn, "
    SELECT
        si.supplier_id,
        si.ingredient_id,
        s.supplier_name,
        i.name AS ingredient_name,
        COUNT(*) AS duplicate_count
    FROM supplier_ingredients si
    INNER JOIN suppliers s
        ON s.id = si.supplier_id
    INNER JOIN ingredients i
        ON i.id = si.ingredient_id
    GROUP BY
        si.supplier_id,
        si.ingredient_id,
        s.supplier_name,
        i.name
    HAVING COUNT(*) > 1
    ORDER BY duplicate_count DESC
");

// 7. Zero-quantity stock movements
$zeroMovements = iia_fetch($conn, "
    SELECT
        sm.id,
        i.name AS ingredient_name,
        sm.movement_type,
        sm.quantity_change,
        sm.note,
        sm.created_at
    FROM stock_movements sm
    INNER JOIN ingredients i
        ON i.id = sm.ingredient_id
    WHERE sm.quantity_change = 0
    ORDER BY sm.id DESC
    LIMIT 100
");

// ==========================================
// SUMMARY
// ==========================================

$checks = [
    [
        'title' => 'Negative Stock',
        'description' => 'Ingredients with stock below zero',
        'count' => count($negativeStock)
    ],
    [
        'title' => 'Invalid Minimum Stock',
        'description' => 'Ingredients configured with a negative minimum stock',
        'count' => count($invalidMinimum)
    ],
    [
        'title' => 'Negative Ingredient Cost',
        'description' => 'Ingredients with a negative cost per unit',
        'count' => count($negativeCost)
    ],
    [
        'title' => 'Missing Supplier',
        'description' => 'Active ingredients without an active supplier',
        'count' => count($missingSupplier)
    ],
    [
        'title' => 'Inactive Supplier Mappings',
        'description' => 'Ingredients linked to inactive suppliers',
        'count' => count($inactiveSupplierMappings)
    ],
    [
        'title' => 'Duplicate Supplier Mappings',
        'description' => 'Repeated supplier and ingredient combinations',
        'count' => count($duplicateMappings)
    ],
    [
        'title' => 'Zero-Quantity Movements',
        'description' => 'Stock movement entries recorded with zero quantity',
        'count' => count($zeroMovements)
    ]
];

$totalIssues = 0;

foreach ($checks as $check) {
    $totalIssues += $check['count'];
}

$cleanChecks = 0;

foreach ($checks as $check) {
    if ($check['count'] === 0) {
        $cleanChecks++;
    }
}

// ==========================================
// TABLE RENDERER
// ==========================================

function iia_render_table($title, $rows, $columns) {
    ?>
    <div class="audit-section">

        <div class="section-heading">
            <h2><?= iia_escape($title) ?></h2>

            <span class="count-badge <?= count($rows) === 0 ? 'good' : 'warning' ?>">
                <?= count($rows) ?> record(s)
            </span>
        </div>

        <?php if (empty($rows)): ?>

            <div class="empty">
                <span class="empty-icon">✓</span>
                No issues detected in this check.
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= iia_escape($column['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <td>
                                        <?= iia_escape(
                                            $row[$column['key']] ?? ''
                                        ) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory Integrity Audit | CafeNest</title>

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
    max-width: 1400px;
    width: 95%;
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
    border: 0;
    border-radius: 8px;
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

.summary {
    padding: 25px;
    background: white;
    border-radius: 13px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.summary-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.summary-top h2 {
    margin: 0;
}

.big-number {
    font-size: 36px;
    font-weight: bold;
    margin-top: 15px;
}

.summary-description {
    color: #64748b;
    margin-top: 8px;
}

.summary-banner {
    margin-top: 20px;
    border-radius: 9px;
    padding: 15px;
    font-weight: bold;
}

.summary-banner.clean {
    background: #dcfce7;
    color: #166534;
}

.summary-banner.issues {
    background: #fee2e2;
    color: #991b1b;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    padding: 19px;
}

.card h3 {
    font-size: 14px;
    color: #64748b;
    margin: 0 0 10px;
}

.card .value {
    font-size: 25px;
    font-weight: bold;
}

.card .sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 7px;
}

.audit-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 18px;
    padding: 20px;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.section-heading h2 {
    margin: 0;
    font-size: 19px;
}

.count-badge {
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.count-badge.good {
    background: #dcfce7;
    color: #166534;
}

.count-badge.warning {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    padding: 20px;
    border-radius: 8px;
    background: #f0fdf4;
    color: #166534;
}

.empty-icon {
    font-weight: bold;
    margin-right: 8px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 14px;
    overflow-wrap: anywhere;
}

th {
    background: #f8fafc;
    color: #475569;
}

.footer-note {
    padding: 18px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 9px;
    color: #1e40af;
    line-height: 1.6;
    margin-top: 20px;
}

@media(max-width: 950px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 550px) {
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

    .card .value {
        font-size: 21px;
    }

    .summary, .audit-section {
        padding: 15px;
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

    .audit-section, .summary {
        break-inside: avoid;
        box-shadow: none;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Inventory Integrity Audit</h1>
            <p>Inventory validation and supplier data integrity report.</p>
        </div>

        <div class="actions">
            <a href="inventory_dashboard.php" class="btn gray">
                Inventory Dashboard
            </a>

            <a href="inventory_activity_log.php" class="btn green">
                Activity Log
            </a>

            <a href="supplier_restock_planner.php" class="btn">
                Restock Planner
            </a>

            <button class="btn gray" onclick="window.print()">
                Print Audit
            </button>
        </div>
    </div>

    <!-- OVERALL SUMMARY -->

    <div class="summary">

        <div class="summary-top">
            <h2>Audit Overview</h2>
        </div>

        <div class="big-number">
            <?= $totalIssues ?>
        </div>

        <div class="summary-description">
            Total records flagged across all audit checks
        </div>

        <div class="summary-banner <?= $totalIssues === 0 ? 'clean' : 'issues' ?>">
            <?php if ($totalIssues === 0): ?>
                All checks passed. No issues were detected.
            <?php else: ?>
                Review the flagged records below. No data has been changed.
            <?php endif; ?>
        </div>

    </div>

    <!-- CHECK SUMMARY CARDS -->

    <div class="summary-grid">

        <div class="card">
            <h3>Checks Passed</h3>
            <div class="value"><?= $cleanChecks ?> / <?= count($checks) ?></div>
            <div class="sub">Audit categories with no flagged rows</div>
        </div>

        <div class="card">
            <h3>Negative Stock</h3>
            <div class="value"><?= count($negativeStock) ?></div>
            <div class="sub">Invalid stock balance</div>
        </div>

        <div class="card">
            <h3>Supplier Issues</h3>
            <div class="value">
                <?= count($missingSupplier) + count($inactiveSupplierMappings) + count($duplicateMappings) ?>
            </div>
            <div class="sub">Missing, inactive or duplicate mapping records</div>
        </div>

        <div class="card">
            <h3>Movement Issues</h3>
            <div class="value"><?= count($zeroMovements) ?></div>
            <div class="sub">Zero-quantity movement records</div>
        </div>

    </div>

    <!-- AUDIT DETAILS -->

    <?php
    iia_render_table(
        'Negative Stock',
        $negativeStock,
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Ingredient'],
            ['key' => 'current_stock', 'label' => 'Current Stock'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'minimum_stock', 'label' => 'Minimum Stock']
        ]
    );

    iia_render_table(
        'Invalid Minimum Stock',
        $invalidMinimum,
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Ingredient'],
            ['key' => 'current_stock', 'label' => 'Current Stock'],
            ['key' => 'minimum_stock', 'label' => 'Minimum Stock'],
            ['key' => 'unit', 'label' => 'Unit']
        ]
    );

    iia_render_table(
        'Negative Ingredient Cost',
        $negativeCost,
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Ingredient'],
            ['key' => 'cost_per_unit', 'label' => 'Cost Per Unit'],
            ['key' => 'unit', 'label' => 'Unit']
        ]
    );

    iia_render_table(
        'Missing Active Supplier',
        $missingSupplier,
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Ingredient'],
            ['key' => 'current_stock', 'label' => 'Current Stock'],
            ['key' => 'minimum_stock', 'label' => 'Minimum Stock'],
            ['key' => 'unit', 'label' => 'Unit']
        ]
    );

    iia_render_table(
        'Inactive Supplier Mappings',
        $inactiveSupplierMappings,
        [
            ['key' => 'mapping_id', 'label' => 'Mapping ID'],
            ['key' => 'ingredient_name', 'label' => 'Ingredient'],
            ['key' => 'supplier_name', 'label' => 'Supplier'],
            ['key' => 'is_active', 'label' => 'Supplier Active']
        ]
    );

    iia_render_table(
        'Duplicate Supplier Mappings',
        $duplicateMappings,
        [
            ['key' => 'supplier_id', 'label' => 'Supplier ID'],
            ['key' => 'supplier_name', 'label' => 'Supplier'],
            ['key' => 'ingredient_id', 'label' => 'Ingredient ID'],
            ['key' => 'ingredient_name', 'label' => 'Ingredient'],
            ['key' => 'duplicate_count', 'label' => 'Occurrences']
        ]
    );

    iia_render_table(
        'Zero-Quantity Stock Movements',
        $zeroMovements,
        [
            ['key' => 'id', 'label' => 'Movement ID'],
            ['key' => 'ingredient_name', 'label' => 'Ingredient'],
            ['key' => 'movement_type', 'label' => 'Movement Type'],
            ['key' => 'quantity_change', 'label' => 'Quantity'],
            ['key' => 'note', 'label' => 'Note'],
            ['key' => 'created_at', 'label' => 'Created At']
        ]
    );
    ?>

    <div class="footer-note">
        <strong>Important:</strong>
        This audit is read-only. Flagged records are not automatically corrected
        or deleted. Verify each result against your purchase records and stock
        counts before making adjustments.
    </div>

</div>

</body>
</html>
