<?php
require 'admin_helpers.php';
requireAdmin();

$statusFilter = $_GET['status'] ?? 'All';
if (!in_array($statusFilter, ['All', 'Approved', 'Rejected'], true)) {
    $statusFilter = 'All';
}
$search = trim($_GET['q'] ?? '');

$allHistory = [];
foreach (getAdminRegistrationReviewHistory() as $registration) {
    $allHistory[] = [
        'key' => 'registration-' . $registration['id'],
        'source' => 'Registration',
        'title' => $registration['full_name'],
        'house' => $registration['house'] ?: '—',
        'landlord' => $registration['full_name'],
        'email' => $registration['email'],
        'phone' => $registration['phone'] ?: '—',
        'address' => $registration['address'] ?: '—',
        'type' => 'Landlord Account',
        'status' => $registration['status'],
        'reviewed_at' => $registration['reviewed_at'],
        'review_notes' => $registration['review_notes'] ?: '',
        'file_name' => '',
        'document_id' => 0,
    ];
}
foreach (getAdminDocumentReviewHistory() as $document) {
    $allHistory[] = [
        'key' => 'document-' . $document['id'],
        'source' => 'Document',
        'title' => $document['document_name'],
        'house' => $document['house'],
        'landlord' => $document['landlord'],
        'email' => $document['email'],
        'phone' => '—',
        'address' => '—',
        'type' => $document['document_type'],
        'requested_room_limit' => $document['requested_room_limit'] !== null ? (int) $document['requested_room_limit'] : null,
        'approved_room_limit' => $document['approved_room_limit'] !== null ? (int) $document['approved_room_limit'] : null,
        'status' => $document['status'],
        'reviewed_at' => $document['reviewed_at'],
        'review_notes' => $document['review_notes'] ?: '',
        'file_name' => $document['original_file_name'] ?: $document['file_name'],
        'document_id' => (int) $document['id'],
    ];
}

usort($allHistory, static fn(array $a, array $b): int => strtotime($b['reviewed_at']) <=> strtotime($a['reviewed_at']));

$history = array_values(array_filter($allHistory, static function (array $item) use ($statusFilter, $search): bool {
    if ($statusFilter !== 'All' && $item['status'] !== $statusFilter) {
        return false;
    }
    if ($search === '') {
        return true;
    }

    return str_contains(
        mb_strtolower(implode(' ', [$item['source'], $item['title'], $item['house'], $item['landlord'], $item['email'], $item['type'], $item['status'], $item['review_notes']])),
        mb_strtolower($search)
    );
}));

$approvedCount = count(array_filter($allHistory, static fn(array $item): bool => $item['status'] === 'Approved'));
$rejectedCount = count(array_filter($allHistory, static fn(array $item): bool => $item['status'] === 'Rejected'));

adminStart('Review History', 'View completed landlord registration and document decisions.');
?>

<div class="admin-history-stats">
    <article><span>Total Decisions</span><strong><?php echo count($allHistory); ?></strong></article>
    <article><span>Approved</span><strong class="green"><?php echo $approvedCount; ?></strong></article>
    <article><span>Rejected</span><strong class="red"><?php echo $rejectedCount; ?></strong></article>
</div>

<form class="admin-filter-row" method="get" action="admin_review_history.php">
    <label class="admin-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search registration, document, or landlord..." data-search-submit></label>
    <select class="admin-filter-select" name="status" data-auto-submit aria-label="Filter decision status">
        <?php foreach (['All', 'Approved', 'Rejected'] as $option): ?>
            <option value="<?php echo $option; ?>" <?php echo $statusFilter === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
        <?php endforeach; ?>
    </select>
</form>

<article class="admin-card admin-table-card">
    <?php if (!$history): ?>
        <div class="empty-state large"><i class="fa-solid fa-clock-rotate-left"></i><strong>No completed reviews</strong><span>Registration and document decisions will appear here.</span></div>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Review</th><th>Boarding House</th><th>Landlord</th><th>Type</th><th>Decision</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                    <?php $approved = $item['status'] === 'Approved'; $modalId = 'history-' . $item['key']; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['title']); ?></strong><br><small><?php echo htmlspecialchars($item['source']); ?></small></td>
                        <td><?php echo htmlspecialchars($item['house']); ?></td>
                        <td><?php echo htmlspecialchars($item['landlord']); ?></td>
                        <td><?php echo adminBadge($item['type'], $item['source'] === 'Registration' ? 'orange' : 'blue'); ?></td>
                        <td><span class="final-decision <?php echo $approved ? 'decision-approved' : 'decision-rejected'; ?>"><i class="fa-solid <?php echo $approved ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i><?php echo htmlspecialchars($item['status']); ?></span></td>
                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($item['reviewed_at']))); ?></td>
                        <td><button class="admin-row-button" data-modal-target="#<?php echo $modalId; ?>" aria-label="View decision"><i class="fa-regular fa-eye"></i></button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</article>

<?php foreach ($history as $item): ?>
    <?php $approved = $item['status'] === 'Approved'; $modalId = 'history-' . $item['key']; ?>
    <div class="modal-overlay" id="<?php echo $modalId; ?>" hidden>
        <div class="portal-modal detail-modal">
            <div class="modal-title-row"><h2><?php echo htmlspecialchars($item['source']); ?> Review Details</h2><button data-modal-close aria-label="Close">&times;</button></div>
            <div class="modal-house-summary">
                <span class="modal-icon <?php echo $approved ? 'green' : 'red'; ?>"><i class="fa-solid <?php echo $item['source'] === 'Registration' ? 'fa-user-check' : 'fa-file-lines'; ?>"></i></span>
                <div><h3><?php echo htmlspecialchars($item['title']); ?></h3><p><?php echo htmlspecialchars($item['source'] === 'Registration' ? $item['email'] : $item['file_name']); ?></p></div>
            </div>
            <dl class="modal-detail-grid">
                <div><dt>Landlord</dt><dd><?php echo htmlspecialchars($item['landlord']); ?></dd></div>
                <div><dt>Boarding House</dt><dd><?php echo htmlspecialchars($item['house']); ?></dd></div>
                <div><dt>Review Type</dt><dd><?php echo htmlspecialchars($item['type']); ?></dd></div>
                <?php if ($item['source'] === 'Document' && $item['type'] === 'Accreditation'): ?>
                    <div><dt>Requested Room Limit</dt><dd><?php echo (int) $item['requested_room_limit']; ?> rooms</dd></div>
                    <div><dt>Approved Room Limit</dt><dd><?php echo (int) $item['approved_room_limit']; ?> rooms</dd></div>
                <?php endif; ?>
                <div><dt>Decision</dt><dd><?php echo htmlspecialchars($item['status']); ?></dd></div>
                <div><dt>Email</dt><dd><?php echo htmlspecialchars($item['email']); ?></dd></div>
                <div><dt>Date Reviewed</dt><dd><?php echo htmlspecialchars(date('M d, Y', strtotime($item['reviewed_at']))); ?></dd></div>
                <?php if ($item['source'] === 'Registration'): ?>
                    <div><dt>Phone</dt><dd><?php echo htmlspecialchars($item['phone']); ?></dd></div>
                    <div><dt>Address</dt><dd><?php echo htmlspecialchars($item['address']); ?></dd></div>
                <?php endif; ?>
                <div class="wide"><dt>Review Notes</dt><dd><?php echo htmlspecialchars($item['review_notes'] ?: 'No review notes.'); ?></dd></div>
            </dl>
            <?php if ($item['source'] === 'Document'): ?>
                <div class="admin-download-row"><span><i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars($item['file_name']); ?></span><a class="admin-primary-action" href="document_download.php?id=<?php echo $item['document_id']; ?>"><i class="fa-solid fa-download"></i> Download File</a></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php adminEnd(); ?>
