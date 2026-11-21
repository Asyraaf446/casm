<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$me = user();

/* open one notification: mark as read, then go to its link */
if (isset($_GET['go'])) {
    $n = qone('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int) $_GET['go'], $me['id']]);
    if ($n) {
        qrun('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]);
        redirect($n['link'] ?: 'notifications.php');
    }
    redirect('notifications.php');
}

if (is_post()) {
    csrf_verify();
    if (post('action') === 'read_all') {
        qrun('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$me['id']]);
        flash('success', 'All notifications marked as read.');
    } elseif (post('action') === 'clear') {
        qrun('DELETE FROM notifications WHERE user_id = ? AND is_read = 1', [$me['id']]);
        flash('success', 'Read notifications cleared.');
    }
    redirect('notifications.php');
}

$list = qall('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 60', [$me['id']]);

$pageTitle = 'Notifications';
$active = '';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Notifications</h1></div>
    <div class="actions">
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-soft btn-sm"><?= icon('check', 16) ?> Mark all read</button></form>
        <form method="post" class="inline-form" data-confirm="Remove all notifications you have already read?"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-ghost btn-sm"><?= icon('trash', 16) ?> Clear read</button></form>
    </div>
</div>

<div class="card">
    <?php if (!$list): ?>
        <div class="empty"><div class="em-ico"><?= icon('bell', 28) ?></div><p>You are all caught up. No notifications yet.</p></div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($list as $n): ?>
                <a class="item <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= url('notifications.php?go=' . (int) $n['id']) ?>" style="color:inherit;text-decoration:none">
                    <span class="ico"><?= icon('bell', 20) ?></span>
                    <span class="grow"><b><?= e($n['title']) ?></b><small><?= e($n['message']) ?></small></span>
                    <small class="nowrap"><?= e(time_ago($n['created_at'])) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
