<?php
require 'portal_helpers.php';
requireLandlord();

$landlordId = (int) currentLandlord()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $documentName = trim($_POST['document_name'] ?? '');
    $houseId = (int) ($_POST['boarding_house_id'] ?? 0);
    $documentType = $_POST['document_type'] ?? '';
    $requestedRoomLimit = (int) ($_POST['requested_room_limit'] ?? 0);
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $allowedTypes = ['Accreditation', 'Safety', 'Health', 'Legal'];

    $stmt = $conn->prepare('SELECT id FROM boarding_houses WHERE id = ? AND landlord_id = ?');
    $stmt->bind_param('ii', $houseId, $landlordId);
    $stmt->execute();
    $validHouse = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    if ($documentName === '' || !$validHouse || !in_array($documentType, $allowedTypes, true)) {
        setFlash('Complete all required document fields.', 'error');
        header('Location: accreditation_docs.php');
        exit;
    }

    if ($documentType === 'Accreditation' && ($requestedRoomLimit < 1 || $requestedRoomLimit > 500)) {
        setFlash('Enter a room limit from 1 to 500 for an Accreditation document.', 'error');
        header('Location: accreditation_docs.php');
        exit;
    }

    if (empty($_FILES['document_file']['name']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('Attach a PDF, JPG, or PNG file before submitting.', 'error');
        header('Location: accreditation_docs.php');
        exit;
    }

    $originalFileName = basename($_FILES['document_file']['name']);
    $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['document_file']['tmp_name']);
    $allowedFiles = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    if (
        !isset($allowedFiles[$extension])
        || !in_array($mimeType, $allowedFiles[$extension], true)
        || $_FILES['document_file']['size'] > 10 * 1024 * 1024
    ) {
        setFlash('Invalid file. Only real PDF, JPG, JPEG, or PNG files up to 10MB are accepted.', 'error');
        header('Location: accreditation_docs.php');
        exit;
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';
    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0775, true);
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        setFlash('The document file could not be stored.', 'error');
        header('Location: accreditation_docs.php');
        exit;
    }

    $status = 'Pending';
    $uploadedDate = date('Y-m-d');
    $expiryValue = $expiryDate !== '' ? $expiryDate : null;
    $roomLimitValue = $documentType === 'Accreditation' ? $requestedRoomLimit : null;
    $stmt = $conn->prepare(
        'INSERT INTO accreditation_documents
            (boarding_house_id, document_name, file_name, original_file_name, document_type,
             requested_room_limit, uploaded_date, expiry_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issssisss', $houseId, $documentName, $fileName, $originalFileName, $documentType, $roomLimitValue, $uploadedDate, $expiryValue, $status);
    $stmt->execute();
    $stmt->close();

    setFlash('Document submitted to the correct UA Pending Reviews category.', 'success');
    header('Location: accreditation_docs.php');
    exit;
}

portalStart(
    'Accreditation Documents',
    'Upload documents and submit them for accreditation review.',
    '<button type="button" class="primary-action" data-modal-target="#accreditation-document-submit-modal"><i class="fa-solid fa-upload"></i> Submit New Document</button>'
);

$allDocs = getDocuments();
$houses = getBoardingHouses();
$search = trim($_GET['q'] ?? '');
$houseFilter = (int) ($_GET['house_id'] ?? 0);
$statusFilter = $_GET['status'] ?? 'All';
if (!in_array($statusFilter, ['All', 'Approved', 'Pending', 'Rejected'], true)) {
    $statusFilter = 'All';
}

$houseNamesById = [];
foreach ($houses as $house) {
    $houseNamesById[$house['id']] = $house['name'];
}

$docs = array_values(array_filter($allDocs, static function (array $doc) use ($search, $houseFilter, $statusFilter, $houseNamesById): bool {
    if ($houseFilter > 0 && ($houseNamesById[$houseFilter] ?? '') !== $doc['house']) {
        return false;
    }
    if ($statusFilter !== 'All' && $doc['status'] !== $statusFilter) {
        return false;
    }
    if ($search !== '') {
        $haystack = mb_strtolower(implode(' ', [$doc['name'], $doc['file'], $doc['house'], $doc['type'], $doc['status']]));
        if (!str_contains($haystack, mb_strtolower($search))) {
            return false;
        }
    }
    return true;
}));
?>

<form class="docs-filter-panel" method="get" action="accreditation_docs.php">
    <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search documents or submissions..." data-search-submit></label>
    <select class="filter-select" name="house_id" data-auto-submit aria-label="Filter by boarding house">
        <option value="0">All Boarding Houses</option>
        <?php foreach ($houses as $house): ?>
            <option value="<?php echo $house['id']; ?>" <?php echo $houseFilter === $house['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($house['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="status" data-auto-submit aria-label="Filter document status">
        <?php foreach (['All', 'Approved', 'Pending', 'Rejected'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $statusFilter === $option ? 'selected' : ''; ?>><?php echo $option; ?> Status</option><?php endforeach; ?>
    </select>
</form>

<div class="portal-card table-card">
    <table class="portal-table">
        <thead><tr><th>Document</th><th>Boarding House</th><th>Type</th><th>Uploaded</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($docs as $doc): ?>
                <?php
                    $typeColor = match ($doc['type']) {
                        'Safety' => 'red',
                        'Health' => 'green',
                        'Legal' => 'orange',
                        default => 'blue',
                    };
                ?>
                <tr data-search-row data-search-text="<?php echo htmlspecialchars(implode(' ', [$doc['name'], $doc['file'], $doc['house'], $doc['type'], $doc['status'], $doc['uploaded'], $doc['expiry'], $doc['review_notes']])); ?>">
                    <td class="document-cell">
                        <span class="doc-icon <?php echo $typeColor; ?>"><i class="fa-regular fa-file-lines"></i></span>
                        <span><strong><?php echo htmlspecialchars($doc['name']); ?></strong><small><?php echo htmlspecialchars($doc['file']); ?></small></span>
                    </td>
                    <td><?php echo htmlspecialchars($doc['house']); ?></td>
                    <td><?php echo badge($doc['type'], $typeColor); ?></td>
                    <td><?php echo htmlspecialchars($doc['uploaded']); ?></td>
                    <td><?php echo htmlspecialchars($doc['expiry']); ?></td>
                    <td><?php echo badge($doc['status'], $doc['status'] === 'Approved' ? 'green' : 'yellow'); ?></td>
                    <td><button class="tiny-button" data-modal-target="#document-<?php echo $doc['id']; ?>" title="View details"><i class="fa-regular fa-eye"></i></button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach ($docs as $doc): ?>
    <div class="modal-overlay" id="document-<?php echo $doc['id']; ?>" hidden>
        <div class="portal-modal detail-modal">
            <div class="modal-title-row">
                <h2>Document Details</h2>
                <button data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-house-summary">
                <span class="modal-icon blue"><i class="fa-regular fa-file-lines"></i></span>
                <div><h3><?php echo htmlspecialchars($doc['name']); ?></h3><p><?php echo htmlspecialchars($doc['file']); ?></p></div>
            </div>
            <dl class="modal-detail-grid">
                <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($doc['house']); ?></dd></div>
                <div><dt>Document Type</dt><dd><?php echo htmlspecialchars($doc['type']); ?></dd></div>
                <?php if ($doc['type'] === 'Accreditation'): ?><div><dt>Requested Room Limit</dt><dd><?php echo (int) $doc['requested_room_limit']; ?> rooms</dd></div><?php endif; ?>
                <?php if ($doc['type'] === 'Accreditation' && $doc['approved_room_limit']): ?><div><dt>Approved Room Limit</dt><dd><?php echo (int) $doc['approved_room_limit']; ?> rooms</dd></div><?php endif; ?>
                <div><dt>Uploaded</dt><dd><?php echo htmlspecialchars($doc['uploaded']); ?></dd></div>
                <div><dt>Expiry</dt><dd><?php echo htmlspecialchars($doc['expiry']); ?></dd></div>
                <div><dt>Review Status</dt><dd><?php echo htmlspecialchars($doc['status']); ?></dd></div>
                <div><dt>UA Review Notes</dt><dd><?php echo htmlspecialchars($doc['review_notes'] ?: 'No review notes yet.'); ?></dd></div>
            </dl>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal-overlay" id="accreditation-document-submit-modal" data-page-modal="accreditation-documents" hidden>
    <div class="portal-modal form-modal document-modal">
        <div class="modal-title-row compact">
            <div><h2>Submit Document for Review</h2><p>Documents will be reviewed by the accreditation officer.</p></div>
            <button data-modal-close aria-label="Close">&times;</button>
        </div>
        <form class="modal-form" action="accreditation_docs.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label class="wide">Document Name<input type="text" name="document_name" required></label>
            <label class="wide">Boarding House<select name="boarding_house_id" required><option value="">Select a house</option><?php foreach ($houses as $house): ?><option value="<?php echo $house['id']; ?>"><?php echo htmlspecialchars($house['name']); ?></option><?php endforeach; ?></select></label>
            <label class="wide">Document Type<select name="document_type" data-document-type required><option value="">Select a type</option><option>Accreditation</option><option>Safety</option><option>Health</option><option>Legal</option></select></label>
            <label class="wide" data-room-limit-field hidden>Room Limit<input type="number" name="requested_room_limit" min="1" max="500" disabled></label>
            <label class="wide">Expiry Date (optional)<input type="date" name="expiry_date"></label>
            <label class="wide">Upload File
                <div class="upload-box" data-file-upload>
                    <div class="upload-empty" data-file-empty>
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <strong>Click to browse or drag and drop</strong>
                        <span>PDF, JPG, PNG up to 10MB</span>
                    </div>
                    <div class="upload-selected" data-file-selected hidden>
                        <i class="fa-solid fa-file-circle-check"></i>
                        <strong data-file-name>No file selected</strong>
                        <span data-file-size></span>
                        <button type="button" data-file-remove><i class="fa-solid fa-xmark"></i> Remove</button>
                    </div>
                    <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-file-input required>
                </div>
            </label>
            <button type="submit" class="primary-action wide"><i class="fa-regular fa-paper-plane"></i> Submit for Review</button>
        </form>
    </div>
</div>

<?php portalEnd(); ?>
