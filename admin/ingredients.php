
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$error = "";

// =====================================
// ADD INGREDIENT
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_ingredient"])) {

    $name = trim($_POST["name"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $stock = filter_var($_POST["current_stock"] ?? null, FILTER_VALIDATE_FLOAT);
    $minimum = filter_var($_POST["minimum_stock"] ?? null, FILTER_VALIDATE_FLOAT);
    $cost = filter_var($_POST["cost_per_unit"] ?? null, FILTER_VALIDATE_FLOAT);

    if (
        $name === "" ||
        $unit === "" ||
        $stock === false || $stock < 0 ||
        $minimum === false || $minimum < 0 ||
        $cost === false || $cost < 0
    ) {
        $error = "Please enter valid ingredient details.";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                INSERT INTO ingredients
                (name, unit, current_stock, minimum_stock, cost_per_unit)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssddd",
                $name,
                $unit,
                $stock,
                $minimum,
                $cost
            );

            $stmt->execute();

            $ingredientId = $conn->insert_id;

            // Record opening stock
            if ($stock > 0) {
                $note = "Opening stock";
                $movementType = "Purchase";

                $movement = $conn->prepare("
                    INSERT INTO stock_movements
                    (ingredient_id, movement_type, quantity_change, note)
                    VALUES (?, ?, ?, ?)
                ");

                $movement->bind_param(
                    "isds",
                    $ingredientId,
                    $movementType,
                    $stock,
                    $note
                );

                $movement->execute();
                $movement->close();
            }

            $conn->commit();

            $message = "Ingredient added successfully.";

            $stmt->close();

        } catch (mysqli_sql_exception $e) {

            $conn->rollback();

            if ($e->getCode() === 1062) {
                $error = "An ingredient with this name already exists.";
            } else {
                $error = "Unable to add ingredient. Please try again.";
            }
        }
    }
}

// =====================================
// FETCH INGREDIENTS
// =====================================

$ingredients = $conn->query("
    SELECT *
    FROM ingredients
    ORDER BY name ASC
");

// =====================================
// SUMMARY
// =====================================

$summary = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN current_stock <= minimum_stock THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(current_stock * cost_per_unit) AS stock_value
    FROM ingredients
    WHERE is_active = 1
")->fetch_assoc();

$totalIngredients = (int)($summary["total"] ?? 0);
$lowStockCount = (int)($summary["low_stock"] ?? 0);
$outOfStockCount = (int)($summary["out_of_stock"] ?? 0);
$stockValue = (float)($summary["stock_value"] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Ingredients | CafeNest Admin</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

* {
    box-sizing: border-box;
}

.inventory-container {
    padding: 30px;
    max-width: 1500px;
    margin: auto;
}

/* HEADER */

.inventory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.inventory-header h1 {
    margin: 0;
}

.inventory-header p {
    color: #64748b;
    margin-top: 8px;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.quick-btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.quick-blue {
    background: #2563eb;
    color: white;
}

.quick-light {
    background: #e2e8f0;
    color: #334155;
}

.quick-btn:hover {
    opacity: .9;
}

/* ALERTS */

.alert-message {
    padding: 14px 18px;
    border-radius: 9px;
    margin-bottom: 18px;
}

.success-message {
    background: #dcfce7;
    color: #166534;
}

.error-message {
    background: #fee2e2;
    color: #991b1b;
}

.stock-alert {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    padding: 18px 20px;
    border-radius: 12px;
    margin-bottom: 22px;
}

.stock-alert-danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.stock-alert-warning {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}

.stock-alert-healthy {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.stock-alert h3 {
    margin: 0 0 5px;
    font-size: 16px;
}

.stock-alert p {
    margin: 0;
    font-size: 14px;
}

.alert-action {
    padding: 9px 14px;
    border-radius: 7px;
    background: white;
    color: inherit;
    border: 1px solid currentColor;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

/* SUMMARY CARDS */

.inventory-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.inventory-stat {
    background: #fff;
    padding: 22px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.inventory-stat span {
    color: #64748b;
    font-size: 14px;
}

.inventory-stat h2 {
    margin: 10px 0 0;
    font-size: 26px;
}

.stat-blue {
    border-top: 3px solid #2563eb;
}

.stat-orange {
    border-top: 3px solid #f59e0b;
}

.stat-red {
    border-top: 3px solid #dc2626;
}

.stat-green {
    border-top: 3px solid #16a34a;
}

/* PANELS */

.inventory-panel {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.inventory-panel h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

/* FORM */

.ingredient-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.ingredient-form input,
.ingredient-form select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    outline: none;
    background: #fff;
}

.ingredient-form input:focus,
.ingredient-form select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}

.ingredient-form label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
    font-size: 14px;
}

.ingredient-form button {
    padding: 12px 20px;
    border: none;
    border-radius: 7px;
    background: #2563eb;
    color: white;
    cursor: pointer;
    font-weight: 600;
}

.ingredient-form button:hover {
    background: #1d4ed8;
}

/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.inventory-table th,
.inventory-table td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.inventory-table th {
    background: #f8fafc;
    color: #64748b;
    text-transform: uppercase;
    font-size: 12px;
    white-space: nowrap;
}

.inventory-table tbody tr:hover td {
    background: #fafcff;
}

/* STOCK STATUS */

.stock-low {
    color: #dc2626;
    font-weight: 700;
}

.stock-good {
    color: #15803d;
    font-weight: 700;
}

.stock-out {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
    font-size: 12px;
}

.stock-warning {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #ffedd5;
    color: #9a3412;
    font-weight: 700;
    font-size: 12px;
}

.stock-healthy {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-weight: 700;
    font-size: 12px;
}

/* EDIT BUTTON */

.edit-button {
    display: inline-block;
    padding: 7px 14px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.edit-button:hover {
    background: #1d4ed8;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .inventory-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 768px) {
    .inventory-container {
        padding: 15px;
    }

    .inventory-stats,
    .ingredient-form {
        grid-template-columns: 1fr;
    }

    .inventory-panel {
        padding: 16px;
    }

    .inventory-header {
        align-items: flex-start;
    }
}

@media(max-width: 500px) {
    .inventory-container {
        padding: 12px;
    }

    .header-actions {
        width: 100%;
    }

    .header-actions a {
        flex: 1;
        text-align: center;
    }

    .inventory-stat h2 {
        font-size: 23px;
    }

    .stock-alert {
        align-items: stretch;
    }

    .alert-action {
        text-align: center;
    }
}

</style>
</head>

<body>

<div class="inventory-container">

    <!-- HEADER -->

    <div class="inventory-header">

        <div>
            <h1>Ingredient Management</h1>
            <p>Manage ingredients, monitor stock and track replenishment.</p>
        </div>

        <div class="header-actions">

            <a href="low_stock.php" class="quick-btn quick-light">
                ⚠ Low Stock
            </a>

           <a href="restock.php" class="quick-btn quick-blue">
    + Restock
</a>

<a href="stock_adjustment.php" class="quick-btn quick-light">
    ⇄ Stock Adjustment
</a>

<a href="inventory_history.php" class="quick-btn quick-light">
    History
</a>

        </div>

    </div>

    <!-- MESSAGES -->

    <?php if ($message): ?>
        <div class="alert-message success-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-message error-message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- LOW STOCK ALERT -->

    <?php if ($outOfStockCount > 0): ?>

        <div class="stock-alert stock-alert-danger">

            <div>
                <h3>⚠ Out of Stock Alert</h3>
                <p>
                    <?= $outOfStockCount ?> ingredient(s) have no stock available.
                    Restock immediately.
                </p>
            </div>

            <a href="low_stock.php" class="alert-action">
                View Alerts →
            </a>

        </div>

    <?php elseif ($lowStockCount > 0): ?>

        <div class="stock-alert stock-alert-warning">

            <div>
                <h3>⚠ Low Stock Warning</h3>
                <p>
                    <?= $lowStockCount ?> ingredient(s) have reached
                    their minimum stock level.
                </p>
            </div>

            <a href="low_stock.php" class="alert-action">
                View Alerts →
            </a>

        </div>

    <?php else: ?>

        <div class="stock-alert stock-alert-healthy">

            <div>
                <h3>✓ Stock Levels Healthy</h3>
                <p>All active ingredients are currently above their minimum stock level.</p>
            </div>

        </div>

    <?php endif; ?>

    <!-- SUMMARY -->

    <div class="inventory-stats">

        <div class="inventory-stat stat-blue">
            <span>Total Ingredients</span>
            <h2><?= $totalIngredients ?></h2>
        </div>

        <div class="inventory-stat stat-orange">
            <span>Low Stock Ingredients</span>
            <h2><?= $lowStockCount ?></h2>
        </div>

        <div class="inventory-stat stat-red">
            <span>Out of Stock</span>
            <h2><?= $outOfStockCount ?></h2>
        </div>

        <div class="inventory-stat stat-green">
            <span>Estimated Stock Value</span>
            <h2>₹<?= number_format($stockValue, 2) ?></h2>
        </div>

    </div>

    <!-- ADD INGREDIENT -->

    <div class="inventory-panel">

        <h2>Add New Ingredient</h2>

        <form method="POST" class="ingredient-form">

            <div>
                <label>Ingredient Name</label>
                <input
                    type="text"
                    name="name"
                    maxlength="150"
                    placeholder="Example: Coffee Beans"
                    required
                >
            </div>

            <div>
                <label>Unit</label>
                <select name="unit" required>
                    <option value="">Select Unit</option>
                    <option value="g">Grams (g)</option>
                    <option value="kg">Kilograms (kg)</option>
                    <option value="ml">Millilitres (ml)</option>
                    <option value="L">Litres (L)</option>
                    <option value="piece">Pieces</option>
                    <option value="packet">Packets</option>
                </select>
            </div>

            <div>
                <label>Current Stock</label>
                <input
                    type="number"
                    name="current_stock"
                    min="0"
                    step="0.001"
                    placeholder="Enter stock quantity"
                    required
                >
            </div>

            <div>
                <label>Minimum Stock Alert</label>
                <input
                    type="number"
                    name="minimum_stock"
                    min="0"
                    step="0.001"
                    placeholder="Minimum stock level"
                    required
                >
            </div>

            <div>
                <label>Cost Per Unit (₹)</label>
                <input
                    type="number"
                    name="cost_per_unit"
                    min="0"
                    step="0.01"
                    placeholder="Enter cost per unit"
                    required
                >
            </div>

            <div style="display:flex;align-items:end;">
                <button type="submit" name="add_ingredient">
                    + Add Ingredient
                </button>
            </div>

        </form>

    </div>

    <!-- INGREDIENT TABLE -->

    <div class="inventory-panel">

        <h2>Ingredient Stock</h2>

        <div class="table-wrapper">

            <table class="inventory-table">

                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Unit</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Cost / Unit</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($ingredients->num_rows > 0): ?>

                    <?php while ($item = $ingredients->fetch_assoc()): ?>

                        <?php
                        $currentStock = (float)$item["current_stock"];
                        $minimumStock = (float)$item["minimum_stock"];

                        $isLow = $currentStock <= $minimumStock;
                        $isOut = $currentStock <= 0;
                        ?>

                        <tr>

                            <td>
                                <strong><?= htmlspecialchars($item["name"]) ?></strong>
                            </td>

                            <td><?= htmlspecialchars($item["unit"]) ?></td>

                            <td>
                                <?= number_format($currentStock, 3) ?>
                            </td>

                            <td>
                                <?= number_format($minimumStock, 3) ?>
                            </td>

                            <td>
                                ₹<?= number_format((float)$item["cost_per_unit"], 2) ?>
                            </td>

                            <td>

                                <?php if ($isOut): ?>

                                    <span class="stock-out">Out of Stock</span>

                                <?php elseif ($isLow): ?>

                                    <span class="stock-warning">Low Stock</span>

                                <?php else: ?>

                                    <span class="stock-healthy">In Stock</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <a
                                    href="edit_ingredient.php?id=<?= (int)$item['id'] ?>"
                                    class="edit-button"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">No ingredients added yet.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
