<?php
require 'portal_helpers.php';
requireLandlord();

$landlordId = (int) currentLandlord()['id'];

function findTenantRoom(mysqli $conn, int $roomId, int $landlordId): ?array
{
    $stmt = $conn->prepare(
        "SELECT r.id, r.status, r.room_number, r.boarding_house_id, bh.name AS house
         FROM rooms r
         INNER JOIN boarding_houses bh ON bh.id = r.boarding_house_id
         WHERE r.id = ? AND bh.landlord_id = ? AND bh.status = 'Accredited'"
    );
    $stmt->bind_param('ii', $roomId, $landlordId);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $room;
}

function tenantFormValue(string $value): string
{
    return in_array($value, ['—', 'â€”'], true) ? '' : $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';
    $tenantId = (int) ($_POST['tenant_id'] ?? 0);

    if (in_array($action, ['move_out', 'delete_tenant'], true)) {
        $stmt = $conn->prepare('SELECT id, room_id, full_name FROM tenants WHERE id = ? AND landlord_id = ?');
        $stmt->bind_param('ii', $tenantId, $landlordId);
        $stmt->execute();
        $tenant = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tenant) {
            setFlash('Tenant was not found.', 'error');
            header('Location: tenants.php');
            exit;
        }

        if ($action === 'move_out') {
            $stmt = $conn->prepare("UPDATE tenants SET status = 'Moving Out', moved_out_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $stmt->close();
            setFlash('Tenant marked as Moving Out.', 'success');
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE rooms SET status = 'Vacant', occupant = NULL WHERE id = ?");
                $stmt->bind_param('i', $tenant['room_id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('UPDATE payments SET tenant_id = NULL WHERE tenant_id = ?');
                $stmt->bind_param('i', $tenantId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM tenants WHERE id = ? AND landlord_id = ?');
                $stmt->bind_param('ii', $tenantId, $landlordId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                setFlash('Tenant removed and the room is now vacant.', 'success');
            } catch (Throwable $exception) {
                $conn->rollback();
                setFlash('Tenant could not be removed.', 'error');
            }
        }

        header('Location: tenants.php');
        exit;
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $moveInDate = trim($_POST['move_in_date'] ?? '');
    $monthlyRent = max(0, (float) ($_POST['monthly_rent'] ?? 0));
    $paymentMethod = $_POST['payment_method'] ?? '';
    $initialPayment = max(0, (float) ($_POST['initial_payment'] ?? 0));
    $allowedMethods = ['GCash', 'Bank Transfer', 'Cash'];
    $room = findTenantRoom($conn, $roomId, $landlordId);

    if (
        $fullName === '' || !$room || $moveInDate === '' || $monthlyRent <= 0
        || !in_array($paymentMethod, $allowedMethods, true)
    ) {
        setFlash('Complete all required tenant, room, rent, and payment fields.', 'error');
        header('Location: tenants.php');
        exit;
    }

    if ($action === 'add_tenant') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM tenants WHERE room_id = ? AND status IN ('Active', 'Moving Out')");
        $stmt->bind_param('i', $roomId);
        $stmt->execute();
        $roomTaken = (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
        $stmt->close();

        if ($roomTaken || $room['status'] !== 'Vacant') {
            setFlash('The selected room is no longer vacant.', 'error');
            header('Location: tenants.php');
            exit;
        }

        $status = 'Active';
        $balance = max(0, $monthlyRent - $initialPayment);
        $paymentStatus = $initialPayment >= $monthlyRent ? 'Paid' : ($initialPayment > 0 ? 'Partial' : 'Pending');
        $billingMonth = date('Y-m-01');
        $paymentDate = $initialPayment > 0 ? date('Y-m-d') : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO tenants
                    (landlord_id, room_id, full_name, course, email, phone, move_in_date, monthly_rent, payment_method, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iisssssdss', $landlordId, $roomId, $fullName, $course, $email, $phone, $moveInDate, $monthlyRent, $paymentMethod, $status);
            $stmt->execute();
            $newTenantId = $stmt->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("UPDATE rooms SET status = 'Occupied', occupant = ?, monthly_rent = ? WHERE id = ?");
            $stmt->bind_param('sdi', $fullName, $monthlyRent, $roomId);
            $stmt->execute();
            $stmt->close();

            $notes = 'Initial tenant payment record';
            $stmt = $conn->prepare(
                'INSERT INTO payments
                    (tenant_id, room_id, tenant_name, rent_due, amount_paid, balance, billing_month,
                     amount, payment_date, method, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iisdddsdssss',
                $newTenantId,
                $roomId,
                $fullName,
                $monthlyRent,
                $initialPayment,
                $balance,
                $billingMonth,
                $initialPayment,
                $paymentDate,
                $paymentMethod,
                $paymentStatus,
                $notes
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            setFlash('Tenant added as Active and the payment record was created.', 'success');
        } catch (Throwable $exception) {
            $conn->rollback();
            setFlash('Tenant could not be saved: ' . $exception->getMessage(), 'error');
        }
    } elseif ($action === 'edit_tenant') {
        $stmt = $conn->prepare('SELECT room_id FROM tenants WHERE id = ? AND landlord_id = ?');
        $stmt->bind_param('ii', $tenantId, $landlordId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            setFlash('Tenant was not found.', 'error');
            header('Location: tenants.php');
            exit;
        }

        $oldRoomId = (int) $existing['room_id'];
        if ($roomId !== $oldRoomId && $room['status'] !== 'Vacant') {
            setFlash('The new room must be vacant.', 'error');
            header('Location: tenants.php');
            exit;
        }

        $conn->begin_transaction();
        try {
            if ($roomId !== $oldRoomId) {
                $stmt = $conn->prepare("UPDATE rooms SET status = 'Vacant', occupant = NULL WHERE id = ?");
                $stmt->bind_param('i', $oldRoomId);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare(
                "UPDATE tenants
                 SET room_id = ?, full_name = ?, course = ?, email = ?, phone = ?, move_in_date = ?,
                     monthly_rent = ?, payment_method = ?, updated_at = NOW()
                 WHERE id = ? AND landlord_id = ?"
            );
            $stmt->bind_param('isssssdsii', $roomId, $fullName, $course, $email, $phone, $moveInDate, $monthlyRent, $paymentMethod, $tenantId, $landlordId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE rooms SET status = 'Occupied', occupant = ?, monthly_rent = ? WHERE id = ?");
            $stmt->bind_param('sdi', $fullName, $monthlyRent, $roomId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                'UPDATE payments SET room_id = ?, tenant_name = ?, method = ? WHERE tenant_id = ?'
            );
            $stmt->bind_param('issi', $roomId, $fullName, $paymentMethod, $tenantId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            setFlash('Tenant details updated.', 'success');
        } catch (Throwable $exception) {
            $conn->rollback();
            setFlash('Tenant could not be updated.', 'error');
        }
    }

    header('Location: tenants.php');
    exit;
}

portalStart(
    'Tenants',
    'Add tenants to vacant rooms in your accredited boarding houses and manage their records.',
    '<a class="primary-action" href="#add-tenant-modal" data-modal-target="#add-tenant-modal" role="button"><i class="fa-solid fa-user-plus"></i> Add Tenant</a>'
);

$allTenants = getDatabaseTenants();
$houses = getAccreditedBoardingHouses();
$rooms = getRooms();
$search = trim($_GET['q'] ?? '');
$houseFilter = $_GET['house'] ?? 'All';
$houseNames = array_column($houses, 'name');
if ($houseFilter !== 'All' && !in_array($houseFilter, $houseNames, true)) {
    $houseFilter = 'All';
}

$tenants = array_values(array_filter($allTenants, static function (array $tenant) use ($search, $houseFilter): bool {
    if ($houseFilter !== 'All' && $tenant['house'] !== $houseFilter) {
        return false;
    }
    if ($search !== '') {
        $haystack = mb_strtolower(implode(' ', [
            $tenant['name'],
            $tenant['email'],
            $tenant['phone'],
            $tenant['room'],
            $tenant['house'],
            $tenant['course'],
            $tenant['status'],
        ]));
        if (!str_contains($haystack, mb_strtolower($search))) {
            return false;
        }
    }
    return true;
}));

$totalTenants = count($allTenants);
$activeTenants = count(array_filter($allTenants, static fn(array $tenant): bool => $tenant['status'] === 'Active'));
$movingOutTenants = count(array_filter($allTenants, static fn(array $tenant): bool => $tenant['status'] === 'Moving Out'));
?>

<div class="stats-overview-grid three-stats">
    <div class="stat-box"><i class="fa-solid fa-people-roof icon-green"></i><strong><?php echo $totalTenants; ?></strong><span>Total Tenants</span></div>
    <div class="stat-box"><i class="fa-solid fa-circle-check icon-green"></i><strong><?php echo $activeTenants; ?></strong><span>Active</span></div>
    <div class="stat-box"><i class="fa-solid fa-door-open icon-yellow"></i><strong><?php echo $movingOutTenants; ?></strong><span>Moving Out</span></div>
</div>

<form class="records-filter-panel tenant-filter-panel" method="get" action="tenants.php">
    <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, room, or email..." data-search-submit></label>
    <select class="filter-select" name="house" data-auto-submit aria-label="Filter by boarding house">
        <option value="All">All Accredited Houses</option>
        <?php foreach ($houses as $house): ?><option value="<?php echo htmlspecialchars($house['name']); ?>" <?php echo $houseFilter === $house['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?>
    </select>
</form>

<div class="portal-card table-card">
    <?php if (!$allTenants): ?>
        <div class="empty-state large"><i class="fa-solid fa-users"></i><strong>No tenants saved</strong><span>Add a tenant to a vacant room in an accredited boarding house.</span></div>
    <?php elseif (!$tenants): ?>
        <div class="empty-state large"><i class="fa-solid fa-magnifying-glass"></i><strong>No tenants match your search</strong><span>Try another name, room, email, or boarding house.</span></div>
    <?php else: ?>
        <table class="portal-table tenants-table">
            <thead><tr><th>Tenant</th><th>Contact</th><th>Room</th><th>Boarding House</th><th>Move-in Date</th><th>Monthly Rent</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <?php
                    $parts = preg_split('/\s+/', trim($tenant['name']));
                    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                    $modalId = 'tenant-' . $tenant['id'];
                    ?>
                    <tr data-search-row data-search-text="<?php echo htmlspecialchars(implode(' ', [$tenant['name'], $tenant['course'], $tenant['email'], $tenant['phone'], $tenant['room'], $tenant['house'], $tenant['move_in'], $tenant['rent'], $tenant['payment_method'], $tenant['status']])); ?>">
                        <td class="tenant-cell"><span class="tenant-avatar"><?php echo htmlspecialchars($initials); ?></span><span><strong><?php echo htmlspecialchars($tenant['name']); ?></strong><small><?php echo htmlspecialchars($tenant['course']); ?></small></span></td>
                        <td><span class="stacked-text"><?php echo htmlspecialchars($tenant['email']); ?><small><?php echo htmlspecialchars($tenant['phone']); ?></small></span></td>
                        <td><strong>Room <?php echo htmlspecialchars($tenant['room']); ?></strong></td>
                        <td><?php echo htmlspecialchars($tenant['house']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['move_in']); ?></td>
                        <td><strong>&#8369;<?php echo htmlspecialchars($tenant['rent']); ?></strong></td>
                        <td><?php echo badge($tenant['status'], $tenant['status'] === 'Active' ? 'green' : 'yellow'); ?></td>
                        <td>
                            <div class="row-actions">
                                <button class="tiny-button" data-modal-target="#<?php echo $modalId; ?>-view" title="View"><i class="fa-regular fa-eye"></i></button>
                                <button
                                    class="tiny-button tenant-edit-trigger"
                                    data-modal-target="#edit-tenant-modal"
                                    data-tenant-id="<?php echo $tenant['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($tenant['name'], ENT_QUOTES); ?>"
                                    data-course="<?php echo htmlspecialchars(tenantFormValue($tenant['course']), ENT_QUOTES); ?>"
                                    data-email="<?php echo htmlspecialchars(tenantFormValue($tenant['email']), ENT_QUOTES); ?>"
                                    data-phone="<?php echo htmlspecialchars(tenantFormValue($tenant['phone']), ENT_QUOTES); ?>"
                                    data-house-id="<?php echo $tenant['boarding_house_id']; ?>"
                                    data-house="<?php echo htmlspecialchars($tenant['house'], ENT_QUOTES); ?>"
                                    data-room-id="<?php echo $tenant['room_id']; ?>"
                                    data-room="<?php echo htmlspecialchars($tenant['room'], ENT_QUOTES); ?>"
                                    data-move-in="<?php echo htmlspecialchars($tenant['move_in_value'], ENT_QUOTES); ?>"
                                    data-rent="<?php echo htmlspecialchars((string) $tenant['rent_value'], ENT_QUOTES); ?>"
                                    data-payment-method="<?php echo htmlspecialchars($tenant['payment_method'], ENT_QUOTES); ?>"
                                    data-status="<?php echo htmlspecialchars($tenant['status'], ENT_QUOTES); ?>"
                                    title="Edit"
                                ><i class="fa-regular fa-pen-to-square"></i></button>
                                <form action="tenants.php" method="post" onsubmit="return confirm('Mark this tenant as Moving Out?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><input type="hidden" name="action" value="move_out"><input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>"><button class="tiny-button move-out-icon" type="submit" title="Move Out" <?php echo $tenant['status'] === 'Moving Out' ? 'disabled' : ''; ?>><i class="fa-solid fa-right-from-bracket"></i></button></form>
                                <form action="tenants.php" method="post" onsubmit="return confirm('Remove this tenant and make the room vacant?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><input type="hidden" name="action" value="delete_tenant"><input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>"><button class="tiny-button danger-icon" type="submit" title="Remove"><i class="fa-regular fa-trash-can"></i></button></form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal-overlay" id="<?php echo $modalId; ?>-view" hidden>
                        <div class="portal-modal detail-modal">
                            <div class="modal-title-row"><h2>Tenant Details</h2><button data-modal-close aria-label="Close">&times;</button></div>
                            <dl class="modal-detail-grid tenant-detail-grid">
                                <div><dt>Full Name</dt><dd><?php echo htmlspecialchars($tenant['name']); ?></dd></div>
                                <div><dt>Status</dt><dd><?php echo htmlspecialchars($tenant['status']); ?></dd></div>
                                <div><dt>Email</dt><dd><?php echo htmlspecialchars($tenant['email']); ?></dd></div>
                                <div><dt>Phone</dt><dd><?php echo htmlspecialchars($tenant['phone']); ?></dd></div>
                                <div><dt>Course</dt><dd><?php echo htmlspecialchars($tenant['course']); ?></dd></div>
                                <div><dt>Payment Method</dt><dd><?php echo htmlspecialchars($tenant['payment_method']); ?></dd></div>
                                <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($tenant['house']); ?></dd></div>
                                <div><dt>Room</dt><dd><?php echo htmlspecialchars($tenant['room']); ?></dd></div>
                                <div><dt>Move-in Date</dt><dd><?php echo htmlspecialchars($tenant['move_in']); ?></dd></div>
                                <div><dt>Monthly Rent</dt><dd>&#8369;<?php echo htmlspecialchars($tenant['rent']); ?></dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="modal-overlay" id="<?php echo $modalId; ?>-edit" hidden>
                        <div class="portal-modal form-modal tenant-edit-modal">
                            <div class="modal-title-row compact"><div><h2>Edit Tenant</h2><p>Update tenant information, room assignment, rent, and payment method.</p></div><button data-modal-close aria-label="Close">&times;</button></div>
                            <form class="modal-form tenant-edit-form dependent-room-form" action="tenants.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                                <input type="hidden" name="action" value="edit_tenant">
                                <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                <div class="form-section-title wide"><i class="fa-solid fa-user"></i><span>Tenant Information</span></div>
                                <label>Full Name<input type="text" name="full_name" value="<?php echo htmlspecialchars($tenant['name']); ?>" required></label>
                                <label>Course<input type="text" name="course" value="<?php echo htmlspecialchars($tenant['course'] === '—' ? '' : $tenant['course']); ?>"></label>
                                <label>Email<input type="email" name="email" value="<?php echo htmlspecialchars($tenant['email'] === '—' ? '' : $tenant['email']); ?>"></label>
                                <label>Phone<input type="text" name="phone" value="<?php echo htmlspecialchars($tenant['phone'] === '—' ? '' : $tenant['phone']); ?>"></label>
                                <div class="form-section-title wide"><i class="fa-solid fa-door-open"></i><span>Room Assignment</span></div>
                                <label>Boarding House<select class="tenant-house-select" required><?php foreach ($houses as $house): ?><option value="<?php echo $house['id']; ?>" <?php echo $house['id'] === $tenant['boarding_house_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?></select></label>
                                <label>Room<select class="tenant-room-select" name="room_id" required><?php foreach ($rooms as $room): ?><?php if ($room['status'] === 'Vacant' || $room['id'] === $tenant['room_id']): ?><option data-house-id="<?php echo $room['boarding_house_id']; ?>" value="<?php echo $room['id']; ?>" <?php echo $room['id'] === $tenant['room_id'] ? 'selected' : ''; ?>>Room <?php echo htmlspecialchars($room['number']); ?></option><?php endif; ?><?php endforeach; ?></select></label>
                                <div class="form-section-title wide"><i class="fa-solid fa-money-bill-wave"></i><span>Rent and Payment</span></div>
                                <label>Move-in Date<input type="date" name="move_in_date" value="<?php echo htmlspecialchars($tenant['move_in_value']); ?>" required></label>
                                <label>Monthly Rent<input type="number" name="monthly_rent" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $tenant['rent_value']); ?>" required></label>
                                <label class="wide">Payment Method<select name="payment_method" required><?php foreach (['GCash', 'Bank Transfer', 'Cash'] as $method): ?><option <?php echo $tenant['payment_method'] === $method ? 'selected' : ''; ?>><?php echo $method; ?></option><?php endforeach; ?></select></label>
                                <div class="modal-actions sticky-actions"><button type="button" class="text-button" data-modal-close>Cancel</button><button type="submit" class="primary-action"><i class="fa-solid fa-floppy-disk"></i> Save Tenant</button></div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="edit-tenant-modal" hidden>
    <div class="portal-modal form-modal tenant-clean-edit-modal">
        <div class="modal-title-row compact">
            <div><h2>Edit Tenant</h2><p>Update tenant details, room assignment, rent, and payment method.</p></div>
            <button data-modal-close aria-label="Close">&times;</button>
        </div>
        <form class="tenant-clean-edit-form dependent-room-form" action="tenants.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="edit_tenant">
            <input type="hidden" name="tenant_id" id="edit_tenant_id">

            <div class="tenant-edit-summary">
                <span class="tenant-avatar large" id="edit_tenant_initials">T</span>
                <div>
                    <strong id="edit_tenant_summary_name">Tenant Name</strong>
                    <small><span id="edit_tenant_summary_room">Room</span> &middot; <span id="edit_tenant_summary_house">Boarding House</span></small>
                </div>
            </div>

            <label>Full Name *
                <input type="text" name="full_name" id="edit_full_name" required>
            </label>
            <label>Course
                <input type="text" name="course" id="edit_course">
            </label>
            <label>Email
                <input type="email" name="email" id="edit_email">
            </label>
            <label>Phone
                <input type="text" name="phone" id="edit_phone">
            </label>
            <label>Boarding House
                <select class="tenant-house-select" id="edit_house" required>
                    <?php foreach ($houses as $house): ?>
                        <option value="<?php echo $house['id']; ?>"><?php echo htmlspecialchars($house['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Room
                <select class="tenant-room-select" name="room_id" id="edit_room" required>
                    <?php foreach ($rooms as $room): ?>
                        <option data-house-id="<?php echo $room['boarding_house_id']; ?>" value="<?php echo $room['id']; ?>">Room <?php echo htmlspecialchars($room['number']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status
                <input type="text" id="edit_status" disabled>
            </label>
            <label>Monthly Rent (₱)
                <input type="number" name="monthly_rent" id="edit_monthly_rent" min="0" step="0.01" required>
            </label>
            <label>Move-in Date
                <input type="date" name="move_in_date" id="edit_move_in_date" required>
            </label>
            <label>Payment Method
                <select name="payment_method" id="edit_payment_method" required>
                    <option>GCash</option>
                    <option>Bank Transfer</option>
                    <option>Cash</option>
                </select>
            </label>

            <div class="modal-actions clean-actions">
                <button type="button" class="text-button" data-modal-close>Cancel</button>
                <button type="submit" class="primary-action"><i class="fa-solid fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="add-tenant-modal" hidden>
    <div class="portal-modal form-modal">
        <div class="modal-title-row compact"><div><h2>Add Tenant</h2><p>The tenant becomes Active and an initial payment record is created.</p></div><button data-modal-close aria-label="Close">&times;</button></div>
        <?php if (!$houses): ?>
            <div class="empty-state modal-empty"><strong>No accredited boarding house available.</strong></div>
        <?php else: ?>
            <form class="modal-form two-col dependent-room-form" action="tenants.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="action" value="add_tenant">
                <label>Full Name<input type="text" name="full_name" required></label>
                <label>Course<input type="text" name="course"></label>
                <label>Email<input type="email" name="email"></label>
                <label>Phone<input type="text" name="phone"></label>
                <label>Boarding House<select class="tenant-house-select" required><option value="">Select accredited house</option><?php foreach ($houses as $house): ?><option value="<?php echo $house['id']; ?>"><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?></select></label>
                <label>Vacant Room<select class="tenant-room-select" name="room_id" required><option value="">Select a house first</option><?php foreach ($rooms as $room): ?><?php if ($room['status'] === 'Vacant'): ?><option data-house-id="<?php echo $room['boarding_house_id']; ?>" value="<?php echo $room['id']; ?>">Room <?php echo htmlspecialchars($room['number']); ?> — &#8369;<?php echo htmlspecialchars($room['rent']); ?></option><?php endif; ?><?php endforeach; ?></select></label>
                <label>Move-in Date<input type="date" name="move_in_date" value="<?php echo date('Y-m-d'); ?>" required></label>
                <label>Monthly Rent<input type="number" name="monthly_rent" min="0" step="0.01" required></label>
                <label>Payment Method<select name="payment_method" required><option value="">Select method</option><option>GCash</option><option>Bank Transfer</option><option>Cash</option></select></label>
                <label>Initial Payment Amount<input type="number" name="initial_payment" min="0" step="0.01" value="0"></label>
                <div class="modal-actions"><button type="button" class="text-button" data-modal-close>Cancel</button><button type="submit" class="primary-action"><i class="fa-solid fa-user-plus"></i> Add Tenant</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php portalEnd(); ?>
