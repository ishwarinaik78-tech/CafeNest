<?php

session_start();

require_once __DIR__ . "/config/db.php";

if (isset($_SESSION['admin_id'])) {
    header("Location: admin/dashboard.php");
    exit;
}

if (empty($_SESSION['login_token'])) {
    $_SESSION['login_token'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? "";

    if (!hash_equals($_SESSION['login_token'], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $email = trim($_POST['email'] ?? "");
        $password = $_POST['password'] ?? "";

        if ($email === "" || $password === "") {
            $error = "Please enter your email and password.";
        } else {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id, username, email, password
                 FROM admins
                 WHERE email = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($result);

            if ($admin && password_verify($password, $admin['password'])) {

                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                unset($_SESSION['login_token']);

                header("Location: admin/dashboard.php");
                exit;

            } else {
                $error = "Incorrect email or password.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | CafeNest</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-page">

    <div class="login-container">

        <a href="index.php" class="login-brand">CafeNest</a>

        <h1>Admin Login</h1>

        <p class="login-subtitle">
            Sign in to manage your cafe.
        </p>

        <?php if ($error !== ""): ?>
            <div class="login-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['login_token']); ?>"
            >

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter admin email"
                required
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter password"
                required
            >

            <button type="submit" class="login-submit">
                Sign In
            </button>

        </form>

        <a href="index.php" class="back-home">
            ← Back to Home
        </a>

    </div>

</body>
</html>