<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    // only the administrator can create classes
    flash('error', 'Only the administrator can create courses and classes. Please ask the administrator.');
    redirect('lecturer/courses.php');
}
$course = lecturer_course($id);

$form = [
    'description' => $course['description'] ?? '',
    'day_of_week' => $course['day_of_week'] ?? '',
    'start_time'  => $course['start_time'] ? substr($course['start_time'], 0, 5) : '',
    'end_time'    => $course['end_time'] ? substr($course['end_time'], 0, 5) : '',
    'room'        => $course['room'] ?? '',
    'color'       => $course['color'] ?? 'lavender',
    'join_code'   => $course['join_code'] ?? '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (array_keys($form) as $k) {
        $form[$k] = post($k);
    }
    $form['join_code'] = strtoupper(str_replace(' ', '', $form['join_code']));

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

    if (!$errors && qval('SELECT COUNT(*) FROM classes WHERE join_code = ? AND id <> ?', [$form['join_code'], $id])) $errors[] = 'That join code is already used by another class.';

    if (!$errors) {
        $day   = $anyTime ? (int) $form['day_of_week'] : null;
        $start = $anyTime ? $form['start_time'] . ':00' : null;
        $end   = $anyTime ? $form['end_time'] . ':00' : null;

        qrun('UPDATE classes SET description=?, join_code=?, day_of_week=?, start_time=?, end_time=?, room=?, color=? WHERE id=? AND lecturer_id=?',
            [$form['description'], $form['join_code'], $day, $start, $end, $form['room'], $form['color'], $id, $me['id']]);
        log_activity('UPDATE_CLASS', 'Updated class ' . $course['code'] . ' - ' . $course['title']);
        flash('success', 'Class updated.');
        redirect('lecturer/class_view.php?id=' . $id);
    }
}

$pageTitle = 'Edit Class';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/courses.php') ?>">My Courses</a> / <?= e($course['code']) ?> / Edit class</div>
        <h1>Edit Class <?= e($course['class_name']) ?></h1>
        <p class="sub"><?= e($course['code']) ?> · <?= e($course['title']) ?></p>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full"><label for="description">Description</label><textarea id="description" name="description" placeholder="What will students learn in this class?"><?= e($form['description']) ?></textarea></div>

            <div class="field"><label for="day_of_week">Weekly class day</label>
                <select id="day_of_week" name="day_of_week">
                    <option value="">Not set</option>
                    <?php for ($d = 1; $d <= 7; $d++): ?><option value="<?= $d ?>" <?= (string) $form['day_of_week'] === (string) $d ? 'selected' : '' ?>><?= e(day_name($d)) ?></option><?php endfor; ?>
                </select></div>
            <div class="field"><label for="room">Room / venue</label><input type="text" id="room" name="room" value="<?= e($form['room']) ?>" maxlength="60" placeholder="Lab 2"></div>
            <div class="field"><label for="start_time">Start time</label><input type="time" id="start_time" name="start_time" value="<?= e($form['start_time']) ?>"></div>
            <div class="field"><label for="end_time">End time</label><input type="time" id="end_time" name="end_time" value="<?= e($form['end_time']) ?>"></div>

            <div class="field"><label for="join_code">Join code</label><input type="text" id="join_code" name="join_code" value="<?= e($form['join_code']) ?>" maxlength="12" placeholder="Leave blank to generate">
                <span class="hint">Students can enroll with this code.</span></div>
            <div class="field"><span class="label">Class colour</span>
                <div class="swatches" style="margin-top:6px">
                    <?php foreach (course_colors() as $k => $label): ?>
                        <label title="<?= e($label) ?>"><input type="radio" name="color" value="<?= e($k) ?>" <?= $form['color'] === $k ? 'checked' : '' ?>><span class="sw-<?= e($k) ?>"></span></label>
                    <?php endforeach; ?>
                </div></div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Save changes</button>
            <a class="btn btn-ghost" href="<?= url('lecturer/class_view.php?id=' . $id) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
