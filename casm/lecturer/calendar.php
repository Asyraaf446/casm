<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'add_event') {
        $course = qone('SELECT * FROM classes WHERE id = ? AND lecturer_id = ?', [(int) ($_POST['class_id'] ?? 0), $me['id']]);
        $title  = post('title');
        $type   = post('event_type');
        $date   = post('event_date');
        $time   = post('start_time');

        if (!$course || $title === '' || !in_array($type, ['assignment', 'quiz', 'task', 'exam', 'other'], true)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time))) {
            flash('error', 'Please fill in the class, a title and a valid date.');
        } else {
            qrun('INSERT INTO events (class_id, title, event_type, event_date, start_time, description, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [$course['id'], $title, $type, $date, $time !== '' ? $time . ':00' : null, post('description') ?: null, $me['id'], date('Y-m-d H:i:s')]);
            log_activity('CREATE_EVENT', 'Scheduled ' . $type . ' "' . $title . '" for ' . $course['code'] . ' on ' . fmt_date($date));
            foreach (enrolled_student_ids((int) $course['id']) as $sid) {
                notify($sid, ucfirst($type) . ' scheduled: ' . $title, $course['code'] . ' on ' . fmt_date($date) . ($time !== '' ? ' at ' . fmt_time($time) : '') . '.', 'student/calendar.php');
            }
            flash('success', 'Added to the calendar and students were notified.');
        }
        redirect('lecturer/calendar.php');
    }

    if ($action === 'delete_event') {
        $ev = qone('SELECT ev.* FROM events ev JOIN classes c ON c.id = ev.class_id WHERE ev.id = ? AND c.lecturer_id = ?', [(int) ($_POST['event_id'] ?? 0), $me['id']]);
        if ($ev) {
            qrun('DELETE FROM events WHERE id = ?', [$ev['id']]);
            log_activity('DELETE_EVENT', 'Deleted event "' . $ev['title'] . '"');
            flash('success', 'Event deleted.');
        }
        redirect('lecturer/calendar.php');
    }
}

$calEvents  = calendar_events($me);
$calCanAdd  = true;
$calCourses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);

$pageTitle = 'Calendar';
$active = 'calendar';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Calendar</h1></div>
</div>
<?php require __DIR__ . '/../includes/calendar_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
