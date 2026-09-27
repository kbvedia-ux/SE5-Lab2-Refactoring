<?php
require 'admin_helpers.php';

adminStart(
    'Accreditation Dashboard',
    'Review landlord, boarding-house, and document submissions.',
    '<a class="admin-primary-action" href="admin_pending_reviews.php"><i class="fa-solid fa-clipboard-check"></i> Open Pending Reviews</a>'
);

$history = getAdminDocumentReviewHistory();
$documentCounts = getAdminDocumentCounts();
$pendingApplications = getPendingLandlordRegistrations();
$pendingDocumentItems = getAdminPendingDocuments(null, 'Pending');
$priorityQueue = [];
foreach ($pendingApplications as $registration) {
    $priorityQueue[] = [
        'title' => $registration['full_name'],
        'subtitle' => ($registration['house'] ?: 'No boarding house') . ' · Account approval',
        'category' => 'registrations',
        'icon' => 'fa-user-plus',
        'tone' => 'orange',
        'created_at' => $registration['created_at'],
    ];
}
foreach ($pendingDocumentItems as $document) {
    $category = match ($document['document_type']) {
        'Accreditation' => 'accreditation',
        'Legal' => 'documents',
        default => 'safety',
    };
    $priorityQueue[] = [
        'title' => $document['document_name'],
        'subtitle' => $document['house'] . ' · ' . $document['landlord'] . ' · ' . $document['document_type'],
        'category' => $category,
        'icon' => $document['document_type'] === 'Accreditation' ? 'fa-house' : 'fa-file-lines',
        'tone' => $document['document_type'] === 'Accreditation' ? 'orange' : 'blue',
        'created_at' => $document['created_at'],
    ];
}
usort($priorityQueue, static fn(array $a, array $b): int => strtotime($a['created_at']) <=> strtotime($b['created_at']));

$completedDecisions = array_slice($history, 0, 5);
$approvedToday = count(array_filter($history, static fn(array $item): bool => $item['status'] === 'Approved' && $item['reviewed_at'] && date('Y-m-d', strtotime($item['reviewed_at'])) === date('Y-m-d')));
$rejectedToday = count(array_filter($history, static fn(array $item): bool => $item['status'] === 'Rejected' && $item['reviewed_at'] && date('Y-m-d', strtotime($item['reviewed_at'])) === date('Y-m-d')));
$pendingDocuments = array_sum($documentCounts);
$stats = [
    ['label' => 'Pending Registrations', 'value' => count($pendingApplications), 'icon' => 'fa-user-check', 'tone' => 'orange', 'note' => 'Pending Reviews'],
    ['label' => 'Pending Documents', 'value' => $pendingDocuments, 'icon' => 'fa-file-lines', 'tone' => 'blue', 'note' => 'Pending Reviews'],
    ['label' => 'Approved Today', 'value' => $approvedToday, 'icon' => 'fa-check', 'tone' => 'green', 'note' => 'Today'],
    ['label' => 'Rejected Today', 'value' => $rejectedToday, 'icon' => 'fa-circle-xmark', 'tone' => 'red', 'note' => 'Today'],
];
?>

<div class="admin-stats-grid">
    <?php foreach ($stats as $stat): ?>
        <article class="admin-stat-card">
            <div class="admin-soft-icon <?php echo htmlspecialchars($stat['tone']); ?>"><i class="fa-solid <?php echo htmlspecialchars($stat['icon']); ?>"></i></div>
            <small><?php echo htmlspecialchars($stat['note']); ?></small>
            <strong><?php echo (int) $stat['value']; ?></strong>
            <span><?php echo htmlspecialchars($stat['label']); ?></span>
        </article>
    <?php endforeach; ?>
</div>

<div class="admin-dashboard-grid">
    <article class="admin-card admin-wide-card">
        <div class="admin-card-heading">
            <div><h2>Priority Review Queue</h2><p>Landlord registrations and documents waiting for review</p></div>
            <?php echo adminBadge(count($priorityQueue) . ' pending', 'orange'); ?>
        </div>

        <?php if (!$priorityQueue): ?>
            <div class="empty-state"><i class="fa-solid fa-clipboard-check"></i><strong>No pending reviews</strong></div>
        <?php else: ?>
            <div class="admin-queue-list">
                <?php foreach (array_slice($priorityQueue, 0, 5) as $item): ?>
                    <a href="admin_pending_reviews.php?category=<?php echo htmlspecialchars($item['category']); ?>" class="admin-queue-row">
                        <span class="admin-soft-icon <?php echo htmlspecialchars($item['tone']); ?>"><i class="fa-solid <?php echo htmlspecialchars($item['icon']); ?>"></i></span>
                        <span>
                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                            <small><?php echo htmlspecialchars($item['subtitle']); ?></small>
                        </span>
                        <?php echo adminBadge('Pending', 'orange'); ?>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a class="admin-card-link" href="admin_pending_reviews.php">Open All Pending Reviews</a>
    </article>

    <div class="admin-side-stack">
        <article class="admin-card">
            <h2>Recent Decisions</h2>
            <p>Latest approved and rejected documents</p>
            <?php if (!$completedDecisions): ?>
                <div class="empty-state"><strong>No completed decisions yet</strong></div>
            <?php else: ?>
                <div class="admin-mini-decisions">
                    <?php foreach ($completedDecisions as $item): ?>
                        <?php $approved = $item['status'] === 'Approved'; ?>
                        <div>
                            <span class="admin-soft-icon <?php echo $approved ? 'green' : 'red'; ?>"><i class="fa-solid <?php echo $approved ? 'fa-check' : 'fa-xmark'; ?>"></i></span>
                            <span><strong><?php echo htmlspecialchars($item['document_name']); ?></strong><small><?php echo htmlspecialchars($item['status']); ?> &middot; <?php echo htmlspecialchars($item['house']); ?></small></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="admin-card">
            <h2>Document Review Queue</h2>
            <div class="admin-document-summary">
                <a href="admin_pending_reviews.php?category=accreditation"><span>Accreditation</span><b><?php echo $documentCounts['accreditation']; ?></b></a>
                <a href="admin_pending_reviews.php?category=documents"><span>Legal Documents</span><b><?php echo $documentCounts['documents']; ?></b></a>
                <a href="admin_pending_reviews.php?category=safety"><span>Safety &amp; Health</span><b><?php echo $documentCounts['safety']; ?></b></a>
            </div>
        </article>
    </div>
</div>

<?php adminEnd(); ?>
