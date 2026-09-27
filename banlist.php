<?php
require 'portal_helpers.php';
requireLandlord();

$landlord = currentLandlord();
$landlordId = (int) $landlord['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $tenantName = trim($_POST['tenant_name'] ?? '');
    $age = max(0, (int) ($_POST['age'] ?? 0));
    $reason = trim($_POST['reason'] ?? '');
    $severity = $_POST['severity'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $houseId = (int) ($_POST['boarding_house_id'] ?? 0);

    $stmt = $conn->prepare(
        "SELECT name FROM boarding_houses
         WHERE id = ? AND landlord_id = ? AND status = 'Accredited'"
    );
    $stmt->bind_param('ii', $houseId, $landlordId);
    $stmt->execute();
    $house = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        $tenantName === '' || $age < 1 || $reason === '' || $description === '' || !$house
        || !in_array($severity, ['High', 'Medium', 'Low'], true)
    ) {
        setFlash('Complete all banlist report fields and choose an accredited boarding house.', 'error');
        header('Location: banlist.php');
        exit;
    }

    $reportedBy = $landlord['full_name'];
    $dateReported = date('Y-m-d');
    $status = 'Published';
    $stmt = $conn->prepare(
        'INSERT INTO banlist
            (landlord_id, tenant_name, age, reason, severity, description, boarding_house, reported_by, date_reported, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isisssssss', $landlordId, $tenantName, $age, $reason, $severity, $description, $house['name'], $reportedBy, $dateReported, $status);
    $stmt->execute();
    $stmt->close();

    setFlash('Tenant report saved to the shared banlist.', 'success');
    header('Location: banlist.php');
    exit;
}

portalStart(
    'Banlist',
    'View the shared database-backed banlist and report problematic tenants.',
    '<button class="danger-action" data-modal-target="#report-tenant-modal"><i class="fa-solid fa-flag"></i> Report a Tenant</button>'
);

$allItems = getDatabaseBanlist();
$houses = getAccreditedBoardingHouses();
$search = trim($_GET['q'] ?? '');
$severityFilter = $_GET['severity'] ?? 'All';
if (!in_array($severityFilter, ['All', 'High', 'Medium', 'Low'], true)) {
    $severityFilter = 'All';
}
$items = array_values(array_filter($allItems, static function (array $item) use ($search, $severityFilter): bool {
    if ($severityFilter !== 'All' && $item['severity'] !== $severityFilter) {
        return false;
    }
    if ($search !== '') {
        $haystack = mb_strtolower(implode(' ', [$item['name'], $item['reason'], $item['details'], $item['house'], $item['reported_by']]));
        if (!str_contains($haystack, mb_strtolower($search))) {
            return false;
        }
    }
    return true;
}));
$highCount = count(array_filter($allItems, static fn(array $item): bool => $item['severity'] === 'High'));
$mediumCount = count(array_filter($allItems, static fn(array $item): bool => $item['severity'] === 'Medium'));
$lowCount = count(array_filter($allItems, static fn(array $item): bool => $item['severity'] === 'Low'));
?>

<div class="stats-overview-grid four-stats">
    <div class="stat-box"><i class="fa-solid fa-users-slash icon-red"></i><strong><?php echo count($allItems); ?></strong><span>Total Reports</span></div>
    <div class="stat-box"><i class="fa-solid fa-triangle-exclamation icon-red"></i><strong><?php echo $highCount; ?></strong><span>High Severity</span></div>
    <div class="stat-box"><i class="fa-solid fa-circle-exclamation icon-yellow"></i><strong><?php echo $mediumCount; ?></strong><span>Medium Severity</span></div>
    <div class="stat-box"><i class="fa-solid fa-circle-info icon-blue"></i><strong><?php echo $lowCount; ?></strong><span>Low Severity</span></div>
</div>

<div class="notice"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>Shared Landlord Notice</strong><p>Reports saved here are visible to accredited landlords using this system.</p></div></div>
<form class="records-filter-panel compact-filters" method="get" action="banlist.php">
    <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tenant, reason, or boarding house..." data-search-submit></label>
    <select class="filter-select" name="severity" data-auto-submit aria-label="Filter severity">
        <?php foreach (['All', 'High', 'Medium', 'Low'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $severityFilter === $option ? 'selected' : ''; ?>><?php echo $option === 'All' ? 'All Severity' : $option . ' Severity'; ?></option><?php endforeach; ?>
    </select>
</form>

<?php if (!$items): ?>
    <div class="portal-card empty-state large"><i class="fa-solid fa-user-shield"></i><strong>No banlist reports</strong><span>Submitted reports will be saved in the database and shown here.</span></div>
<?php else: ?>
    <div class="ban-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $parts = preg_split('/\s+/', trim($item['name']));
            $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
            $modalId = 'ban-entry-' . $item['id'];
            $severityTone = $item['severity'] === 'High' ? 'red' : ($item['severity'] === 'Medium' ? 'yellow' : 'gray');
            ?>
            <article class="portal-card ban-card" data-search-row data-search-text="<?php echo htmlspecialchars(implode(' ', [$item['name'], (string) ($item['age'] ?? ''), $item['severity'], $item['reason'], $item['details'], $item['house'], $item['date_reported'], $item['reported_by']])); ?>">
                <div class="ban-head">
                    <div class="ban-title"><span class="ban-avatar"><?php echo htmlspecialchars($initials); ?></span><h3><?php echo htmlspecialchars($item['name']); ?></h3></div>
                    <?php echo badge($item['severity'], $severityTone); ?>
                </div>
                <small>Age <?php echo htmlspecialchars((string) ($item['age'] ?? '—')); ?></small>
                <?php echo badge($item['reason'], 'orange'); ?>
                <p><?php echo htmlspecialchars($item['details']); ?></p>
                <ul><li><i class="fa-solid fa-house"></i><?php echo htmlspecialchars($item['house']); ?></li><li><i class="fa-regular fa-calendar"></i><?php echo htmlspecialchars($item['date_reported']); ?></li></ul>
                <button class="light-button" data-modal-target="#<?php echo $modalId; ?>">View Details</button>
            </article>

            <div class="modal-overlay" id="<?php echo $modalId; ?>" hidden>
                <div class="portal-modal ban-detail-modal">
                    <div class="modal-title-row"><h2>Banlist Entry Details</h2><button data-modal-close aria-label="Close">&times;</button></div>
                    <div class="ban-entry-person">
                        <span class="ban-avatar large"><?php echo htmlspecialchars($initials); ?></span>
                        <div><h3><?php echo htmlspecialchars($item['name']); ?></h3><p>Age <?php echo htmlspecialchars((string) ($item['age'] ?? '—')); ?></p><?php echo badge($item['severity'] . ' Severity', $severityTone); ?></div>
                    </div>
                    <dl class="ban-entry-details">
                        <div><dt>Reason</dt><dd><?php echo htmlspecialchars($item['reason']); ?></dd></div>
                        <div><dt>Description</dt><dd><?php echo htmlspecialchars($item['details']); ?></dd></div>
                        <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($item['house']); ?></dd></div>
                        <div><dt>Reported By</dt><dd><?php echo htmlspecialchars($item['reported_by']); ?></dd></div>
                        <div><dt>Date Reported</dt><dd><?php echo htmlspecialchars($item['date_reported']); ?></dd></div>
                    </dl>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal-overlay" id="report-tenant-modal" hidden>
    <div class="portal-modal report-modal">
        <div class="modal-title-row"><h2>Report a Tenant</h2><button data-modal-close aria-label="Close">&times;</button></div>
        <form class="modal-form report-form" action="banlist.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label class="wide">Tenant Full Name<input type="text" name="tenant_name" required></label>
            <label>Age<input type="number" name="age" min="1" required></label>
            <label>Boarding House<select name="boarding_house_id" required><option value="">Select accredited house</option><?php foreach ($houses as $house): ?><option value="<?php echo $house['id']; ?>"><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?></select></label>
            <label>Reason<select name="reason" required><option value="">Select reason</option><option>Property Damage</option><option>Non-Payment</option><option>Company Refusal</option><option>Unauthorized Guests</option><option>Theft</option><option>Other</option></select></label>
            <label>Severity<select name="severity" required><option value="">Select severity</option><option>High</option><option>Medium</option><option>Low</option></select></label>
            <label class="wide">Description<textarea name="description" maxlength="500" required></textarea><small class="char-count">Maximum 500 characters</small></label>
            <button type="submit" class="danger-action wide">Save Report</button>
        </form>
    </div>
</div>

<?php portalEnd(); ?>
