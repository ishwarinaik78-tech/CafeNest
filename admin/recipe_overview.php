
<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";

// =====================================
// FETCH PRODUCTS AND THEIR RECIPES
// =====================================

$sql = "
    SELECT
        p.id AS product_id,
        p.name AS product_name,
        pr.id AS recipe_id,
        i.name AS ingredient_name,
        i.unit,
        pr.quantity_required
    FROM products p
    LEFT JOIN product_recipes pr
        ON p.id = pr.product_id
    LEFT JOIN ingredients i
        ON pr.ingredient_id = i.id
    ORDER BY p.name ASC, i.name ASC
";

$result = $conn->query($sql);

$products = [];
$totalRecipeLines = 0;
$configuredProducts = 0;
$productsWithoutRecipes = 0;

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $productId = (int)$row["product_id"];

        if (!isset($products[$productId])) {

            $products[$productId] = [
                "name" => $row["product_name"],
                "ingredients" => []
            ];
        }

        if ($row["recipe_id"] !== null) {

            $products[$productId]["ingredients"][] = [
                "name" => $row["ingredient_name"],
                "unit" => $row["unit"],
                "quantity" => $row["quantity_required"]
            ];

            $totalRecipeLines++;
        }
    }
}

// =====================================
// CALCULATE SUMMARY
// =====================================

$totalProducts = count($products);

foreach ($products as $product) {

    if (count($product["ingredients"]) > 0) {
        $configuredProducts++;
    } else {
        $productsWithoutRecipes++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recipe Overview | CafeNest</title>

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
            max-width: 1150px;
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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #ffffff;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .summary-card p {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .summary-card h2 {
            margin: 0;
            font-size: 27px;
            color: #1e293b;
        }

        .product-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .product-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .product-header h2 {
            margin: 0;
            font-size: 19px;
            color: #1e293b;
        }

        .status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .configured {
            background: #dcfce7;
            color: #166534;
        }

        .missing {
            background: #fef3c7;
            color: #92400e;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 450px;
        }

        th,
        td {
            padding: 13px;
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
            color: #334155;
            font-size: 14px;
        }

        .empty-message {
            padding: 18px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            color: #92400e;
            font-size: 14px;
        }

        .empty-state {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 35px;
            text-align: center;
            color: #64748b;
        }

        .action-link {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 16px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
        }

        .action-link:hover {
            background: #1d4ed8;
        }

        @media (max-width: 850px) {

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 550px) {

            body {
                padding: 15px;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
            }

            .summary-card h2 {
                font-size: 23px;
            }

            .product-card {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <a href="recipes.php" class="back-link">
        ← Back to Recipe Management
    </a>

    <div class="page-header">

        <h1>Product-wise Recipe Overview</h1>

        <p>
            View all ingredients required for each menu product.
        </p>

    </div>

    <!-- SUMMARY -->

    <div class="summary-grid">

        <div class="summary-card">
            <p>Total Products</p>
            <h2><?= $totalProducts ?></h2>
        </div>

        <div class="summary-card">
            <p>Recipes Configured</p>
            <h2><?= $configuredProducts ?></h2>
        </div>

        <div class="summary-card">
            <p>Missing Recipes</p>
            <h2><?= $productsWithoutRecipes ?></h2>
        </div>

        <div class="summary-card">
            <p>Total Recipe Entries</p>
            <h2><?= $totalRecipeLines ?></h2>
        </div>

    </div>

    <!-- PRODUCT-WISE RECIPES -->

    <?php if (count($products) > 0): ?>

        <?php foreach ($products as $product): ?>

            <div class="product-card">

                <div class="product-header">

                    <h2>
                        <?= htmlspecialchars($product["name"]) ?>
                    </h2>

                    <?php if (count($product["ingredients"]) > 0): ?>

                        <span class="status configured">
                            Recipe Configured
                        </span>

                    <?php else: ?>

                        <span class="status missing">
                            Recipe Not Configured
                        </span>

                    <?php endif; ?>

                </div>

                <?php if (count($product["ingredients"]) > 0): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>Ingredient</th>
                                    <th>Quantity Required</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($product["ingredients"] as $ingredient): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($ingredient["name"]) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars((string)$ingredient["quantity"]) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($ingredient["unit"]) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-message">
                        No ingredients have been assigned to this product yet.
                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-state">

            <h3>No Products Found</h3>

            <p>
                Add products before configuring their recipes.
            </p>

        </div>

    <?php endif; ?>

    <a href="recipes.php" class="action-link">
        Manage Recipes
    </a>

</div>

</body>
</html>
