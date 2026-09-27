<?php
require 'admin_helpers.php';

adminStart('Settings', 'Manage your account and preferences.');
?>

<div class="admin-settings-tabs">
    <button class="active">Profile</button>
    <button>Password</button>
    <button>About</button>
</div>

<article class="admin-card admin-settings-card">
    <h2>Profile Information</h2>
    <p>Update your personal details.</p>

    <div class="admin-profile-row">
        <div class="admin-avatar large">MS</div>
        <div>
            <strong>Maria Santos</strong>
            <span>Accreditation Officer</span>
        </div>
    </div>

    <form class="admin-settings-form" action="#" method="post">
        <label>Full Name<input type="text" value="Maria Santos"></label>
        <label>Email Address<input type="email" value="maria.santos@ua.edu.ph"></label>
        <label>Phone Number<input type="text" value="+63 912 345 6789"></label>
        <label>Department<input type="text" value="Student Affairs - Housing Accreditation"></label>
        <button type="submit" class="admin-primary-action">Save Changes</button>
    </form>
</article>

<?php adminEnd(); ?>
