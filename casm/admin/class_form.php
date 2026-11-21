<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$id     = (int) ($_GET['id'] ?? 0);
$class  = null;
if ($id) {
    $class = qone('SELECT * FROM classes WHERE id = ?', [$id]);
    if (!$class) {
        flash('error', 'That class could not be found.');
        redirect('admin/courses.php');
    }
    $courseId = (int) $class['course_id'];
} else {
    $courseId = (int) ($_GET['course'] ?? 0);
}
$course = qone('SELECT c.*, u.name AS lecturer_name FROM courses c JOIN users u ON u.id = c.lecturer_id WHERE c.id = ?', [$courseId]);
if (!$course) {
    flash('error', 'Please choose a course first, then add a class inside it.');
    redirect('admin/courses.php');
}

$form = [
    'class_name'  => $class['class_name'] ?? next_class_name($courseId),
    'description' => $class['description'] ?? ($course['description'] ?? ''),
    'day_of_week' => $class['day_of_week'] ?? '',
    'start_time'  => !empty($class['start_time']) ? substr($class['start_time'], 0, 5) : '',
    'end_time'    => !empty($class['end_time']) ? substr($class['end_time'], 0, 5) : '',
    'room'        => $class['room'] ?? '',
    'color'       => $class['color'] ?? (qval('SELECT color FROM classes WHERE course_id = ? ORDER BY id LIMIT 1', [$courseId]) ?: 'lavender'),
    'join_code'   => $class['join_code'] ?? '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (array_keys($form) as $k) {
        $form[$k] = post($k);
    }
    $form['class_name'] = strtoupper(str_replace(' ', '', $form['class_name']));
    $form['join_code']  = strtoupper(str_replace(' ', '', $form['join_code']));

    if ($form['class_name'] === '' || strlen($form['class_name']) > 8 || !preg_match('/^[A-Z0-9]+$/', $form['class_name'])) {
        $errors[] = 'Please enter a class name such as A, B or C (letters or numbers, up to 8 characters).';
    }
    if (!array_key_exists($form['color'], course_colors())) $form['color'] = 'lavender';

    $anyTime = $form['day_of_week'] !== '' || $form['start_time'] !== '' || $form['end_time'] !== '';
    if ($anyTime) {
        if ($form['day_of_week'] === '' || $form['start_time'] === '' || $form['end_time'] === '') {
            $errors[] = 'To show this class on the timetable, set the day, start time and end time together.';
        } elseif ($form['end_time'] <= $form['start_time']) {
            $errors[] = 'The end time must be after the start time.';
        }
    }
    if ($form['join_code'] === '') $form['join_code'] = new_class_join_code();
    if (strlen($form['join_code']) > 12) $errors[] = 'The join code can be at most 12 characters.';

    if (!$errors && qval('SELECT COUNT(*) FROM classes WHERE course_id = ? AND class_name = ? AND id <> ?', [$courseId, $form['class_name'], $id])) {
        $errors[] = 'This course already has a Class ' . $form['class_name'] . '.';
    }
    if (!$errors && qval('SELECT COUNT(*) FROM classes WHERE join_code = ? AND id <> ?', [$form['join_code'], $id])) $errors[] = 'That join code is already used by another class.';

    if (!$errors) {
        $day   = $anyTime ? (int) $form['day_of_week'] : null;
        $start = $anyTime ? $form['start_time'] . ':00' : null;
        $end   = $anyTime ? $form['end_time'] . ':00' : null;
        $code  = $course['code'] . '-' . $form['class_name'];

        if ($id) {
            qrun('UPDATE classes SET class_name=?, code=?, description=?, join_code=?, day_of_week=?, start_time=?, end_time=?, room=?, color=? WHERE id=?',
                [$form['class_name'], $code, $form['description'], $form['join_code'], $day, $start, $end, $form['room'], $form['color'], $id]);
            log_activity('UPDATE_CLASS', 'Updated class ' . $code . ' - ' . $course['title']);
            flash('success', 'Class updated.');
            redirect('admin/class_view.php?id=' . $id);
        }

        qrun('INSERT INTO classes (course_id, class_name, code, title, description, lecturer_id, join_code, day_of_week, start_time, end_time, room, color, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$courseId, $form['class_name'], $code, $course['title'], $form['description'], $course['lecturer_id'], $form['join_code'], $day, $start, $end, $form['room'], $form['color'], date('Y-m-d H:i:s')]);
        $newId = last_id();
        log_activity('CREATE_CLASS', 'Created class ' . $code . ' - ' . $course['title'] . ' (lecturer: ' . $course['lecturer_name'] . ')');
        notify((int) $course['lecturer_id'], 'New class added', 'Class ' . $form['class_name'] . ' was added to ' . $course['code'] . ' - ' . $course['title'] . '.', 'lecturer/class_view.php?id=' . $newId);
        flash('success', 'Class created. Share the join code ' . $form['join_code'] . ' with the students.');
        redirect('admin/class_view.php?id=' . $newId);
    }
}

$pageTitle = $id ? 'Edit Class' : 'New Class';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/courses.php') ?>">Courses</a> / <a href="<?= url('admin/course_view.php?id=' . $courseId) ?>"><?= e($course['code']) ?></a> / <?= $id ? 'Edit class' : 'New class' ?></div>
        <h1><?= $id ? 'Edit Class ' . e($class['class_name']) : 'Add a class to ' . e($course['code']) ?></h1>
        <p class="sub"><?= e($course['title']) ?> · taught by <?= e($course['lecturer_name']) ?></p>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="class_name">Class name</label><input type="text" id="class_name" name="class_name" value="<?= e($form['class_name']) ?>" maxlength="8" placeholder="A" required>
                <span class="hint">Shown as Class <?= e($form['class_name'] ?: 'A') ?> (code <?= e($course['code']) ?>-<?= e($form['class_name'] ?: 'A') ?>).</span></div>
            <div class="field"><label for="room">Room / venue</label><input type="text" id="room" name="room" value="<?= e($form['room']) ?>" maxlength="60" placeholder="Lab 2"></div>
            <div class="field full"><label for="description">Description</label><textarea id="description" name="description" placeholder="Anything students should know about this class?"><?= e($form['description']) ?></textarea></div>

            <div class="field"><label for="day_of_week">Weekly class day</label>
                <select id="day_of_week" name="day_of_week">
                    <option value="">Not set</option>
                    <?php for ($d = 1; $d <= 7; $d++): ?><option value="<?= $d ?>" <?= (string) $form['day_of_week'] === (string) $d ? 'selected' : '' ?>><?= e(day_name($d)) ?></option><?php endfor; ?>
                </select></div>
            <div class="field"><label for="join_code">Join code</label><input type="text" id="join_code" name="join_code" value="<?= e($form['join_code']) ?>" maxlength="12" placeholder="Leave blank to generate">
                <span class="hint">Students can enroll in this class with this code.</span></div>
            <div class="field"><label for="start_time">Start time</label><input type="time" id="start_time" name="start_time" value="<?= e($form['start_time']) ?>"></div>
            <div class="field"><label for="end_time">End time</label><input type="time" id="end_time" name="end_time" value="<?= e($form['end_time']) ?>"></div>

            <div class="field full"><span class="label">Class colour</span>
                <div class="swatches" style="margin-top:6px">
                    <?php foreach (course_colors() as $k => $label): ?>
                        <label title="<?= e($label) ?>"><input type="radio" name="color" value="<?= e($k) ?>" <?= $form['color'] === $k ? 'checked' : '' ?>><span class="sw-<?= e($k) ?>"></span></label>
                    <?php endforeach; ?>
                </div></div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Create class' ?></button>
            <a class="btn btn-ghost" href="<?= url($id ? 'admin/class_view.php?id=' . $id : 'admin/course_view.php?id=' . $courseId) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
