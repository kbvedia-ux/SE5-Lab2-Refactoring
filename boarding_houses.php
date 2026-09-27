<?php
require 'portal_helpers.php';
requireLandlord();

$landlord = currentLandlord();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $address === '') {
        setFlash('Boarding-house name and address are required.', 'error');
        header('Location: boarding_houses.php');
        exit;
    }

    if ($action === 'add_house') {
        $status = 'Not Accredited';
        $applicationType = 'New';
        $landlordId = (int) $landlord['id'];

        $stmt = $conn->prepare(
            'INSERT INTO boarding_houses
                (landlord_id, name, address, contact_number, description, status, application_type)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssss', $landlordId, $name, $address, $contact, $description, $status, $applicationType);
        $stmt->execute();
        $stmt->close();

        setFlash('Boarding house added as Not Accredited. Upload its accreditation documents for UA review.', 'success');
    } elseif ($action === 'edit_house') {
        $houseId = (int) ($_POST['house_id'] ?? 0);
        $landlordId = (int) $landlord['id'];

        $stmt = $conn->prepare(
            'UPDATE boarding_houses
             SET name = ?, address = ?, contact_number = ?, description = ?
             WHERE id = ? AND landlord_id = ?'
        );
        $stmt->bind_param('ssssii', $name, $address, $contact, $description, $houseId, $landlordId);
        $stmt->execute();
        $stmt->close();

        setFlash('Boarding-house details updated.', 'success');
    }

    header('Location: boarding_houses.php');
    exit;
}

portalStart(
    'My Boarding Houses',
    'New properties remain Not Accredited until their accreditation documents are approved.',
    '<button class="primary-action" data-modal-target="#add-house-modal"><i class="fa-solid fa-plus"></i> Add Boarding House</button>'
);

$houses = getBoardingHouses();
?>

<?php if (!$houses): ?>
    <div class="portal-card empty-state large">
        <i class="fa-solid fa-house-circle-xmark"></i>
        <strong>No boarding houses found</strong>
        <span>Submit your first property for accreditation review.</span>
    </div>
<?php else: ?>
    <div class="house-cards">
        <?php foreach ($houses as $house): ?>
            <?php
            $modalId = 'house-' . $house['id'];
            $tone = $house['status'] === 'Accredited' ? 'green' : ($house['status'] === 'Rejected' ? 'red' : 'yellow');
            ?>
            <article class="house-card">
                <div class="house-head">
                    <span><?php echo badge($house['status'], $tone); ?></span>
                    <h3><?php echo htmlspecialchars($house['name']); ?></h3>
                    <p><?php echo htmlspecialchars($house['address']); ?></p>
                </div>
                <div class="house-stats">
                    <div><b><?php echo $house['rooms']; ?></b><span>Total Rooms</span></div>
                    <div><b><?php echo $house['room_limit']; ?></b><span>Approved Limit</span></div>
                    <div><b><?php echo $house['vacant']; ?></b><span>Vacant</span></div>
                    <div><b><?php echo $house['tenants']; ?></b><span>Tenants</span></div>
                    <div><b><?php echo htmlspecialchars($house['approved']); ?></b><span>Date Approved</span></div>
                </div>
                <div class="house-actions">
                    <button class="wide-action" data-modal-target="#<?php echo $modalId; ?>-details"><i class="fa-regular fa-eye"></i> Details</button>
                    <button class="wide-action light-green" data-modal-target="#<?php echo $modalId; ?>-edit"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
                </div>
            </article>

            <div class="modal-overlay" id="<?php echo $modalId; ?>-details" hidden>
                <div class="portal-modal detail-modal">
                    <div class="modal-title-row">
                        <h2><?php echo htmlspecialchars($house['name']); ?></h2>
                        <button data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-house-summary">
                        <span class="modal-icon green"><i class="fa-solid fa-house"></i></span>
                        <div><h3><?php echo htmlspecialchars($house['name']); ?></h3><p><?php echo htmlspecialchars($house['address']); ?></p></div>
                    </div>
                    <dl class="modal-detail-grid">
                        <div><dt>Total Rooms</dt><dd><?php echo $house['rooms']; ?></dd></div>
                        <div><dt>Approved Room Limit</dt><dd><?php echo $house['room_limit']; ?></dd></div>
                        <div><dt>Occupied Rooms</dt><dd><?php echo $house['tenants']; ?></dd></div>
                        <div><dt>Vacant Rooms</dt><dd><?php echo $house['vacant']; ?></dd></div>
                        <div><dt>Total Tenants</dt><dd><?php echo $house['tenants']; ?></dd></div>
                        <div><dt>Contact</dt><dd><?php echo htmlspecialchars($house['contact']); ?></dd></div>
                        <div><dt>Status</dt><dd><?php echo htmlspecialchars($house['status']); ?></dd></div>
                        <div><dt>Date Approved</dt><dd><?php echo htmlspecialchars($house['approved']); ?></dd></div>
                        <div><dt>Review Notes</dt><dd><?php echo htmlspecialchars($house['review_notes'] ?: 'No notes yet.'); ?></dd></div>
                    </dl>
                </div>
            </div>

            <div class="modal-overlay" id="<?php echo $modalId; ?>-edit" hidden>
                <div class="portal-modal form-modal">
                    <div class="modal-title-row compact">
                        <div><h2>Edit Boarding House</h2><p>Update property details without changing its accreditation status.</p></div>
                        <button data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <form class="modal-form" action="boarding_houses.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="action" value="edit_house">
                        <input type="hidden" name="house_id" value="<?php echo $house['id']; ?>">
                        <label>Boarding House Name<input type="text" name="name" value="<?php echo htmlspecialchars($house['name']); ?>" required></label>
                        <label>Contact Number<input type="text" name="contact_number" value="<?php echo htmlspecialchars($house['contact'] === '—' ? '' : $house['contact']); ?>"></label>
                        <label class="wide">Address<input type="text" name="address" value="<?php echo htmlspecialchars($house['address']); ?>" required></label>
                        <label class="wide">Description<textarea name="description"><?php echo htmlspecialchars($house['description']); ?></textarea></label>
                        <div class="modal-actions">
                            <button type="button" class="text-button" data-modal-close>Cancel</button>
                            <button type="submit" class="primary-action"><i class="fa-solid fa-check"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal-overlay" id="add-house-modal" hidden>
    <div class="portal-modal form-modal">
        <div class="modal-title-row compact">
            <div><h2>Add New Boarding House</h2><p>The new property starts as Not Accredited until its documents are approved.</p></div>
            <button data-modal-close aria-label="Close">&times;</button>
        </div>
        <form class="modal-form" action="boarding_houses.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="add_house">
            <label class="wide">Boarding House Name<input type="text" name="name" required></label>
            <label class="wide">Address<input type="text" name="address" required></label>
            <label class="wide">Contact Number<input type="text" name="contact_number" value="<?php echo htmlspecialchars($landlord['phone'] ?? ''); ?>"></label>
            <label class="wide">Description<textarea name="description" placeholder="Brief property description"></textarea></label>
            <div class="modal-actions">
                <button type="button" class="text-button" data-modal-close>Cancel</button>
                <button type="submit" class="primary-action"><i class="fa-solid fa-paper-plane"></i> Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<?php portalEnd(); ?>
