
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

$message = "";
$messageType = "";

// =====================================
// ADD OR UPDATE RECIPE
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_recipe"])) {

    $productId = (int)($_POST["product_id"] ?? 0);
    $ingredientId = (int)($_POST["ingredient_id"] ?? 0);
    $quantity = (float)($_POST["quantity_required"] ?? 0);

    if ($productId <= 0 || $ingredientId <= 0 || $quantity <= 0) {

        $message = "Please enter valid recipe details.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO product_recipes
                (product_id, ingredient_id, quantity_required)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                quantity_required = VALUES(quantity_required)
        ");

        $stmt->bind_param("iid", $productId, $ingredientId, $quantity);

        if ($stmt->execute()) {
            $message = "Recipe ingredient saved successfully.";
            $messageType = "success";
        } else {
            $message = "Unable to save recipe. Please try again.";
            $messageType = "error";
        }

        $stmt->close();
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

$products = $conn->query("
    SELECT id, name
    FROM products
    ORDER BY name ASC
");

// =====================================
// FETCH ACTIVE INGREDIENTS
// =====================================

$ingredients = $conn->query("
    SELECT id, name, unit
    FROM ingredients
    WHERE is_active = 1
    ORDER BY name ASC
");

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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
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

        .save-button {
            margin-top: 20px;
            padding: 12px 22px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 14px;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .save-button {
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

    <div class="card">

        <h2>Add Recipe Ingredient</h2>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Select Product</label>

                    <select name="product_id" required>
                        <option value="">Choose Product</option>

                        <?php if ($products): ?>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <option value="<?= (int)$product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Select Ingredient</label>

                    <select name="ingredient_id" required>
                        <option value="">Choose Ingredient</option>

                        <?php if ($ingredients): ?>
                            <?php while ($ingredient = $ingredients->fetch_assoc()): ?>
                                <option value="<?= (int)$ingredient['id'] ?>">
                                    <?= htmlspecialchars($ingredient['name']) ?>
                                    (<?= htmlspecialchars($ingredient['unit']) ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity Required</label>

                    <input
                        type="number"
                        name="quantity_required"
                        min="0.001"
                        step="0.001"
                        placeholder="Example: 18"
                        required
                    >
                </div>

            </div>

            <button type="submit" name="save_recipe" class="save-button">
                Save Recipe
            </button>

            <p class="help-text">
                Enter the quantity of this ingredient required to prepare
                one unit of the selected product. Use the same unit shown
                beside the ingredient.
            </p>

        </form>

    </div>

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

</body>
</html>
