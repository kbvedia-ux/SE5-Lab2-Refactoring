<?php
require 'portal_helpers.php';
requireLandlord();

$landlordId = (int) currentLandlord()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $houseId = (int) ($_POST['boarding_house_id'] ?? 0);
    $roomNumber = trim($_POST['room_number'] ?? '');
    $roomSizeValue = (int) ($_POST['room_size'] ?? 0);
    $windows = max(0, (int) ($_POST['windows'] ?? 0));
    $monthlyRent = max(0, (float) ($_POST['monthly_rent'] ?? 0));
    $fireAlarm = isset($_POST['fire_alarm']) ? 'Yes' : 'No';
    $emergencyExit = isset($_POST['emergency_exit']) ? 'Yes' : 'No';
    $ownCr = isset($_POST['own_cr']) ? 'Yes' : 'No';
    $remarks = trim($_POST['remarks'] ?? '');

    $stmt = $conn->prepare(
        "SELECT bh.id, bh.room_limit, COUNT(r.id) AS current_rooms
         FROM boarding_houses bh
         LEFT JOIN rooms r ON r.boarding_house_id = bh.id
         WHERE bh.id = ? AND bh.landlord_id = ? AND bh.status = 'Accredited'
         GROUP BY bh.id"
    );
    $stmt->bind_param('ii', $houseId, $landlordId);
    $stmt->execute();
    $houseApproval = $stmt->get_result()->fetch_assoc();
    $validHouse = $houseApproval !== null;
    $stmt->close();

    if (!$validHouse || $roomNumber === '' || $roomSizeValue < 1 || $roomSizeValue > 20) {
        setFlash('Choose an accredited boarding house and enter valid room details.', 'error');
        header('Location: room_management.php');
        exit;
    }

    $roomSize = $roomSizeValue . ' sqm';

    if ($action === 'add_room') {
        $approvedRoomLimit = (int) $houseApproval['room_limit'];
        $currentRoomCount = (int) $houseApproval['current_rooms'];
        if ($approvedRoomLimit < 1 || $currentRoomCount >= $approvedRoomLimit) {
            setFlash(
                $approvedRoomLimit < 1
                    ? 'This boarding house has no approved room limit. Submit an Accreditation document with a room limit first.'
                    : 'The approved limit of ' . $approvedRoomLimit . ' rooms has already been reached.',
                'error'
            );
            header('Location: room_management.php');
            exit;
        }

        $stmt = $conn->prepare('SELECT id FROM rooms WHERE boarding_house_id = ? AND room_number = ?');
        $stmt->bind_param('is', $houseId, $roomNumber);
        $stmt->execute();
        $duplicate = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($duplicate) {
            setFlash('That room number already exists in the selected boarding house.', 'error');
            header('Location: room_management.php');
            exit;
        }

        $safetyStatus = 'Pending Review';
        $status = 'Vacant';
        $occupantValue = null;
        $stmt = $conn->prepare(
            'INSERT INTO rooms
                (boarding_house_id, room_number, status, occupant, monthly_rent, room_size,
                 windows, fire_alarm, emergency_exit, own_cr, safety_status, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isssdsisssss',
            $houseId,
            $roomNumber,
            $status,
            $occupantValue,
            $monthlyRent,
            $roomSize,
            $windows,
            $fireAlarm,
            $emergencyExit,
            $ownCr,
            $safetyStatus,
            $remarks
        );
        $stmt->execute();
        $stmt->close();
        setFlash('Room added and marked Pending Review for safety inspection.', 'success');
    } elseif ($action === 'edit_room') {
        $safetyStatus = in_array($_POST['safety_status'] ?? '', ['Safe', 'Pending Review', 'Needs Review', 'Unsafe'], true)
            ? $_POST['safety_status']
            : 'Pending Review';

        $stmt = $conn->prepare(
            "UPDATE rooms r
             INNER JOIN boarding_houses bh ON bh.id = r.boarding_house_id
             SET r.boarding_house_id = ?, r.room_number = ?,
                 r.monthly_rent = ?, r.room_size = ?, r.windows = ?, r.fire_alarm = ?,
                 r.emergency_exit = ?, r.own_cr = ?, r.safety_status = ?, r.remarks = ?
             WHERE r.id = ? AND bh.landlord_id = ? AND bh.status = 'Accredited'"
        );
        $stmt->bind_param(
            'isdsisssssii',
            $houseId,
            $roomNumber,
            $monthlyRent,
            $roomSize,
            $windows,
            $fireAlarm,
            $emergencyExit,
            $ownCr,
            $safetyStatus,
            $remarks,
            $roomId,
            $landlordId
        );
        $stmt->execute();
        $stmt->close();
        setFlash('Room details updated.', 'success');
    }

    header('Location: room_management.php');
    exit;
}

portalStart(
    'Room Management',
    'Rooms can only be added within each boarding house approved room limit.',
    '<button type="button" class="primary-action" data-modal-target="#room-management-add-modal"><i class="fa-solid fa-plus"></i> Add Room</button>'
);

$allRooms = getRooms();
$houses = getAccreditedBoardingHouses();
$search = trim($_GET['q'] ?? '');
$houseFilter = $_GET['house'] ?? 'All';
$statusFilter = $_GET['status'] ?? 'All';
$houseNames = array_column($houses, 'name');

if ($houseFilter !== 'All' && !in_array($houseFilter, $houseNames, true)) {
    $houseFilter = 'All';
}
if (!in_array($statusFilter, ['All', 'Occupied', 'Vacant'], true)) {
    $statusFilter = 'All';
}

$rooms = array_values(array_filter($allRooms, static function (array $room) use ($search, $houseFilter, $statusFilter): bool {
    if ($houseFilter !== 'All' && $room['house'] !== $houseFilter) {
        return false;
    }
    if ($statusFilter !== 'All' && $room['status'] !== $statusFilter) {
        return false;
    }
    if ($search !== '') {
        $haystack = mb_strtolower(implode(' ', [
            $room['number'],
            $room['house'],
            $room['occupant'],
            $room['status'],
            $room['safety'],
        ]));
        if (!str_contains($haystack, mb_strtolower($search))) {
            return false;
        }
    }
    return true;
}));
?>

<form class="room-filter-panel" method="get" action="room_management.php">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
    <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search occupant, room, or boarding house..." data-search-submit></label>
    <select class="filter-select" name="house" data-auto-submit aria-label="Filter by boarding house">
        <option value="All">All accredited houses</option>
        <?php foreach ($houses as $house): ?><option value="<?php echo htmlspecialchars($house['name']); ?>" <?php echo $houseFilter === $house['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?>
    </select>
</form>

<div class="room-status-tabs">
    <span>Status:</span>
    <?php foreach (['All', 'Occupied', 'Vacant'] as $statusOption): ?>
        <a class="filter-chip <?php echo $statusFilter === $statusOption ? 'active' : ''; ?>" href="room_management.php?<?php echo http_build_query(['q' => $search, 'house' => $houseFilter, 'status' => $statusOption]); ?>"><?php echo $statusOption; ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$houses): ?>
    <div class="portal-card empty-state large">
        <i class="fa-solid fa-shield-circle-exclamation"></i>
        <strong>No accredited boarding house yet</strong>
        <span>You can add rooms after the UA admin approves at least one boarding house.</span>
    </div>
<?php elseif (!$allRooms): ?>
    <div class="portal-card empty-state large">
        <i class="fa-solid fa-door-open"></i>
        <strong>No rooms saved</strong>
        <span>Use Add Room to create the first room for an accredited property.</span>
    </div>
<?php elseif (!$rooms): ?>
    <div class="portal-card empty-state large">
        <i class="fa-solid fa-magnifying-glass"></i>
        <strong>No rooms match your search</strong>
        <span>Try another room, occupant, boarding house, or status.</span>
    </div>
<?php else: ?>
    <div class="room-grid">
        <?php foreach ($rooms as $room): ?>
            <?php
            $isVacant = $room['status'] === 'Vacant';
            $safetyType = $room['safety'] === 'Safe' ? 'green' : ($room['safety'] === 'Unsafe' ? 'red' : 'yellow');
            $modalId = 'room-' . $room['id'];
            $roomSizeNumber = (int) $room['size'];
            ?>
            <article class="room-card <?php echo $isVacant ? 'vacant' : 'occupied'; ?>" data-search-row data-search-text="<?php echo htmlspecialchars(implode(' ', [$room['number'], $room['house'], $room['status'], $room['occupant'], $room['rent'], $room['size'], $room['windows'], $room['fire_alarm'], $room['emergency_exit'], $room['own_cr'], $room['safety'], $room['remarks']])); ?>">
                <div class="room-card-head">
                    <span class="room-icon <?php echo $isVacant ? 'green' : 'blue'; ?>"><i class="fa-solid fa-door-open"></i></span>
                    <div><h3>Room <?php echo htmlspecialchars($room['number']); ?></h3><p><?php echo htmlspecialchars($room['house']); ?></p></div>
                    <?php echo badge($room['status'], $isVacant ? 'green' : 'blue'); ?>
                </div>
                <dl class="room-details">
                    <dt>Occupant</dt><dd><?php echo htmlspecialchars($room['occupant']); ?></dd>
                    <dt>Monthly Rent</dt><dd>&#8369;<?php echo htmlspecialchars($room['rent']); ?></dd>
                    <dt>Safety</dt><dd><?php echo badge($room['safety'], $safetyType); ?></dd>
                </dl>
                <div class="room-actions">
                    <button class="room-action muted" data-modal-target="#<?php echo $modalId; ?>-details"><i class="fa-regular fa-eye"></i> Details</button>
                    <button class="room-action review" data-modal-target="#<?php echo $modalId; ?>-edit"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
                </div>
            </article>

            <div class="modal-overlay" id="<?php echo $modalId; ?>-details" hidden>
                <div class="portal-modal detail-modal compact-detail">
                    <div class="modal-title-row">
                        <h2>Room <?php echo htmlspecialchars($room['number']); ?> Details</h2>
                        <button data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-house-summary">
                        <span class="modal-icon blue"><i class="fa-solid fa-door-open"></i></span>
                        <div><h3>Room <?php echo htmlspecialchars($room['number']); ?></h3><p><?php echo htmlspecialchars($room['house']); ?></p></div>
                    </div>
                    <dl class="modal-detail-grid">
                        <div><dt>Status</dt><dd><?php echo htmlspecialchars($room['status']); ?></dd></div>
                        <div><dt>Occupant</dt><dd><?php echo htmlspecialchars($room['occupant']); ?></dd></div>
                        <div><dt>Monthly Rent</dt><dd>&#8369;<?php echo htmlspecialchars($room['rent']); ?></dd></div>
                        <div><dt>Room Size</dt><dd><?php echo htmlspecialchars($room['size']); ?></dd></div>
                        <div><dt>Windows</dt><dd><?php echo htmlspecialchars($room['windows']); ?></dd></div>
                        <div><dt>Fire Alarm</dt><dd><?php echo htmlspecialchars($room['fire_alarm']); ?></dd></div>
                        <div><dt>Emergency Exit</dt><dd><?php echo htmlspecialchars($room['emergency_exit']); ?></dd></div>
                        <div><dt>Own CR</dt><dd><?php echo htmlspecialchars($room['own_cr']); ?></dd></div>
                        <div><dt>Safety Status</dt><dd><?php echo htmlspecialchars($room['safety']); ?></dd></div>
                        <div><dt>Remarks</dt><dd><?php echo htmlspecialchars($room['remarks'] ?: '—'); ?></dd></div>
                    </dl>
                </div>
            </div>

            <div class="modal-overlay" id="<?php echo $modalId; ?>-edit" hidden>
                <div class="portal-modal form-modal small-modal">
                    <div class="modal-title-row compact">
                        <div><h2>Edit Room <?php echo htmlspecialchars($room['number']); ?></h2><p>Update the room details and safety information.</p></div>
                        <button data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <form class="modal-form two-col" action="room_management.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="action" value="edit_room">
                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                        <label>Room Number<input type="text" name="room_number" value="<?php echo htmlspecialchars($room['number']); ?>" required></label>
                        <label>Boarding House<select name="boarding_house_id" required><?php foreach ($houses as $house): ?><option value="<?php echo $house['id']; ?>" <?php echo $house['id'] === $room['boarding_house_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Room Size<select name="room_size" required><?php for ($size = 1; $size <= 20; $size++): ?><option value="<?php echo $size; ?>" <?php echo $roomSizeNumber === $size ? 'selected' : ''; ?>><?php echo $size; ?> sqm</option><?php endfor; ?></select></label>
                        <label>Windows<input type="number" name="windows" min="0" value="<?php echo htmlspecialchars($room['windows']); ?>"></label>
                        <label>Monthly Rent<input type="number" name="monthly_rent" min="0" step="0.01" value="<?php echo htmlspecialchars(str_replace(',', '', $room['rent'])); ?>" required></label>
                        <label>Safety Status<select name="safety_status"><?php foreach (['Pending Review', 'Needs Review', 'Safe', 'Unsafe'] as $option): ?><option <?php echo $room['safety'] === $option ? 'selected' : ''; ?>><?php echo $option; ?></option><?php endforeach; ?></select></label>
                        <label class="check-label"><input type="checkbox" name="fire_alarm" <?php echo $room['fire_alarm'] === 'Yes' ? 'checked' : ''; ?>> Fire Alarm</label>
                        <label class="check-label"><input type="checkbox" name="emergency_exit" <?php echo $room['emergency_exit'] === 'Yes' ? 'checked' : ''; ?>> Emergency Exit</label>
                        <label class="check-label"><input type="checkbox" name="own_cr" <?php echo $room['own_cr'] === 'Yes' ? 'checked' : ''; ?>> Own CR</label>
                        <label class="wide">Remarks<textarea name="remarks"><?php echo htmlspecialchars($room['remarks']); ?></textarea></label>
                        <div class="modal-actions wide">
                            <button type="button" class="text-button" data-modal-close>Cancel</button>
                            <button type="submit" class="primary-action"><i class="fa-solid fa-check"></i> Save Room</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="center-note">Showing <?php echo count($rooms); ?> rooms across accredited boarding houses</p>
<?php endif; ?>

<div class="modal-overlay" id="room-management-add-modal" data-page-modal="room-management" hidden>
    <div class="portal-modal form-modal small-modal">
        <div class="modal-title-row compact">
            <div><h2>Add New Room</h2><p>The room will be linked to an accredited boarding house and marked Pending Review.</p></div>
            <button data-modal-close aria-label="Close">&times;</button>
        </div>
        <?php if (!$houses): ?>
            <div class="empty-state modal-empty"><strong>No accredited boarding house available.</strong></div>
        <?php else: ?>
            <form class="modal-form two-col" action="room_management.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="action" value="add_room">
                <label>Room Number *<input type="text" name="room_number" required></label>
                <label>Boarding House<select name="boarding_house_id" required><option value="">Select a house</option><?php foreach ($houses as $house): ?><option value="<?php echo $house['id']; ?>"><?php echo htmlspecialchars($house['name']); ?> (<?php echo $house['rooms']; ?>/<?php echo $house['room_limit']; ?> rooms)</option><?php endforeach; ?></select></label>
                <label>Room Size<select name="room_size" required><option value="">Select size</option><?php for ($size = 1; $size <= 20; $size++): ?><option value="<?php echo $size; ?>"><?php echo $size; ?> sqm</option><?php endfor; ?></select></label>
                <label>Windows<input type="number" name="windows" min="0" value="0"></label>
                <label>Monthly Rent<input type="number" name="monthly_rent" min="0" step="0.01" required></label>
                <label class="check-label"><input type="checkbox" name="fire_alarm"> Fire Alarm</label>
                <label class="check-label"><input type="checkbox" name="emergency_exit"> Emergency Exit</label>
                <label class="check-label"><input type="checkbox" name="own_cr"> Own CR</label>
                <label class="wide">Remarks<textarea name="remarks"></textarea></label>
                <button type="submit" class="primary-action wide"><i class="fa-solid fa-plus"></i> Add Room</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php portalEnd(); ?>
