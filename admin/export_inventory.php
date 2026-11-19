
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================
// CSV SAFETY
// =====================================

function csv_safe($value) {
    $value = (string)$value;

    // Prevent Excel formula injection
    if (preg_match('/^[\s]*[=+\-@]/', $value)) {
        $value = "'" . $value;
    }

    return $value;
}

// =====================================
// FILTER
// =====================================

$filter = $_GET['filter'] ?? 'all';

$allowedFilters = ['all', 'low_stock'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

// =====================================
// FETCH INGREDIENTS
// =====================================

$sql = "
    SELECT
        id,
        name,
        unit,
        current_stock,
        minimum_stock,
        cost_per_unit
    FROM ingredients
    WHERE is_active = 1
";

if ($filter === 'low_stock') {
    $sql .= " AND current_stock <= minimum_stock";
}

$sql .= " ORDER BY name ASC";

$result = $conn->query($sql);

// =====================================
// DOWNLOAD CSV
// =====================================

$filename = ($filter === 'low_stock')
    ? 'CafeNest_Low_Stock_' . date('Y-m-d') . '.csv'
    : 'CafeNest_Inventory_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output buffer
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// =====================================
// CSV HEADERS
// =====================================

fputcsv($output, [
    'Ingredient ID',
    'Ingredient Name',
    'Unit',
    'Current Stock',
    'Minimum Stock',
    'Cost Per Unit (INR)',
    'Stock Value (INR)',
    'Status'
]);

// =====================================
// CSV ROWS
// =====================================

while ($row = $result->fetch_assoc()) {

    $currentStock = (float)$row['current_stock'];
    $minimumStock = (float)$row['minimum_stock'];
    $costPerUnit = (float)$row['cost_per_unit'];

    $stockValue = $currentStock * $costPerUnit;

    if ($currentStock <= 0) {
        $status = 'Out of Stock';
    } elseif ($currentStock <= $minimumStock) {
        $status = 'Low Stock';
    } else {
        $status = 'In Stock';
    }

    fputcsv($output, [
        (int)$row['id'],
        csv_safe($row['name']),
        csv_safe($row['unit']),
        number_format($currentStock, 3, '.', ''),
        number_format($minimumStock, 3, '.', ''),
        number_format($costPerUnit, 2, '.', ''),
        number_format($stockValue, 2, '.', ''),
        $status
    ]);
}

fclose($output);
exit;
?>
