<?php
require 'portal_helpers.php';

$landlord = currentLandlord();

portalStart(
    'Dashboard Overview',
    'Welcome back, ' . ($landlord['full_name'] ?? 'Landlord') . '. Here is your live boarding-house summary.',
    '<div class="date-card"><i class="fa-regular fa-calendar"></i>' . date('l, F d, Y') . '</div>'
);

$houses = getBoardingHouses();
$accreditedHouses = getAccreditedBoardingHouses();
$stats = getDashboardStats();
?>

<div class="stats-overview-grid">
    <a class="stat-box stat-link" href="boarding_houses.php">
        <i class="fa-solid fa-house icon-green"></i>
        <strong><?php echo $stats['houses']; ?></strong>
        <span>My Boarding Houses</span>
        <em>Accredited properties</em>
    </a>
    <a class="stat-box stat-link" href="room_management.php">
        <i class="fa-solid fa-door-open icon-amber"></i>
        <strong><?php echo $stats['rooms']; ?></strong>
        <span>Total Rooms</span>
        <em><?php echo $stats['vacant']; ?> vacant</em>
    </a>
    <a class="stat-box stat-link" href="room_management.php">
        <i class="fa-solid fa-chart-pie icon-green"></i>
        <strong><?php echo $stats['occupancy_rate']; ?>%</strong>
        <span>Occupancy Rate</span>
        <em><?php echo $stats['occupied']; ?> of <?php echo $stats['rooms']; ?> rooms occupied</em>
    </a>
    <a class="stat-box stat-link" href="accreditation_docs.php">
        <i class="fa-solid fa-file-lines icon-red"></i>
        <strong><?php echo $stats['pending_documents']; ?></strong>
        <span>Pending Documents</span>
        <em>Awaiting UA review</em>
    </a>
    <a class="stat-box stat-link" href="tenants.php">
        <i class="fa-solid fa-peso-sign icon-blue"></i>
        <strong>&#8369;<?php echo number_format($stats['monthly_income'], 2); ?></strong>
        <span>Monthly Income</span>
        <em>Rent from occupied rooms</em>
    </a>
    <a class="stat-box stat-link" href="tenants.php">
        <i class="fa-solid fa-users icon-rose"></i>
        <strong><?php echo $stats['active_tenants']; ?></strong>
        <span>Active Tenants</span>
        <em>Based on occupied rooms</em>
    </a>
</div>

<div class="portal-grid two">
    <article class="portal-card">
        <h3>My Boarding Houses</h3>
        <?php if (!$houses): ?>
            <div class="empty-state">
                <i class="fa-solid fa-house-circle-xmark"></i>
                <strong>No boarding houses yet</strong>
                <span>Your approved and pending applications will appear here.</span>
            </div>
        <?php else: ?>
            <div class="mini-list">
                <?php foreach ($houses as $house): ?>
                    <a href="boarding_houses.php">
                        <i class="fa-solid fa-house icon-green"></i>
                        <span>
                            <strong><?php echo htmlspecialchars($house['name']); ?></strong>
                            <small>Rooms: <?php echo $house['rooms']; ?> &middot; Occupied: <?php echo $house['tenants']; ?> &middot; Vacant: <?php echo $house['vacant']; ?></small>
                        </span>
                        <?php
                        $statusTone = $house['status'] === 'Accredited' ? 'green' : ($house['status'] === 'Rejected' ? 'red' : 'yellow');
                        echo badge($house['status'], $statusTone);
                        ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a class="card-link" href="boarding_houses.php">Manage Boarding Houses &rarr;</a>
    </article>

    <article class="portal-card">
        <h3>Occupancy Overview</h3>
        <?php if (!$accreditedHouses): ?>
            <div class="empty-state">
                <i class="fa-solid fa-chart-column"></i>
                <strong>No accredited property data</strong>
                <span>Occupancy starts calculating after a boarding house is approved and rooms are added.</span>
            </div>
        <?php else: ?>
            <div class="progress-group">
                <?php foreach ($accreditedHouses as $house): ?>
                    <?php $rate = $house['rooms'] > 0 ? (int) round(($house['tenants'] / $house['rooms']) * 100) : 0; ?>
                    <div>
                        <p><span><?php echo htmlspecialchars($house['name']); ?></span><b><?php echo $house['tenants']; ?>/<?php echo $house['rooms']; ?> rooms - <?php echo $rate; ?>%</b></p>
                        <div class="track <?php echo $rate < 60 ? 'amber' : ''; ?>"><span style="width:<?php echo $rate; ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</div>

<?php portalEnd(); ?>
