<?php
/**
 * Enter a meeting (opens its Google Meet link). POST only (so a link can never join/start a meeting by accident).
 *  - Lecturer who owns the class: starts the meeting if it is not live yet, then enters.
 *  - Enrolled student: can enter only while the meeting is live; attendance is recorded.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$me = user();

if (!is_post() || !in_array($me['role'], ['lecturer', 'student'], true) || !meetings_ready()) {
    redirect(home_for($me['role']));
}
csrf_verify();

$back = $me['role'] === 'lecturer' ? 'lecturer/meetings.php' : 'student/meetings.php';
expire_old_meetings();
$m = meeting_for_user((int) ($_POST['id'] ?? 0), $me);
if (!$m) {
    http_response_code(404);
    exit('Meeting not found.');
}

if ($m['status'] === 'ended') {
    flash('info', 'That meeting has already ended.');
    redirect($back);
}

$meetLink = meeting_link($m);
if ($meetLink === '') {
    flash('error', $me['role'] === 'lecturer'
        ? 'Please add a Google Meet link to this meeting first.'
        : 'The lecturer has not added a Google Meet link yet. Please check again later.');
    redirect($me['role'] === 'lecturer' ? 'lecturer/meeting_form.php?id=' . (int) $m['id'] : $back);
}

if ($m['status'] === 'scheduled') {
    if ($me['role'] !== 'lecturer') {
        flash('info', 'The lecturer has not started this meeting yet. Please wait, the Join button appears when it goes live.');
        redirect($back);
    }
    qrun("UPDATE meetings SET status = 'live', started_at = ? WHERE id = ? AND status = 'scheduled'", [date('Y-m-d H:i:s'), $m['id']]);
    $m['status'] = 'live';
    log_activity('START_MEETING', 'Started online meeting "' . $m['title'] . '" for ' . $m['code']);
    foreach (enrolled_student_ids((int) $m['class_id']) as $sid) {
        notify($sid, 'Meeting is live: ' . $m['title'], $m['code'] . ' · tap to join now.', 'student/meetings.php');
    }
}

if ($me['role'] === 'student') {
    record_attendance((int) $m['id'], (int) $me['id']);
    log_activity('JOIN_MEETING', 'Joined online meeting "' . $m['title'] . '" (' . $m['code'] . ')');
}

// open the Google Meet room (the page that sent us here opened a new tab for it)
header('Location: ' . $meetLink);
exit;
