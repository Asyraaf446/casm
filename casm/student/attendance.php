<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me = user();
$uid = (int) $me['id'];
$ready = attendance_ready();

if ($ready) {
    expire_old_attendance();
}

/* ---- tick "Present" ---- */
if ($ready && is_post() && post('action') === 'tick') {
    csrf_verify();
    $sid = (int) ($_POST['session_id'] ?? 0);
    $s = attendance_session_for_user($sid, $me);   // also checks that I am enrolled
    if (!$s) {
        flash('error', 'That attendance could not be found.');
    } elseif ($s['status'] !== 'open') {
        flash('error', 'This attendance is closed, so it can no longer be ticked. Please speak to your lecturer.');
    } else {
        $rec = qone('SELECT status, marked_by FROM attendance_records WHERE session_id = ? AND student_id = ?', [$sid, $uid]);
        if ($rec && $rec['status'] === 'present') {
            flash('info', 'You already ticked Present for this class.');
        } elseif ($rec && $rec['marked_by'] === 'lecturer') {
            flash('error', 'Your lecturer has already recorded your attendance for this class. Please speak to your lecturer if it is wrong.');
        } else {
            qrun(
                "INSERT INTO attendance_records (session_id, student_id, status, reason, marked_by, marked_at) VALUES (?,?,'present',NULL,'student',?)
                 ON DUPLICATE KEY UPDATE status = 'present', reason = NULL, marked_by = 'student', marked_at = VALUES(marked_at)",
                [$sid, $uid, date('Y-m-d H:i:s')]
            );
            log_activity('TICK_ATTENDANCE', 'Ticked present for "' . $s['title'] . '" (' . $s['code'] . ')');
            flash('success', 'Your attendance for ' . $s['code'] . ' is recorded as Present. ✔');
        }
    }
    redirect('student/attendance.php');
}

$courses = qall(
    'SELECT c.id, c.code, c.title FROM classes c JOIN enrollments e ON e.class_id = c.id AND e.student_id = ? ORDER BY c.code',
    [$uid]
);
$courseIds = array_map('intval', array_column($courses, 'id'));
$cid = (int) ($_GET['course'] ?? 0);
if ($cid && !in_array($cid, $courseIds, true)) $cid = 0;

$open = $summary = $history = [];
$pg = null;
if ($ready && $courses) {
    $open = qall(
        "SELECT s.*, c.code, c.title AS course_title, r.status AS my_status
           FROM attendance_sessions s
           JOIN classes c ON c.id = s.class_id
           JOIN enrollments e ON e.class_id = s.class_id AND e.student_id = ?
           LEFT JOIN attendance_records r ON r.session_id = s.id AND r.student_id = ?
          WHERE s.status = 'open' ORDER BY s.opened_at DESC",
        [$uid, $uid]
    );

    $summary = qall(
        "SELECT c.id, c.code, c.title,
                SUM(r.status = 'present') AS n_present,
                SUM(r.status = 'absent') AS n_absent,
                SUM(r.status = 'absent_permission') AS n_perm,
                COUNT(r.id) AS n_total
           FROM classes c
           JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
           LEFT JOIN attendance_sessions s ON s.class_id = c.id AND s.status <> 'pending'
           LEFT JOIN attendance_records r ON r.session_id = s.id AND r.student_id = e.student_id
          GROUP BY c.id, c.code, c.title ORDER BY c.code",
        [$uid]
    );

    $where = "s.status <> 'pending' AND s.class_id IN (" . implode(',', $courseIds) . ')';
    $params = [$uid];
    if ($cid) { $where .= ' AND s.class_id = ?'; $params[] = $cid; }
    $total = (int) qval("SELECT COUNT(*) FROM attendance_sessions s WHERE $where", array_slice($params, 1));
    $pg = paginate($total, 15, (int) ($_GET['page'] ?? 1));
    $history = qall(
        "SELECT s.id, s.title, s.session_date, s.status, c.code, r.status AS my_status, r.reason, r.marked_at
           FROM attendance_sessions s
           JOIN classes c ON c.id = s.class_id
           LEFT JOIN attendance_records r ON r.session_id = s.id AND r.student_id = ?
          WHERE $where ORDER BY s.session_date DESC, s.id DESC LIMIT " . (int) $pg['per'] . ' OFFSET ' . (int) $pg['offset'],
        $params
    );
}

$pageTitle = 'Attendance';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>My Attendance</h1></div>
</div>

<?php if (!$ready): ?>
    <div class="notice"><?= icon('help', 20) ?><div>Attendance is not set up yet. Please ask the administrator to run database/update_add_attendance.sql.</div></div>
<?php elseif (!$courses): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('book', 28) ?></div><p>You have not joined any class yet.</p><a class="btn" href="<?= url('student/courses.php') ?>">Join a class</a></div></div>
<?php else: ?>

    <div class="meet-section"><h2>Tick your attendance</h2>
    <?php if (!$open): ?>
        <div class="card"><div class="empty"><div class="em-ico"><?= icon('clipboard', 28) ?></div><p>No attendance is open right now. When your lecturer opens one you will get a notification.</p></div></div>
    <?php else: ?>
        <div class="list">
        <?php foreach ($open as $o): ?>
            <div class="att-tick is-open">
                <span class="ico"><?= icon('clipboard', 24) ?></span>
                <div class="grow">
                    <b><?= e($o['title']) ?></b>
                    <small><?= e($o['code']) ?> · <?= e($o['course_title']) ?></small>
                    <small><?= icon('clock', 14) ?> <?= e(fmt_date($o['session_date'])) ?><?= $o['note'] ? ' · ' . e($o['note']) : '' ?></small>
                </div>
                <?php if ($o['my_status']): ?>
                    <?= attendance_pill($o['my_status']) ?>
                <?php endif; ?>
                <?php if ($o['my_status'] !== 'present'): ?>
                <form class="inline-form" method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="tick"><input type="hidden" name="session_id" value="<?= (int) $o['id'] ?>">
                    <button class="btn" type="submit"><?= icon('check', 18) ?> Tick Present</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>

    <div class="card mb">
        <div class="card-head"><h2>My attendance by class</h2><span class="muted small">Classes below <?= ATTENDANCE_MIN_RATE ?>% are flagged</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Class</th><th>Present</th><th>Absent</th><th>With permission</th><th style="width:240px">Attendance rate</th></tr></thead>
            <tbody>
            <?php foreach ($summary as $r):
                $tot = (int) $r['n_total']; $rate = attendance_rate((int) $r['n_present'], $tot); $low = $tot > 0 && $rate < ATTENDANCE_MIN_RATE; ?>
                <tr>
                    <td><b><?= e($r['code']) ?></b><small class="muted" style="display:block"><?= e($r['title']) ?></small></td>
                    <td><?= (int) $r['n_present'] ?></td><td><?= (int) $r['n_absent'] ?></td><td><?= (int) $r['n_perm'] ?></td>
                    <td><?php if ($tot === 0): ?><span class="muted small">No records yet</span><?php else: ?>
                        <div class="rate-cell <?= $low ? 'rate-low' : 'rate-ok' ?>"><div class="progress"><span style="width:<?= $rate ?>%"></span></div><b><?= $rate ?>%</b></div>
                        <?php if ($low): ?><?= pill('att-absent', 'Low') ?><?php endif; ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>History</h2>
            <form class="inline-form" method="get">
                <select name="course" onchange="this.form.submit()" style="width:auto;min-width:200px">
                    <option value="">All my classes</option>
                    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-soft btn-sm" type="submit">Show</button></noscript>
            </form></div>
        <?php if (!$history): ?>
            <div class="empty"><p>No attendance has been recorded yet.</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Class</th><th>Session</th><th>My attendance</th><th>Reason</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td class="nowrap small"><?= e(fmt_date($h['session_date'])) ?></td>
                    <td><?= e($h['code']) ?></td>
                    <td><b><?= e($h['title']) ?></b></td>
                    <td><?php if ($h['my_status']): ?><?= attendance_pill($h['my_status']) ?>
                        <?php elseif ($h['status'] === 'open'): ?><?= pill('info', 'Not ticked yet') ?>
                        <?php else: ?><span class="muted small">No record</span><?php endif; ?></td>
                    <td class="small"><?= e($h['reason'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= pagination_html($pg, array_filter(['course' => $cid])) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
