<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$ready = attendance_ready();

$cid    = (int) ($_GET['course'] ?? 0);
$lid    = (int) ($_GET['lecturer'] ?? 0);
$status = $_GET['status'] ?? '';
$q      = trim((string) ($_GET['q'] ?? ''));
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
if (!isset(attendance_statuses()[$status])) $status = '';

$courses   = $ready ? qall('SELECT id, code, title FROM classes ORDER BY code') : [];
$lecturers = $ready ? qall("SELECT id, name FROM users WHERE role = 'lecturer' ORDER BY name") : [];

$stats = ['present' => 0, 'absent' => 0, 'absent_permission' => 0];
$sessionsN = 0;
$byClass = $atRisk = $records = [];
$pg = null;
$total = 0;
$query = array_filter(['course' => $cid, 'lecturer' => $lid, 'status' => $status, 'q' => $q, 'from' => $from, 'to' => $to]);

if ($ready) {
    expire_old_attendance();

    /* filters shared by the statistics ($base) and by the records table ($base + status) */
    $base = ["s.status <> 'pending'"];
    $params = [];
    if ($cid) { $base[] = 's.class_id = ?'; $params[] = $cid; }
    if ($lid) { $base[] = 'c.lecturer_id = ?'; $params[] = $lid; }
    if ($q !== '') { $base[] = '(u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $base[] = 's.session_date >= ?'; $params[] = $from; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $base[] = 's.session_date <= ?'; $params[] = $to; }
    $wBase = implode(' AND ', $base);
    $wAll = $wBase;
    $pAll = $params;
    if ($status !== '') { $wAll .= ' AND r.status = ?'; $pAll[] = $status; }

    $from_sql = 'FROM attendance_records r
                 JOIN attendance_sessions s ON s.id = r.session_id
                 JOIN classes c ON c.id = s.class_id
                 JOIN users u ON u.id = r.student_id
                 JOIN users l ON l.id = c.lecturer_id';

    /* CSV export of the current filter */
    if (isset($_GET['export'])) {
        $rows = qall(
            "SELECT s.session_date, c.code, c.title, s.title AS session_title, l.name AS lecturer, u.name AS student, u.email,
                    r.status, r.reason, r.marked_by, r.marked_at $from_sql WHERE $wAll ORDER BY s.session_date DESC, c.code, u.name",
            $pAll
        );
        $labels = attendance_statuses();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="casm_attendance_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Class code', 'Class', 'Session', 'Lecturer', 'Student', 'Email', 'Attendance', 'Reason', 'Marked by', 'Marked at']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['session_date'], $r['code'], $r['title'], $r['session_title'], $r['lecturer'], $r['student'], $r['email'],
                $labels[$r['status']] ?? $r['status'], $r['reason'], $r['marked_by'], $r['marked_at']]);
        }
        fclose($out);
        exit;
    }

    foreach (qall("SELECT r.status, COUNT(*) AS n $from_sql WHERE $wBase GROUP BY r.status", $params) as $r) {
        $stats[$r['status']] = (int) $r['n'];
    }
    $sessionsN = (int) qval("SELECT COUNT(DISTINCT s.id) $from_sql WHERE $wBase", $params);

    $byClass = qall(
        "SELECT c.id, c.code, c.title, l.name AS lecturer, COUNT(DISTINCT s.id) AS n_sessions,
                SUM(r.status = 'present') AS n_present, SUM(r.status = 'absent') AS n_absent,
                SUM(r.status = 'absent_permission') AS n_perm, COUNT(r.id) AS n_total
           $from_sql WHERE $wBase GROUP BY c.id, c.code, c.title, l.name ORDER BY c.code",
        $params
    );

    $atRisk = qall(
        "SELECT u.id, u.name, u.email, SUM(r.status = 'present') AS n_present, COUNT(r.id) AS n_total,
                ROUND(SUM(r.status = 'present') / COUNT(r.id) * 100) AS rate
           $from_sql WHERE $wBase GROUP BY u.id, u.name, u.email
         HAVING COUNT(r.id) > 0 AND ROUND(SUM(r.status = 'present') / COUNT(r.id) * 100) < " . (int) ATTENDANCE_MIN_RATE . '
          ORDER BY rate, u.name LIMIT 8',
        $params
    );

    $total = (int) qval("SELECT COUNT(*) $from_sql WHERE $wAll", $pAll);
    $pg = paginate($total, 20, (int) ($_GET['page'] ?? 1));
    $records = qall(
        "SELECT s.id AS session_id, s.title AS session_title, s.session_date, c.code, u.name AS student, r.status, r.reason, r.marked_by, r.marked_at
           $from_sql WHERE $wAll ORDER BY s.session_date DESC, s.id DESC, u.name LIMIT " . (int) $pg['per'] . ' OFFSET ' . (int) $pg['offset'],
        $pAll
    );
}
$allRecords = array_sum($stats);
$overall = attendance_rate($stats['present'], $allRecords);
$byLabels = ['student' => 'Student ticked', 'lecturer' => 'Lecturer', 'system' => 'Class closed'];

$pageTitle = 'Attendance';
$active = 'attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Attendance</h1></div>
    <?php if ($ready): ?><div class="actions"><a class="btn btn-soft" href="?<?= e(http_build_query($query + ['export' => 1])) ?>"><?= icon('download', 18) ?> Export CSV</a></div><?php endif; ?>
</div>

<?php if (!$ready): ?>
    <div class="notice"><?= icon('help', 20) ?><div>The attendance tables are not in the database yet. In phpMyAdmin open database/update_add_attendance.sql, paste it in the SQL tab and press Go.</div></div>
<?php else: ?>

<form class="filters" method="get">
    <select name="course"><option value="">All classes</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?></select>
    <select name="lecturer"><option value="">All lecturers</option>
        <?php foreach ($lecturers as $l): ?><option value="<?= (int) $l['id'] ?>" <?= $lid === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">All attendance</option>
        <?php foreach (attendance_statuses() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search student…">
    <input type="date" name="from" value="<?= e($from) ?>" title="From date">
    <input type="date" name="to" value="<?= e($to) ?>" title="To date">
    <button class="btn btn-soft" type="submit"><?= icon('search', 16) ?> Filter</button>
    <?php if ($query): ?><a class="btn btn-ghost" href="<?= url('admin/attendance.php') ?>">Reset</a><?php endif; ?>
</form>

<div class="grid cols-4 mb">
    <div class="stat att-g"><span class="ico"><?= icon('check', 24) ?></span><div><b><?= $stats['present'] ?></b><span>Present</span></div></div>
    <div class="stat att-r"><span class="ico"><?= icon('x', 24) ?></span><div><b><?= $stats['absent'] ?></b><span>Absent</span></div></div>
    <div class="stat att-a"><span class="ico"><?= icon('alert', 24) ?></span><div><b><?= $stats['absent_permission'] ?></b><span>Absent with permission</span></div></div>
    <div class="stat sky"><span class="ico"><?= icon('chart', 24) ?></span><div><b><?= $overall ?>%</b><span>Overall rate · <?= $sessionsN ?> session<?= $sessionsN === 1 ? '' : 's' ?></span></div></div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>By class</h2></div>
        <?php if (!$byClass): ?><div class="empty"><p>No attendance data matches your filters.</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Class</th><th>Sessions</th><th>P</th><th>A</th><th>AP</th><th style="width:150px">Rate</th></tr></thead>
            <tbody>
            <?php foreach ($byClass as $r):
                $t = (int) $r['n_total']; $rate = attendance_rate((int) $r['n_present'], $t); ?>
                <tr>
                    <td><b><?= e($r['code']) ?></b><small class="muted" style="display:block"><?= e($r['lecturer']) ?></small></td>
                    <td><?= (int) $r['n_sessions'] ?></td><td><?= (int) $r['n_present'] ?></td><td><?= (int) $r['n_absent'] ?></td><td><?= (int) $r['n_perm'] ?></td>
                    <td><div class="rate-cell <?= $rate < ATTENDANCE_MIN_RATE ? 'rate-low' : 'rate-ok' ?>"><div class="progress"><span style="width:<?= $rate ?>%"></span></div><b><?= $rate ?>%</b></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="muted small" style="margin-bottom:0">P = Present, A = Absent, AP = Absent with permission.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Students below <?= ATTENDANCE_MIN_RATE ?>%</h2></div>
        <?php if (!$atRisk): ?><div class="empty"><p>No student is below <?= ATTENDANCE_MIN_RATE ?>% for these filters. 🎉</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Student</th><th>Present</th><th style="width:150px">Rate</th></tr></thead>
            <tbody>
            <?php foreach ($atRisk as $r): ?>
                <tr>
                    <td><b><?= e($r['name']) ?></b><small class="muted" style="display:block"><?= e($r['email']) ?></small></td>
                    <td><?= (int) $r['n_present'] ?> of <?= (int) $r['n_total'] ?></td>
                    <td><div class="rate-cell rate-low"><div class="progress"><span style="width:<?= (int) $r['rate'] ?>%"></span></div><b><?= (int) $r['rate'] ?>%</b></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Attendance records</h2><span class="muted small"><?= $total ?> record<?= $total === 1 ? '' : 's' ?></span></div>
    <?php if (!$records): ?>
        <div class="empty"><div class="em-ico"><?= icon('clipboard', 28) ?></div><p>No attendance records match your filters.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Class</th><th>Session</th><th>Student</th><th>Attendance</th><th>Reason</th><th>Recorded by</th></tr></thead>
        <tbody>
        <?php foreach ($records as $r): ?>
            <tr>
                <td class="nowrap small"><?= e(fmt_date($r['session_date'])) ?></td>
                <td><?= e($r['code']) ?></td>
                <td><a href="<?= url('admin/attendance_view.php?id=' . (int) $r['session_id']) ?>" style="color:inherit"><b><?= e($r['session_title']) ?></b></a></td>
                <td><?= e($r['student']) ?></td>
                <td><?= attendance_pill($r['status']) ?></td>
                <td class="small"><?= e($r['reason'] ?? '') ?></td>
                <td class="small muted"><?= e($byLabels[$r['marked_by']] ?? '') ?> · <?= e(fmt_time($r['marked_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pagination_html($pg, $query) ?>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
