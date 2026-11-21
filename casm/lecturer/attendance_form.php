<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();
if (!attendance_ready()) {
    flash('error', 'Please run database/update_add_attendance.sql first.');
    redirect('lecturer/attendance.php');
}

$id = (int) ($_GET['id'] ?? 0);
$session = null;
if ($id) {
    $session = attendance_session_for_user($id, $me);
    if (!$session) {
        flash('error', 'That attendance could not be found.');
        redirect('lecturer/attendance.php');
    }
}
$courses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);

$form = [
    'class_id'    => $session['class_id'] ?? ($_GET['class_id'] ?? ''),
    'title'        => $session['title'] ?? '',
    'session_date' => $session['session_date'] ?? date('Y-m-d'),
    'note'         => $session['note'] ?? '',
    'open_now'     => $session ? '' : '1',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (['class_id', 'title', 'session_date', 'note'] as $k) {
        $form[$k] = post($k);
    }
    $form['open_now'] = isset($_POST['open_now']) && !$id ? '1' : '';
    if ($id) {
        $form['class_id'] = (string) $session['class_id'];   // the class cannot be changed after creation
    }
    $course = qone('SELECT * FROM classes WHERE id = ? AND lecturer_id = ?', [(int) $form['class_id'], $me['id']]);

    if (!$course) $errors[] = 'Please choose one of your classes.';
    if ($form['title'] === '' || mb_strlen($form['title']) > 150) $errors[] = 'Please enter a title (up to 150 characters), for example "Week 5 lecture".';
    $d = DateTime::createFromFormat('Y-m-d', $form['session_date']);
    if (!$d || $d->format('Y-m-d') !== $form['session_date']) $errors[] = 'Please choose a valid date.';
    if (mb_strlen($form['note']) > 255) $errors[] = 'The note can be at most 255 characters.';

    if (!$errors) {
        $note = $form['note'] !== '' ? $form['note'] : null;
        if ($id) {
            qrun('UPDATE attendance_sessions SET title = ?, session_date = ?, note = ? WHERE id = ?', [$form['title'], $form['session_date'], $note, $id]);
            log_activity('UPDATE_ATTENDANCE', 'Updated attendance "' . $form['title'] . '" for ' . $course['code']);
            flash('success', 'Attendance updated.');
            redirect('lecturer/attendance_view.php?id=' . $id);
        }

        $now = date('Y-m-d H:i:s');
        $open = $form['open_now'] === '1';
        qrun(
            'INSERT INTO attendance_sessions (class_id, title, session_date, note, status, opened_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$course['id'], $form['title'], $form['session_date'], $note, $open ? 'open' : 'pending', $open ? $now : null, $me['id'], $now]
        );
        $newId = last_id();
        log_activity('CREATE_ATTENDANCE', 'Created attendance "' . $form['title'] . '" for ' . $course['code'] . ' (' . fmt_date($form['session_date']) . ')');
        if ($open) {
            foreach (enrolled_student_ids((int) $course['id']) as $sid) {
                notify($sid, 'Attendance is open: ' . $form['title'], $course['code'] . ' · tap to tick your attendance.', 'student/attendance.php');
            }
        }
        flash('success', $open ? 'Attendance is open and students were notified. They can tick it now.' : 'Attendance created. Press "Open for students" when you are ready.');
        redirect('lecturer/attendance_view.php?id=' . $newId);
    }
}

$pageTitle = $id ? 'Edit Attendance' : 'New Attendance';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/attendance.php') ?>">Attendance</a> / <?= $id ? 'Edit' : 'New' ?></div>
        <h1><?= $id ? 'Edit attendance' : 'Create attendance for students to tick' ?></h1>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if (!$courses): ?>
        <div class="empty"><p>You need a class first. Please ask the administrator to assign one to you.</p></div>
    <?php else: ?>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="class_id">Class</label>
                <select id="class_id" name="class_id" <?= $id ? 'disabled' : 'required' ?>>
                    <option value="">Choose a class</option>
                    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $form['class_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                </select>
                <?php if ($id): ?><span class="hint">The class cannot be changed after the attendance is created.</span><?php endif; ?></div>
            <div class="field"><label for="session_date">Class date</label><input type="date" id="session_date" name="session_date" value="<?= e($form['session_date']) ?>" required></div>
            <div class="field full"><label for="title">Title</label><input type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="150" placeholder="Week 5 lecture" required></div>
            <div class="field full"><label for="note">Note for students (optional)</label><input type="text" id="note" name="note" value="<?= e($form['note']) ?>" maxlength="255" placeholder="Tick before the class ends"></div>
            <?php if (!$id): ?>
            <div class="field full"><label style="display:flex;gap:10px;align-items:center;font-weight:700"><input type="checkbox" name="open_now" value="1" <?= $form['open_now'] ? 'checked' : '' ?>> Open for students right now (they are notified and can tick Present)</label>
                <span class="hint">If you leave this unticked, students cannot see it until you press "Open for students". An open attendance closes automatically after 24 hours.</span></div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Create attendance' ?></button>
            <a class="btn btn-ghost" href="<?= url($id ? 'lecturer/attendance_view.php?id=' . $id : 'lecturer/attendance.php') ?>">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
