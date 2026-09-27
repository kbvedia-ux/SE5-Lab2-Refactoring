<?php
require_once 'config.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['landlord_id']);
    session_regenerate_id(true);
    setFlash('You have been signed out.', 'success');
    header('Location: login.php');
    exit;
}

$error = '';
$flash = pullFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT id, full_name, password, account_status
         FROM users
         WHERE email = ? AND role = 'landlord'
         LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        $error = 'Invalid email address or password.';
    } elseif (strcasecmp($user['account_status'], 'Pending') === 0) {
        $error = 'Please wait for UA admin approval. Your registration is still under review.';
    } elseif (strcasecmp($user['account_status'], 'Rejected') === 0) {
        $error = 'Your registration was rejected. Please contact the UA accreditation office for details.';
    } elseif (strcasecmp($user['account_status'], 'Approved') !== 0) {
        $error = 'This account is not available for login.';
    } else {
        session_regenerate_id(true);
        $_SESSION['landlord_id'] = (int) $user['id'];
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Login - Boarding House Accreditation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=40">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-role-switch">
            <a class="role-option landlord-option active" href="login.php"><i class="fa-regular fa-user"></i><span>Landlord</span></a>
            <a class="role-option officer-option" href="admin_login.php"><i class="fa-solid fa-shield-halved"></i><span>Officer</span></a>
        </div>

        <div class="icon-shield"><i class="fa-solid fa-shield-halved"></i></div>
        <h2>Welcome</h2>
        <div class="subtitle">Sign in after your registration is approved</div>

        <?php if ($flash): ?>
            <div class="portal-flash flash-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" required>
                    <i id="eyeIcon" class="fa-regular fa-eye eye-icon" onclick="togglePassword()"></i>
                </div>
            </div>
            <div class="form-options">
                <label class="remember-me"><input type="checkbox" name="remember"> Remember me</label>
                <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
            </div>
            <button type="submit" class="submit-btn">Sign In</button>
        </form>

        <div class="register-text">Don't have an account? <a href="register.php">Register as Landlord</a></div>
        <div class="footer-note">&copy; 2026 Boarding House Management System.<br>All rights reserved.</div>
    </div>

    <a href="landing_page.php" class="back-home">&larr; Back to Landing Page</a>
    <script>
    function togglePassword() {
        const password = document.getElementById('password');
        const eye = document.getElementById('eyeIcon');
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        eye.classList.toggle('fa-eye', !show);
        eye.classList.toggle('fa-eye-slash', show);
    }
    </script>
</body>
</html>
