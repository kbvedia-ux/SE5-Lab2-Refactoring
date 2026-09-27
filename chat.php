<?php
require 'portal_helpers.php';

portalStart('Chat with Admin', 'Communicate directly with the accreditation team and administrators.');

$search = trim($_GET['q'] ?? '');
$conversations = [
    ['initials' => 'R', 'name' => 'Admin - Juan Delos Reyes', 'preview' => 'Your accreditation renewal has been received.', 'unread' => 2],
    ['initials' => 'S', 'name' => 'Admin - Maria Clara Santos', 'preview' => 'Please submit the updated building permit.', 'unread' => 0],
    ['initials' => 'C', 'name' => 'Admin - Pedro Cruz', 'preview' => 'I will check on that and get back to you.', 'unread' => 0],
];
if ($search !== '') {
    $needle = mb_strtolower($search);
    $conversations = array_values(array_filter($conversations, static function (array $conversation) use ($needle): bool {
        return str_contains(mb_strtolower($conversation['name'] . ' ' . $conversation['preview']), $needle);
    }));
}
?>

<div class="chat-shell portal-card">
    <aside class="chat-list">
        <form method="get" action="chat.php">
            <label class="portal-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search conversations..." data-search-submit></label>
        </form>
        <?php if (!$conversations): ?>
            <div class="chat-search-empty">No conversations match your search.</div>
        <?php else: ?>
            <?php foreach ($conversations as $index => $conversation): ?>
                <div class="chat-person <?php echo $index === 0 ? 'active' : ''; ?>" data-search-row data-search-text="<?php echo htmlspecialchars($conversation['name'] . ' ' . $conversation['preview']); ?>">
                    <div class="avatar"><?php echo htmlspecialchars($conversation['initials']); ?></div>
                    <span><strong><?php echo htmlspecialchars($conversation['name']); ?></strong><small><?php echo htmlspecialchars($conversation['preview']); ?></small></span>
                    <?php if ($conversation['unread']): ?><em><?php echo (int) $conversation['unread']; ?></em><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>
    <section class="chat-empty"><i class="fa-regular fa-comments"></i><h3>Select a conversation</h3><p>Choose an admin conversation from the list to view messages.</p></section>
</div>

<?php portalEnd(); ?>
