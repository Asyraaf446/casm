<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$id = (int) ($_GET['id'] ?? 0);
$a  = null;
if ($id) {
    $a = qone('SELECT a.* FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.id = ? AND c.lecturer_id = ?', [$id, $me['id']]);
    if (!$a) {
        flash('error', 'That assignment could not be found.');
        redirect('lecturer/assignments.php');
    }
}

$courses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);
if (!$courses) {
    flash('info', 'You have no classes yet. Ask the administrator to assign a class to you first.');
    redirect('lecturer/courses.php');
}

$form = [
    'class_id'   => (int) ($a['class_id'] ?? ($_GET['class_id'] ?? $courses[0]['id'])),
    'title'       => $a['title'] ?? '',
    'description' => $a['description'] ?? '',
    'due_date'    => $a ? date('Y-m-d\TH:i', strtotime($a['due_date'])) : date('Y-m-d\T23:59', strtotime('+7 days')),
    'max_marks'   => $a['max_marks'] ?? 100,
];
$errors    = [];
$conflicts = [];

if (is_post()) {
    csrf_verify();
    $form['class_id']   = (int) ($_POST['class_id'] ?? 0);
    $form['title']       = post('title');
    $form['description'] = post('description');
    $form['due_date']    = post('due_date');
    $form['max_marks']   = (int) ($_POST['max_marks'] ?? 0);

    $course = qone('SELECT * FROM classes WHERE id = ? AND lecturer_id = ?', [$form['class_id'], $me['id']]);
    if (!$course) $errors[] = 'Please choose one of your classes.';
    if ($form['title'] === '') $errors[] = 'Please enter the assignment title.';
    $ts = strtotime($form['due_date']);
    if (!$ts) $errors[] = 'Please choose a valid due date and time.';
    if ($form['max_marks'] < 1 || $form['max_marks'] > 1000) $errors[] = 'Maximum marks must be between 1 and 1000.';

    if (!$errors) {
        $dueSql    = date('Y-m-d H:i:s', $ts);
        $conflicts = workload_conflicts((int) $course['id'], substr($dueSql, 0, 10), $id);

        // Smart Workload Balancer: warn first, save only after the lecturer confirms
        if (!$conflicts || !empty($_POST['confirm_workload'])) {
            if ($id) {
                $dateChanged = $a['due_date'] !== $dueSql;
                qrun('UPDATE assignments SET class_id=?, title=?, description=?, due_date=?, max_marks=? WHERE id=?',
                    [$course['id'], $form['title'], $form['description'], $dueSql, $form['max_marks'], $id]);
                log_activity('UPDATE_ASSIGNMENT', 'Updated "' . $form['title'] . '" in ' . $course['code']);
                if ($dateChanged) {
                    foreach (enrolled_student_ids((int) $course['id']) as $sid) {
                        notify($sid, 'Deadline changed', '"' . $form['title'] . '" (' . $course['code'] . ') is now due ' . fmt_datetime($dueSql) . '.', 'student/assignment_view.php?id=' . $id);
                    }
                }
                flash('success', 'Assignment updated.');
                redirect('lecturer/assignment_view.php?id=' . $id);
            }

            qrun('INSERT INTO assignments (class_id, title, description, due_date, max_marks, created_by, created_at) VALUES (?,?,?,?,?,?,?)',
                [$course['id'], $form['title'], $form['description'], $dueSql, $form['max_marks'], $me['id'], date('Y-m-d H:i:s')]);
            $newId = last_id();
            log_activity('CREATE_ASSIGNMENT', 'Created "' . $form['title'] . '" in ' . $course['code']);
            foreach (enrolled_student_ids((int) $course['id']) as $sid) {
                notify($sid, 'New assignment in ' . $course['code'], '"' . $form['title'] . '" is due ' . fmt_datetime($dueSql) . '.', 'student/assignment_view.php?id=' . $newId);
            }
            flash('success', 'Assignment created and students were notified.');
            redirect('lecturer/assignment_view.php?id=' . $newId);
        }
    }
}

$pageTitle = $id ? 'Edit Assignment' : 'New Assignment';
$active = 'assignments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/assignments.php') ?>">Assignments</a> / <?= $id ? 'Edit' : 'New' ?></div>
        <h1><?= $id ? 'Edit assignment' : 'Create an assignment' ?></h1>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($conflicts): ?>
        <div class="notice">
            <?= icon('alert', 22) ?>
            <div>
                <b>Warning: High student workload detected on this date. Suggest moving deadline.</b>
                <span><?= count($conflicts) ?> student<?= count($conflicts) > 1 ? 's' : '' ?> already ha<?= count($conflicts) > 1 ? 've' : 's' ?> 2 or more assignments due on <?= e(fmt_date($form['due_date'])) ?>:</span>
                <ul>
                    <?php foreach (array_slice($conflicts, 0, 6) as $c): ?><li><?= e($c['name']) ?> (<?= (int) $c['total'] ?> other assignments)</li><?php endforeach; ?>
                    <?php if (count($conflicts) > 6): ?><li>…and <?= count($conflicts) - 6 ?> more</li><?php endif; ?>
                </ul>
                <div class="btns">
                    <button class="btn btn-sm" type="submit" form="assignForm" name="confirm_workload" value="1">Keep this date anyway</button>
                    <a class="btn btn-ghost btn-sm" href="#due_date" onclick="document.getElementById('due_date').focus();return false;">Change the date</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" id="assignForm">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $form['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field"><label for="title">Title</label><input type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="150" placeholder="ER Diagram Design" required></div>
            <div class="field full"><label for="description">Instructions</label><textarea id="description" name="description" placeholder="What should students do? Mention file format, requirements and marking tips."><?= e($form['description']) ?></textarea></div>
            <div class="field"><label for="due_date">Due date &amp; time</label><input type="datetime-local" id="due_date" name="due_date" value="<?= e($form['due_date']) ?>" required></div>
            <div class="field"><label for="max_marks">Maximum marks</label><input type="number" id="max_marks" name="max_marks" value="<?= (int) $form['max_marks'] ?>" min="1" max="1000" required></div>
        </div>

        <div id="workloadBox" style="margin-top:16px" data-endpoint="<?= url('lecturer/api_workload.php') ?>" data-aid="<?= $id ?>"></div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Create assignment' ?></button>
            <a class="btn btn-ghost" href="<?= url($id ? 'lecturer/assignment_view.php?id=' . $id : 'lecturer/assignments.php') ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
