<?php

require_once __DIR__ . "/config/db.php";

$username = "admin";
$email = "admin@cafenest.com";
$password = "CafeNest@123";

// Check whether this admin already exists
$check = mysqli_prepare(
    $conn,
    "SELECT id FROM admins WHERE email = ?"
);

mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);

$result = mysqli_stmt_get_result($check);

if (mysqli_num_rows($result) > 0) {
    die("Admin account already exists. You can delete this setup file.");
}

// Hash the password before saving it
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO admins (username, email, password)
     VALUES (?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $username,
    $email,
    $hashedPassword
);

if (mysqli_stmt_execute($stmt)) {
    echo "<h2>CafeNest admin account created successfully!</h2>";
    echo "<p>Email: admin@cafenest.com</p>";
    echo "<p>Password: CafeNest@123</p>";
    echo "<p><strong>Important:</strong> Delete create_admin.php now.</p>";
} else {
    echo "Could not create admin account: " . htmlspecialchars(mysqli_error($conn));
}