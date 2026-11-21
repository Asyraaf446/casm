<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$ready = attendance_ready();
$courses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);
$courseIds = array_map('intval', array_column($courses, 'id'));

$tab = ($_GET['tab'] ?? 'sessions') === 'summary' ? 'summary' : 'sessions';
$cid = (int) ($_GET['course'] ?? 0);
if ($cid && !in_array($cid, $courseIds, true)) {
    $cid = 0;
}
if ($tab === 'summary' && !$cid && $courses) {
    $cid = (int) $courses[0]['id'];   // the summary needs one class
}

$sessions = [];
$summary = [];
$sessionCount = 0;
if ($ready && $courseIds) {
    expire_old_attendance();

    if ($tab === 'sessions') {
        $where = 'c.lecturer_id = ?';
        $params = [$me['id']];
        if ($cid) { $where .= ' AND s.class_id = ?'; $params[] = $cid; }
        $sessions = qall(
            "SELECT s.*, c.code,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = s.class_id) AS total,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id AND r.status = 'present') AS n_present,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id AND r.status = 'absent') AS n_absent,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id AND r.status = 'absent_permission') AS n_perm
               FROM attendance_sessions s JOIN classes c ON c.id = s.class_id
              WHERE $where ORDER BY s.session_date DESC, s.id DESC",
            $params
        );
    } elseif ($cid) {
        $sessionCount = (int) qval("SELECT COUNT(*) FROM attendance_sessions WHERE class_id = ? AND status <> 'pending'", [$cid]);
        $summary = qall(
            "SELECT u.id, u.name, u.email, u.avatar,
                    SUM(r.status = 'present') AS n_present,
                    SUM(r.status = 'absent') AS n_absent,
                    SUM(r.status = 'absent_permission') AS n_perm,
                    COUNT(r.id) AS n_total
               FROM enrollments e
               JOIN users u ON u.id = e.student_id
               LEFT JOIN (SELECT r2.* FROM attendance_records r2 JOIN attendance_sessions s2 ON s2.id = r2.session_id AND s2.class_id = ?) r
                      ON r.student_id = u.id
              WHERE e.class_id = ?
              GROUP BY u.id, u.name, u.email, u.avatar
              ORDER BY u.name",
            [$cid, $cid]
        );
    }
}

$pageTitle = 'Attendance';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Attendance</h1></div>
    <?php if ($ready && $courses): ?><div class="actions"><a class="btn" href="<?= url('lecturer/attendance_form.php' . ($cid ? '?class_id=' . $cid : '')) ?>"><?= icon('plus', 18) ?> New attendance</a></div><?php endif; ?>
</div>

<?php if (!$ready): ?>
    <div class="notice"><?= icon('help', 20) ?><div>The attendance tables are not in the database yet. In phpMyAdmin open database/update_add_attendance.sql, paste it in the SQL tab and press Go.</div></div>
<?php elseif (!$courses): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('clipboard', 28) ?></div><p>You do not have a class yet. Please ask the administrator to assign one to you.</p></div></div>
<?php else: ?>
    <div class="tabs">
        <a class="<?= $tab === 'sessions' ? 'active' : '' ?>" href="?tab=sessions<?= $cid ? '&course=' . $cid : '' ?>">Attendance sessions</a>
        <a class="<?= $tab === 'summary' ? 'active' : '' ?>" href="?tab=summary<?= $cid ? '&course=' . $cid : '' ?>">Student summary</a>
    </div>

    <form class="filters" method="get">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <select name="course" onchange="this.form.submit()">
            <?php if ($tab === 'sessions'): ?><option value="">All my classes</option><?php endif; ?>
            <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-soft" type="submit">Show</button></noscript>
    </form>

    <?php if ($tab === 'sessions'): ?>
    <div class="card">
        <?php if (!$sessions): ?>
            <div class="empty"><div class="em-ico"><?= icon('clipboard', 28) ?></div><p>No attendance has been created yet.</p>
                <a class="btn" href="<?= url('lecturer/attendance_form.php' . ($cid ? '?class_id=' . $cid : '')) ?>">Create the first one</a></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Session</th><th>Class</th><th>Date</th><th>Status</th><th>Present</th><th>Absent</th><th>Permission</th><th>Not marked</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s):
                $none = max(0, (int) $s['total'] - (int) $s['n_present'] - (int) $s['n_absent'] - (int) $s['n_perm']); ?>
                <tr>
                    <td><b><a href="<?= url('lecturer/attendance_view.php?id=' . (int) $s['id']) ?>" style="color:inherit"><?= e($s['title']) ?></a></b></td>
                    <td><?= e($s['code']) ?></td>
                    <td class="nowrap small"><?= e(fmt_date($s['session_date'])) ?></td>
                    <td><?= attendance_state_pill($s['status']) ?></td>
                    <td><?= pill('att-present', (string) (int) $s['n_present']) ?></td>
                    <td><?= pill('att-absent', (string) (int) $s['n_absent']) ?></td>
                    <td><?= pill('att-perm', (string) (int) $s['n_perm']) ?></td>
                    <td><?= $none ?> <span class="muted small">of <?= (int) $s['total'] ?></span></td>
                    <td class="right"><a class="btn btn-soft btn-sm" href="<?= url('lecturer/attendance_view.php?id=' . (int) $s['id']) ?>">Check</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="card">
        <div class="card-head"><h2>Student summary</h2><span class="muted small"><?= $sessionCount ?> session<?= $sessionCount === 1 ? '' : 's' ?> so far · students below <?= ATTENDANCE_MIN_RATE ?>% are flagged</span></div>
        <?php if (!$summary): ?>
            <div class="empty"><p>No students are enrolled in this class yet.</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>Permission</th><th style="width:240px">Attendance rate</th></tr></thead>
            <tbody>
            <?php foreach ($summary as $s):
                $tot = (int) $s['n_total']; $rate = attendance_rate((int) $s['n_present'], $tot); $low = $tot > 0 && $rate < ATTENDANCE_MIN_RATE; ?>
                <tr>
                    <td><div class="person"><?= avatar_html($s['name'], $s['avatar'] ?? null, (int) $s['id']) ?><div><b><?= e($s['name']) ?></b><small><?= e($s['email']) ?></small></div></div></td>
                    <td><?= (int) $s['n_present'] ?></td>
                    <td><?= (int) $s['n_absent'] ?></td>
                    <td><?= (int) $s['n_perm'] ?></td>
                    <td><?php if ($tot === 0): ?><span class="muted small">No records yet</span><?php else: ?>
                        <div class="rate-cell <?= $low ? 'rate-low' : 'rate-ok' ?>"><div class="progress"><span style="width:<?= $rate ?>%"></span></div><b><?= $rate ?>%</b></div>
                        <?php if ($low): ?><?= pill('att-absent', 'Low') ?><?php endif; ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="muted small" style="margin-bottom:0">Attendance rate = Present ÷ all recorded sessions. Absent with permission is shown separately but is not counted as present.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
