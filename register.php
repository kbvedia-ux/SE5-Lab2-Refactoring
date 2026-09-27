<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $fullName = trim($_POST['fullname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $houseName = trim($_POST['boarding_house'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $phone === '' || $houseName === '' || $address === '') {
        $error = 'Please complete all registration and boarding-house fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^\d{11}$/', $phone)) {
        $error = 'Phone number must contain exactly 11 digits.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif (strlen($password) > 20) {
        $error = 'Password must not exceed 20 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $error = 'An account with that email address already exists.';
        } else {
            $conn->begin_transaction();

            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'landlord';
                $accountStatus = 'Pending';

                $stmt = $conn->prepare(
                    'INSERT INTO users (full_name, email, phone, password, role, account_status)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('ssssss', $fullName, $email, $phone, $passwordHash, $role, $accountStatus);
                if (!$stmt->execute()) {
                    throw new RuntimeException($stmt->error);
                }
                $landlordId = $stmt->insert_id;
                $stmt->close();

                $status = 'Not Accredited';
                $applicationType = 'New';
                $description = 'Created during landlord registration. Accreditation documents are still required.';
                $stmt = $conn->prepare(
                    'INSERT INTO boarding_houses
                        (landlord_id, name, address, contact_number, description, status, application_type)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'issssss',
                    $landlordId,
                    $houseName,
                    $address,
                    $phone,
                    $description,
                    $status,
                    $applicationType
                );
                if (!$stmt->execute()) {
                    throw new RuntimeException($stmt->error);
                }
                $stmt->close();

                $conn->commit();
                setFlash('Registration submitted. After your account is approved, sign in and upload the required accreditation documents.', 'success');
                header('Location: login.php');
                exit;
            } catch (Throwable $exception) {
                $conn->rollback();
                $error = 'Registration could not be saved. Please try again.';
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
    <title>Landlord Registration</title>
    <link rel="stylesheet" href="css/style.css?v=40">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="register-page">
<div class="register-container">
    <div class="register-header">
        <div class="register-icon"><i class="fa-solid fa-user-plus"></i></div>
        <h2>Landlord Registration</h2>
        <p>Your account will be reviewed first. Boarding-house accreditation requires approved documents after login.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">

        <div class="form-group">
            <label>Full Name</label>
            <div class="input-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" name="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope input-icon"></i>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-phone input-icon"></i>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" oninput="this.value=this.value.replace(/\D/g, '').slice(0, 11)" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Boarding House Name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-house input-icon"></i>
                <input type="text" name="boarding_house" value="<?php echo htmlspecialchars($_POST['boarding_house'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Boarding House Address</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" minlength="8" maxlength="20" required>
                    <i id="eyePassword" class="fa-regular fa-eye eye-icon" onclick="toggleField('password', 'eyePassword')"></i>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="20" required>
                    <i id="eyeConfirm" class="fa-regular fa-eye eye-icon" onclick="toggleField('confirm_password', 'eyeConfirm')"></i>
                </div>
            </div>
        </div>

        <button type="submit" class="register-btn">Submit Registration</button>
    </form>

    <div class="signin-link">Already registered? <a href="login.php">Sign In</a></div>
</div>

<script>
function toggleField(fieldId, iconId) {
    const input = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.classList.toggle('fa-eye', !show);
    icon.classList.toggle('fa-eye-slash', show);
}
</script>
</body>
</html>
