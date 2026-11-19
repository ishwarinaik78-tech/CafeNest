<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['receive_po_csrf'])) {
    $_SESSION['receive_po_csrf'] = bin2hex(random_bytes(32));
}

function rpo_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = "";
$success = isset($_GET['received']);
$poId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$poId) {
    die("Invalid purchase order ID.");
}

// =========================================
// RECEIVE PURCHASE ORDER
// =========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {

    $poId = filter_input(INPUT_POST, 'po_id', FILTER_VALIDATE_INT);
    $token = $_POST['csrf_token'] ?? '';

    if (!$poId) {
        $error = "Invalid purchase order.";
    } elseif (!hash_equals($_SESSION['receive_po_csrf'], $token)) {
        $error = "Invalid security token. Refresh the page and try again.";
    } else {

        try {

            $conn->begin_transaction();

            // Lock the PO row to prevent duplicate receiving
            $stmt = $conn->prepare("
                SELECT id, supplier_id, status, po_number
                FROM purchase_orders
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->bind_param("i", $poId);
            $stmt->execute();

            $po = $stmt->get_result()->fetch_assoc();

            if (!$po) {
                throw new Exception("Purchase order not found.");
            }

            if ($po['status'] !== 'Ordered') {
                throw new Exception(
                    "Only purchase orders with Ordered status can be received."
                );
            }

            // Fetch PO items
            $stmt = $conn->prepare("
                SELECT
                    poi.ingredient_id,
                    poi.quantity,
                    poi.unit_cost,
                    poi.subtotal,
                    i.name,
                    i.unit
                FROM purchase_order_items poi
                INNER JOIN ingredients i
                    ON i.id = poi.ingredient_id
                WHERE poi.purchase_order_id = ?
            ");

            $stmt->bind_param("i", $poId);
            $stmt->execute();

            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($items)) {
                throw new Exception("This purchase order contains no ingredients.");
            }

            // Prepared statements
            $lockIngredient = $conn->prepare("
                SELECT id, current_stock
                FROM ingredients
                WHERE id = ?
                FOR UPDATE
            ");

            $updateStock = $conn->prepare("
                UPDATE ingredients
                SET current_stock = current_stock + ?
                WHERE id = ?
            ");

            $insertMovement = $conn->prepare("
                INSERT INTO stock_movements
                (ingredient_id, order_id, movement_type, quantity_change, note)
                VALUES (?, NULL, 'Purchase', ?, ?)
            ");

            $insertPurchase = $conn->prepare("
                INSERT INTO inventory_purchases
                (supplier_id, ingredient_id, stock_movement_id,
                 quantity, total_cost, note)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            // Process each ingredient
            foreach ($items as $item) {

                $ingredientId = (int)$item['ingredient_id'];
                $quantity = (float)$item['quantity'];
                $unitCost = (float)$item['unit_cost'];
                $totalCost = round($quantity * $unitCost, 2);

                if ($quantity <= 0) {
                    throw new Exception("Invalid quantity in purchase order.");
                }

                // Lock ingredient row
                $lockIngredient->bind_param("i", $ingredientId);
                $lockIngredient->execute();

                $ingredient = $lockIngredient->get_result()->fetch_assoc();

                if (!$ingredient) {
                    throw new Exception(
                        "Ingredient not found: " . $item['name']
                    );
                }

                // Increase ingredient stock
                $updateStock->bind_param(
                    "di",
                    $quantity,
                    $ingredientId
                );

                $updateStock->execute();

                // Record stock movement
                $note = "Received against PO " . $po['po_number'];

                $insertMovement->bind_param(
                    "ids",
                    $ingredientId,
                    $quantity,
                    $note
                );

                $insertMovement->execute();

                $movementId = $conn->insert_id;

                // Record purchase history
                $supplierId = (int)$po['supplier_id'];

                $insertPurchase->bind_param(
                    "iiidds",
                    $supplierId,
                    $ingredientId,
                    $movementId,
                    $quantity,
                    $totalCost,
                    $note
                );

                $insertPurchase->execute();
            }

            // Mark purchase order as received
            $stmt = $conn->prepare("
                UPDATE purchase_orders
                SET status = 'Received'
                WHERE id = ?
            ");

            $stmt->bind_param("i", $poId);
            $stmt->execute();

            $conn->commit();

            $_SESSION['receive_po_csrf'] = bin2hex(random_bytes(32));

            header("Location: receive_purchase_order.php?id=" . $poId . "&received=1");
            exit;

        } catch (Throwable $e) {

            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // Transaction may already have ended
            }

            error_log("CafeNest Receive PO Error: " . $e->getMessage());

            $error = $e->getMessage();
        }
    }
}

// =========================================
// FETCH PURCHASE ORDER
// =========================================

$stmt = $conn->prepare("
    SELECT
        po.*,
        s.supplier_name,
        s.company_name,
        s.contact_person,
        s.phone,
        s.email
    FROM purchase_orders po
    INNER JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.id = ?
");

$stmt->bind_param("i", $poId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Purchase order not found.");
}

// =========================================
// FETCH ORDER ITEMS
// =========================================

$stmt = $conn->prepare("
    SELECT
        poi.quantity,
        poi.unit_cost,
        poi.subtotal,
        i.name AS ingredient_name,
        i.unit
    FROM purchase_order_items poi
    INNER JOIN ingredients i ON i.id = poi.ingredient_id
    WHERE poi.purchase_order_id = ?
    ORDER BY poi.id
");

$stmt->bind_param("i", $poId);
$stmt->execute();

$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Receive Purchase Order | CafeNest</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #222;
}

.container {
    max-width: 1050px;
    margin: 30px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 24px;
    border-radius: 13px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.muted {
    color: #777;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.info-item {
    margin-bottom: 14px;
}

.info-item strong {
    display: block;
    margin-bottom: 5px;
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
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background: #f7f7f7;
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.Draft {
    background: #fff4d6;
    color: #936900;
}

.Ordered {
    background: #e4efff;
    color: #245aab;
}

.Received {
    background: #dff7e7;
    color: #237c43;
}

.Cancelled {
    background: #ffe4e4;
    color: #a52828;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success {
    background: #e8f8ed;
    color: #217a3b;
}

.error {
    background: #fdeaea;
    color: #a52828;
}

.warning {
    background: #fff4d6;
    color: #886300;
}

.btn {
    display: inline-block;
    border: none;
    padding: 12px 17px;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
    margin-top: 12px;
}

.btn-primary {
    background: #218347;
    color: white;
}

.btn-secondary {
    background: #e9e9ef;
    color: #333;
}

.total {
    text-align: right;
    font-size: 24px;
    font-weight: bold;
    margin-top: 20px;
}

@media(max-width:650px) {
    .container {
        margin: 10px auto;
        padding: 12px;
    }

    .card {
        padding: 16px;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="card header">

        <div>
            <h1>Receive Purchase Order</h1>
            <p class="muted"><?= rpo_h($order['po_number']) ?></p>
        </div>

        <span class="status <?= rpo_h($order['status']) ?>">
            <?= rpo_h($order['status']) ?>
        </span>

    </div>

    <?php if ($success): ?>
        <div class="alert success">
            Purchase order received successfully! Inventory stock has been updated.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <?= rpo_h($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Supplier Information</h2>

        <div class="info-grid">

            <div>

                <div class="info-item">
                    <strong>Supplier</strong>
                    <?= rpo_h($order['supplier_name']) ?>
                </div>

                <div class="info-item">
                    <strong>Company</strong>
                    <?= rpo_h($order['company_name'] ?? '-') ?>
                </div>

                <div class="info-item">
                    <strong>Contact Person</strong>
                    <?= rpo_h($order['contact_person'] ?? '-') ?>
                </div>

            </div>

            <div>

                <div class="info-item">
                    <strong>Phone</strong>
                    <?= rpo_h($order['phone'] ?? '-') ?>
                </div>

                <div class="info-item">
                    <strong>Email</strong>
                    <?= rpo_h($order['email'] ?? '-') ?>
                </div>

                <div class="info-item">
                    <strong>Created Date</strong>
                    <?= rpo_h($order['created_at']) ?>
                </div>

            </div>

        </div>

    </div>

    <div class="card">

        <h2>Ingredients to Receive</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Ordered Quantity</th>
                        <th>Unit Cost</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($items as $item): ?>

                    <tr>

                        <td><?= rpo_h($item['ingredient_name']) ?></td>

                        <td>
                            <?= number_format((float)$item['quantity'], 3) ?>
                            <?= rpo_h($item['unit']) ?>
                        </td>

                        <td>
                            ₹<?= number_format((float)$item['unit_cost'], 2) ?>
                        </td>

                        <td>
                            ₹<?= number_format((float)$item['subtotal'], 2) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <div class="total">
            Total: ₹<?= number_format((float)$order['total_amount'], 2) ?>
        </div>

    </div>

    <?php if (!empty($order['notes'])): ?>

    <div class="card">

        <h2>Order Notes</h2>

        <p><?= nl2br(rpo_h($order['notes'])) ?></p>

    </div>

    <?php endif; ?>

    <?php if ($order['status'] === 'Ordered'): ?>

        <div class="card">

            <div class="alert warning">
                Confirm that you have received all the ingredients before proceeding.
                This action will increase your inventory stock.
            </div>

            <form method="POST"
                  onsubmit="return confirm('Confirm receipt of this entire purchase order? Inventory stock will be increased.');">

                <input type="hidden" name="po_id"
                       value="<?= (int)$order['id'] ?>">

                <input type="hidden" name="csrf_token"
                       value="<?= rpo_h($_SESSION['receive_po_csrf']) ?>">

                <button type="submit"
                        name="receive_po"
                        value="1"
                        class="btn btn-primary">
                    Confirm Receipt & Update Stock
                </button>

            </form>

        </div>

    <?php elseif ($order['status'] === 'Received'): ?>

        <div class="card">
            <div class="alert success">
                This purchase order has already been received. Stock was updated when it was received.
            </div>
        </div>

    <?php else: ?>

        <div class="card">
            <div class="alert warning">
                Only purchase orders with Ordered status can be received.
            </div>
        </div>

    <?php endif; ?>

    <a href="view_purchase_order.php?id=<?= (int)$order['id'] ?>"
       class="btn btn-secondary">
        ← Back to PO Details
    </a>

    <a href="purchase_orders.php" class="btn btn-secondary">
        Purchase Orders
    </a>

</div>

</body>
</html>