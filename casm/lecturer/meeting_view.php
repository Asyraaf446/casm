<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();
if (!meetings_ready()) redirect('lecturer/meetings.php');

$id = (int) ($_GET['id'] ?? 0);
$m = meeting_for_user($id, $me);
if (!$m) {
    http_response_code(404);
    exit('Meeting not found.');
}

if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'end' && $m['status'] === 'live') {
        qrun("UPDATE meetings SET status='ended', ended_at=? WHERE id=?", [date('Y-m-d H:i:s'), $id]);
        log_activity('END_MEETING', 'Ended online meeting "' . $m['title'] . '" (' . $m['code'] . ')');
        flash('success', 'Meeting ended. Students can no longer join it.');
    } elseif ($action === 'delete') {
        qrun('DELETE FROM meetings WHERE id = ?', [$id]);
        log_activity('DELETE_MEETING', 'Deleted online meeting "' . $m['title'] . '" (' . $m['code'] . ')');
        flash('success', 'Meeting deleted.');
        redirect('lecturer/meetings.php');
    }
    redirect('lecturer/meeting_view.php?id=' . $id);
}

expire_old_meetings();
$m = meeting_for_user($id, $me);
$state = meeting_state($m);
$students = qall(
    'SELECT u.id, u.name, u.avatar, a.joined_at FROM enrollments e JOIN users u ON u.id = e.student_id
       LEFT JOIN meeting_attendance a ON a.meeting_id = ? AND a.user_id = u.id
      WHERE e.class_id = ? ORDER BY a.joined_at IS NULL, a.joined_at, u.name',
    [$id, $m['class_id']]
);
$joined = count(array_filter($students, fn($s) => $s['joined_at']));
$link = meeting_link($m);

$pageTitle = $m['title'];
$active = 'meetings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/meetings.php') ?>">Meetings</a> / <?= e($m['code']) ?></div>
        <h1><?= e($m['title']) ?> <?= meeting_pill($state) ?></h1>
        <p class="sub"><?= e($m['code'] . ' · ' . $m['course_title']) ?> · <?= e(fmt_datetime($m['scheduled_at'])) ?></p>
    </div>
    <div class="actions">
        <?php if ($state !== 'ended' && $link === ''): ?>
        <a class="btn" href="<?= url('lecturer/meeting_form.php?id=' . $id) ?>"><?= icon('link', 18) ?> Add Meet link</a>
        <?php elseif ($state !== 'ended'): ?>
        <form class="inline-form js-join" method="post" action="<?= url('meeting_join.php') ?>" target="_blank">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn <?= $state === 'live' ? '' : 'btn-mint' ?>" type="submit"><?= icon('video', 18) ?> <?= $state === 'live' ? 'Join meeting' : 'Start meeting' ?></button>
        </form>
        <?php endif; ?>
        <?php if ($state === 'live'): ?>
        <form class="inline-form" method="post" onsubmit="return confirm('End this meeting for everyone?');">
            <?= csrf_field() ?><input type="hidden" name="action" value="end"><button class="btn btn-danger" type="submit">End meeting</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <div class="card-head"><h3>Details</h3></div>
        <?php if ($m['description']): ?><p><?= nl2br(e($m['description'])) ?></p><?php else: ?><p class="muted">No description.</p><?php endif; ?>
        <div class="field"><span class="label">Google Meet link</span>
            <?php if ($link !== ''): ?>
            <div class="linkbox"><?= icon('link', 18) ?><code><?= e($link) ?></code><button type="button" class="btn btn-soft btn-sm" data-copy="<?= e($link) ?>">Copy</button></div>
            <span class="hint">Students join from their Meetings page in CASM, which also records attendance.</span>
            <?php else: ?>
            <div class="notice"><?= icon('help', 20) ?><div>No Google Meet link has been added yet, so students cannot join. Press "Add Meet link".</div></div>
            <?php endif; ?></div>
        <?php if ($link !== '' && $state !== 'ended'): ?>
            <div class="notice" style="margin-top:14px"><?= icon('help', 20) ?><div>Press "Start meeting" to open the meeting. Students may see "Ask to join" in Google Meet, so admit them from the Meet screen.</div></div>
        <?php endif; ?>
        <div class="form-actions">
            <?php if ($m['status'] !== 'ended'): ?><a class="btn btn-soft btn-sm" href="<?= url('lecturer/meeting_form.php?id=' . $id) ?>">Edit</a><?php endif; ?>
            <form class="inline-form" method="post" onsubmit="return confirm('Delete this meeting?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Attendance</h3><span class="muted"><?= $joined ?> of <?= count($students) ?> joined</span></div>
        <?php if (!$students): ?><p class="muted">No students are enrolled in this class yet.</p><?php else: ?>
        <div class="attend">
            <?php foreach ($students as $s): ?>
                <div class="row"><?= avatar_html($s['name'], $s['avatar'], (int) $s['id']) ?>
                    <div class="grow"><b><?= e($s['name']) ?></b></div>
                    <?= $s['joined_at'] ? '<small>' . e(fmt_time($s['joined_at'])) . '</small> ' . pill('success', 'Joined') : pill('muted', 'Not joined') ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
