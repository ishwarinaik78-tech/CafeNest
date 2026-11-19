<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

$message = "";
$messageType = "";

// =====================================
// SAVE MULTIPLE RECIPE INGREDIENTS
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_recipe"])) {

    $productId = (int)($_POST["product_id"] ?? 0);

    $ingredientIds = $_POST["ingredient_ids"] ?? [];
    $quantities = $_POST["quantities"] ?? [];

    if (
        $productId <= 0 ||
        !is_array($ingredientIds) ||
        !is_array($quantities) ||
        count($ingredientIds) === 0 ||
        count($ingredientIds) !== count($quantities)
    ) {
        $message = "Please enter valid recipe details.";
        $messageType = "error";

    } else {

        $validatedRows = [];
        $seenIngredients = [];
        $valid = true;

        // Validate every ingredient row
        foreach ($ingredientIds as $index => $ingredientValue) {

            $ingredientId = filter_var(
                $ingredientValue,
                FILTER_VALIDATE_INT
            );

            $quantityValue = $quantities[$index] ?? "";

            if (
                !$ingredientId ||
                $ingredientId <= 0 ||
                !is_numeric($quantityValue) ||
                !is_finite((float)$quantityValue) ||
                (float)$quantityValue <= 0
            ) {
                $valid = false;
                break;
            }

            $quantity = (float)$quantityValue;

            // Prevent duplicate ingredients
            if (isset($seenIngredients[$ingredientId])) {
                $valid = false;
                break;
            }

            $seenIngredients[$ingredientId] = true;

            $validatedRows[] = [
                "ingredient_id" => $ingredientId,
                "quantity" => $quantity
            ];
        }

        if (!$valid) {

            $message = "Please check the ingredient selections and quantities. Duplicate ingredients are not allowed.";
            $messageType = "error";

        } else {

            try {

                // Check that the selected product exists
                $productCheck = $conn->prepare("
                    SELECT id
                    FROM products
                    WHERE id = ?
                ");

                $productCheck->bind_param("i", $productId);
                $productCheck->execute();

                $productExists = $productCheck->get_result()->fetch_assoc();

                $productCheck->close();

                if (!$productExists) {
                    throw new Exception("Selected product does not exist.");
                }

                // Check that every ingredient is active
                $ingredientCheck = $conn->prepare("
                    SELECT id
                    FROM ingredients
                    WHERE id = ?
                    AND is_active = 1
                ");

                foreach ($validatedRows as $row) {

                    $ingredientId = $row["ingredient_id"];

                    $ingredientCheck->bind_param("i", $ingredientId);
                    $ingredientCheck->execute();

                    $ingredientExists = $ingredientCheck->get_result()->fetch_assoc();

                    if (!$ingredientExists) {
                        throw new Exception(
                            "One or more selected ingredients are unavailable."
                        );
                    }
                }

                $ingredientCheck->close();

                // Save all rows together
                $conn->begin_transaction();

                $saveStmt = $conn->prepare("
                    INSERT INTO product_recipes
                        (product_id, ingredient_id, quantity_required)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        quantity_required = VALUES(quantity_required)
                ");

                foreach ($validatedRows as $row) {

                    $ingredientId = $row["ingredient_id"];
                    $quantity = $row["quantity"];

                    $saveStmt->bind_param(
                        "iid",
                        $productId,
                        $ingredientId,
                        $quantity
                    );

                    $saveStmt->execute();
                }

                $saveStmt->close();

                $conn->commit();

                $message = "All recipe ingredients saved successfully!";
                $messageType = "success";

            } catch (Throwable $e) {

                try {
                    $conn->rollback();
                } catch (Throwable $rollbackError) {
                    // Keep the original error message.
                }

                $message = "Unable to save the recipe. " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}


// =====================================
// DELETE RECIPE INGREDIENT
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_recipe"])) {

    $recipeId = (int)($_POST["recipe_id"] ?? 0);

    if ($recipeId > 0) {

        $stmt = $conn->prepare("
            DELETE FROM product_recipes
            WHERE id = ?
        ");

        $stmt->bind_param("i", $recipeId);

        if ($stmt->execute()) {
            $message = "Recipe ingredient removed successfully.";
            $messageType = "success";
        } else {
            $message = "Unable to remove recipe ingredient.";
            $messageType = "error";
        }

        $stmt->close();
    }
}


// =====================================
// FETCH PRODUCTS
// =====================================

$products = [];

$productResult = $conn->query("
    SELECT id, name
    FROM products
    ORDER BY name ASC
");

while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
}


// =====================================
// FETCH ACTIVE INGREDIENTS
// =====================================

$ingredients = [];

$ingredientResult = $conn->query("
    SELECT id, name, unit
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

while ($row = $ingredientResult->fetch_assoc()) {
    $ingredients[] = $row;
}


// =====================================
// FETCH EXISTING RECIPES
// =====================================

$recipes = $conn->query("
    SELECT
        pr.id AS recipe_id,
        p.name AS product_name,
        i.name AS ingredient_name,
        i.unit,
        pr.quantity_required
    FROM product_recipes pr
    INNER JOIN products p
        ON pr.product_id = p.id
    INNER JOIN ingredients i
        ON pr.ingredient_id = i.id
    ORDER BY p.name ASC, i.name ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recipe Management | CafeNest</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f8fafc;
            font-family: Arial, sans-serif;
            color: #1e293b;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin-bottom: 8px;
            font-size: 28px;
            color: #1e293b;
        }

        .page-header p {
            color: #64748b;
            margin: 0;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 19px;
            color: #1e293b;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            color: #334155;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            background: white;
            color: #334155;
            outline: none;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .product-selection {
            margin-bottom: 22px;
            max-width: 100%;
        }

        .ingredient-rows {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .ingredient-row {
            display: grid;
            grid-template-columns: 1.5fr 1fr auto;
            gap: 15px;
            align-items: end;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
        }

        .remove-row-button {
            padding: 12px 15px;
            border: none;
            border-radius: 7px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .remove-row-button:hover {
            background: #fecaca;
        }

        .add-row-button {
            margin-top: 16px;
            padding: 11px 16px;
            border: 1px solid #2563eb;
            border-radius: 7px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .add-row-button:hover {
            background: #dbeafe;
        }

        .save-button {
            margin-top: 20px;
            width: 100%;
            padding: 13px 22px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .save-button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f1f5f9;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
        }

        td {
            font-size: 14px;
            color: #334155;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .edit-button {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 6px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .edit-button:hover {
            background: #bfdbfe;
        }

        .delete-button {
            padding: 8px 13px;
            border: none;
            border-radius: 6px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .delete-button:hover {
            background: #fecaca;
        }

        .empty-message {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .help-text {
            margin-top: 15px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .ingredient-row {
                grid-template-columns: 1fr;
            }

            .remove-row-button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <a href="ingredients.php" class="back-link">
        ← Back to Ingredients
    </a>

    <div class="page-header">
        <h1>Recipe Management</h1>
        <p>Connect menu products with the ingredients required to prepare them.</p>
    </div>


    <?php if ($message !== ""): ?>

        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================
         MULTIPLE INGREDIENT RECIPE FORM
    ====================================== -->

    <div class="card">

        <h2>Add Product Recipe</h2>

        <form method="POST" id="recipeForm">

            <div class="form-group product-selection">

                <label for="product_id">Select Product</label>

                <select name="product_id" id="product_id" required>

                    <option value="">Choose Product</option>

                    <?php foreach ($products as $product): ?>

                        <option value="<?= (int)$product['id'] ?>">
                            <?= htmlspecialchars($product['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <h3 style="font-size:15px; margin-bottom:12px;">
                Ingredients Required
            </h3>


            <div class="ingredient-rows" id="ingredientRows">

                <div class="ingredient-row">

                    <div class="form-group">

                        <label>Select Ingredient</label>

                        <select name="ingredient_ids[]" class="ingredient-select" required>

                            <option value="">Choose Ingredient</option>

                            <?php foreach ($ingredients as $ingredient): ?>

                                <option value="<?= (int)$ingredient['id'] ?>">
                                    <?= htmlspecialchars($ingredient['name']) ?>
                                    (<?= htmlspecialchars($ingredient['unit']) ?>)
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Quantity Required</label>

                        <input
                            type="number"
                            name="quantities[]"
                            min="0.001"
                            step="0.001"
                            placeholder="Example: 18"
                            required
                        >

                    </div>


                    <button
                        type="button"
                        class="remove-row-button"
                        disabled
                        style="opacity:0.5; cursor:not-allowed;"
                    >
                        Remove
                    </button>

                </div>

            </div>


            <button
                type="button"
                class="add-row-button"
                id="addIngredientButton"
            >
                + Add Another Ingredient
            </button>


            <button
                type="submit"
                name="save_recipe"
                value="1"
                class="save-button"
            >
                Save Complete Recipe
            </button>


            <p class="help-text">
                Add all ingredients required to prepare one unit of the selected product.
                Use the same unit shown beside each ingredient.
                Existing ingredient quantities for this product will be updated if you save them again.
            </p>

        </form>

    </div>


    <!-- =====================================
         EXISTING PRODUCT RECIPES
    ====================================== -->

    <div class="card">

        <h2>Existing Product Recipes</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Product</th>
                        <th>Ingredient</th>
                        <th>Quantity Required</th>
                        <th>Unit</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if ($recipes && $recipes->num_rows > 0): ?>

                        <?php while ($recipe = $recipes->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($recipe['product_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($recipe['ingredient_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($recipe['quantity_required']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($recipe['unit']) ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="edit_recipe.php?id=<?= (int)$recipe['recipe_id'] ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>


                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Remove this ingredient from the recipe?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="recipe_id"
                                                value="<?= (int)$recipe['recipe_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_recipe"
                                                class="delete-button"
                                            >
                                                Remove
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="empty-message">
                                No recipes added yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- =====================================
     JAVASCRIPT: ADD / REMOVE ROWS
====================================== -->

<script>

const ingredientRows = document.getElementById("ingredientRows");
const addIngredientButton = document.getElementById("addIngredientButton");

function updateRemoveButtons() {

    const rows = ingredientRows.querySelectorAll(".ingredient-row");

    rows.forEach(function(row) {

        const removeButton = row.querySelector(".remove-row-button");

        removeButton.disabled = rows.length === 1;

        removeButton.style.opacity = rows.length === 1 ? "0.5" : "1";

        removeButton.style.cursor = rows.length === 1
            ? "not-allowed"
            : "pointer";

    });
}


// Prevent selecting the same ingredient twice
function updateIngredientOptions() {

    const selects = ingredientRows.querySelectorAll(".ingredient-select");

    const selectedValues = Array.from(selects).map(function(select) {
        return select.value;
    });

    selects.forEach(function(select) {

        Array.from(select.options).forEach(function(option) {

            if (option.value === "") {
                return;
            }

            const selectedInAnotherRow = selectedValues.some(function(value, index) {
                return value === option.value && selects[index] !== select;
            });

            option.disabled = selectedInAnotherRow;

        });

    });

}


// Add ingredient row
addIngredientButton.addEventListener("click", function() {

    const firstRow = ingredientRows.querySelector(".ingredient-row");

    const newRow = firstRow.cloneNode(true);

    newRow.querySelector(".ingredient-select").value = "";

    newRow.querySelector('input[name="quantities[]"]').value = "";

    ingredientRows.appendChild(newRow);

    updateRemoveButtons();
    updateIngredientOptions();

});


// Remove ingredient row
ingredientRows.addEventListener("click", function(event) {

    if (event.target.classList.contains("remove-row-button")) {

        const rows = ingredientRows.querySelectorAll(".ingredient-row");

        if (rows.length > 1) {

            event.target.closest(".ingredient-row").remove();

            updateRemoveButtons();
            updateIngredientOptions();

        }

    }

});


// Update dropdowns when an ingredient is selected
ingredientRows.addEventListener("change", function(event) {

    if (event.target.classList.contains("ingredient-select")) {
        updateIngredientOptions();
    }

});


// Initial setup
updateRemoveButtons();
updateIngredientOptions();

</script>

</body>
</html>