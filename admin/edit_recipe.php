
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

$message = "";
$messageType = "";

$recipeId = (int)($_GET["id"] ?? $_POST["recipe_id"] ?? 0);

if ($recipeId <= 0) {
    header("Location: recipes.php");
    exit;
}

// =====================================
// UPDATE RECIPE QUANTITY
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_recipe"])) {

    $quantity = (float)($_POST["quantity_required"] ?? 0);

    if ($quantity <= 0) {

        $message = "Please enter a valid quantity.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare("
            UPDATE product_recipes
            SET quantity_required = ?
            WHERE id = ?
        ");

        $stmt->bind_param("di", $quantity, $recipeId);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $message = "Recipe quantity updated successfully.";
            $messageType = "success";
        } else {
            $message = "Unable to update recipe quantity.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

// =====================================
// FETCH RECIPE DETAILS
// =====================================

$stmt = $conn->prepare("
    SELECT
        pr.id,
        pr.quantity_required,
        p.name AS product_name,
        i.name AS ingredient_name,
        i.unit
    FROM product_recipes pr
    INNER JOIN products p ON pr.product_id = p.id
    INNER JOIN ingredients i ON pr.ingredient_id = i.id
    WHERE pr.id = ?
");

$stmt->bind_param("i", $recipeId);
$stmt->execute();

$result = $stmt->get_result();
$recipe = $result->fetch_assoc();

$stmt->close();

if (!$recipe) {
    header("Location: recipes.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Recipe | CafeNest</title>

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
            max-width: 750px;
            margin: auto;
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

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin-bottom: 8px;
            font-size: 28px;
        }

        .page-header p {
            margin: 0;
            color: #64748b;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 22px;
            font-size: 19px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #334155;
        }

        .readonly-field {
            width: 100%;
            padding: 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            color: #475569;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .save-button {
            padding: 12px 22px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
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

        .help-text {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 15px;
        }

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .card {
                padding: 18px;
            }

            .save-button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="recipes.php" class="back-link">
        ← Back to Recipes
    </a>

    <div class="page-header">
        <h1>Edit Recipe</h1>
        <p>Update the ingredient quantity required for this product.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Recipe Details</h2>

        <form method="POST">

            <input
                type="hidden"
                name="recipe_id"
                value="<?= (int)$recipe['id'] ?>"
            >

            <div class="form-group">
                <label>Product</label>

                <div class="readonly-field">
                    <?= htmlspecialchars($recipe['product_name']) ?>
                </div>
            </div>

            <div class="form-group">
                <label>Ingredient</label>

                <div class="readonly-field">
                    <?= htmlspecialchars($recipe['ingredient_name']) ?>
                </div>
            </div>

            <div class="form-group">
                <label>
                    Quantity Required (<?= htmlspecialchars($recipe['unit']) ?>)
                </label>

                <input
                    type="number"
                    name="quantity_required"
                    min="0.001"
                    step="0.001"
                    value="<?= htmlspecialchars((string)$recipe['quantity_required']) ?>"
                    required
                >
            </div>

            <button
                type="submit"
                name="update_recipe"
                class="save-button"
            >
                Update Recipe
            </button>

            <p class="help-text">
                This changes only the recipe quantity. Your current ingredient
                stock will not be changed.
            </p>

        </form>

    </div>

</div>

</body>
</html>
