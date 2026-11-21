<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$id     = (int) ($_GET['id'] ?? 0);
$course = null;
if ($id) {
    $course = qone('SELECT * FROM courses WHERE id = ?', [$id]);
    if (!$course) {
        flash('error', 'That course could not be found.');
        redirect('admin/courses.php');
    }
}

$lecturers = qall("SELECT id, name, email, is_active FROM users WHERE role = 'lecturer' ORDER BY name");

$form = [
    'code'        => $course['code'] ?? '',
    'title'       => $course['title'] ?? '',
    'description' => $course['description'] ?? '',
    'lecturer_id' => (string) ($course['lecturer_id'] ?? ''),
    'class_count' => '3',
    'color'       => 'lavender',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (array_keys($form) as $k) {
        $form[$k] = post($k);
    }
    $form['code'] = strtoupper(str_replace(' ', '', $form['code']));

    if ($form['code'] === '' || strlen($form['code']) > 20) $errors[] = 'Please enter a course code (up to 20 characters), e.g. CS201.';
    if ($form['title'] === '') $errors[] = 'Please enter the course title.';
    if (!array_key_exists($form['color'], course_colors())) $form['color'] = 'lavender';

    $lecturer = $form['lecturer_id'] !== ''
        ? qone("SELECT id, name FROM users WHERE id = ? AND role = 'lecturer'", [(int) $form['lecturer_id']])
        : null;
    if (!$lecturer) $errors[] = 'Please choose the lecturer who will teach this course.';

    $count = 0;
    if (!$id) {
        if (!ctype_digit($form['class_count']) || (int) $form['class_count'] > 10) {
            $errors[] = 'Number of classes must be between 0 and 10.';
        } else {
            $count = (int) $form['class_count'];
        }
    }

    if (!$errors && qval('SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?', [$form['code'], $id])) $errors[] = 'A course with that code already exists.';

    if (!$errors) {
        $lid = (int) $lecturer['id'];

        if ($id) {
            qrun('UPDATE courses SET code = ?, title = ?, description = ?, lecturer_id = ? WHERE id = ?',
                [$form['code'], $form['title'], $form['description'], $lid, $id]);
            sync_course_classes($id);          // classes follow the new code, title and lecturer
            log_activity('UPDATE_COURSE', 'Updated course ' . $form['code'] . ' - ' . $form['title']);
            if ($lid !== (int) $course['lecturer_id']) {
                log_activity('ASSIGN_LECTURER', 'Assigned ' . $lecturer['name'] . ' to ' . $form['code']);
                notify($lid, 'New course assigned', 'You are now the lecturer of ' . $form['code'] . ' - ' . $form['title'] . '.', 'lecturer/courses.php');
            }
            flash('success', 'Course updated.');
            redirect('admin/course_view.php?id=' . $id);
        }

        qrun('INSERT INTO courses (code, title, description, lecturer_id, created_at) VALUES (?,?,?,?,?)',
            [$form['code'], $form['title'], $form['description'], $lid, date('Y-m-d H:i:s')]);
        $newId = last_id();
        for ($i = 1; $i <= $count; $i++) {
            $name = class_letter($i);
            qrun('INSERT INTO classes (course_id, class_name, code, title, description, lecturer_id, join_code, color, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$newId, $name, $form['code'] . '-' . $name, $form['title'], $form['description'], $lid, new_class_join_code(), $form['color'], date('Y-m-d H:i:s')]);
        }
        log_activity('CREATE_COURSE', 'Created course ' . $form['code'] . ' - ' . $form['title'] . ' (lecturer: ' . $lecturer['name'] . ', ' . $count . ' classes)');
        notify($lid, 'New course assigned', 'You are now the lecturer of ' . $form['code'] . ' - ' . $form['title'] . '.', 'lecturer/courses.php');
        flash('success', 'Course created' . ($count ? ' with ' . $count . ' class' . ($count === 1 ? '' : 'es') . '. Set the schedule of each class next.' : '. Add its classes next.'));
        redirect('admin/course_view.php?id=' . $newId);
    }
}

$pageTitle = $id ? 'Edit Course' : 'New Course';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/courses.php') ?>">Courses</a> / <?= $id ? 'Edit' : 'New' ?></div>
        <h1><?= $id ? 'Edit course' : 'Create a new course' ?></h1>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if (!$lecturers): ?>
        <div class="empty"><div class="em-ico"><?= icon('user', 28) ?></div>
            <p>There are no lecturers yet. Create a lecturer account first, then you can assign a course to them.</p>
            <a class="btn" href="<?= url('admin/user_form.php') ?>">Add a lecturer</a></div>
    <?php else: ?>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="code">Course code</label><input type="text" id="code" name="code" value="<?= e($form['code']) ?>" maxlength="20" placeholder="CS201" required></div>
            <div class="field"><label for="title">Course title</label><input type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="150" placeholder="Database Systems" required></div>
            <div class="field full"><label for="lecturer_id">Lecturer</label>
                <select id="lecturer_id" name="lecturer_id" required data-searchable data-search-placeholder="Type a name or email to search lecturers…" data-search-empty="No lecturer found">
                    <option value="">Choose a lecturer…</option>
                    <?php foreach ($lecturers as $l): ?>
                        <option value="<?= (int) $l['id'] ?>" <?= (string) $form['lecturer_id'] === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?> (<?= e($l['email']) ?>)<?= $l['is_active'] ? '' : ' - deactivated' ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">This lecturer teaches every class inside the course.</span></div>
            <div class="field full"><label for="description">Description</label><textarea id="description" name="description" placeholder="What will students learn in this course?"><?= e($form['description']) ?></textarea></div>

            <?php if (!$id): ?>
            <div class="field"><label for="class_count">Classes to create now</label><input type="number" id="class_count" name="class_count" value="<?= e($form['class_count']) ?>" min="0" max="10" required>
                <span class="hint">3 creates Class A, Class B and Class C. You can add or remove classes later.</span></div>
            <div class="field"><span class="label">Colour of the classes</span>
                <div class="swatches" style="margin-top:6px">
                    <?php foreach (course_colors() as $k => $label): ?>
                        <label title="<?= e($label) ?>"><input type="radio" name="color" value="<?= e($k) ?>" <?= $form['color'] === $k ? 'checked' : '' ?>><span class="sw-<?= e($k) ?>"></span></label>
                    <?php endforeach; ?>
                </div></div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Create course' ?></button>
            <a class="btn btn-ghost" href="<?= url($id ? 'admin/course_view.php?id=' . $id : 'admin/courses.php') ?>">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
