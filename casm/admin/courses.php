<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $c = qone('SELECT * FROM courses WHERE id = ?', [(int) ($_POST['course_id'] ?? 0)]);
    if ($c) {
        // remove stored files of every class first (database rows cascade automatically)
        foreach (qall('SELECT id FROM classes WHERE course_id = ?', [$c['id']]) as $cl) delete_class_uploads((int) $cl['id']);
        qrun('DELETE FROM courses WHERE id = ?', [$c['id']]);
        log_activity('DELETE_COURSE', 'Deleted course ' . $c['code'] . ' - ' . $c['title']);
        flash('success', 'Course ' . $c['code'] . ' was deleted with all of its classes.');
    }
    redirect('admin/courses.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$where = '1 = 1';
$params = [];
if ($q !== '') {
    $where = '(c.code LIKE ? OR c.title LIKE ? OR u.name LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$courses = qall(
    "SELECT c.*, u.name AS lecturer_name,
            (SELECT COUNT(*) FROM classes k WHERE k.course_id = c.id) AS classes,
            (SELECT COUNT(*) FROM enrollments e JOIN classes k ON k.id = e.class_id WHERE k.course_id = c.id) AS students
       FROM courses c JOIN users u ON u.id = c.lecturer_id WHERE $where ORDER BY c.code",
    $params
);

// class letters per course (A, B, C ...) for the small badges
$letters = [];
foreach (qall('SELECT course_id, class_name FROM classes ORDER BY class_name') as $r) {
    $letters[(int) $r['course_id']][] = $r['class_name'];
}

$pageTitle = 'Courses';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Courses</h1><p class="sub">Each course has one lecturer and one or more classes (Class A, B, C ...).</p></div>
    <div class="actions"><a class="btn" href="<?= url('admin/course_form.php') ?>"><?= icon('plus', 18) ?> New course</a></div>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search code, title or lecturer…" style="min-width:260px">
    <button class="btn btn-soft" type="submit"><?= icon('search', 16) ?> Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= url('admin/courses.php') ?>">Reset</a><?php endif; ?>
</form>

<div class="card">
    <?php if (!$courses): ?>
        <div class="empty"><div class="em-ico"><?= icon('book', 28) ?></div><p>No courses found.</p><a class="btn" href="<?= url('admin/course_form.php') ?>">Create a course</a></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Course</th><th>Lecturer</th><th>Classes</th><th>Students</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td><b><a href="<?= url('admin/course_view.php?id=' . (int) $c['id']) ?>" style="color:inherit"><?= e($c['code']) ?></a></b><br><small class="muted"><?= e($c['title']) ?></small></td>
                <td><?= e($c['lecturer_name']) ?></td>
                <td><?= (int) $c['classes'] ?>
                    <?php foreach ($letters[(int) $c['id']] ?? [] as $l): ?> <?= pill('primary', 'Class ' . $l) ?><?php endforeach; ?></td>
                <td><?= (int) $c['students'] ?></td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-sm" href="<?= url('admin/course_view.php?id=' . (int) $c['id']) ?>">Manage</a>
                        <a class="btn btn-soft btn-sm" href="<?= url('admin/course_form.php?id=' . (int) $c['id']) ?>"><?= icon('edit', 15) ?> Edit</a>
                        <form method="post" class="inline-form" data-confirm="Delete course <?= e($c['code']) ?>? All of its classes, notes, assignments and submissions will be removed.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
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
