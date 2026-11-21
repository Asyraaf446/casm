<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me = user();

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'enroll') {
        // by join code (typed) or by class id (from search results)
        $code = strtoupper(str_replace(' ', '', post('join_code')));
        $course = $code !== ''
            ? qone('SELECT * FROM classes WHERE join_code = ?', [$code])
            : qone('SELECT * FROM classes WHERE id = ?', [(int) ($_POST['class_id'] ?? 0)]);

        if (!$course) {
            flash('error', 'No class matches that join code. Please check it with your lecturer.');
        } elseif (qval('SELECT COUNT(*) FROM enrollments WHERE class_id = ? AND student_id = ?', [$course['id'], $me['id']])) {
            flash('info', 'You are already enrolled in ' . $course['code'] . '.');
        } elseif ($other = qone('SELECT c.code FROM enrollments e JOIN classes c ON c.id = e.class_id
                                  WHERE e.student_id = ? AND c.course_id = ? AND c.id <> ?', [$me['id'], $course['course_id'], $course['id']])) {
            flash('error', 'You are already in ' . $other['code'] . ' for this course. A student joins only one class per course. Ask the administrator if you need to move.');
        } else {
            qrun('INSERT INTO enrollments (class_id, student_id, enrolled_at) VALUES (?,?,?)', [$course['id'], $me['id'], date('Y-m-d H:i:s')]);
            log_activity('ENROLL', 'Enrolled in ' . $course['code'] . ' - ' . $course['title']);
            notify((int) $course['lecturer_id'], 'New student', $me['name'] . ' joined ' . $course['code'] . '.', 'lecturer/class_view.php?id=' . $course['id'] . '&tab=students');
            flash('success', 'You joined ' . $course['code'] . ' · ' . $course['title'] . '.');
            redirect('student/class_view.php?id=' . $course['id']);
        }
        redirect('student/courses.php');
    }

    if ($action === 'leave') {
        $course = student_course((int) ($_POST['class_id'] ?? 0));
        qrun('DELETE FROM enrollments WHERE class_id = ? AND student_id = ?', [$course['id'], $me['id']]);
        log_activity('LEAVE_COURSE', 'Left ' . $course['code']);
        flash('success', 'You left ' . $course['code'] . '.');
        redirect('student/courses.php');
    }
}

$courses = qall(
    'SELECT c.*, u.name AS lecturer_name,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) AS assignments,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?)) AS todo
       FROM classes c
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
       JOIN users u ON u.id = c.lecturer_id
      ORDER BY c.code',
    [$me['id'], $me['id']]
);

$pageTitle = 'My Courses';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>My Courses</h1><p class="sub">The class you joined in each course.</p></div>
    <form method="post" class="actions">
        <?= csrf_field() ?><input type="hidden" name="action" value="enroll">
        <input type="text" name="join_code" placeholder="Enter join code" maxlength="12" style="width:170px;text-transform:uppercase" required>
        <button class="btn" type="submit"><?= icon('plus', 18) ?> Join class</button>
    </form>
</div>

<?php if (!$courses): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('book', 28) ?></div>
        <p>You have not joined any course yet. Ask your lecturer for the join code of your class, or <a href="<?= url('search.php?type=courses') ?>">browse the courses</a>.</p></div></div>
<?php else: ?>
<div class="grid cols-3">
    <?php foreach ($courses as $c): ?>
        <div class="card course-card c-<?= e($c['color']) ?>">
            <div class="course-top">
                <span class="code-badge"><?= e($c['code']) ?></span>
                <h3><a href="<?= url('student/class_view.php?id=' . (int) $c['id']) ?>"><?= e($c['title']) ?></a></h3>
                <small class="muted">Class <?= e($c['class_name']) ?> · <?= e($c['lecturer_name']) ?></small>
            </div>
            <div class="course-body">
                <div class="meta"><?= icon('clock', 16) ?> <?= $c['day_of_week'] ? e(day_name((int) $c['day_of_week'])) . ', ' . e(fmt_time($c['start_time'])) . ' – ' . e(fmt_time($c['end_time'])) : 'Schedule not set' ?></div>
                <?php if ($c['room']): ?><div class="meta"><?= icon('pin', 16) ?> <?= e($c['room']) ?></div><?php endif; ?>
                <div class="meta"><?= icon('file', 16) ?> <?= (int) $c['todo'] ?> of <?= (int) $c['assignments'] ?> assignment<?= $c['assignments'] == 1 ? '' : 's' ?> still to do</div>
            </div>
            <div class="course-foot">
                <a class="btn btn-sm" href="<?= url('student/class_view.php?id=' . (int) $c['id']) ?>">Open class</a>
                <form method="post" class="inline-form" data-confirm="Leave <?= e($c['code']) ?>? Your submissions for this class stay saved.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="leave"><input type="hidden" name="class_id" value="<?= (int) $c['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">Leave</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
