<?php
include "config/db.php";

if (isset($_POST['register'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    // auto customer role
    $role = "customer";

    $query = "INSERT INTO users (username, password, role)
              VALUES ('$username', '$password', '$role')";

    mysqli_query($conn, $query);

    header("Location: login.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">

            <div class="card shadow p-4">

                <h3 class="text-center">Create Customer Account</h3>

                <form method="POST">

                    <input type="text" name="username" class="form-control mb-2" placeholder="Username" required>

                    <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>

                    <button name="register" class="btn btn-success w-100">
                        Register
                    </button>

                </form>

                <a href="login.php" class="btn btn-link mt-2">Back to Login</a>

            </div>

        </div>
    </div>
</div>

</body>
</html>