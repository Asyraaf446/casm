<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
if (!attendance_ready()) redirect('admin/attendance.php');

expire_old_attendance();
$s = attendance_session_for_user((int) ($_GET['id'] ?? 0), user());
if (!$s) {
    flash('error', 'That attendance session could not be found.');
    redirect('admin/attendance.php');
}
$roll = attendance_roll((int) $s['id'], (int) $s['class_id']);
$count = attendance_counts($roll);
$byLabels = ['student' => 'ticked by student', 'lecturer' => 'set by lecturer', 'system' => 'class closed'];

$pageTitle = $s['title'];
$active = 'attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/attendance.php') ?>">Attendance</a> / <?= e($s['code']) ?></div>
        <h1><?= e($s['title']) ?> <?= attendance_state_pill($s['status']) ?></h1>
        <p class="sub"><?= e($s['code'] . ' · ' . $s['course_title']) ?> · <?= e($s['lecturer_name']) ?> · <?= e(fmt_date($s['session_date'])) ?><?= $s['note'] ? ' · ' . e($s['note']) : '' ?></p>
    </div>
    <div class="actions"><a class="btn btn-soft" href="<?= url('admin/attendance.php?course=' . (int) $s['class_id']) ?>">All records for <?= e($s['code']) ?></a></div>
</div>

<div class="grid cols-4 mb">
    <div class="stat att-g"><span class="ico"><?= icon('check', 24) ?></span><div><b><?= $count['present'] ?></b><span>Present</span></div></div>
    <div class="stat att-r"><span class="ico"><?= icon('x', 24) ?></span><div><b><?= $count['absent'] ?></b><span>Absent</span></div></div>
    <div class="stat att-a"><span class="ico"><?= icon('alert', 24) ?></span><div><b><?= $count['absent_permission'] ?></b><span>Absent with permission</span></div></div>
    <div class="stat sky"><span class="ico"><?= icon('users', 24) ?></span><div><b><?= $count['none'] ?></b><span>Not marked yet</span></div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Students (<?= count($roll) ?>)</h2></div>
    <?php if (!$roll): ?>
        <div class="empty"><p>No students are enrolled in this class.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Attendance</th><th>Reason</th><th>Recorded</th></tr></thead>
        <tbody>
        <?php foreach ($roll as $r): ?>
            <tr>
                <td><div class="person"><?= avatar_html($r['name'], $r['avatar'] ?? null, (int) $r['id']) ?><div><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></div></div></td>
                <td><?= attendance_pill($r['status']) ?></td>
                <td class="small"><?= e($r['reason'] ?? '') ?></td>
                <td class="small muted"><?= $r['status'] === null ? '-' : e(fmt_datetime($r['marked_at'])) . ' · ' . e($byLabels[$r['marked_by']] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
