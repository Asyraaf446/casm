<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
require_once __DIR__ . '/../includes/meeting_card.php';
$me = user();

$ready = meetings_ready();
$live = $upcoming = $past = [];
if ($ready) {
    expire_old_meetings();
    $rows = qall(
        'SELECT m.*, c.code, c.title AS course_title FROM meetings m JOIN classes c ON c.id = m.class_id
          WHERE c.lecturer_id = ? ORDER BY m.scheduled_at DESC',
        [$me['id']]
    );
    foreach ($rows as $m) {
        $s = meeting_state($m);
        if ($s === 'live') $live[] = $m;
        elseif ($s === 'upcoming') $upcoming[] = $m;
        else $past[] = $m;
    }
    $upcoming = array_reverse($upcoming); // soonest first
}

$pageTitle = 'Meetings';
$active = 'meetings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Online Meetings</h1></div>
    <?php if ($ready): ?><div class="actions"><a class="btn" href="<?= url('lecturer/meeting_form.php') ?>"><?= icon('plus', 18) ?> Schedule meeting</a></div><?php endif; ?>
</div>

<?php if (!$ready): ?>
    <div class="notice"><?= icon('help', 20) ?><div>The meetings tables are not in the database yet. In phpMyAdmin open database/update_add_meetings.sql, paste it in the SQL tab and press Go.</div></div>
<?php else: ?>
    <?php if ($live): ?>
        <div class="meet-section"><h2>Happening now</h2><div class="list"><?php foreach ($live as $m) render_meeting_card($m, 'lecturer'); ?></div></div>
    <?php endif; ?>

    <div class="meet-section"><h2>Upcoming</h2>
        <?php if ($upcoming): ?><div class="list"><?php foreach ($upcoming as $m) render_meeting_card($m, 'lecturer'); ?></div>
        <?php else: ?><div class="card"><div class="empty"><div class="em-ico"><?= icon('video', 28) ?></div><p>No meetings scheduled yet.</p><a class="btn btn-soft" href="<?= url('lecturer/meeting_form.php') ?>">Schedule your first meeting</a></div></div><?php endif; ?>
    </div>

    <?php if ($past): ?>
        <div class="meet-section"><h2>Past meetings</h2><div class="list"><?php foreach ($past as $m) render_meeting_card($m, 'lecturer'); ?></div></div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
