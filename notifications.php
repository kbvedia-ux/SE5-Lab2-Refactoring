<?php
require 'portal_helpers.php';

portalStart(
    'Notifications',
    'Stay updated with notifications from admin and your boarding houses.',
    '<a class="mark-read" href="#">Mark all as read (2)</a>'
);
?>

<div class="tab-row notifications-tabs">
    <button class="filter-chip active">All</button><button class="filter-chip">Urgent</button><button class="filter-chip">Approval</button><button class="filter-chip">Reminder</button><button class="filter-chip">Payment</button><button class="filter-chip">Message</button><button class="filter-chip">Room Update</button><button class="filter-chip">Tenant</button><button class="filter-chip">System</button>
</div>

<div class="notification-list">
    <?php foreach (getNotifications() as $note): ?>
        <div class="notification-row"><i class="fa-solid fa-<?php echo $note['icon']; ?> icon-<?php echo $note['color']; ?>"></i><span><?php echo $note['text']; ?><small><?php echo $note['time']; ?> &middot; <?php echo badge($note['tag'], $note['color']); ?></small></span><button class="tiny-button"><i class="fa-regular fa-trash-can"></i></button></div>
    <?php endforeach; ?>
</div>

<?php portalEnd(); ?>
