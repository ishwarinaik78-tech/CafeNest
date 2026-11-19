
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$messageType = "success";

$selectedIngredient = 0;
$selectedSupplier = 0;
$quantityValue = "";
$costValue = "";
$noteValue = "";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["restock_csrf"])) {
    $_SESSION["restock_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["restock_csrf"];

// =====================================
// RESTOCK INGREDIENT
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["restock"])) {

    $token = $_POST["csrf_token"] ?? "";

    $ingredientId = (int)($_POST["ingredient_id"] ?? 0);
    $supplierId = (int)($_POST["supplier_id"] ?? 0);

    $quantity = (float)($_POST["quantity"] ?? 0);
    $totalCost = (float)($_POST["total_cost"] ?? 0);

    $note = trim($_POST["note"] ?? "");

    $selectedIngredient = $ingredientId;
    $selectedSupplier = $supplierId;
    $quantityValue = $_POST["quantity"] ?? "";
    $costValue = $_POST["total_cost"] ?? "";
    $noteValue = $note;

    try {

        if (!hash_equals($_SESSION["restock_csrf"], $token)) {
            throw new Exception("Invalid request. Please refresh the page.");
        }

        if ($ingredientId <= 0 || $supplierId <= 0) {
            throw new Exception("Please select an ingredient and supplier.");
        }

        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than zero.");
        }

        if ($totalCost < 0) {
            throw new Exception("Purchase cost cannot be negative.");
        }

        if (strlen($note) > 255) {
            throw new Exception("Note cannot exceed 255 characters.");
        }

        $conn->begin_transaction();

        // ---------------------------------
        // LOCK INGREDIENT
        // ---------------------------------

        $stmt = $conn->prepare("
            SELECT id, name, unit
            FROM ingredients
            WHERE id = ?
              AND is_active = 1
            FOR UPDATE
        ");

        $stmt->bind_param("i", $ingredientId);
        $stmt->execute();

        $ingredientResult = $stmt->get_result();
        $ingredient = $ingredientResult->fetch_assoc();

        $stmt->close();

        if (!$ingredient) {
            throw new Exception("Selected ingredient is unavailable.");
        }

        // ---------------------------------
        // VALIDATE SUPPLIER MAPPING
        // ---------------------------------

        $stmt = $conn->prepare("
            SELECT s.id
            FROM supplier_ingredients si
            JOIN suppliers s ON s.id = si.supplier_id
            WHERE si.supplier_id = ?
              AND si.ingredient_id = ?
              AND s.is_active = 1
            LIMIT 1
        ");

        $stmt->bind_param("ii", $supplierId, $ingredientId);
        $stmt->execute();

        $supplierResult = $stmt->get_result();
        $validSupplier = $supplierResult->fetch_assoc();

        $stmt->close();

        if (!$validSupplier) {
            throw new Exception("This supplier is not linked to the selected ingredient.");
        }

        // ---------------------------------
        // UPDATE STOCK
        // ---------------------------------

        $stmt = $conn->prepare("
            UPDATE ingredients
            SET current_stock = current_stock + ?
            WHERE id = ?
              AND is_active = 1
        ");

        $stmt->bind_param("di", $quantity, $ingredientId);
        $stmt->execute();
        $stmt->close();

        // ---------------------------------
        // RECORD STOCK MOVEMENT
        // ---------------------------------

        $movementType = "Purchase";

        $stmt = $conn->prepare("
            INSERT INTO stock_movements
            (ingredient_id, movement_type, quantity_change, note)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isds",
            $ingredientId,
            $movementType,
            $quantity,
            $note
        );

        $stmt->execute();

        $movementId = $conn->insert_id;

        $stmt->close();

        // ---------------------------------
        // RECORD SUPPLIER PURCHASE
        // ---------------------------------

        $stmt = $conn->prepare("
            INSERT INTO inventory_purchases
            (
                supplier_id,
                ingredient_id,
                stock_movement_id,
                quantity,
                total_cost,
                note
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiidds",
            $supplierId,
            $ingredientId,
            $movementId,
            $quantity,
            $totalCost,
            $note
        );

        $stmt->execute();
        $stmt->close();

        // ---------------------------------
        // COMMIT TRANSACTION
        // ---------------------------------

        $conn->commit();

        header("Location: restock.php?purchase_saved=1");
        exit;

    } catch (Exception $e) {

        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Ignore rollback error
        }

        $message = $e->getMessage();
        $messageType = "error";
    }
}

// =====================================
// SUCCESS MESSAGE
// =====================================

if (isset($_GET["purchase_saved"])) {
    $message = "Purchase recorded successfully and stock updated!";
    $messageType = "success";
}

// =====================================
// FETCH ACTIVE INGREDIENTS
// =====================================

$ingredients = [];

$result = $conn->query("
    SELECT id, name, unit, current_stock
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

// =====================================
// FETCH SUPPLIER-INGREDIENT MAPPINGS
// =====================================

$supplierMappings = [];

$result = $conn->query("
    SELECT
        si.ingredient_id,
        s.id AS supplier_id,
        s.supplier_name,
        s.company_name
    FROM supplier_ingredients si
    JOIN suppliers s ON s.id = si.supplier_id
    WHERE s.is_active = 1
    ORDER BY s.supplier_name ASC
");

while ($row = $result->fetch_assoc()) {
    $supplierMappings[] = $row;
}

// =====================================
// FETCH RECENT PURCHASES
// Includes new purchases and old Purchase movements
// =====================================

$recentPurchases = [];

$result = $conn->query("
    SELECT *
    FROM (
        SELECT
            ip.id AS purchase_id,
            i.name AS ingredient_name,
            i.unit,
            s.supplier_name,
            ip.quantity,
            ip.total_cost,
            ip.note,
            ip.purchased_at AS purchase_date,
            1 AS has_supplier

        FROM inventory_purchases ip
        JOIN ingredients i ON i.id = ip.ingredient_id
        JOIN suppliers s ON s.id = ip.supplier_id

        UNION ALL

        SELECT
            sm.id AS purchase_id,
            i.name AS ingredient_name,
            i.unit,
            '—' AS supplier_name,
            sm.quantity_change AS quantity,
            NULL AS total_cost,
            sm.note,
            sm.created_at AS purchase_date,
            0 AS has_supplier

        FROM stock_movements sm
        JOIN ingredients i ON i.id = sm.ingredient_id

        WHERE sm.movement_type = 'Purchase'
          AND NOT EXISTS (
              SELECT 1
              FROM inventory_purchases ip
              WHERE ip.stock_movement_id = sm.id
          )
    ) AS purchase_history
    ORDER BY purchase_date DESC
    LIMIT 20
");

while ($row = $result->fetch_assoc()) {
    $recentPurchases[] = $row;
}

// =====================================
// HELPER
// =====================================

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatCurrency($value) {
    if ($value === null) {
        return "—";
    }

    return "₹" . number_format((float)$value, 2);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Restock Inventory | CafeNest</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

.restock-container {
    max-width: 1200px;
    margin: auto;
    padding: 25px 20px;
}

.restock-heading {
    margin-bottom: 25px;
}

.restock-heading h1 {
    margin-bottom: 8px;
    font-size: 28px;
}

.restock-heading p {
    color: #64748b;
}

.restock-card {
    background: #ffffff;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.restock-card h2 {
    font-size: 20px;
    margin-bottom: 20px;
}

.restock-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.restock-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.restock-field label {
    font-weight: 600;
    font-size: 14px;
    color: #334155;
}

.restock-field input,
.restock-field select,
.restock-field textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
    background: #fff;
}

.restock-field input:focus,
.restock-field select:focus,
.restock-field textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
}

.restock-field textarea {
    resize: vertical;
    min-height: 90px;
}

.restock-full {
    grid-column: 1 / -1;
}

.restock-btn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 13px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.restock-btn:hover {
    background: #1d4ed8;
}

.restock-message {
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-weight: 500;
}

.restock-message.success {
    background: #dcfce7;
    color: #166534;
}

.restock-message.error {
    background: #fee2e2;
    color: #991b1b;
}

.restock-table-wrapper {
    overflow-x: auto;
}

.restock-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

.restock-table th,
.restock-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 14px;
}

.restock-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
}

.restock-table tr:hover td {
    background: #fafcff;
}

.restock-cost {
    font-weight: 600;
    color: #15803d;
}

.restock-link {
    display: inline-block;
    margin-top: 15px;
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}

.restock-link:hover {
    text-decoration: underline;
}

.supplier-info {
    font-size: 13px;
    color: #64748b;
    margin-top: 5px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

@media (max-width: 700px) {

    .restock-container {
        padding: 16px 12px;
    }

    .restock-heading h1 {
        font-size: 23px;
    }

    .restock-card {
        padding: 18px;
    }

    .restock-form-grid {
        grid-template-columns: 1fr;
    }

    .restock-full {
        grid-column: auto;
    }

    .restock-btn {
        width: 100%;
    }

}

</style>

</head>

<body>

<div class="restock-container">

    <div class="restock-heading">
        <h1>Inventory Restock</h1>
        <p>Record ingredient purchases and automatically update inventory stock.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="restock-message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================= -->
    <!-- RESTOCK FORM -->
    <!-- ================================= -->

    <div class="restock-card">

        <h2>Record New Purchase</h2>

        <form method="POST" action="restock.php">

            <input type="hidden" name="csrf_token"
                   value="<?= e($csrfToken) ?>">

            <div class="restock-form-grid">

                <div class="restock-field">

                    <label for="ingredient_id">Ingredient</label>

                    <select name="ingredient_id"
                            id="ingredient_id"
                            required>

                        <option value="">Select ingredient</option>

                        <?php foreach ($ingredients as $ingredient): ?>

                            <option
                                value="<?= (int)$ingredient["id"] ?>"
                                <?= $selectedIngredient == $ingredient["id"] ? "selected" : "" ?>
                            >

                                <?= e($ingredient["name"]) ?>
                                (Stock:
                                <?= e($ingredient["current_stock"]) ?>
                                <?= e($ingredient["unit"]) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="restock-field">

                    <label for="supplier_id">Supplier</label>

                    <select name="supplier_id"
                            id="supplier_id"
                            required
                            disabled>

                        <option value="">Select ingredient first</option>

                    </select>

                    <div class="supplier-info" id="supplierInfo">
                        Select an ingredient to see its suppliers.
                    </div>

                </div>

                <div class="restock-field">

                    <label for="quantity">Purchased Quantity</label>

                    <input
                        type="number"
                        name="quantity"
                        id="quantity"
                        min="0.001"
                        step="0.001"
                        value="<?= e($quantityValue) ?>"
                        placeholder="Enter purchased quantity"
                        required
                    >

                </div>

                <div class="restock-field">

                    <label for="total_cost">Total Purchase Cost (₹)</label>

                    <input
                        type="number"
                        name="total_cost"
                        id="total_cost"
                        min="0"
                        step="0.01"
                        value="<?= e($costValue) ?>"
                        placeholder="Total amount paid"
                        required
                    >

                </div>

                <div class="restock-field restock-full">

                    <label for="note">Purchase Note</label>

                    <textarea
                        name="note"
                        id="note"
                        maxlength="255"
                        placeholder="Optional: invoice number, quality, delivery details..."
                ><?= e($noteValue) ?></textarea>

                </div>

                <div class="restock-field restock-full">

                    <button
                        type="submit"
                        name="restock"
                        value="1"
                        class="restock-btn"
                    >
                        Record Purchase & Update Stock
                    </button>

                </div>

            </div>

        </form>

        <a href="manage_supplier_ingredients.php" class="restock-link">
            Manage Supplier–Ingredient Mapping →
        </a>

    </div>

    <!-- ================================= -->
    <!-- RECENT PURCHASES -->
    <!-- ================================= -->

    <div class="restock-card">

        <h2>Recent Purchases</h2>

        <div class="restock-table-wrapper">

            <table class="restock-table">

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

                <?php if (count($recentPurchases) > 0): ?>

                    <?php foreach ($recentPurchases as $purchase): ?>

                        <tr>

                            <td>
                                <?= e($purchase["ingredient_name"]) ?>
                            </td>

                            <td>
                                <?= e($purchase["supplier_name"]) ?>
                            </td>

                            <td>
                                <?= e($purchase["quantity"]) ?>
                                <?= e($purchase["unit"]) ?>
                            </td>

                            <td class="restock-cost">
                                <?= formatCurrency($purchase["total_cost"]) ?>
                            </td>

                            <td>
                                <?= e($purchase["note"] ?: "—") ?>
                            </td>

                            <td>
                                <?= e(date("d M Y, h:i A", strtotime($purchase["purchase_date"]))) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            No purchases recorded yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>

// =====================================
// INGREDIENT-SPECIFIC SUPPLIERS
// =====================================

const supplierMappings = <?= json_encode(
    $supplierMappings,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const ingredientSelect = document.getElementById("ingredient_id");
const supplierSelect = document.getElementById("supplier_id");
const supplierInfo = document.getElementById("supplierInfo");

const previousSupplier = <?= json_encode((string)$selectedSupplier) ?>;

function updateSuppliers() {

    const ingredientId = ingredientSelect.value;

    supplierSelect.innerHTML = "";
    supplierSelect.disabled = true;

    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "Select supplier";

    supplierSelect.appendChild(defaultOption);

    if (!ingredientId) {
        supplierInfo.textContent =
            "Select an ingredient to see its suppliers.";
        return;
    }

    const matchedSuppliers = supplierMappings.filter(function(item) {
        return String(item.ingredient_id) === String(ingredientId);
    });

    if (matchedSuppliers.length === 0) {

        supplierInfo.textContent =
            "No suppliers are linked to this ingredient. Add a mapping first.";

        return;
    }

    matchedSuppliers.forEach(function(item) {

        const option = document.createElement("option");

        option.value = item.supplier_id;

        option.textContent = item.company_name
            ? item.supplier_name + " — " + item.company_name
            : item.supplier_name;

        if (String(item.supplier_id) === String(previousSupplier)) {
            option.selected = true;
        }

        supplierSelect.appendChild(option);

    });

    supplierSelect.disabled = false;

    supplierInfo.textContent =
        matchedSuppliers.length + " supplier(s) available for this ingredient.";

}

ingredientSelect.addEventListener("change", function() {
    updateSuppliers();
});

updateSuppliers();

</script>

</body>
</html>
