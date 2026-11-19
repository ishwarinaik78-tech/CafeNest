
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";
$messageType = "success";

$selectedIngredient = 0;
$quantityValue = "";
$reasonValue = "";
$noteValue = "";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["wastage_csrf"])) {
    $_SESSION["wastage_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["wastage_csrf"];

// =====================================
// RECORD WASTAGE
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["record_wastage"])) {

    $token = $_POST["csrf_token"] ?? "";

    $ingredientId = (int)($_POST["ingredient_id"] ?? 0);
    $quantity = (float)($_POST["quantity"] ?? 0);
    $reason = trim($_POST["reason"] ?? "");
    $note = trim($_POST["note"] ?? "");

    $selectedIngredient = $ingredientId;
    $quantityValue = $_POST["quantity"] ?? "";
    $reasonValue = $reason;
    $noteValue = $note;

    $transactionStarted = false;

    try {

        if (!hash_equals($_SESSION["wastage_csrf"], $token)) {
            throw new Exception("Invalid request. Please refresh the page.");
        }

        if ($ingredientId <= 0) {
            throw new Exception("Please select an ingredient.");
        }

        if ($quantity <= 0) {
            throw new Exception("Wastage quantity must be greater than zero.");
        }

        $allowedReasons = [
            "Spillage",
            "Expired",
            "Spoiled",
            "Burnt",
            "Preparation Error",
            "Damaged",
            "Other"
        ];

        if (!in_array($reason, $allowedReasons, true)) {
            throw new Exception("Please select a valid wastage reason.");
        }

        if (strlen($note) > 255) {
            throw new Exception("Note cannot exceed 255 characters.");
        }

        // Start transaction
        $conn->begin_transaction();
        $transactionStarted = true;

        // ---------------------------------
        // LOCK INGREDIENT ROW
        // ---------------------------------

        $stmt = $conn->prepare("
            SELECT id, name, unit, current_stock
            FROM ingredients
            WHERE id = ?
              AND is_active = 1
            FOR UPDATE
        ");

        $stmt->bind_param("i", $ingredientId);
        $stmt->execute();

        $result = $stmt->get_result();
        $ingredient = $result->fetch_assoc();

        $stmt->close();

        if (!$ingredient) {
            throw new Exception("Selected ingredient is unavailable.");
        }

        // ---------------------------------
        // CHECK AVAILABLE STOCK
        // ---------------------------------

        if ($quantity > (float)$ingredient["current_stock"]) {
            throw new Exception(
                "Insufficient stock. Available: " .
                $ingredient["current_stock"] . " " .
                $ingredient["unit"]
            );
        }

        // ---------------------------------
        // REDUCE STOCK
        // ---------------------------------

        $stmt = $conn->prepare("
            UPDATE ingredients
            SET current_stock = current_stock - ?
            WHERE id = ?
              AND is_active = 1
        ");

        $stmt->bind_param("di", $quantity, $ingredientId);
        $stmt->execute();
        $stmt->close();

        // ---------------------------------
        // RECORD STOCK MOVEMENT
        // ---------------------------------

        $movementType = "Wastage";

        $movementNote = "Reason: " . $reason;

        if ($note !== "") {
            $movementNote .= " | " . $note;
        }

        $stmt = $conn->prepare("
            INSERT INTO stock_movements
            (ingredient_id, movement_type, quantity_change, note)
            VALUES (?, ?, ?, ?)
        ");

        // Wastage is stored as a negative stock movement.
        $negativeQuantity = -$quantity;

        $stmt->bind_param(
            "isds",
            $ingredientId,
            $movementType,
            $negativeQuantity,
            $movementNote
        );

        $stmt->execute();
        $stmt->close();

        // ---------------------------------
        // COMMIT
        // ---------------------------------

        $conn->commit();
        $transactionStarted = false;

        header("Location: wastage.php?recorded=1");
        exit;

    } catch (Throwable $e) {

        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // Ignore rollback failure
            }
        }

        $message = $e->getMessage();
        $messageType = "error";
    }
}

// =====================================
// SUCCESS MESSAGE
// =====================================

if (isset($_GET["recorded"])) {
    $message = "Wastage recorded successfully and stock updated!";
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
// FETCH RECENT WASTAGE RECORDS
// =====================================

$wastageRecords = [];

$result = $conn->query("
    SELECT
        sm.id,
        i.name AS ingredient_name,
        i.unit,
        ABS(sm.quantity_change) AS wasted_quantity,
        sm.note,
        sm.created_at
    FROM stock_movements sm
    JOIN ingredients i ON i.id = sm.ingredient_id
    WHERE sm.movement_type = 'Wastage'
    ORDER BY sm.created_at DESC
    LIMIT 30
");

while ($row = $result->fetch_assoc()) {
    $wastageRecords[] = $row;
}

// =====================================
// WASTAGE SUMMARY
// =====================================

$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total_records,
        COALESCE(SUM(ABS(quantity_change)), 0) AS total_quantity
    FROM stock_movements
    WHERE movement_type = 'Wastage'
");

$wastageSummary = $summaryResult->fetch_assoc();

// =====================================
// HELPER
// =====================================

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Inventory Wastage | CafeNest</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

.wastage-container {
    max-width: 1250px;
    margin: auto;
    padding: 25px 20px;
}

.wastage-header {
    margin-bottom: 25px;
}

.wastage-header h1 {
    font-size: 28px;
    margin-bottom: 8px;
}

.wastage-header p {
    color: #64748b;
}

.wastage-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.wastage-stat,
.wastage-card {
    background: #fff;
    padding: 22px;
    border-radius: 13px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}

.wastage-stat span {
    color: #64748b;
    display: block;
    font-size: 14px;
    margin-bottom: 10px;
}

.wastage-stat h2 {
    margin: 0;
    font-size: 25px;
    color: #1e293b;
}

.wastage-card {
    margin-bottom: 25px;
}

.wastage-card h2 {
    font-size: 20px;
    margin: 0 0 20px;
}

.wastage-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.wastage-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.wastage-field label {
    font-size: 14px;
    font-weight: 600;
    color: #334155;
}

.wastage-field input,
.wastage-field select,
.wastage-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    border: 1px solid #dbe2ea;
    border-radius: 8px;
    outline: none;
    background: #fff;
    font-size: 14px;
}

.wastage-field input:focus,
.wastage-field select:focus,
.wastage-field textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
}

.wastage-field textarea {
    resize: vertical;
    min-height: 90px;
}

.wastage-full {
    grid-column: 1 / -1;
}

.wastage-button {
    padding: 13px 20px;
    border: none;
    border-radius: 8px;
    background: #dc2626;
    color: white;
    font-weight: 600;
    cursor: pointer;
}

.wastage-button:hover {
    background: #b91c1c;
}

.wastage-message {
    padding: 14px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-weight: 500;
}

.wastage-message.success {
    background: #dcfce7;
    color: #166534;
}

.wastage-message.error {
    background: #fee2e2;
    color: #991b1b;
}

.wastage-table-wrapper {
    overflow-x: auto;
}

.wastage-table {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
}

.wastage-table th,
.wastage-table td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.wastage-table th {
    background: #f8fafc;
    color: #475569;
}

.wastage-table tbody tr:hover {
    background: #fafcff;
}

.wastage-quantity {
    font-weight: 700;
    color: #dc2626;
}

.wastage-empty {
    text-align: center;
    color: #64748b;
    padding: 30px !important;
}

.wastage-link {
    display: inline-block;
    margin-top: 15px;
    text-decoration: none;
    color: #2563eb;
    font-weight: 600;
}

@media(max-width: 700px) {

    .wastage-container {
        padding: 15px 10px;
    }

    .wastage-header h1 {
        font-size: 23px;
    }

    .wastage-stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .wastage-stat {
        padding: 16px;
    }

    .wastage-stat h2 {
        font-size: 20px;
    }

    .wastage-grid {
        grid-template-columns: 1fr;
    }

    .wastage-full {
        grid-column: auto;
    }

    .wastage-button {
        width: 100%;
    }

}

</style>

</head>

<body>

<div class="wastage-container">

    <!-- HEADER -->

    <div class="wastage-header">
        <h1>Inventory Wastage</h1>
        <p>Record spoiled, damaged, expired or wasted ingredients.</p>
    </div>

    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="wastage-message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <!-- SUMMARY -->

    <div class="wastage-stats">

        <div class="wastage-stat">
            <span>Total Wastage Records</span>
            <h2><?= (int)$wastageSummary["total_records"] ?></h2>
        </div>

        <div class="wastage-stat">
            <span>Total Quantity Wasted</span>
            <h2><?= number_format((float)$wastageSummary["total_quantity"], 3) ?></h2>
        </div>

    </div>

    <!-- FORM -->

    <div class="wastage-card">

        <h2>Record New Wastage</h2>

        <form method="POST" action="wastage.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($csrfToken) ?>">

            <div class="wastage-grid">

                <div class="wastage-field">

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
                                (Available:
                                <?= e($ingredient["current_stock"]) ?>
                                <?= e($ingredient["unit"]) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="wastage-field">

                    <label for="quantity">Wasted Quantity</label>

                    <input
                        type="number"
                        name="quantity"
                        id="quantity"
                        min="0.001"
                        step="0.001"
                        value="<?= e($quantityValue) ?>"
                        placeholder="Enter quantity wasted"
                        required
                    >

                </div>

                <div class="wastage-field">

                    <label for="reason">Reason</label>

                    <select name="reason" id="reason" required>

                        <option value="">Select reason</option>

                        <?php
                        $reasons = [
                            "Spillage",
                            "Expired",
                            "Spoiled",
                            "Burnt",
                            "Preparation Error",
                            "Damaged",
                            "Other"
                        ];
                        ?>

                        <?php foreach ($reasons as $reason): ?>

                            <option
                                value="<?= e($reason) ?>"
                                <?= $reasonValue === $reason ? "selected" : "" ?>
                            >
                                <?= e($reason) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="wastage-field">

                    <label for="note">Additional Note</label>

                    <textarea
                        name="note"
                        id="note"
                        maxlength="255"
                        placeholder="Optional details..."
                ><?= e($noteValue) ?></textarea>

                </div>

                <div class="wastage-field wastage-full">

                    <button
                        type="submit"
                        name="record_wastage"
                        value="1"
                        class="wastage-button"
                    >
                        Record Wastage & Reduce Stock
                    </button>

                </div>

            </div>

        </form>

    </div>

    <!-- RECENT WASTAGE -->

    <div class="wastage-card">

        <h2>Recent Wastage Records</h2>

        <div class="wastage-table-wrapper">

            <table class="wastage-table">

                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Quantity Wasted</th>
                        <th>Reason / Note</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($wastageRecords)): ?>

                    <?php foreach ($wastageRecords as $record): ?>

                        <tr>

                            <td><?= e($record["ingredient_name"]) ?></td>

                            <td class="wastage-quantity">
                                <?= number_format((float)$record["wasted_quantity"], 3) ?>
                                <?= e($record["unit"]) ?>
                            </td>

                            <td><?= e($record["note"] ?: "—") ?></td>

                            <td>
                                <?= e(date("d M Y, h:i A", strtotime($record["created_at"]))) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4" class="wastage-empty">
                            No wastage records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <a href="stock_history.php" class="wastage-link">
            View Full Stock Movement History →
        </a>

    </div>

</div>

</body>
</html>
