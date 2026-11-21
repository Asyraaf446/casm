<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();
if (!meetings_ready()) {
    flash('error', 'Please run database/update_add_meetings.sql first.');
    redirect('lecturer/meetings.php');
}

$id = (int) ($_GET['id'] ?? 0);
$meeting = null;
if ($id) {
    $meeting = meeting_for_user($id, $me);
    if (!$meeting || $meeting['status'] === 'ended') {
        flash('error', 'A meeting that has ended cannot be edited.');
        redirect('lecturer/meetings.php');
    }
}
$courses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);

$form = [
    'class_id'   => $meeting['class_id'] ?? ($_GET['class_id'] ?? ''),
    'title'       => $meeting['title'] ?? '',
    'description' => $meeting['description'] ?? '',
    'when'        => $meeting ? date('Y-m-d\TH:i', strtotime($meeting['scheduled_at'])) : date('Y-m-d\TH:00', time() + 3600),
    'custom_link' => $meeting['custom_link'] ?? '',
    'start_now'   => '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (['class_id', 'title', 'description', 'when', 'custom_link'] as $k) {
        $form[$k] = post($k);
    }
    $form['start_now'] = isset($_POST['start_now']) && !$id ? '1' : '';
    $course = qone('SELECT * FROM classes WHERE id = ? AND lecturer_id = ?', [(int) $form['class_id'], $me['id']]);

    if (!$course) $errors[] = 'Please choose one of your classes.';
    if ($form['title'] === '' || mb_strlen($form['title']) > 150) $errors[] = 'Please enter a meeting title (up to 150 characters).';
    $ts = DateTime::createFromFormat('Y-m-d\TH:i', $form['when']);
    if (!$ts || $ts->format('Y-m-d\TH:i') !== $form['when']) $errors[] = 'Please choose a valid date and time.';
    if ($form['custom_link'] === '') $errors[] = 'Please paste the Google Meet link (use the "Create Meet link" button to make one).';
    elseif (!valid_meeting_link($form['custom_link'])) $errors[] = 'That is not a Google Meet link. It should look like https://meet.google.com/abc-defg-hij';

    if (!$errors) {
        $custom = $form['custom_link'] !== '' ? $form['custom_link'] : null;
        $desc   = $form['description'] !== '' ? $form['description'] : null;
        $when   = $form['start_now'] ? date('Y-m-d H:i:s') : $ts->format('Y-m-d H:i:s');

        if ($id) {
            qrun("UPDATE meetings SET class_id=?, title=?, description=?, custom_link=?, scheduled_at=? WHERE id=? AND status <> 'ended'",
                [$course['id'], $form['title'], $desc, $custom, $when, $id]);
            log_activity('UPDATE_MEETING', 'Updated online meeting "' . $form['title'] . '" for ' . $course['code']);
            flash('success', 'Meeting updated.');
            redirect('lecturer/meeting_view.php?id=' . $id);
        }

        qrun('INSERT INTO meetings (class_id, title, description, room_code, custom_link, scheduled_at, status, started_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$course['id'], $form['title'], $desc, new_room_code($course['code']), $custom, $when,
             $form['start_now'] ? 'live' : 'scheduled', $form['start_now'] ? $when : null, $me['id'], date('Y-m-d H:i:s')]);
        $newId = last_id();
        log_activity('CREATE_MEETING', 'Scheduled online meeting "' . $form['title'] . '" for ' . $course['code'] . ' on ' . fmt_datetime($when));
        foreach (enrolled_student_ids((int) $course['id']) as $sid) {
            if ($form['start_now']) {
                notify($sid, 'Meeting is live: ' . $form['title'], $course['code'] . ' · tap to join now.', 'student/meetings.php');
            } else {
                notify($sid, 'Online meeting scheduled', $course['code'] . ' · ' . $form['title'] . ' on ' . fmt_datetime($when) . '.', 'student/meetings.php');
            }
        }
        flash('success', $form['start_now'] ? 'Meeting is live. Press Join to enter the room.' : 'Meeting scheduled and students were notified.');
        redirect('lecturer/meeting_view.php?id=' . $newId);
    }
}

$pageTitle = $id ? 'Edit Meeting' : 'Schedule Meeting';
$active = 'meetings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/meetings.php') ?>">Meetings</a> / <?= $id ? 'Edit' : 'New' ?></div>
        <h1><?= $id ? 'Edit meeting' : 'Schedule an online meeting' ?></h1>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if (!$courses): ?>
        <div class="empty"><p>You need a class first before you can hold a meeting. Please ask the administrator to assign one to you.</p></div>
    <?php else: ?>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <option value="">Choose a class</option>
                    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $form['class_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field"><label for="when">Date and time</label><input type="datetime-local" id="when" name="when" value="<?= e($form['when']) ?>" required></div>
            <div class="field full"><label for="title">Meeting title</label><input type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="150" placeholder="Revision class: Chapter 3" required></div>
            <div class="field full"><label for="description">Description (optional)</label><textarea id="description" name="description" placeholder="What will you cover? What should students prepare?"><?= e($form['description']) ?></textarea></div>
            <div class="field full"><label for="custom_link">Google Meet link</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <input type="text" id="custom_link" name="custom_link" value="<?= e($form['custom_link']) ?>" maxlength="255" placeholder="https://meet.google.com/abc-defg-hij" style="flex:1;min-width:220px" required>
                    <a class="btn btn-soft" href="https://meet.google.com/new" target="_blank" rel="noopener noreferrer"><?= icon('video', 18) ?> Create Meet link</a>
                </div>
                <span class="hint">Press "Create Meet link" (it opens Google Meet in a new tab, sign in with your Google account), then copy the link of the meeting and paste it here.</span></div>
            <?php if (!$id): ?>
            <div class="field full"><label style="display:flex;gap:10px;align-items:center;font-weight:700"><input type="checkbox" name="start_now" value="1" <?= $form['start_now'] ? 'checked' : '' ?>> Start right now (students are told it is live)</label></div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Schedule meeting' ?></button>
            <a class="btn btn-ghost" href="<?= url($id ? 'lecturer/meeting_view.php?id=' . $id : 'lecturer/meetings.php') ?>">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
