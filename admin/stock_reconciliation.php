
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['reconciliation_csrf'])) {
    $_SESSION['reconciliation_csrf'] = bin2hex(random_bytes(32));
}

function sr_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$error = "";

$selectedIngredient = (int)($_GET['ingredient_id'] ?? 0);
$physicalStockInput = '';
$reasonInput = '';

// =====================================
// HANDLE STOCK RECONCILIATION
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['reconciliation_csrf']) ||
        !hash_equals($_SESSION['reconciliation_csrf'], $token)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $ingredientId = filter_var(
            $_POST['ingredient_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $physicalStockInput = trim($_POST['physical_stock'] ?? '');
        $reasonInput = trim($_POST['reason'] ?? '');

        if (!$ingredientId || $ingredientId <= 0) {
            $error = "Please select a valid ingredient.";
        } elseif (
            $physicalStockInput === '' ||
            !is_numeric($physicalStockInput) ||
            (float)$physicalStockInput < 0
        ) {
            $error = "Enter a valid physical stock quantity.";
        } elseif (strlen($reasonInput) > 500) {
            $error = "Reason cannot exceed 500 characters.";
        } else {

            $physicalStock = (float)$physicalStockInput;

            try {

                $conn->begin_transaction();

                // Lock ingredient row
                $lockStmt = $conn->prepare("
                    SELECT id, name, unit, current_stock
                    FROM ingredients
                    WHERE id = ?
                      AND is_active = 1
                    FOR UPDATE
                ");

                $lockStmt->bind_param("i", $ingredientId);
                $lockStmt->execute();

                $ingredient = $lockStmt->get_result()->fetch_assoc();

                if (!$ingredient) {
                    throw new Exception("Ingredient not found or inactive.");
                }

                $systemStock = (float)$ingredient['current_stock'];
                $difference = $physicalStock - $systemStock;

                // No adjustment required
                if (abs($difference) < 0.000001) {

                    $conn->commit();

                    $message = "Stock matches the physical count. No adjustment was required.";

                } else {

                    // Update stock
                    $updateStmt = $conn->prepare("
                        UPDATE ingredients
                        SET current_stock = ?
                        WHERE id = ?
                    ");

                    $updateStmt->bind_param(
                        "di",
                        $physicalStock,
                        $ingredientId
                    );

                    $updateStmt->execute();

                    // Audit note
                    $auditNote = "Stock reconciliation. "
                        . "System stock: " . number_format($systemStock, 3)
                        . ", Physical stock: " . number_format($physicalStock, 3)
                        . ", Difference: " . number_format($difference, 3);

                    if ($reasonInput !== '') {
                        $auditNote .= ". Reason: " . $reasonInput;
                    }

                    // Record adjustment movement
                    $movementStmt = $conn->prepare("
                        INSERT INTO stock_movements
                        (
                            ingredient_id,
                            movement_type,
                            quantity_change,
                            note,
                            created_at
                        )
                        VALUES (?, 'Adjustment', ?, ?, NOW())
                    ");

                    $movementStmt->bind_param(
                        "ids",
                        $ingredientId,
                        $difference,
                        $auditNote
                    );

                    $movementStmt->execute();

                    $conn->commit();

                    $message = "Stock reconciled successfully for "
                        . $ingredient['name']
                        . ". Difference: "
                        . ($difference > 0 ? '+' : '')
                        . number_format($difference, 3)
                        . " "
                        . $ingredient['unit'];

                    $physicalStockInput = '';
                    $reasonInput = '';
                }

            } catch (Throwable $e) {

                $conn->rollback();

                $error = "Reconciliation failed: " . $e->getMessage();
            }
        }
    }
}

// =====================================
// FETCH INGREDIENTS
// =====================================

$ingredientsQuery = $conn->query("
    SELECT
        id,
        name,
        unit,
        current_stock,
        minimum_stock
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

$ingredients = [];

while ($row = $ingredientsQuery->fetch_assoc()) {
    $ingredients[] = $row;
}

// =====================================
// FETCH RECENT ADJUSTMENTS
// =====================================

$recentQuery = $conn->query("
    SELECT
        sm.id,
        sm.ingredient_id,
        sm.quantity_change,
        sm.note,
        sm.created_at,
        i.name AS ingredient_name,
        i.unit
    FROM stock_movements sm
    INNER JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE sm.movement_type = 'Adjustment'
      AND sm.note LIKE '%Stock reconciliation%'
    ORDER BY sm.id DESC
    LIMIT 10
");

$recentAdjustments = [];

while ($row = $recentQuery->fetch_assoc()) {
    $recentAdjustments[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Stock Reconciliation | CafeNest</title>

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
    max-width: 1200px;
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
    margin: 0;
    color: #64748b;
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
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.btn.gray {
    background: #475569;
}

.btn.green {
    background: #059669;
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

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.field label {
    display: block;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 8px;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
}

.field textarea {
    min-height: 100px;
    resize: vertical;
}

.full {
    grid-column: 1 / -1;
}

.stock-info {
    padding: 15px;
    border-radius: 9px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    line-height: 1.7;
}

.notice {
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
    line-height: 1.6;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 750px;
    border-collapse: collapse;
}

th, td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #475569;
    white-space: nowrap;
}

.positive {
    color: #059669;
    font-weight: bold;
}

.negative {
    color: #dc2626;
    font-weight: bold;
}

.note {
    color: #64748b;
    font-size: 13px;
    max-width: 400px;
    overflow-wrap: anywhere;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 30px;
}

@media(max-width: 700px) {

    .container {
        width: 94%;
        margin: 20px auto;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .panel {
        padding: 16px;
    }
}

@media print {

    body {
        background: white;
    }

    .actions,
    form,
    .no-print {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 0;
    }

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
        <h1>Stock Reconciliation</h1>
        <p>Compare recorded stock with the actual physical inventory.</p>
    </div>

    <div class="actions">
        <a href="inventory_dashboard.php" class="btn gray">Dashboard</a>
        <a href="inventory_activity_log.php" class="btn green">Activity Log</a>
        <button onclick="window.print()" class="btn">Print</button>
    </div>

</div>

<!-- MESSAGES -->

<?php if ($message !== ''): ?>
    <div class="notice success">
        <?= sr_escape($message) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="notice error">
        <?= sr_escape($error) ?>
    </div>
<?php endif; ?>

<!-- RECONCILIATION FORM -->

<div class="panel">

    <h2>Perform Stock Count</h2>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= sr_escape($_SESSION['reconciliation_csrf']) ?>"
        >

        <div class="form-grid">

            <div class="field full">

                <label for="ingredient_id">Select Ingredient</label>

                <select name="ingredient_id" id="ingredient_id" required>

                    <option value="">-- Select Ingredient --</option>

                    <?php foreach ($ingredients as $ingredient): ?>

                        <option
                            value="<?= (int)$ingredient['id'] ?>"
                            data-stock="<?= (float)$ingredient['current_stock'] ?>"
                            data-unit="<?= sr_escape($ingredient['unit']) ?>"
                            <?= $selectedIngredient === (int)$ingredient['id'] ? 'selected' : '' ?>
                        >
                            <?= sr_escape($ingredient['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="stock-info full" id="stockInfo">
                Select an ingredient to view its recorded stock.
            </div>

            <div class="field">

                <label for="physical_stock">Physically Counted Quantity</label>

                <input
                    type="number"
                    name="physical_stock"
                    id="physical_stock"
                    min="0"
                    step="0.001"
                    value="<?= sr_escape($physicalStockInput) ?>"
                    placeholder="Enter actual stock quantity"
                    required
                >

            </div>

            <div class="field">

                <label for="reason">Reason for Difference</label>

                <textarea
                    name="reason"
                    id="reason"
                    maxlength="500"
                    placeholder="Example: Stock counted manually, damaged goods, missing stock..."
                ><?= sr_escape($reasonInput) ?></textarea>

            </div>

            <div class="field full">

                <button type="submit" class="btn">
                    Save Reconciliation
                </button>

            </div>

        </div>

    </form>

</div>

<!-- RECENT RECONCILIATIONS -->

<div class="panel">

    <h2>Recent Reconciliation Adjustments</h2>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Movement ID</th>
                    <th>Date</th>
                    <th>Ingredient</th>
                    <th>Difference</th>
                    <th>Adjustment Note</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($recentAdjustments)): ?>

                <tr>
                    <td colspan="5" class="empty">
                        No reconciliation adjustments recorded yet.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($recentAdjustments as $adjustment): ?>

                    <?php
                    $change = (float)$adjustment['quantity_change'];
                    ?>

                    <tr>

                        <td>#<?= (int)$adjustment['id'] ?></td>

                        <td>
                            <?= sr_escape(date(
                                'd M Y, h:i A',
                                strtotime($adjustment['created_at'])
                            )) ?>
                        </td>

                        <td>
                            <strong><?= sr_escape($adjustment['ingredient_name']) ?></strong>
                        </td>

                        <td class="<?= $change >= 0 ? 'positive' : 'negative' ?>">
                            <?= ($change > 0 ? '+' : '') . number_format($change, 3) ?>
                            <?= sr_escape($adjustment['unit']) ?>
                        </td>

                        <td class="note">
                            <?= sr_escape($adjustment['note'] ?: '—') ?>
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

const ingredientSelect = document.getElementById('ingredient_id');
const stockInfo = document.getElementById('stockInfo');
const physicalStockInput = document.getElementById('physical_stock');

function updateStockInfo() {

    const option = ingredientSelect.options[ingredientSelect.selectedIndex];

    if (!option || !option.value) {
        stockInfo.textContent =
            'Select an ingredient to view its recorded stock.';
        return;
    }

    const stock = parseFloat(option.dataset.stock || 0);
    const unit = option.dataset.unit || '';

    stockInfo.innerHTML =
        'System Recorded Stock: <strong>' +
        stock.toFixed(3) + ' ' + unit +
        '</strong>';

    physicalStockInput.setAttribute('data-system-stock', stock);
}

ingredientSelect.addEventListener('change', updateStockInfo);

updateStockInfo();

</script>

</body>
</html>
