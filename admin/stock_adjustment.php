
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$messageType = "";

if (!isset($_SESSION["stock_adjustment_csrf"])) {
    $_SESSION["stock_adjustment_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["stock_adjustment_csrf"];

// =====================================
// PROCESS STOCK ADJUSTMENT
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["adjust_stock"])) {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["stock_adjustment_csrf"], $token)) {

        $message = "Invalid request. Please refresh and try again.";
        $messageType = "error";

    } else {

        $ingredientId = (int)($_POST["ingredient_id"] ?? 0);
        $actualStock = (float)($_POST["actual_stock"] ?? -1);
        $note = trim($_POST["note"] ?? "");

        if ($ingredientId <= 0 || $actualStock < 0) {

            $message = "Please select an ingredient and enter a valid stock quantity.";
            $messageType = "error";

        } else {

            try {

                $conn->begin_transaction();

                // Lock ingredient row
                $stmt = $conn->prepare("
                    SELECT id, name, unit, current_stock
                    FROM ingredients
                    WHERE id = ? AND is_active = 1
                    FOR UPDATE
                ");

                $stmt->bind_param("i", $ingredientId);
                $stmt->execute();

                $ingredient = $stmt->get_result()->fetch_assoc();

                if (!$ingredient) {
                    throw new Exception("Ingredient not found or inactive.");
                }

                $systemStock = (float)$ingredient["current_stock"];
                $difference = $actualStock - $systemStock;

                // No adjustment needed
                if (abs($difference) < 0.00001) {

                    $conn->rollback();

                    $message = "No adjustment required. System stock already matches actual stock.";
                    $messageType = "success";

                } else {

                    // Update current stock
                    $update = $conn->prepare("
                        UPDATE ingredients
                        SET current_stock = ?
                        WHERE id = ?
                    ");

                    $update->bind_param("di", $actualStock, $ingredientId);
                    $update->execute();

                    // Record adjustment movement
                    if ($note === "") {
                        $note = "Physical stock correction";
                    }

                    $movementNote =
                        "Stock adjustment: " .
                        number_format($systemStock, 2) .
                        " → " .
                        number_format($actualStock, 2) .
                        " " .
                        $ingredient["unit"] .
                        ". " .
                        $note;

                    $movement = $conn->prepare("
                        INSERT INTO stock_movements
                        (ingredient_id, order_id, movement_type, quantity_change, note)
                        VALUES (?, NULL, 'Adjustment', ?, ?)
                    ");

                    $movement->bind_param(
                        "ids",
                        $ingredientId,
                        $difference,
                        $movementNote
                    );

                    $movement->execute();

                    $conn->commit();

                    $message = "Stock adjusted successfully for " .
                        htmlspecialchars($ingredient["name"]) .
                        ". Difference: " .
                        number_format($difference, 2) .
                        " " .
                        htmlspecialchars($ingredient["unit"]);

                    $messageType = "success";
                }

            } catch (Throwable $e) {

                $conn->rollback();

                $message = "Stock adjustment failed: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// =====================================
// FETCH INGREDIENTS
// =====================================

$ingredients = [];

$result = $conn->query("
    SELECT id, name, unit, current_stock, minimum_stock
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

// =====================================
// RECENT ADJUSTMENTS
// =====================================

$adjustments = [];

$result = $conn->query("
    SELECT
        sm.id,
        sm.quantity_change,
        sm.note,
        sm.created_at,
        i.name AS ingredient_name,
        i.unit
    FROM stock_movements sm
    JOIN ingredients i ON sm.ingredient_id = i.id
    WHERE sm.movement_type = 'Adjustment'
    ORDER BY sm.created_at DESC
    LIMIT 15
");

while ($row = $result->fetch_assoc()) {
    $adjustments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Adjustment | CafeNest</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #f8fafc;
    font-family: Arial, Helvetica, sans-serif;
    color: #1e293b;
}

.container {
    max-width: 1200px;
    margin: auto;
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
    margin: 0;
    font-size: 28px;
}

.subtitle {
    color: #64748b;
    margin-top: 8px;
    font-size: 14px;
}

.btn {
    display: inline-block;
    text-decoration: none;
    padding: 11px 17px;
    border-radius: 9px;
    background: #2563eb;
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn:hover {
    background: #1d4ed8;
}

.card {
    background: #fff;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.05);
    border: 1px solid #e8edf4;
}

.card h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 19px;
}

.message {
    padding: 14px 17px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.message.success {
    background: #dcfce7;
    color: #166534;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
}

.info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    color: #1e40af;
    font-size: 14px;
    line-height: 1.6;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    font-size: 14px;
    background: #fff;
    outline: none;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 90px;
}

.stock-preview {
    background: #f8fafc;
    padding: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin: 15px 0 20px;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.preview-item small {
    display: block;
    color: #64748b;
    margin-bottom: 7px;
}

.preview-item strong {
    font-size: 20px;
}

.difference-positive {
    color: #16a34a;
}

.difference-negative {
    color: #dc2626;
}

.difference-zero {
    color: #64748b;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #e8edf4;
    font-size: 14px;
}

th {
    color: #64748b;
    background: #f8fafc;
    font-weight: 600;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
}

.positive {
    color: #16a34a;
    font-weight: 600;
}

.negative {
    color: #dc2626;
    font-weight: 600;
}

.empty {
    text-align: center;
    color: #94a3b8;
    padding: 30px;
}

@media (max-width: 700px) {
    body {
        padding: 14px;
    }

    .header h1 {
        font-size: 23px;
    }

    .card {
        padding: 17px;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .preview-grid {
        grid-template-columns: 1fr;
        gap: 13px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Stock Adjustment</h1>
            <div class="subtitle">
                Correct inventory based on your physical stock count.
            </div>
        </div>

        <a href="ingredients.php" class="btn">← Back to Ingredients</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Adjust Inventory</h2>

        <div class="info-box">
            Enter the actual quantity you physically counted.
            CafeNest will calculate the difference and update your inventory automatically.
        </div>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-grid">

                <div class="form-group">
                    <label>Select Ingredient</label>

                    <select name="ingredient_id" id="ingredientSelect" required>
                        <option value="">-- Select Ingredient --</option>

                        <?php foreach ($ingredients as $ingredient): ?>
                            <option
                                value="<?= (int)$ingredient["id"] ?>"
                                data-stock="<?= htmlspecialchars($ingredient["current_stock"]) ?>"
                                data-unit="<?= htmlspecialchars($ingredient["unit"]) ?>"
                            >
                                <?= htmlspecialchars($ingredient["name"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Actual Physical Stock</label>

                    <input
                        type="number"
                        name="actual_stock"
                        id="actualStock"
                        min="0"
                        step="0.001"
                        placeholder="Enter actual stock counted"
                        required
                    >
                </div>

            </div>

            <div class="stock-preview">

                <div class="preview-grid">

                    <div class="preview-item">
                        <small>Current System Stock</small>
                        <strong id="systemStock">--</strong>
                    </div>

                    <div class="preview-item">
                        <small>Actual Stock</small>
                        <strong id="actualPreview">--</strong>
                    </div>

                    <div class="preview-item">
                        <small>Difference</small>
                        <strong id="difference">--</strong>
                    </div>

                </div>

            </div>

            <div class="form-group">
                <label>Reason / Note</label>

                <textarea name="note"
                    placeholder="Example: Physical stock count, damaged stock, correction..."></textarea>
            </div>

            <button type="submit" name="adjust_stock" class="btn">
                Save Stock Adjustment
            </button>

        </form>

    </div>

    <div class="card">

        <h2>Recent Stock Adjustments</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ingredient</th>
                        <th>Difference</th>
                        <th>Unit</th>
                        <th>Note</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($adjustments)): ?>

                    <tr>
                        <td colspan="5" class="empty">
                            No stock adjustments recorded yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($adjustments as $adjustment): ?>

                        <tr>
                            <td>
                                <?= htmlspecialchars($adjustment["created_at"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($adjustment["ingredient_name"]) ?>
                            </td>

                            <td class="<?= $adjustment["quantity_change"] >= 0 ? "positive" : "negative" ?>">
                                <?= $adjustment["quantity_change"] > 0 ? "+" : "" ?>
                                <?= number_format((float)$adjustment["quantity_change"], 3) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($adjustment["unit"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($adjustment["note"] ?? "") ?>
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
const ingredientSelect = document.getElementById("ingredientSelect");
const actualStockInput = document.getElementById("actualStock");

const systemStockDisplay = document.getElementById("systemStock");
const actualPreviewDisplay = document.getElementById("actualPreview");
const differenceDisplay = document.getElementById("difference");

function updatePreview() {

    const selectedOption = ingredientSelect.options[ingredientSelect.selectedIndex];

    if (!selectedOption || !selectedOption.value) {

        systemStockDisplay.textContent = "--";
        actualPreviewDisplay.textContent = "--";
        differenceDisplay.textContent = "--";

        differenceDisplay.className = "";

        return;
    }

    const currentStock = parseFloat(selectedOption.dataset.stock || 0);
    const unit = selectedOption.dataset.unit || "";
    const actualStock = actualStockInput.value;

    systemStockDisplay.textContent = currentStock + " " + unit;

    if (actualStock === "") {

        actualPreviewDisplay.textContent = "--";
        differenceDisplay.textContent = "--";
        differenceDisplay.className = "";

        return;
    }

    const actual = parseFloat(actualStock);
    const difference = actual - currentStock;

    actualPreviewDisplay.textContent = actual + " " + unit;

    differenceDisplay.textContent =
        (difference > 0 ? "+" : "") +
        difference.toFixed(3) +
        " " +
        unit;

    if (difference > 0) {
        differenceDisplay.className = "difference-positive";
    } else if (difference < 0) {
        differenceDisplay.className = "difference-negative";
    } else {
        differenceDisplay.className = "difference-zero";
    }
}

ingredientSelect.addEventListener("change", updatePreview);
actualStockInput.addEventListener("input", updatePreview);
</script>

</body>
</html>
