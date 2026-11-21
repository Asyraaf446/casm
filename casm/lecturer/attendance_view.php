<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();
if (!attendance_ready()) redirect('lecturer/attendance.php');

$id = (int) ($_GET['id'] ?? 0);
expire_old_attendance();
$s = attendance_session_for_user($id, $me);
if (!$s) {
    flash('error', 'That attendance could not be found.');
    redirect('lecturer/attendance.php');
}
$cid = (int) $s['class_id'];

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $label = '"' . $s['title'] . '" (' . $s['code'] . ')';

    if ($action === 'open' && $s['status'] !== 'open') {
        $first = $s['status'] === 'pending';
        qrun("UPDATE attendance_sessions SET status = 'open', opened_at = ?, closed_at = NULL WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
        log_activity($first ? 'OPEN_ATTENDANCE' : 'REOPEN_ATTENDANCE', ($first ? 'Opened' : 'Reopened') . ' attendance ' . $label);
        if ($first) {
            foreach (enrolled_student_ids($cid) as $sid) {
                notify($sid, 'Attendance is open: ' . $s['title'], $s['code'] . ' · tap to tick your attendance.', 'student/attendance.php');
            }
        }
        flash('success', $first ? 'Attendance is open. Students were notified and can tick Present now.' : 'Attendance reopened for 24 hours.');

    } elseif ($action === 'close' && $s['status'] === 'open') {
        close_attendance_session($id);
        log_activity('CLOSE_ATTENDANCE', 'Closed attendance ' . $label);
        flash('success', 'Attendance closed. Students who did not tick were recorded as Absent. You can still change any of them below.');

    } elseif ($action === 'delete') {
        qrun('DELETE FROM attendance_sessions WHERE id = ?', [$id]);   // records are removed with it (ON DELETE CASCADE)
        log_activity('DELETE_ATTENDANCE', 'Deleted attendance ' . $label);
        flash('success', 'Attendance deleted.');
        redirect('lecturer/attendance.php');

    } elseif ($action === 'save' || $action === 'all_present') {
        $roll = attendance_roll($id, $cid);
        $byId = [];
        foreach ($roll as $r) $byId[(int) $r['id']] = $r;
        $valid = attendance_statuses();
        $changed = 0;
        $now = date('Y-m-d H:i:s');

        $wanted = [];   // student id => [status, reason]
        if ($action === 'all_present') {
            foreach ($byId as $sid => $r) {
                $wanted[$sid] = ['present', null];
            }
        } else {
            $st = is_array($_POST['status'] ?? null) ? $_POST['status'] : [];
            $rs = is_array($_POST['reason'] ?? null) ? $_POST['reason'] : [];
            foreach ($st as $sid => $val) {
                $sid = (int) $sid;
                if (!isset($byId[$sid]) || !is_string($val) || !isset($valid[$val])) continue;
                $reason = is_string($rs[$sid] ?? null) ? mb_substr(trim($rs[$sid]), 0, 255) : '';
                $wanted[$sid] = [$val, ($val !== 'present' && $reason !== '') ? $reason : null];
            }
        }

        foreach ($wanted as $sid => [$status, $reason]) {
            $old = $byId[$sid];
            if ($old['status'] === $status && (string) $old['reason'] === (string) $reason) continue;   // nothing changed
            qrun(
                'INSERT INTO attendance_records (session_id, student_id, status, reason, marked_by, marked_at) VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), reason = VALUES(reason), marked_by = VALUES(marked_by), marked_at = VALUES(marked_at)',
                [$id, $sid, $status, $reason, 'lecturer', $now]
            );
            $changed++;
        }
        if ($changed) {
            log_activity('UPDATE_ATTENDANCE', 'Updated ' . $changed . ' attendance record' . ($changed === 1 ? '' : 's') . ' for ' . $label);
            flash('success', $changed . ' record' . ($changed === 1 ? '' : 's') . ' saved.');
        } else {
            flash('info', 'Nothing was changed.');
        }
    }
    redirect('lecturer/attendance_view.php?id=' . $id);
}

$s = attendance_session_for_user($id, $me);
$roll = attendance_roll($id, $cid);
$count = attendance_counts($roll);

$pageTitle = $s['title'];
$active = 'attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/attendance.php') ?>">Attendance</a> / <?= e($s['code']) ?></div>
        <h1><?= e($s['title']) ?> <?= attendance_state_pill($s['status']) ?></h1>
        <p class="sub"><?= e($s['code'] . ' · ' . $s['course_title']) ?> · <?= e(fmt_date($s['session_date'])) ?><?= $s['note'] ? ' · ' . e($s['note']) : '' ?></p>
    </div>
    <div class="actions">
        <?php if ($s['status'] !== 'open'): ?>
        <form class="inline-form" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="open">
            <button class="btn" type="submit"><?= icon('check', 18) ?> <?= $s['status'] === 'pending' ? 'Open for students' : 'Reopen' ?></button>
        </form>
        <?php else: ?>
        <form class="inline-form" method="post" onsubmit="return confirm('Close attendance? Students who have not ticked will be recorded as Absent.');">
            <?= csrf_field() ?><input type="hidden" name="action" value="close">
            <button class="btn btn-danger" type="submit">Close attendance</button>
        </form>
        <?php endif; ?>
        <a class="btn btn-soft" href="<?= url('lecturer/attendance_form.php?id=' . $id) ?>"><?= icon('edit', 17) ?> Edit</a>
        <form class="inline-form" method="post" data-confirm="Delete this attendance and all its records?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 15) ?></button>
        </form>
    </div>
</div>

<div class="grid cols-4 mb">
    <div class="stat att-g"><span class="ico"><?= icon('check', 24) ?></span><div><b><?= $count['present'] ?></b><span>Present</span></div></div>
    <div class="stat att-r"><span class="ico"><?= icon('x', 24) ?></span><div><b><?= $count['absent'] ?></b><span>Absent</span></div></div>
    <div class="stat att-a"><span class="ico"><?= icon('alert', 24) ?></span><div><b><?= $count['absent_permission'] ?></b><span>Absent with permission</span></div></div>
    <div class="stat sky"><span class="ico"><?= icon('users', 24) ?></span><div><b><?= $count['none'] ?></b><span>Not marked yet</span></div></div>
</div>

<?php if ($s['status'] === 'pending'): ?>
    <div class="notice"><?= icon('help', 20) ?><div>Students cannot see this attendance yet. Press "Open for students" so they can tick Present. You can also mark students yourself below at any time.</div></div>
<?php elseif ($s['status'] === 'open'): ?>
    <div class="notice good"><?= icon('check', 20) ?><div>Attendance is open. Students tick Present from their Attendance page. It closes automatically 24 hours after it was opened (<?= e(fmt_datetime($s['opened_at'])) ?>).</div></div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><h2>Students (<?= count($roll) ?>)</h2></div>
    <?php if (!$roll): ?>
        <div class="empty"><div class="em-ico"><?= icon('users', 28) ?></div><p>No students are enrolled in this class yet.</p></div>
    <?php else: ?>
    <form method="post" id="markForm">
        <?= csrf_field() ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Student</th><th>Ticked / marked</th><th>Attendance</th><th>Reason (optional)</th></tr></thead>
            <tbody>
            <?php foreach ($roll as $r): $sid = (int) $r['id']; ?>
                <tr>
                    <td><div class="person"><?= avatar_html($r['name'], $r['avatar'] ?? null, $sid) ?><div><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></div></div></td>
                    <td class="small">
                        <?php if ($r['status'] === null): ?><span class="muted">Not marked</span>
                        <?php else: ?><?= e(fmt_time($r['marked_at'])) ?><small class="muted" style="display:block">
                            <?= ['student' => 'ticked by student', 'lecturer' => 'set by you', 'system' => 'class closed'][$r['marked_by']] ?? '' ?></small><?php endif; ?>
                    </td>
                    <td>
                        <div class="att-options">
                            <label class="o-present"><input type="radio" name="status[<?= $sid ?>]" value="present" <?= $r['status'] === 'present' ? 'checked' : '' ?>><span>Present</span></label>
                            <label class="o-absent"><input type="radio" name="status[<?= $sid ?>]" value="absent" <?= $r['status'] === 'absent' ? 'checked' : '' ?>><span>Absent</span></label>
                            <label class="o-perm"><input type="radio" name="status[<?= $sid ?>]" value="absent_permission" <?= $r['status'] === 'absent_permission' ? 'checked' : '' ?>><span>Absent with permission</span></label>
                        </div>
                    </td>
                    <td><input class="att-reason" type="text" name="reason[<?= $sid ?>]" value="<?= e($r['reason'] ?? '') ?>" maxlength="255" placeholder="e.g. medical certificate"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="form-actions">
            <button class="btn" type="submit" name="action" value="save"><?= icon('check', 18) ?> Save attendance</button>
            <button class="btn btn-soft" type="submit" name="action" value="all_present" onclick="return confirm('Mark every student in this class as Present?');">Mark all present</button>
        </div>
        <p class="muted small" style="margin-bottom:0">A reason is only kept for Absent and Absent with permission. If you change a student's record here, the student can no longer change it themselves.</p>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
