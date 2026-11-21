<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$cid    = (int) ($_GET['id'] ?? 0);
$course = qone('SELECT * FROM classes WHERE id = ?', [$cid]);
if (!$course) {
    flash('error', 'That class could not be found.');
    redirect('admin/courses.php');
}
$parent = qone('SELECT id, code, title FROM courses WHERE id = ?', [(int) $course['course_id']]);

if (is_post() && post('action') === 'remove_student') {
    csrf_verify();
    $sid = (int) ($_POST['student_id'] ?? 0);
    $stu = qone('SELECT name FROM users WHERE id = ?', [$sid]);
    if ($stu && qrun('DELETE FROM enrollments WHERE class_id = ? AND student_id = ?', [$cid, $sid])) {
        log_activity('REMOVE_STUDENT', 'Removed ' . $stu['name'] . ' from ' . $course['code']);
        notify($sid, 'Removed from class', 'You were removed from ' . $course['code'] . ' - ' . $course['title'] . ' by the administrator.', 'student/courses.php');
        flash('success', $stu['name'] . ' was removed from the class.');
    }
    redirect('admin/class_view.php?id=' . $cid);
}

$lecturer = qone('SELECT id, name, email, avatar, is_active FROM users WHERE id = ?', [(int) $course['lecturer_id']]);
$students = qall(
    'SELECT u.id, u.name, u.email, u.avatar, e.enrolled_at,
            (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.class_id = ? AND s.student_id = u.id) AS done
       FROM enrollments e JOIN users u ON u.id = e.student_id WHERE e.class_id = ? ORDER BY u.name',
    [$cid, $cid]
);
$assignments = qall(
    'SELECT a.id, a.title, a.due_date, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submitted
       FROM assignments a WHERE a.class_id = ? ORDER BY a.due_date DESC',
    [$cid]
);
$materials = (int) qval('SELECT COUNT(*) FROM materials WHERE class_id = ?', [$cid]);
$totalStudents = count($students);

$pageTitle = $course['code'] . ' ' . $course['title'];
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/courses.php') ?>">Courses</a> / <a href="<?= url('admin/course_view.php?id=' . (int) $course['course_id']) ?>"><?= e($parent['code'] ?? '') ?></a> / Class <?= e($course['class_name']) ?></div>
        <h1><?= e($course['title']) ?> · Class <?= e($course['class_name']) ?></h1>
        <p class="sub"><?= e($course['description']) ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-soft" href="<?= url('admin/class_form.php?id=' . $cid) ?>"><?= icon('edit', 17) ?> Edit class</a>
        <a class="btn btn-soft" href="<?= url('admin/attendance.php?course=' . $cid) ?>"><?= icon('clipboard', 17) ?> Attendance</a>
    </div>
</div>

<div class="card c-<?= e($course['color']) ?> mb" style="background:linear-gradient(135deg,var(--tint),#fff)">
    <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:center">
        <span class="code-badge" style="margin:0"><?= e($course['code']) ?></span>
        <div class="meta"><?= icon('clock', 17) ?> <?= $course['day_of_week'] ? e(day_name((int) $course['day_of_week'])) . ', ' . e(fmt_time($course['start_time'])) . ' – ' . e(fmt_time($course['end_time'])) : 'Schedule not set' ?></div>
        <?php if ($course['room']): ?><div class="meta"><?= icon('pin', 17) ?> <?= e($course['room']) ?></div><?php endif; ?>
        <div class="meta"><?= icon('users', 17) ?> <?= $totalStudents ?> student<?= $totalStudents === 1 ? '' : 's' ?></div>
        <div class="meta"><?= icon('file', 17) ?> <?= count($assignments) ?> assignment<?= count($assignments) === 1 ? '' : 's' ?> · <?= $materials ?> note<?= $materials === 1 ? '' : 's' ?></div>
        <div class="meta"><?= icon('key', 17) ?> Join code: <b style="color:var(--ink);letter-spacing:1px"><?= e($course['join_code']) ?></b></div>
    </div>
</div>

<div class="card mb">
    <div class="card-head"><h2>Lecturer</h2><a class="btn btn-soft btn-sm" href="<?= url('admin/course_form.php?id=' . (int) $course['course_id']) ?>">Change lecturer</a></div>
    <?php if ($lecturer): ?>
    <div class="person"><?= avatar_html($lecturer['name'], $lecturer['avatar'] ?? null, (int) $lecturer['id']) ?>
        <div><b><?= e($lecturer['name']) ?></b><small><?= e($lecturer['email']) ?></small></div>
        <?= $lecturer['is_active'] ? '' : pill('muted', 'Deactivated') ?></div>
    <?php endif; ?>
</div>

<div class="card mb">
    <div class="card-head"><h2>Students (<?= $totalStudents ?>)</h2><span class="muted small">Students join with the code <b><?= e($course['join_code']) ?></b></span></div>
    <?php if (!$students): ?>
        <div class="empty"><div class="em-ico"><?= icon('users', 28) ?></div><p>No students have joined this class yet.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Enrolled</th><th>Submissions</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><div class="person"><?= avatar_html($s['name'], $s['avatar'] ?? null, (int) $s['id']) ?><div><b><?= e($s['name']) ?></b><small><?= e($s['email']) ?></small></div></div></td>
                <td class="small muted"><?= e(fmt_date($s['enrolled_at'])) ?></td>
                <td><?= (int) $s['done'] ?> of <?= count($assignments) ?></td>
                <td><form method="post" class="inline-form right" data-confirm="Remove <?= e($s['name']) ?> from this class?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="remove_student"><input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Remove</button></form></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><h2>Assignments (<?= count($assignments) ?>)</h2></div>
    <?php if (!$assignments): ?>
        <div class="empty"><p>No assignments have been posted in this class.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Assignment</th><th>Due</th><th>Submitted</th><th style="width:200px">Progress</th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $a): $pct = $totalStudents ? min(100, round($a['submitted'] / $totalStudents * 100)) : 0; ?>
            <tr>
                <td><b><?= e($a['title']) ?></b></td>
                <td class="small nowrap"><?= e(fmt_datetime($a['due_date'])) ?></td>
                <td><?= (int) $a['submitted'] ?> of <?= $totalStudents ?></td>
                <td><div class="progress"><span style="width:<?= $pct ?>%"></span></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
