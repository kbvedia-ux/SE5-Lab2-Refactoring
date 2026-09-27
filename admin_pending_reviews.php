<?php
require 'admin_helpers.php';
requireAdmin();

$category = $_GET['category'] ?? 'all';
if (!in_array($category, ['all', 'registrations', 'accreditation', 'documents', 'safety'], true)) {
    $category = 'all';
}
$statusFilter = 'Pending';
$search = trim($_GET['q'] ?? '');

function pendingReviewCategory(array $document): string
{
    if ($document['document_type'] === 'Accreditation') {
        return 'accreditation';
    }

    if ($document['document_type'] === 'Legal') {
        return 'documents';
    }

    return 'safety';
}

function pendingReviewMatchesSearch(array $document, string $search): bool
{
    if ($search === '') {
        return true;
    }

    $haystack = mb_strtolower(implode(' ', [
        $document['document_name'] ?? '',
        ($document['original_file_name'] ?? '') ?: ($document['file_name'] ?? ''),
        $document['document_type'] ?? '',
        $document['status'] ?? '',
        $document['house'] ?? '',
        $document['address'] ?? '',
        $document['landlord'] ?? '',
        $document['landlord_email'] ?? '',
        $document['landlord_phone'] ?? '',
        $document['review_notes'] ?? '',
    ]));

    return str_contains($haystack, mb_strtolower($search));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $reviewAction = $_POST['review_action'] ?? 'document';
    if ($reviewAction === 'registration') {
        $landlordId = (int) ($_POST['landlord_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $notes = trim($_POST['review_notes'] ?? '');

        if ($landlordId < 1 || !in_array($decision, ['Approved', 'Rejected'], true)) {
            setFlash('Choose Approved or Rejected for the landlord registration.', 'error');
            header('Location: admin_pending_reviews.php?category=registrations');
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "UPDATE users
                 SET account_status = ?, reviewed_at = NOW(), review_notes = ?
                 WHERE id = ? AND role = 'landlord' AND account_status = 'Pending'"
            );
            $stmt->bind_param('ssi', $decision, $notes, $landlordId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('This registration already has a final decision.');
            }
            $stmt->close();

            if ($decision === 'Approved') {
                $stmt = $conn->prepare(
                    "UPDATE boarding_houses
                     SET status = 'Not Accredited', date_approved = NULL
                     WHERE landlord_id = ? AND status = 'Pending'"
                );
                $stmt->bind_param('i', $landlordId);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            setFlash(
                $decision === 'Approved'
                    ? 'Landlord account approved. The landlord can now log in, but the boarding house is still Not Accredited.'
                    : 'Landlord registration rejected.',
                'success'
            );
        } catch (Throwable $exception) {
            $conn->rollback();
            setFlash($exception->getMessage(), 'error');
        }

        header('Location: admin_pending_reviews.php?category=registrations');
        exit;
    }

    $documentId = (int) ($_POST['document_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $notes = trim($_POST['review_notes'] ?? '');
    $approvedRoomLimitInput = (int) ($_POST['approved_room_limit'] ?? 0);
    $returnCategory = $_POST['category'] ?? 'all';
    $returnStatus = $_POST['status_filter'] ?? 'All';
    $returnSearch = trim($_POST['q'] ?? '');
    if (!in_array($returnCategory, ['all', 'registrations', 'accreditation', 'documents', 'safety'], true)) {
        $returnCategory = 'all';
    }
    $returnStatus = 'Pending';

    if ($documentId < 1 || !in_array($decision, ['Approved', 'Rejected'], true)) {
        setFlash('Choose Approved or Rejected.', 'error');
        header('Location: admin_pending_reviews.php?' . http_build_query(['category' => $returnCategory, 'status' => $returnStatus, 'q' => $returnSearch]));
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT boarding_house_id, document_type, requested_room_limit
             FROM accreditation_documents
             WHERE id = ? AND status = 'Pending'
             FOR UPDATE"
        );
        $stmt->bind_param('i', $documentId);
        $stmt->execute();
        $reviewDocument = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reviewDocument) {
            throw new RuntimeException('This document already has a final review.');
        }

        $approvedRoomLimit = null;
        if ($decision === 'Approved' && $reviewDocument['document_type'] === 'Accreditation') {
            $approvedRoomLimit = $approvedRoomLimitInput;
            if ($approvedRoomLimit < 1 || $approvedRoomLimit > 500) {
                throw new RuntimeException('Enter an approved room limit from 1 to 500.');
            }
        }

        $stmt = $conn->prepare(
            "UPDATE accreditation_documents
             SET status = ?, approved_room_limit = ?, review_notes = ?, reviewed_at = NOW()
             WHERE id = ? AND status = 'Pending'"
        );
        $stmt->bind_param('sisi', $decision, $approvedRoomLimit, $notes, $documentId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('This document already has a final review.');
        }
        $stmt->close();

        if ($decision === 'Approved' && $reviewDocument['document_type'] === 'Accreditation') {
            $houseId = (int) $reviewDocument['boarding_house_id'];
            $stmt = $conn->prepare(
                "UPDATE boarding_houses
                 SET status = 'Accredited', date_approved = CURDATE(),
                     room_limit = ?, reviewed_at = NOW(), review_notes = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('isi', $approvedRoomLimit, $notes, $houseId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        setFlash(
            $decision === 'Approved' && $reviewDocument['document_type'] === 'Accreditation'
                ? 'Accreditation document approved. The boarding house is now Accredited.'
                : 'Document review saved as ' . $decision . '.',
            'success'
        );
    } catch (Throwable $exception) {
        $conn->rollback();
        setFlash($exception->getMessage(), 'error');
    }
    header('Location: admin_pending_reviews.php?' . http_build_query(['category' => $returnCategory, 'status' => $returnStatus, 'q' => $returnSearch]));
    exit;
}

$pendingCounts = getAdminDocumentCounts();
$registrations = getPendingLandlordRegistrations();
$totalPending = array_sum($pendingCounts) + count($registrations);
$allDocuments = getAdminPendingDocuments(null, 'Pending');
$filteredDocuments = array_values(array_filter($allDocuments, static function (array $document) use ($statusFilter, $search): bool {
    if ($document['status'] !== 'Pending') {
        return false;
    }

    return pendingReviewMatchesSearch($document, $search);
}));

$counts = [
    'all' => count($filteredDocuments) + count($registrations),
    'registrations' => count($registrations),
    'accreditation' => 0,
    'documents' => 0,
    'safety' => 0,
];

foreach ($filteredDocuments as $document) {
    $counts[pendingReviewCategory($document)]++;
}

$documents = $category === 'all'
    ? $filteredDocuments
    : ($category === 'registrations'
        ? []
        : array_values(array_filter($filteredDocuments, static fn(array $document): bool => pendingReviewCategory($document) === $category)));

$visibleRegistrations = in_array($category, ['all', 'registrations'], true) ? $registrations : [];

adminStart(
    'Pending Reviews',
    'Review documents submitted by landlords. Each document is routed by its selected type.',
    adminBadge($totalPending . ' Pending', 'orange')
);
?>

<div class="admin-review-tabs">
    <a class="<?php echo $category === 'all' ? 'active' : ''; ?>" href="admin_pending_reviews.php?<?php echo http_build_query(['category' => 'all', 'status' => $statusFilter, 'q' => $search]); ?>">
        <i class="fa-solid fa-layer-group"></i> All Reviews <b><?php echo $counts['all']; ?></b>
    </a>
    <a class="<?php echo $category === 'registrations' ? 'active' : ''; ?>" href="admin_pending_reviews.php?<?php echo http_build_query(['category' => 'registrations']); ?>">
        <i class="fa-solid fa-user-check"></i> Landlord Registrations <b><?php echo $counts['registrations']; ?></b>
    </a>
    <a class="<?php echo $category === 'accreditation' ? 'active' : ''; ?>" href="admin_pending_reviews.php?<?php echo http_build_query(['category' => 'accreditation', 'status' => $statusFilter, 'q' => $search]); ?>">
        <i class="fa-solid fa-house"></i> Accreditation <b><?php echo $counts['accreditation']; ?></b>
    </a>
    <a class="<?php echo $category === 'documents' ? 'active' : ''; ?>" href="admin_pending_reviews.php?<?php echo http_build_query(['category' => 'documents', 'status' => $statusFilter, 'q' => $search]); ?>">
        <i class="fa-solid fa-file-lines"></i> Document Reviews <b><?php echo $counts['documents']; ?></b>
    </a>
    <a class="<?php echo $category === 'safety' ? 'active' : ''; ?>" href="admin_pending_reviews.php?<?php echo http_build_query(['category' => 'safety', 'status' => $statusFilter, 'q' => $search]); ?>">
        <i class="fa-solid fa-shield-halved"></i> Room Safety <b><?php echo $counts['safety']; ?></b>
    </a>
</div>

<form class="admin-filter-row" method="get" action="admin_pending_reviews.php">
    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
    <label class="admin-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search document, boarding house, or landlord..." data-search-submit></label>
    <span class="admin-badge badge-orange">Pending Only</span>
</form>

<?php if (!$documents && !$visibleRegistrations): ?>
    <article class="admin-card empty-state large">
        <i class="fa-solid fa-clipboard-check"></i>
        <strong>No documents match these filters</strong>
        <span>Try another category, review status, or search term.</span>
    </article>
<?php else: ?>
    <div class="admin-application-grid">
        <?php foreach ($visibleRegistrations as $registration): ?>
            <?php $registrationModalId = 'registration-review-' . (int) $registration['id']; ?>
            <article class="admin-application-card">
                <div class="admin-app-head">
                    <span class="admin-soft-icon orange"><i class="fa-solid fa-user-plus"></i></span>
                    <div>
                        <h2><?php echo htmlspecialchars($registration['full_name']); ?></h2>
                        <p><?php echo htmlspecialchars($registration['house'] ?: 'No boarding house'); ?></p>
                    </div>
                    <?php echo adminBadge('Account Review', 'orange'); ?>
                </div>
                <div class="admin-app-meta">
                    <span><i class="fa-solid fa-envelope"></i><?php echo htmlspecialchars($registration['email']); ?></span>
                    <span><i class="fa-solid fa-phone"></i><?php echo htmlspecialchars($registration['phone'] ?: '—'); ?></span>
                    <span><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($registration['address'] ?: '—'); ?></span>
                    <span><i class="fa-solid fa-calendar"></i>Registered <?php echo htmlspecialchars(date('M d, Y', strtotime($registration['created_at']))); ?></span>
                </div>
                <p class="admin-app-note">Approval grants portal login only. The boarding house remains Not Accredited.</p>
                <button class="admin-review-button" data-modal-target="#<?php echo $registrationModalId; ?>"><i class="fa-regular fa-eye"></i> Review Registration</button>
            </article>

            <div class="modal-overlay" id="<?php echo $registrationModalId; ?>" hidden>
                <div class="portal-modal form-modal">
                    <div class="modal-title-row compact">
                        <div><h2>Review Landlord Registration</h2><p>Approve portal access without granting boarding-house accreditation.</p></div>
                        <button data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <dl class="modal-detail-grid">
                        <div><dt>Landlord</dt><dd><?php echo htmlspecialchars($registration['full_name']); ?></dd></div>
                        <div><dt>Email</dt><dd><?php echo htmlspecialchars($registration['email']); ?></dd></div>
                        <div><dt>Phone</dt><dd><?php echo htmlspecialchars($registration['phone'] ?: '—'); ?></dd></div>
                        <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($registration['house'] ?: '—'); ?></dd></div>
                        <div class="wide"><dt>Address</dt><dd><?php echo htmlspecialchars($registration['address'] ?: '—'); ?></dd></div>
                    </dl>
                    <form class="modal-form" action="admin_pending_reviews.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="review_action" value="registration">
                        <input type="hidden" name="landlord_id" value="<?php echo (int) $registration['id']; ?>">
                        <label class="wide">Decision
                            <select name="decision" required>
                                <option value="">Select decision</option>
                                <option value="Approved">Approve Login</option>
                                <option value="Rejected">Reject Registration</option>
                            </select>
                        </label>
                        <label class="wide">Review Notes<textarea name="review_notes"></textarea></label>
                        <div class="modal-actions">
                            <button type="button" class="text-button" data-modal-close>Cancel</button>
                            <button type="submit" class="admin-primary-action"><i class="fa-solid fa-check"></i> Save Decision</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($documents as $document): ?>
            <?php
            $modalId = 'document-review-' . (int) $document['id'];
            $displayFile = $document['original_file_name'] ?: $document['file_name'];
            ?>
            <article class="admin-application-card" data-search-row data-search-text="<?php echo htmlspecialchars(implode(' ', [$document['document_name'], $displayFile, $document['document_type'], $document['house'], $document['landlord'], $document['landlord_email'], $document['landlord_phone'], $document['address'], $document['status'], $document['review_notes']])); ?>">
                <div class="admin-app-head">
                    <span class="admin-soft-icon blue"><i class="fa-solid fa-file-arrow-up"></i></span>
                    <div>
                        <h2><?php echo htmlspecialchars($document['document_name']); ?></h2>
                        <p><?php echo htmlspecialchars($document['house']); ?></p>
                    </div>
                    <?php echo adminBadge($document['document_type'], 'blue'); ?>
                </div>

                <div class="admin-app-meta">
                    <span><i class="fa-solid fa-user"></i><?php echo htmlspecialchars($document['landlord']); ?></span>
                    <span><i class="fa-solid fa-file"></i><?php echo htmlspecialchars($displayFile); ?></span>
                    <span><i class="fa-solid fa-calendar"></i>Uploaded <?php echo htmlspecialchars(date('M d, Y', strtotime($document['uploaded_date'] ?: $document['created_at']))); ?></span>
                    <span><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($document['address']); ?></span>
                </div>

                <p class="admin-app-note">
                    <?php echo htmlspecialchars($document['document_type']); ?> submission
                    <?php if ($document['document_type'] === 'Accreditation'): ?>
                        &middot; Requested limit: <?php echo (int) $document['requested_room_limit']; ?> rooms
                    <?php endif; ?>
                    &middot; <?php echo htmlspecialchars($document['status']); ?>
                </p>
                <?php
                $documentStatusTone = $document['status'] === 'Approved' ? 'green' : ($document['status'] === 'Rejected' ? 'red' : 'orange');
                echo adminBadge($document['status'], $documentStatusTone);
                ?>
                <button class="admin-review-button" data-modal-target="#<?php echo $modalId; ?>"><i class="fa-regular fa-eye"></i> Review Document</button>
            </article>

            <div class="modal-overlay" id="<?php echo $modalId; ?>" hidden>
                <div class="portal-modal detail-modal">
                    <div class="modal-title-row compact">
                        <div><h2>Review Submitted Document</h2><p>Check the details and download the original file before deciding.</p></div>
                        <button data-modal-close aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-house-summary">
                        <span class="modal-icon blue"><i class="fa-solid fa-file-lines"></i></span>
                        <div>
                            <h3><?php echo htmlspecialchars($document['document_name']); ?></h3>
                            <p><?php echo htmlspecialchars($displayFile); ?></p>
                        </div>
                    </div>
                    <dl class="modal-detail-grid">
                        <div><dt>Landlord</dt><dd><?php echo htmlspecialchars($document['landlord']); ?></dd></div>
                        <div><dt>Email</dt><dd><?php echo htmlspecialchars($document['landlord_email']); ?></dd></div>
                        <div><dt>Phone</dt><dd><?php echo htmlspecialchars($document['landlord_phone'] ?: '—'); ?></dd></div>
                        <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($document['house']); ?></dd></div>
                        <div><dt>Document Type</dt><dd><?php echo htmlspecialchars($document['document_type']); ?></dd></div>
                        <?php if ($document['document_type'] === 'Accreditation'): ?><div><dt>Requested Room Limit</dt><dd><?php echo (int) $document['requested_room_limit']; ?> rooms</dd></div><?php endif; ?>
                        <div><dt>Expiry Date</dt><dd><?php echo $document['expiry_date'] ? htmlspecialchars(date('M d, Y', strtotime($document['expiry_date']))) : '—'; ?></dd></div>
                    </dl>
                    <div class="admin-download-row">
                        <span><i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars($displayFile); ?></span>
                        <a class="admin-primary-action" href="document_download.php?id=<?php echo (int) $document['id']; ?>"><i class="fa-solid fa-download"></i> Download File</a>
                    </div>
                    <?php if ($document['status'] === 'Pending'): ?>
                        <form class="modal-form" action="admin_pending_reviews.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                            <input type="hidden" name="review_action" value="document">
                            <input type="hidden" name="document_id" value="<?php echo (int) $document['id']; ?>">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
                            <label class="wide">Decision
                                <select name="decision" required>
                                    <option value="">Select decision</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </label>
                            <?php if ($document['document_type'] === 'Accreditation'): ?>
                                <label class="wide">Approved Room Limit
                                    <input type="number" name="approved_room_limit" min="1" max="500" value="<?php echo (int) ($document['requested_room_limit'] ?: 0); ?>">
                                </label>
                            <?php endif; ?>
                            <label class="wide">Review Notes<textarea name="review_notes" placeholder="Optional feedback for the landlord"></textarea></label>
                            <div class="modal-actions">
                                <button type="button" class="text-button" data-modal-close>Cancel</button>
                                <button type="submit" class="admin-primary-action"><i class="fa-solid fa-check"></i> Save Review</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="admin-final-review">
                            <span class="final-decision <?php echo $document['status'] === 'Approved' ? 'decision-approved' : 'decision-rejected'; ?>">
                                <i class="fa-solid <?php echo $document['status'] === 'Approved' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                                <?php echo htmlspecialchars($document['status']); ?>
                            </span>
                            <p><?php echo htmlspecialchars($document['review_notes'] ?: 'No review notes.'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php adminEnd(); ?>
