<?php
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $message = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $message = "If this officer email is registered, password reset instructions will be sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Forgot Password - Boarding House Accreditation</title>
    <link rel="stylesheet" href="css/style.css?v=35">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="login-page officer-login">

    <div class="login-container">
        <div class="icon-shield">
            <i class="fa-solid fa-key"></i>
        </div>

        <h2>Officer Password Reset</h2>
        <div class="subtitle">Enter your officer email to reset your password</div>

        <?php if ($message !== ""): ?>
            <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form action="officer_forgot_password.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">Send Reset Link</button>
        </form>

        <div class="register-text">
            Remember your password? <a href="login.php">Sign In</a>
        </div>

        <div class="footer-note">
            Secure officer account recovery protected by University of Antique
        </div>
    </div>

</body>
</html>
