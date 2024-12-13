<?php
include './backend/database.php';

session_start();
$error_message = '';

if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['nama'] = $user['nama'];
            
            header("Location: ./pages/dashboard.php");
            exit();
        } else {
            $error_message = "Email atau password salah. Silakan coba lagi.";
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <title>Login</title>
</head>
<style>
    body {
        background-color: #28a745;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        font-family: 'Arial', sans-serif;
    }

    .login-container {
        background-color: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 400px;
        margin: 20px;
    }

    .login-container h2 {
        text-align: center;
        margin-bottom: 30px;
        font-weight: bold;
        color: #333;
    }

    .form-control {
        margin-bottom: 20px;
        border-radius: 10px;
    }

    .btn-primary {
        background-color: #28a745;
        border: none;
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        font-size: 16px;
    }

    .btn-primary:hover {
        background-color: #218838;
    }

    .form-check-label {
        margin-bottom: 20px;
    }

    .text-center a {
        color: #28a745;
        text-decoration: none;
    }

    .text-center a:hover {
        text-decoration: underline;
    }
</style>
</head>

<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if($error_message != ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <form action="index.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Masukkan email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="showPassword">
                <label class="form-check-label" for="showPassword">Show Password</label>
            </div>
            <button type="submit" class="btn btn-primary">SIGN IN</button>
        </form>
        <div class="text-center mt-3">
            <a>Don't have an account?</a>
            <a href="signup.php">Sign up</a>
        </div>
    </div>

    <script>
        document.getElementById('showPassword').addEventListener('change', function() {
            var passwordInput = document.getElementById('password');
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>

</html>