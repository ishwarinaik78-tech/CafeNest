
<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================
// REQUEST VALIDATION
// =====================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: orders.php");
    exit;
}

$token = $_POST['csrf_token'] ?? "";

if (
    !isset($_SESSION['order_csrf']) ||
    !hash_equals($_SESSION['order_csrf'], $token)
) {
    die("Invalid request. Please go back and try again.");
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? "";

$allowedStatuses = [
    "Pending",
    "Preparing",
    "Ready",
    "Completed",
    "Cancelled"
];

if (!$orderId || $orderId < 1) {
    die("Invalid order ID.");
}

if (!in_array($status, $allowedStatuses, true)) {
    die("Invalid order status.");
}

// =====================================
// INVENTORY HELPERS
// =====================================

function getIngredientStock($conn, $ingredientId)
{
    $stmt = $conn->prepare(
        "SELECT id, name, current_stock
         FROM ingredients
         WHERE id = ?
         FOR UPDATE"
    );

    $stmt->bind_param("i", $ingredientId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

// =====================================
// MAIN ORDER TRANSACTION
// =====================================

try {

    $conn->begin_transaction();

    // Lock the order so simultaneous updates cannot
    // process the same order at the same time.

    $stmt = $conn->prepare(
        "SELECT id, status
         FROM orders
         WHERE id = ?
         FOR UPDATE"
    );

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Order not found.");
    }

    $currentStatus = $order['status'];

    // No work needed if status is unchanged.

    if ($currentStatus === $status) {
        $conn->commit();

        header("Location: order_details.php?id=" . $orderId . "&updated=1");
        exit;
    }

    // Cancelled orders cannot be reopened.

    if ($currentStatus === "Cancelled") {
        throw new Exception(
            "A cancelled order cannot be reopened."
        );
    }

    // =====================================
    // CHECK INVENTORY TRACKING
    // =====================================

    $stmt = $conn->prepare(
        "SELECT inventory_status
         FROM inventory_order_tracking
         WHERE order_id = ?
         FOR UPDATE"
    );

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $tracking = $stmt->get_result()->fetch_assoc();

    $inventoryStatus = $tracking['inventory_status'] ?? null;

    // =====================================
    // PENDING -> PREPARING
    // DEDUCT INGREDIENT STOCK
    // =====================================

    if (
        $currentStatus === "Pending" &&
        $status === "Preparing"
    ) {

        if ($inventoryStatus === "Deducted") {
            throw new Exception(
                "Inventory has already been deducted for this order."
            );
        }

        if ($inventoryStatus === "Restored") {
            throw new Exception(
                "This order's inventory was already restored. It cannot be processed again."
            );
        }

        // Check every ordered product has a recipe.

        $stmt = $conn->prepare(
            "SELECT DISTINCT oi.product_id
             FROM order_items oi
             WHERE oi.order_id = ?
             AND NOT EXISTS (
                 SELECT 1
                 FROM product_recipes pr
                 WHERE pr.product_id = oi.product_id
             )"
        );

        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $missingRecipes = $stmt->get_result();

        if ($missingRecipes->num_rows > 0) {

            $missingNames = [];

            while ($row = $missingRecipes->fetch_assoc()) {

                $productId = (int)$row['product_id'];

                $productStmt = $conn->prepare(
                    "SELECT name FROM products WHERE id = ?"
                );

                $productStmt->bind_param("i", $productId);
                $productStmt->execute();

                $product = $productStmt->get_result()->fetch_assoc();

                $missingNames[] = $product['name'] ?? "Product #" . $productId;
            }

            throw new Exception(
                "Recipe missing for: " . implode(", ", $missingNames)
            );
        }

        // Calculate total ingredient requirements.
        // Same ingredient used in multiple products is combined.

        $stmt = $conn->prepare(
            "SELECT
                pr.ingredient_id,
                i.name AS ingredient_name,
                SUM(pr.quantity_required * oi.quantity) AS required_quantity
             FROM order_items oi
             INNER JOIN product_recipes pr
                ON pr.product_id = oi.product_id
             INNER JOIN ingredients i
                ON i.id = pr.ingredient_id
             WHERE oi.order_id = ?
             GROUP BY pr.ingredient_id, i.name
             ORDER BY pr.ingredient_id ASC"
        );

        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $requirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($requirements)) {
            throw new Exception(
                "No recipe ingredients found for this order."
            );
        }

        // Lock ingredients in ascending ID order and
        // verify all stock before deducting anything.

        foreach ($requirements as $item) {

            $ingredientId = (int)$item['ingredient_id'];
            $required = (float)$item['required_quantity'];

            $ingredient = getIngredientStock($conn, $ingredientId);

            if (!$ingredient) {
                throw new Exception(
                    "An ingredient in the recipe no longer exists."
                );
            }

            if ((float)$ingredient['current_stock'] < $required) {

                throw new Exception(
                    "Insufficient stock for " .
                    $ingredient['name'] .
                    ". Required: " . $required .
                    ", Available: " . $ingredient['current_stock']
                );
            }
        }

        // Deduct stock and record every movement.

        foreach ($requirements as $item) {

            $ingredientId = (int)$item['ingredient_id'];
            $required = (float)$item['required_quantity'];

            $negativeQuantity = -$required;

            $updateStock = $conn->prepare(
                "UPDATE ingredients
                 SET current_stock = current_stock - ?
                 WHERE id = ?
                 AND current_stock >= ?"
            );

            $updateStock->bind_param(
                "did",
                $required,
                $ingredientId,
                $required
            );

            $updateStock->execute();

            if ($updateStock->affected_rows !== 1) {
                throw new Exception(
                    "Stock changed while processing. Please try again."
                );
            }

            $note = "Automatic usage for order #" . $orderId;

            $movement = $conn->prepare(
                "INSERT INTO stock_movements
                 (ingredient_id, order_id, movement_type, quantity_change, note)
                 VALUES (?, ?, 'Usage', ?, ?)"
            );

            $movement->bind_param(
                "iids",
                $ingredientId,
                $orderId,
                $negativeQuantity,
                $note
            );

            $movement->execute();
        }

        // Mark inventory as deducted.

        $trackingUpdate = $conn->prepare(
            "INSERT INTO inventory_order_tracking
             (order_id, inventory_status, deducted_at)
             VALUES (?, 'Deducted', NOW())
             ON DUPLICATE KEY UPDATE
                inventory_status = 'Deducted',
                deducted_at = NOW(),
                restored_at = NULL"
        );

        $trackingUpdate->bind_param("i", $orderId);
        $trackingUpdate->execute();
    }

    // =====================================
    // CANCEL ORDER
    // RESTORE PREVIOUSLY DEDUCTED STOCK
    // =====================================

    if ($status === "Cancelled" && $inventoryStatus === "Deducted") {

        // Restore from original Usage movements.
        // Do not use current recipes because they may have changed.

        $stmt = $conn->prepare(
            "SELECT ingredient_id, ABS(quantity_change) AS restore_quantity
             FROM stock_movements
             WHERE order_id = ?
             AND movement_type = 'Usage'
             ORDER BY ingredient_id ASC"
        );

        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $movements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($movements)) {
            throw new Exception(
                "Cannot restore stock: original usage records were not found."
            );
        }

        foreach ($movements as $movementRow) {

            $ingredientId = (int)$movementRow['ingredient_id'];
            $quantity = (float)$movementRow['restore_quantity'];

            // Lock ingredient before restoring.

            $ingredient = getIngredientStock($conn, $ingredientId);

            if (!$ingredient) {
                throw new Exception(
                    "An ingredient required for restoration no longer exists."
                );
            }

            $restore = $conn->prepare(
                "UPDATE ingredients
                 SET current_stock = current_stock + ?
                 WHERE id = ?"
            );

            $restore->bind_param("di", $quantity, $ingredientId);
            $restore->execute();

            $note = "Automatic restoration for cancelled order #" . $orderId;

            $restoration = $conn->prepare(
                "INSERT INTO stock_movements
                 (ingredient_id, order_id, movement_type, quantity_change, note)
                 VALUES (?, ?, 'Restoration', ?, ?)"
            );

            $restoration->bind_param(
                "iids",
                $ingredientId,
                $orderId,
                $quantity,
                $note
            );

            $restoration->execute();
        }

        // Mark inventory as restored.

        $trackingUpdate = $conn->prepare(
            "UPDATE inventory_order_tracking
             SET inventory_status = 'Restored',
                 restored_at = NOW()
             WHERE order_id = ?"
        );

        $trackingUpdate->bind_param("i", $orderId);
        $trackingUpdate->execute();
    }

    // =====================================
    // UPDATE ORDER STATUS
    // =====================================

    $update = $conn->prepare(
        "UPDATE orders
         SET status = ?
         WHERE id = ?"
    );

    $update->bind_param("si", $status, $orderId);
    $update->execute();

    $conn->commit();

    header("Location: order_details.php?id=" . $orderId . "&updated=1");
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    die(
        "Order update failed: " .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        ' <a href="order_details.php?id=' . (int)$orderId . '">Go back</a>'
    );
}
