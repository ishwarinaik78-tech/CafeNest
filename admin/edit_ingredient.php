
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = "";
$success = "";

$id = filter_var($_GET["id"] ?? $_POST["id"] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid ingredient ID.");
}

// Update ingredient
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $unit = trim($_POST["unit"] ?? "");

    $minimum = filter_var(
        $_POST["minimum_stock"] ?? null,
        FILTER_VALIDATE_FLOAT
    );

    $cost = filter_var(
        $_POST["cost_per_unit"] ?? null,
        FILTER_VALIDATE_FLOAT
    );

    if (
        $name === "" ||
        strlen($name) > 150 ||
        $unit === "" ||
        $minimum === false || $minimum < 0 ||
        $cost === false || $cost < 0
    ) {
        $error = "Please enter valid ingredient details.";
    } else {

        try {
            $stmt = $conn->prepare("
                UPDATE ingredients
                SET name = ?,
                    unit = ?,
                    minimum_stock = ?,
                    cost_per_unit = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssddi",
                $name,
                $unit,
                $minimum,
                $cost,
                $id
            );

            $stmt->execute();

            $success = "Ingredient updated successfully.";

        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() === 1062) {
                $error = "An ingredient with this name already exists.";
            } else {
                $error = "Unable to update ingredient.";
            }
        }
    }
}

// Fetch ingredient details
$stmt = $conn->prepare("
    SELECT *
    FROM ingredients
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$ingredient = $stmt->get_result()->fetch_assoc();

if (!$ingredient) {
    die("Ingredient not found.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Ingredient | CafeNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-container {
            max-width: 650px;
            margin: 40px auto;
            padding: 20px;
        }

        .edit-panel {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,.06);
        }

        .edit-panel label {
            display: block;
            margin: 18px 0 7px;
            font-weight: 600;
        }

        .edit-panel input,
        .edit-panel select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .edit-panel button {
            margin-top: 22px;
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin: 15px 0;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .stock-info {
            margin-top: 18px;
            padding: 13px;
            background: #f1f5f9;
            border-radius: 7px;
        }
    </style>
</head>

<body>

<div class="edit-container">

    <div class="edit-panel">

        <h1>Edit Ingredient</h1>

        <p>Update ingredient information.</p>

        <?php if ($success): ?>
            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <label>Ingredient Name</label>

            <input
                type="text"
                name="name"
                maxlength="150"
                value="<?= htmlspecialchars($ingredient["name"]) ?>"
                required
            >

            <label>Unit</label>

            <select name="unit" required>
                <?php
                $units = ["g", "kg", "ml", "L", "piece", "packet"];
                foreach ($units as $unit):
                ?>
                    <option
                        value="<?= htmlspecialchars($unit) ?>"
                        <?= $ingredient["unit"] === $unit ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($unit) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Minimum Stock Alert</label>

            <input
                type="number"
                name="minimum_stock"
                min="0"
                step="0.001"
                value="<?= htmlspecialchars($ingredient["minimum_stock"]) ?>"
                required
            >

            <label>Cost Per Unit (₹)</label>

            <input
                type="number"
                name="cost_per_unit"
                min="0"
                step="0.01"
                value="<?= htmlspecialchars($ingredient["cost_per_unit"]) ?>"
                required
            >

            <div class="stock-info">
                <strong>Current Stock:</strong>
                <?= number_format((float)$ingredient["current_stock"], 3) ?>
                <?= htmlspecialchars($ingredient["unit"]) ?>

                <p>
                    To change current stock, use the Stock Restocking page.
                    This keeps your stock history consistent.
                </p>
            </div>

            <button type="submit">Save Changes</button>

        </form>

        <p>
            <a href="ingredients.php">← Back to Ingredients</a>
        </p>

    </div>

</div>

</body>
</html>
