<?php
require 'portal_helpers.php';

portalStart('Payment Records', 'Payment records are created from saved tenants and their selected payment methods.');

$allPayments = getDatabasePayments();
$houses = getAccreditedBoardingHouses();
$search = trim($_GET['q'] ?? '');
$houseFilter = $_GET['house'] ?? 'All';
$statusFilter = $_GET['status'] ?? 'All';
$allowedStatuses = ['All', 'Paid', 'Pending', 'Partial', 'Overdue'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

$payments = array_values(array_filter($allPayments, static function (array $payment) use ($search, $houseFilter, $statusFilter): bool {
    if ($houseFilter !== 'All' && $payment['house'] !== $houseFilter) {
        return false;
    }
    if ($statusFilter !== 'All' && $payment['status'] !== $statusFilter) {
        return false;
    }
    if ($search !== '') {
        $haystack = mb_strtolower(implode(' ', [$payment['tenant'], $payment['room'], $payment['house'], $payment['month'], $payment['method'], $payment['status']]));
        if (!str_contains($haystack, mb_strtolower($search))) {
            return false;
        }
    }
    return true;
}));

$totalCollected = array_sum(array_column($allPayments, 'paid_value'));
$partialAmount = array_sum(array_map(static fn(array $payment): float => $payment['status'] === 'Partial' ? $payment['balance_value'] : 0, $allPayments));
?>

<div class="stats-overview-grid two-stats">
    <div class="stat-box"><i class="fa-solid fa-circle-check icon-green"></i><strong>&#8369;<?php echo number_format($totalCollected, 2); ?></strong><span>Total Collected</span><em><?php echo count(array_filter($allPayments, static fn(array $payment): bool => $payment['status'] === 'Paid')); ?> paid</em></div>
    <div class="stat-box"><i class="fa-solid fa-chart-pie icon-blue"></i><strong>&#8369;<?php echo number_format($partialAmount, 2); ?></strong><span>Partial Payments</span><em><?php echo count(array_filter($allPayments, static fn(array $payment): bool => $payment['status'] === 'Partial')); ?> partial</em></div>
</div>

<form class="records-filter-panel" method="get" action="payment_records.php">
    <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tenant, room, or boarding house..." data-search-submit></label>
    <select class="filter-select" name="house" data-auto-submit aria-label="Filter by boarding house">
        <option value="All">All Accredited Houses</option>
        <?php foreach ($houses as $house): ?><option value="<?php echo htmlspecialchars($house['name']); ?>" <?php echo $houseFilter === $house['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?>
    </select>
    <select class="filter-select" name="status" data-auto-submit aria-label="Filter payment status">
        <?php foreach ($allowedStatuses as $option): ?><option value="<?php echo $option; ?>" <?php echo $statusFilter === $option ? 'selected' : ''; ?>><?php echo $option; ?> Status</option><?php endforeach; ?>
    </select>
</form>

<div class="portal-card table-card">
    <?php if (!$payments): ?>
        <div class="empty-state large"><i class="fa-solid fa-receipt"></i><strong>No payment records yet</strong><span>An initial record is created automatically when you add a tenant.</span></div>
    <?php else: ?>
        <table class="portal-table payment-table">
            <thead><tr><th>Tenant</th><th>Room</th><th>Boarding House</th><th>Month</th><th>Rent Due</th><th>Paid</th><th>Date Paid</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <?php
                    $parts = preg_split('/\s+/', trim($payment['tenant']));
                    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                    $statusColor = match ($payment['status']) {
                        'Paid' => 'green',
                        'Partial' => 'blue',
                        'Overdue' => 'red',
                        default => 'yellow',
                    };
                    $modalId = 'payment-' . $payment['id'];
                    ?>
                    <tr data-search-row data-search-text="<?php echo htmlspecialchars(implode(' ', [$payment['tenant'], $payment['room'], $payment['house'], $payment['month'], $payment['due'], $payment['paid'], $payment['date'], $payment['method'], $payment['status'], $payment['notes']])); ?>">
                        <td class="tenant-cell"><span class="tenant-avatar"><?php echo htmlspecialchars($initials); ?></span><strong><?php echo htmlspecialchars($payment['tenant']); ?></strong></td>
                        <td><?php echo htmlspecialchars($payment['room']); ?></td>
                        <td><?php echo htmlspecialchars($payment['house']); ?></td>
                        <td><?php echo htmlspecialchars($payment['month']); ?></td>
                        <td><strong>&#8369;<?php echo htmlspecialchars($payment['due']); ?></strong></td>
                        <td class="paid-amount">&#8369;<?php echo htmlspecialchars($payment['paid']); ?></td>
                        <td><?php echo htmlspecialchars($payment['date']); ?></td>
                        <td><?php echo htmlspecialchars($payment['method']); ?></td>
                        <td><?php echo badge($payment['status'], $statusColor); ?></td>
                        <td><button class="tiny-button" data-modal-target="#<?php echo $modalId; ?>" title="View payment"><i class="fa-regular fa-eye"></i></button></td>
                    </tr>

                    <div class="modal-overlay" id="<?php echo $modalId; ?>" hidden>
                        <div class="portal-modal detail-modal">
                            <div class="modal-title-row"><h2>Payment Record</h2><button data-modal-close aria-label="Close">&times;</button></div>
                            <dl class="modal-detail-grid">
                                <div><dt>Tenant</dt><dd><?php echo htmlspecialchars($payment['tenant']); ?></dd></div>
                                <div><dt>Status</dt><dd><?php echo htmlspecialchars($payment['status']); ?></dd></div>
                                <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($payment['house']); ?></dd></div>
                                <div><dt>Room</dt><dd><?php echo htmlspecialchars($payment['room']); ?></dd></div>
                                <div><dt>Billing Month</dt><dd><?php echo htmlspecialchars($payment['month']); ?></dd></div>
                                <div><dt>Payment Method</dt><dd><?php echo htmlspecialchars($payment['method']); ?></dd></div>
                                <div><dt>Rent Due</dt><dd>&#8369;<?php echo htmlspecialchars($payment['due']); ?></dd></div>
                                <div><dt>Amount Paid</dt><dd>&#8369;<?php echo htmlspecialchars($payment['paid']); ?></dd></div>
                                <div><dt>Date Paid</dt><dd><?php echo htmlspecialchars($payment['date']); ?></dd></div>
                                <div class="wide"><dt>Notes</dt><dd><?php echo htmlspecialchars($payment['notes'] ?: '—'); ?></dd></div>
                            </dl>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<p class="center-note">Showing <?php echo count($payments); ?> payment records</p>

<?php portalEnd(); ?>
