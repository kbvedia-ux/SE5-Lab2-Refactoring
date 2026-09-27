<?php
require 'admin_helpers.php';

adminStart('Chat', 'Communicate with landlords about their accreditation applications.');

$search = trim($_GET['q'] ?? '');
$conversations = getAdminConversations();
if ($search !== '') {
    $needle = mb_strtolower($search);
    $conversations = array_values(array_filter($conversations, static function (array $conversation) use ($needle): bool {
        return str_contains(mb_strtolower($conversation['name'] . ' ' . $conversation['preview']), $needle);
    }));
}
?>

<article class="admin-card admin-chat-card">
    <div class="admin-chat-list">
        <form method="get" action="admin_chat.php">
            <label class="admin-search wide"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search conversations..." data-search-submit></label>
        </form>
        <?php if (!$conversations): ?>
            <div class="chat-search-empty">No conversations match your search.</div>
        <?php else: ?>
            <?php foreach ($conversations as $conversation): ?>
                <a href="#" class="admin-chat-person" data-search-row data-search-text="<?php echo htmlspecialchars($conversation['name'] . ' ' . $conversation['preview'] . ' ' . $conversation['time']); ?>">
                    <span class="admin-chat-avatar">
                        <?php echo htmlspecialchars($conversation['initials']); ?>
                        <?php if ($conversation['online']): ?><i></i><?php endif; ?>
                    </span>
                    <span>
                        <strong><?php echo htmlspecialchars($conversation['name']); ?></strong>
                        <small><?php echo htmlspecialchars($conversation['preview']); ?></small>
                    </span>
                    <em>
                        <?php echo htmlspecialchars($conversation['time']); ?>
                        <?php if ($conversation['unread']): ?><b><?php echo $conversation['unread']; ?></b><?php endif; ?>
                    </em>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="admin-chat-empty">
        <i class="fa-regular fa-comments"></i>
        <strong>Select a conversation</strong>
        <span>Messages will appear here.</span>
    </div>
</article>

<?php adminEnd(); ?>
