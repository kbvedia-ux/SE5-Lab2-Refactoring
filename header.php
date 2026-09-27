<?php
require_once __DIR__ . '/config.php';

$activeFile = basename($_SERVER['PHP_SELF']);
$landlord = currentLandlord();
$displayName = $landlord['full_name'] ?? 'Landlord';
$nameParts = preg_split('/\s+/', trim($displayName));
$initials = strtoupper(substr($nameParts[0] ?? 'L', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
$searchablePortalPages = [
    'room_management.php',
    'accreditation_docs.php',
    'payment_records.php',
    'tenants.php',
    'banlist.php',
    'chat.php',
];
$topbarSearchTarget = in_array($activeFile, $searchablePortalPages, true) ? $activeFile : 'tenants.php';
$topbarSearch = trim($_GET['q'] ?? '');

$navItems = [
    'dashboard.php' => ['Dashboard', 'fa-table-columns'],
    'boarding_houses.php' => ['My Boarding Houses', 'fa-house'],
    'room_management.php' => ['Room Management', 'fa-door-open'],
    'accreditation_docs.php' => ['Accreditation Docs', 'fa-file-lines'],
    'payment_records.php' => ['Payment Records', 'fa-money-bill-wave'],
    'tenants.php' => ['Tenants', 'fa-people-roof'],
    'banlist.php' => ['Banlist', 'fa-user-slash'],
    'notifications.php' => ['Notifications', 'fa-bell'],
    'chat.php' => ['Chat with Admin', 'fa-comments'],
    'settings.php' => ['Settings', 'fa-gear'],
];
?>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand-mark"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <h2>BH Accreditation</h2>
            <span>Landlord Portal</span>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php foreach ($navItems as $file => $item): ?>
            <li class="<?php echo $activeFile === $file ? 'active' : ''; ?>">
                <a href="<?php echo htmlspecialchars($file); ?>">
                    <i class="fa-solid <?php echo htmlspecialchars($item[1]); ?>"></i>
                    <span><?php echo htmlspecialchars($item[0]); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        <li class="logout-item">
            <a href="login.php?logout=1">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<header class="topbar">
    <div class="portal-title"><i class="fa-solid fa-table-cells-large"></i><span>Landlord Portal</span></div>
    <div class="topbar-right">
        <form class="topbar-search-form" method="get" action="<?php echo htmlspecialchars($topbarSearchTarget); ?>">
            <label class="portal-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($topbarSearch); ?>" placeholder="Search..." data-search-submit></label>
        </form>
        <button class="icon-button"><i class="fa-regular fa-bell"></i><span></span></button>
        <div class="profile-mini">
            <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
            <div><strong><?php echo htmlspecialchars($displayName); ?></strong><small>Landlord</small></div>
        </div>
    </div>
</header>
