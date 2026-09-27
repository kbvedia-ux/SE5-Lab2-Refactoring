<?php
$adminActiveFile = basename($_SERVER['PHP_SELF']);
$adminUser = currentAdmin();
$adminDisplayName = $adminUser['full_name'] ?? 'Accreditation Officer';
$adminNameParts = preg_split('/\s+/', trim($adminDisplayName));
$adminInitials = strtoupper(substr($adminNameParts[0] ?? 'A', 0, 1) . substr($adminNameParts[1] ?? '', 0, 1));
$searchableAdminPages = ['admin_pending_reviews.php', 'admin_review_history.php', 'admin_chat.php'];
$adminTopbarSearchTarget = in_array($adminActiveFile, $searchableAdminPages, true)
    ? $adminActiveFile
    : 'admin_review_history.php';
$adminTopbarSearch = trim($_GET['q'] ?? '');

$adminNavItems = [
    'admin_dashboard.php' => ['Dashboard', 'fa-table-cells-large'],
    'admin_pending_reviews.php' => ['Pending Reviews', 'fa-clipboard-list'],
    'admin_review_history.php' => ['Review History', 'fa-clock-rotate-left'],
    'admin_chat.php' => ['Chat', 'fa-comment'],
    'admin_settings.php' => ['Settings', 'fa-gear'],
];
?>

<aside class="admin-sidebar">
    <div class="admin-brand">
        <div class="admin-brand-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <strong>BH Accreditation</strong>
            <span>UA Admin Panel</span>
        </div>
    </div>

    <nav class="admin-menu" aria-label="Admin navigation">
        <?php foreach ($adminNavItems as $file => $item): ?>
            <a class="<?php echo $adminActiveFile === $file ? 'active' : ''; ?>" href="<?php echo $file; ?>">
                <i class="fa-solid <?php echo $item[1]; ?>"></i>
                <span><?php echo $item[0]; ?></span>
            </a>
        <?php endforeach; ?>
        <a class="admin-logout-link" href="admin_login.php?logout=1">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<header class="admin-topbar">
    <div class="admin-portal-title">
        <i class="fa-solid fa-shield-heart"></i>
        <span>Accreditation Officer</span>
    </div>
    <div class="admin-topbar-right">
        <form class="topbar-search-form" method="get" action="<?php echo htmlspecialchars($adminTopbarSearchTarget); ?>">
            <label class="admin-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="q" value="<?php echo htmlspecialchars($adminTopbarSearch); ?>" placeholder="Search applications..." data-search-submit>
            </label>
        </form>
        <button class="admin-icon-button" aria-label="Notifications">
            <i class="fa-regular fa-bell"></i>
            <span></span>
        </button>
        <div class="admin-profile-mini">
            <div class="admin-avatar"><?php echo htmlspecialchars($adminInitials); ?></div>
            <div>
                <strong><?php echo htmlspecialchars($adminDisplayName); ?></strong>
                <small><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $adminUser['role'] ?? 'Officer'))); ?></small>
            </div>
        </div>
    </div>
</header>
