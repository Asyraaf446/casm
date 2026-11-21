<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$courseId = (int) ($_GET['id'] ?? 0);
$course   = qone('SELECT * FROM courses WHERE id = ?', [$courseId]);
if (!$course) {
    flash('error', 'That course could not be found.');
    redirect('admin/courses.php');
}

if (is_post() && post('action') === 'delete_class') {
    csrf_verify();
    $cl = qone('SELECT * FROM classes WHERE id = ? AND course_id = ?', [(int) ($_POST['class_id'] ?? 0), $courseId]);
    if ($cl) {
        delete_class_uploads((int) $cl['id']);
        qrun('DELETE FROM classes WHERE id = ?', [$cl['id']]);
        log_activity('DELETE_CLASS', 'Deleted class ' . $cl['code'] . ' - ' . $cl['title']);
        flash('success', 'Class ' . $cl['code'] . ' was deleted.');
    }
    redirect('admin/course_view.php?id=' . $courseId);
}

$lecturer = qone('SELECT id, name, email, avatar, is_active FROM users WHERE id = ?', [(int) $course['lecturer_id']]);
$classes = qall(
    'SELECT c.*,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id) AS students,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) AS assignments,
            (SELECT COUNT(*) FROM materials m WHERE m.class_id = c.id) AS materials
       FROM classes c WHERE c.course_id = ? ORDER BY c.class_name',
    [$courseId]
);
$totalStudents = array_sum(array_column($classes, 'students'));

$pageTitle = $course['code'] . ' ' . $course['title'];
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/courses.php') ?>">Courses</a> / <?= e($course['code']) ?></div>
        <h1><?= e($course['title']) ?></h1>
        <p class="sub"><?= e($course['description']) ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-soft" href="<?= url('admin/course_form.php?id=' . $courseId) ?>"><?= icon('edit', 17) ?> Edit course</a>
        <a class="btn" href="<?= url('admin/class_form.php?course=' . $courseId) ?>"><?= icon('plus', 18) ?> New class</a>
    </div>
</div>

<div class="card mb">
    <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:center">
        <span class="code-badge" style="margin:0"><?= e($course['code']) ?></span>
        <div class="meta"><?= icon('book', 17) ?> <?= count($classes) ?> class<?= count($classes) === 1 ? '' : 'es' ?></div>
        <div class="meta"><?= icon('users', 17) ?> <?= (int) $totalStudents ?> student<?= $totalStudents == 1 ? '' : 's' ?></div>
        <?php if ($lecturer): ?>
        <div class="person"><?= avatar_html($lecturer['name'], $lecturer['avatar'] ?? null, (int) $lecturer['id']) ?>
            <div><b><?= e($lecturer['name']) ?></b><small><?= e($lecturer['email']) ?></small></div>
            <?= $lecturer['is_active'] ? '' : pill('muted', 'Deactivated') ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Classes (<?= count($classes) ?>)</h2><a class="btn btn-soft btn-sm" href="<?= url('admin/course_form.php?id=' . $courseId) ?>">Change lecturer</a></div>
    <?php if (!$classes): ?>
        <div class="empty"><div class="em-ico"><?= icon('book', 28) ?></div><p>This course has no classes yet.</p><a class="btn" href="<?= url('admin/class_form.php?course=' . $courseId) ?>">Create Class A</a></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Class</th><th>Schedule</th><th>Students</th><th>Notes</th><th>Assignments</th><th>Join code</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
            <tr>
                <td><div class="c-<?= e($c['color']) ?>"><span class="dotc"></span> <b><a href="<?= url('admin/class_view.php?id=' . (int) $c['id']) ?>" style="color:inherit">Class <?= e($c['class_name']) ?></a></b></div><small class="muted"><?= e($c['code']) ?></small></td>
                <td class="small"><?= $c['day_of_week'] ? e(day_name((int) $c['day_of_week'])) . '<br><span class="muted">' . e(fmt_time($c['start_time'])) . ' – ' . e(fmt_time($c['end_time'])) . ($c['room'] ? ' · ' . e($c['room']) : '') . '</span>' : '<span class="muted">Not set</span>' ?></td>
                <td><?= (int) $c['students'] ?></td>
                <td><?= (int) $c['materials'] ?></td>
                <td><?= (int) $c['assignments'] ?></td>
                <td><code><?= e($c['join_code']) ?></code></td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-sm" href="<?= url('admin/class_view.php?id=' . (int) $c['id']) ?>">Manage</a>
                        <a class="btn btn-soft btn-sm" href="<?= url('admin/class_form.php?id=' . (int) $c['id']) ?>"><?= icon('edit', 15) ?> Edit</a>
                        <form method="post" class="inline-form" data-confirm="Delete <?= e($c['code']) ?>? All its notes, assignments and submissions will be removed.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 15) ?> Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
