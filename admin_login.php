<?php
require_once 'config.php';

$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id'], $_SESSION['admin_authenticated'], $_SESSION['admin_name']);
    session_regenerate_id(true);
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare(
            "SELECT id, full_name, password, role
             FROM users
             WHERE email = ? AND role IN ('admin', 'officer', 'ua_admin')
             LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_name'] = $admin['full_name'];
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Invalid admin email, password, or role.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Login - Boarding House Accreditation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=35">
</head>
<body class="login-page officer-login">

    <div class="login-container">
        <div class="login-role-switch">
            <a class="role-option landlord-option" href="login.php">
                <i class="fa-regular fa-user"></i>
                <span>Landlord</span>
            </a>
            <a class="role-option officer-option active" href="admin_login.php">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Officer</span>
            </a>
        </div>

        <div class="icon-shield">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <h2>Welcome</h2>
        <div class="subtitle">Sign in to your account to continue</div>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="admin_login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <div class="form-group">
                <label for="admin_email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope input-icon"></i>
                    <input type="email" id="admin_email" name="email" required>
                </div>
            </div>

            <div class="form-group">
                <label for="admin_password">Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="admin_password" name="password" required>
                    <i id="adminEyeIcon" class="fa-regular fa-eye eye-icon" onclick="toggleAdminPassword()"></i>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="officer_forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="submit-btn"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
        </form>

        <div class="register-text login-spacer">
            Don't have an account? <a href="#">Register as Landlord</a>
        </div>

        <div class="footer-note">
            &copy; 2026 Boarding House Management System.<br>All rights reserved.
        </div>
    </div>

    <a href="landing_page.php" class="back-home">&larr; Back to Landing Page</a>

    <script>
    function toggleAdminPassword() {
        const password = document.getElementById("admin_password");
        const eye = document.getElementById("adminEyeIcon");

        if (password.type === "password") {
            password.type = "text";
            eye.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            password.type = "password";
            eye.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
    </script>
</body>
</html>
