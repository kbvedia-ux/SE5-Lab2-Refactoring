<?php
require 'portal_helpers.php';

portalStart('Settings', 'Manage your account settings and preferences.');
?>

<div class="settings-layout">
    <aside class="settings-tabs">
        <button class="active"><i class="fa-regular fa-user"></i>Profile</button>
        <button><i class="fa-solid fa-lock"></i>Password</button>
        <button><i class="fa-solid fa-phone"></i>Contact Info</button>
    </aside>
    <section class="portal-card settings-card">
        <h3>Profile Information</h3>
        <div class="profile-row"><div class="avatar large">MS</div><div><h2>Maria Santos</h2><p>Landlord &middot; Registered since Jan 2024</p><a href="#">Change Photo</a></div></div>
        <form class="settings-form">
            <label>Full Name<input type="text" value="Maria Santos"></label>
            <label>Email Address<input type="email" value="maria.santos@email.com"></label>
            <label>Phone Number<input type="text" value="+63 912 345 6789"></label>
            <label>Address<input type="text" value="123 University Ave, Sibalom, Antique"></label>
            <button class="primary-action" type="button">Save Changes</button>
        </form>
    </section>
</div>

<?php portalEnd(); ?>
