<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$course = lecturer_course((int) ($_GET['id'] ?? 0));
$cid    = (int) $course['id'];
$tab    = $_GET['tab'] ?? 'materials';
if ($tab === 'students') $tab = 'members';   // old links still work
if (!in_array($tab, ['materials', 'assignments', 'members'], true)) $tab = 'materials';

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'delete_material') {
        $m = qone('SELECT * FROM materials WHERE id = ? AND class_id = ?', [(int) ($_POST['material_id'] ?? 0), $cid]);
        if ($m) {
            delete_upload($m['file_path']);
            qrun('DELETE FROM materials WHERE id = ?', [$m['id']]);
            log_activity('DELETE_MATERIAL', 'Deleted "' . $m['title'] . '" from ' . $course['code']);
            flash('success', 'Material deleted.');
        }
        redirect('lecturer/class_view.php?id=' . $cid . '&tab=materials');
    }
}

$materials = qall('SELECT * FROM materials WHERE class_id = ? ORDER BY created_at DESC, id DESC', [$cid]);
$assignments = qall(
    'SELECT a.*, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submitted
       FROM assignments a WHERE a.class_id = ? ORDER BY a.due_date DESC',
    [$cid]
);
$students = qall(
    'SELECT u.id, u.name, u.email, u.avatar, e.enrolled_at,
            (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.class_id = ? AND s.student_id = u.id) AS done
       FROM enrollments e JOIN users u ON u.id = e.student_id WHERE e.class_id = ? ORDER BY u.name',
    [$cid, $cid]
);
$totalStudents = count($students);
$lecturer = qone('SELECT id, name, email, avatar FROM users WHERE id = ?', [(int) $course['lecturer_id']]);
$totalMembers = $totalStudents + ($lecturer ? 1 : 0);

$pageTitle = $course['code'] . ' ' . $course['title'];
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/courses.php') ?>">My Courses</a> / <?= e($course['code']) ?></div>
        <h1><?= e($course['title']) ?> · Class <?= e($course['class_name']) ?></h1>
        <p class="sub"><?= e($course['description']) ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-soft" href="<?= url('lecturer/class_form.php?id=' . $cid) ?>"><?= icon('edit', 17) ?> Edit class</a>
        <a class="btn btn-soft" href="<?= url('lecturer/doubts.php?course=' . $cid) ?>"><?= icon('help', 17) ?> Doubts</a>
        <a class="btn btn-soft" href="<?= url('lecturer/attendance.php?course=' . $cid) ?>"><?= icon('clipboard', 17) ?> Attendance</a>
    </div>
</div>

<div class="card c-<?= e($course['color']) ?> mb" style="background:linear-gradient(135deg,var(--tint),#fff)">
    <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:center">
        <span class="code-badge" style="margin:0"><?= e($course['code']) ?></span>
        <div class="meta"><?= icon('clock', 17) ?> <?= $course['day_of_week'] ? e(day_name((int) $course['day_of_week'])) . ', ' . e(fmt_time($course['start_time'])) . ' – ' . e(fmt_time($course['end_time'])) : 'Schedule not set' ?></div>
        <?php if ($course['room']): ?><div class="meta"><?= icon('pin', 17) ?> <?= e($course['room']) ?></div><?php endif; ?>
        <div class="meta"><?= icon('users', 17) ?> <?= $totalMembers ?> member<?= $totalMembers === 1 ? '' : 's' ?></div>
        <div class="meta"><?= icon('key', 17) ?> Join code: <b style="color:var(--ink);letter-spacing:1px"><?= e($course['join_code']) ?></b></div>
    </div>
</div>

<div class="tabs">
    <a class="<?= $tab === 'materials' ? 'active' : '' ?>" href="?id=<?= $cid ?>&tab=materials">Notes &amp; materials (<?= count($materials) ?>)</a>
    <a class="<?= $tab === 'assignments' ? 'active' : '' ?>" href="?id=<?= $cid ?>&tab=assignments">Assignments (<?= count($assignments) ?>)</a>
    <a class="<?= $tab === 'members' ? 'active' : '' ?>" href="?id=<?= $cid ?>&tab=members">Members (<?= $totalMembers ?>)</a>
</div>

<?php if ($tab === 'materials'): ?>
<div class="card">
    <div class="card-head"><h2>Notes &amp; materials</h2>
        <a class="btn btn-sm" href="<?= url('lecturer/material_form.php?class_id=' . $cid) ?>"><?= icon('upload', 16) ?> Upload material</a></div>
    <?php if (!$materials): ?>
        <div class="empty"><div class="em-ico"><?= icon('file', 28) ?></div><p>No notes yet. Upload slides, notes or links for your students.</p>
            <a class="btn" href="<?= url('lecturer/material_form.php?class_id=' . $cid) ?>">Upload the first one</a></div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($materials as $m): ?>
                <div class="item c-<?= e($course['color']) ?>">
                    <span class="ico"><?= icon('file', 20) ?></span>
                    <span class="grow"><b><?= e($m['title']) ?></b><small><?= e($m['original_name']) ?> · uploaded <?= e(fmt_date($m['created_at'])) ?><?= $m['description'] ? ' · ' . e(mb_strimwidth($m['description'], 0, 80, '…')) : '' ?></small></span>
                    <div class="row-actions">
                        <a class="btn btn-soft btn-sm" href="<?= url('download.php?type=material&id=' . (int) $m['id']) ?>"><?= icon('download', 15) ?></a>
                        <a class="btn btn-soft btn-sm" href="<?= url('lecturer/material_form.php?id=' . (int) $m['id']) ?>"><?= icon('edit', 15) ?></a>
                        <form method="post" class="inline-form" data-confirm="Delete this material?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_material"><input type="hidden" name="material_id" value="<?= (int) $m['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 15) ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'assignments'): ?>
<div class="card">
    <div class="card-head"><h2>Assignments</h2>
        <a class="btn btn-sm" href="<?= url('lecturer/assignment_form.php?class_id=' . $cid) ?>"><?= icon('plus', 16) ?> New assignment</a></div>
    <?php if (!$assignments): ?>
        <div class="empty"><div class="em-ico"><?= icon('file', 28) ?></div><p>No assignments yet.</p></div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($assignments as $a): $pct = $totalStudents ? round($a['submitted'] / $totalStudents * 100) : 0; ?>
                <div class="item c-<?= e($course['color']) ?>">
                    <span class="ico"><?= icon('file', 20) ?></span>
                    <span class="grow">
                        <b><a href="<?= url('lecturer/assignment_view.php?id=' . (int) $a['id']) ?>" style="color:inherit"><?= e($a['title']) ?></a></b>
                        <small>Due <?= e(fmt_datetime($a['due_date'])) ?> · <?= (int) $a['submitted'] ?> of <?= $totalStudents ?> submitted</small>
                        <div class="progress" style="margin-top:6px"><span style="width:<?= $pct ?>%"></span></div>
                    </span>
                    <div class="row-actions">
                        <a class="btn btn-soft btn-sm" href="<?= url('lecturer/assignment_view.php?id=' . (int) $a['id']) ?>">Analytics &amp; grading</a>
                        <a class="btn btn-soft btn-sm" href="<?= url('lecturer/assignment_form.php?id=' . (int) $a['id']) ?>"><?= icon('edit', 15) ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card mb">
    <div class="card-head"><h2>Lecturer</h2></div>
    <?php if ($lecturer): ?>
    <div class="table-wrap"><table>
        <tbody>
            <tr>
                <td><div class="person"><?= avatar_html($lecturer['name'], $lecturer['avatar'] ?? null, (int) $lecturer['id']) ?><div><b><?= e($lecturer['name']) ?><?= (int) $lecturer['id'] === (int) $me['id'] ? ' (you)' : '' ?></b><small><?= e($lecturer['email']) ?></small></div></div></td>
                <td class="right"><?= pill('primary', 'Lecturer') ?></td>
            </tr>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><h2>Students (<?= $totalStudents ?>)</h2><span class="muted small">Students join with the code <b><?= e($course['join_code']) ?></b></span></div>
    <?php if (!$students): ?>
        <div class="empty"><div class="em-ico"><?= icon('users', 28) ?></div><p>No students have joined yet. Share the join code with your class.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Enrolled</th><th>Submissions</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><div class="person"><?= avatar_html($s['name'], $s['avatar'] ?? null, (int) $s['id']) ?><div><b><?= e($s['name']) ?></b><small><?= e($s['email']) ?></small></div></div></td>
                <td class="small muted"><?= e(fmt_date($s['enrolled_at'])) ?></td>
                <td><?= (int) $s['done'] ?> of <?= count($assignments) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
