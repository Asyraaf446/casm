<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$now = date('Y-m-d H:i:s');
$pct = function ($part, $whole) { return $whole > 0 ? (int) round($part / $whole * 100) : 0; };

/* ---------- people ---------- */
$roleCounts = ['student' => 0, 'lecturer' => 0, 'admin' => 0];
foreach (qall('SELECT role, COUNT(*) AS n FROM users GROUP BY role') as $r) $roleCounts[$r['role']] = (int) $r['n'];
$totalUsers   = array_sum($roleCounts);
$activeUsers  = (int) qval('SELECT COUNT(*) FROM users WHERE is_active = 1');
$newThisMonth = (int) qval('SELECT COUNT(*) FROM users WHERE created_at >= ?', [date('Y-m-01 00:00:00')]);

/* ---------- classes / learning ---------- */
$totalClasses     = (int) qval('SELECT COUNT(*) FROM classes');
$totalCourses     = (int) qval('SELECT COUNT(*) FROM courses');
$totalEnrollments = (int) qval('SELECT COUNT(*) FROM enrollments');
$avgClassSize     = $totalClasses ? round($totalEnrollments / $totalClasses, 1) : 0;
$totalAssignments = (int) qval('SELECT COUNT(*) FROM assignments');
$totalMaterials   = (int) qval('SELECT COUNT(*) FROM materials');

$expected = (int) qval('SELECT COUNT(*) FROM assignments a JOIN enrollments e ON e.class_id = a.class_id');
$received = (int) qval('SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = s.student_id');
$onTime   = (int) qval('SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = s.student_id WHERE s.submitted_at <= a.due_date');
$late     = $received - $onTime;
$pending  = max(0, $expected - $received);
$graded   = (int) qval('SELECT COUNT(*) FROM submissions WHERE marks IS NOT NULL');
$avgGrade = qval('SELECT AVG(s.marks / a.max_marks * 100) FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.marks IS NOT NULL AND a.max_marks > 0');
$avgGrade = $avgGrade === null ? null : (int) round((float) $avgGrade);

/* grade distribution (% of max marks) */
$buckets = ['90 – 100%' => 0, '80 – 89%' => 0, '70 – 79%' => 0, '60 – 69%' => 0, 'Below 60%' => 0];
foreach (qall('SELECT (s.marks / a.max_marks * 100) AS p FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.marks IS NOT NULL AND a.max_marks > 0') as $g) {
    $p = (float) $g['p'];
    $k = $p >= 90 ? '90 – 100%' : ($p >= 80 ? '80 – 89%' : ($p >= 70 ? '70 – 79%' : ($p >= 60 ? '60 – 69%' : 'Below 60%')));
    $buckets[$k]++;
}

/* doubts */
$openDoubts     = (int) qval('SELECT COUNT(*) FROM doubts WHERE is_resolved = 0');
$resolvedDoubts = (int) qval('SELECT COUNT(*) FROM doubts WHERE is_resolved = 1');

/* meetings (table may not exist on older databases) */
$meet = null;
if (meetings_ready()) {
    try {
        $meet = [
            'total'  => (int) qval('SELECT COUNT(*) FROM meetings'),
            'ended'  => (int) qval("SELECT COUNT(*) FROM meetings WHERE status = 'ended'"),
            'joined' => (int) qval('SELECT COUNT(*) FROM meeting_attendance'),
        ];
    } catch (Throwable $t) {
        $meet = null;
    }
}

/* ---------- trends ---------- */
// new accounts per month (last 6 months)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    $months[$key] = ['label' => date('M', strtotime($key . '-01')), 'student' => 0, 'lecturer' => 0];
}
foreach (qall("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, role, COUNT(*) AS n FROM users WHERE role IN ('student','lecturer') AND created_at >= ? GROUP BY m, role", [array_key_first($months) . '-01 00:00:00']) as $r) {
    if (isset($months[$r['m']])) $months[$r['m']][$r['role']] = (int) $r['n'];
}
$monthMax = max(1, max(array_map(fn($m) => $m['student'] + $m['lecturer'], array_values($months))));

// system activity per day (last 14 days)
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = ['label' => date('j', strtotime($d)), 'n' => 0];
}
foreach (qall('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM activity_logs WHERE created_at >= ? GROUP BY d', [array_key_first($days) . ' 00:00:00']) as $r) {
    if (isset($days[$r['d']])) $days[$r['d']]['n'] = (int) $r['n'];
}
$dayMax = max(1, max(array_column($days, 'n')));
$dayTotal = array_sum(array_column($days, 'n'));

$topActions = qall('SELECT action, COUNT(*) AS n FROM activity_logs GROUP BY action ORDER BY n DESC, action LIMIT 6');
$actionMax  = $topActions ? max(1, (int) $topActions[0]['n']) : 1;

/* ---------- per class ---------- */
$classes = qall(
    'SELECT c.id, c.code, c.title, c.color, u.name AS lecturer,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id) AS students,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) AS assignments,
            (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = s.student_id WHERE a.class_id = c.id) AS received,
            (SELECT AVG(s.marks / a.max_marks * 100) FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.class_id = c.id AND s.marks IS NOT NULL AND a.max_marks > 0) AS avg_grade,
            (SELECT COUNT(*) FROM doubts d WHERE d.class_id = c.id AND d.is_resolved = 0) AS open_doubts
       FROM classes c JOIN users u ON u.id = c.lecturer_id ORDER BY c.code'
);
foreach ($classes as &$c) {
    $c['expected'] = (int) $c['students'] * (int) $c['assignments'];
    $c['rate']     = $pct((int) $c['received'], $c['expected']);
}
unset($c);

/* CSV export of the per-class report */
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="casm_class_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code', 'Title', 'Lecturer', 'Students', 'Assignments', 'Submission rate %', 'Average grade %', 'Open questions']);
    foreach ($classes as $c) {
        fputcsv($out, [$c['code'], $c['title'], $c['lecturer'], $c['students'], $c['assignments'], $c['expected'] ? $c['rate'] : '', $c['avg_grade'] === null ? '' : round((float) $c['avg_grade']), $c['open_doubts']]);
    }
    fclose($out);
    exit;
}
$enrollMax = $classes ? max(1, (int) max(array_column($classes, 'students'))) : 1;

/* ---------- per lecturer ---------- */
$lecturers = qall(
    "SELECT u.id, u.name, u.email, u.avatar, u.is_active,
            (SELECT COUNT(*) FROM classes c WHERE c.lecturer_id = u.id) AS classes,
            (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN classes c ON c.id = e.class_id WHERE c.lecturer_id = u.id) AS students,
            (SELECT COUNT(*) FROM assignments a JOIN classes c ON c.id = a.class_id WHERE c.lecturer_id = u.id) AS assignments,
            (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN classes c ON c.id = a.class_id WHERE c.lecturer_id = u.id AND s.marks IS NULL) AS to_grade
       FROM users u WHERE u.role = 'lecturer' ORDER BY classes DESC, u.name"
);

/* ---------- students who need attention (most overdue, not handed in) ---------- */
$atRisk = qall(
    'SELECT u.id, u.name, u.email, u.avatar, COUNT(*) AS missing
       FROM enrollments e
       JOIN assignments a ON a.class_id = e.class_id AND a.due_date < ?
       JOIN users u ON u.id = e.student_id
  LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
      WHERE s.id IS NULL
   GROUP BY u.id, u.name, u.email, u.avatar
   ORDER BY missing DESC, u.name LIMIT 5',
    [$now]
);

$pageTitle = 'Statistics & Analytics';
$active = 'analytics';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Statistics &amp; Analytics</h1></div>
    <div class="actions"><a class="btn btn-soft" href="?export=1"><?= icon('download', 18) ?> Export class report (CSV)</a></div>
</div>

<div class="grid cols-4 mb">
    <div class="stat lav"><span class="ico"><?= icon('users', 24) ?></span><div><b><?= $totalUsers ?></b><span>Total users</span></div></div>
    <div class="stat mint"><span class="ico"><?= icon('book', 24) ?></span><div><b><?= $totalCourses ?></b><span>Courses · <?= $totalClasses ?> classes</span></div></div>
    <div class="stat sky"><span class="ico"><?= icon('user', 24) ?></span><div><b><?= $avgClassSize ?></b><span>Avg. students per class</span></div></div>
    <div class="stat peach"><span class="ico"><?= icon('check', 24) ?></span><div><b><?= $expected ? $pct($received, $expected) . '%' : '–' ?></b><span>Submission rate</span></div></div>
    <div class="stat rose"><span class="ico"><?= icon('star', 24) ?></span><div><b><?= $avgGrade === null ? '–' : $avgGrade . '%' ?></b><span>Average grade</span></div></div>
    <div class="stat sun"><span class="ico"><?= icon('help', 24) ?></span><div><b><?= $openDoubts ?></b><span>Open questions</span></div></div>
    <div class="stat lav"><span class="ico"><?= icon('file', 24) ?></span><div><b><?= $totalAssignments ?></b><span>Assignments · <?= $totalMaterials ?> notes</span></div></div>
    <div class="stat mint"><span class="ico"><?= icon('video', 24) ?></span><div><b><?= $meet ? $meet['total'] : '–' ?></b><span>Online meetings<?= $meet ? ' · ' . $meet['joined'] . ' joins' : '' ?></span></div></div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>Users</h2><span class="muted small"><?= $activeUsers ?> active · <?= $totalUsers - $activeUsers ?> deactivated · <?= $newThisMonth ?> new this month</span></div>
        <div class="analytics">
            <?= donut_svg([
                ['Students',  $roleCounts['student'],  '#2563eb'],
                ['Lecturers', $roleCounts['lecturer'], '#38bdf8'],
                ['Admins',    $roleCounts['admin'],    '#0f172a'],
            ], (string) $totalUsers, 'users') ?>
            <div class="legend">
                <div class="legend-row"><span class="sw" style="background:#2563eb"></span><b><?= $roleCounts['student'] ?></b><span>Students <span class="muted">(<?= $pct($roleCounts['student'], $totalUsers) ?>%)</span></span></div>
                <div class="legend-row"><span class="sw" style="background:#38bdf8"></span><b><?= $roleCounts['lecturer'] ?></b><span>Lecturers <span class="muted">(<?= $pct($roleCounts['lecturer'], $totalUsers) ?>%)</span></span></div>
                <div class="legend-row"><span class="sw" style="background:#0f172a"></span><b><?= $roleCounts['admin'] ?></b><span>Administrators <span class="muted">(<?= $pct($roleCounts['admin'], $totalUsers) ?>%)</span></span></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Submissions</h2><span class="muted small"><?= $received ?> of <?= $expected ?> expected handed in</span></div>
        <?php if ($expected === 0): ?>
            <div class="empty"><p>No assignments have been given to enrolled students yet.</p></div>
        <?php else: ?>
        <div class="analytics">
            <?= donut_svg([
                ['On time', $onTime,  '#2563eb'],
                ['Late',    $late,    '#38bdf8'],
                ['Pending', $pending, '#94a3b8'],
            ], $pct($received, $expected) . '%', 'handed in') ?>
            <div class="legend">
                <div class="legend-row"><span class="sw" style="background:#2563eb"></span><b><?= $onTime ?></b><span>On time <span class="muted">(<?= $pct($onTime, $expected) ?>%)</span></span></div>
                <div class="legend-row"><span class="sw" style="background:#38bdf8"></span><b><?= $late ?></b><span>Late <span class="muted">(<?= $pct($late, $expected) ?>%)</span></span></div>
                <div class="legend-row"><span class="sw" style="background:#94a3b8"></span><b><?= $pending ?></b><span>Pending <span class="muted">(<?= $pct($pending, $expected) ?>%)</span></span></div>
                <div>
                    <div class="progress"><span style="width:<?= $pct($graded, max(1, $received)) ?>%"></span></div>
                    <small class="muted">Graded <?= $graded ?> of <?= $received ?> submission<?= $received === 1 ? '' : 's' ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>New accounts</h2><span class="muted small">Last 6 months</span></div>
        <div class="vbars">
            <?php foreach ($months as $m): $t = $m['student'] + $m['lecturer']; ?>
                <div class="vbar" title="<?= (int) $m['student'] ?> students, <?= (int) $m['lecturer'] ?> lecturers">
                    <span class="val"><?= $t ?></span>
                    <div class="stack" style="height:<?= $t ? max(6, round($t / $monthMax * 100)) : 0 ?>%">
                        <i style="flex:<?= (int) $m['lecturer'] ?>;background:#38bdf8"></i><i style="flex:<?= (int) $m['student'] ?>;background:#2563eb"></i>
                    </div>
                    <em><?= e($m['label']) ?></em>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-key"><span><i style="background:#2563eb"></i> Students</span><span><i style="background:#38bdf8"></i> Lecturers</span></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>System activity</h2><span class="muted small"><?= $dayTotal ?> actions in the last 14 days</span></div>
        <div class="vbars">
            <?php foreach ($days as $d => $v): ?>
                <div class="vbar" title="<?= e(fmt_date($d)) ?>: <?= (int) $v['n'] ?> actions">
                    <span class="val"><?= $v['n'] ?: '' ?></span>
                    <div class="stack" style="height:<?= $v['n'] ? max(6, round($v['n'] / $dayMax * 100)) : 0 ?>%"><i style="flex:1;background:#60a5fa"></i></div>
                    <em><?= e($v['label']) ?></em>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($topActions): ?>
        <div class="hbars" style="margin-top:16px">
            <?php foreach ($topActions as $a): ?>
                <div class="hrow"><span class="lbl small"><?= e(action_label($a['action'])) ?></span><div class="progress"><span style="width:<?= round($a['n'] / $actionMax * 100) ?>%"></span></div><b><?= (int) $a['n'] ?></b></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>Students per class</h2><span class="muted small"><?= $totalEnrollments ?> enrollments</span></div>
        <?php if (!$classes): ?>
            <div class="empty"><p>No classes yet.</p></div>
        <?php else: ?>
        <div class="hbars">
            <?php foreach ($classes as $c): ?>
                <div class="hrow c-<?= e($c['color']) ?>"><span class="lbl"><b><?= e($c['code']) ?></b></span><div class="progress"><span style="width:<?= round($c['students'] / $enrollMax * 100) ?>%"></span></div><b><?= (int) $c['students'] ?></b></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Grade distribution</h2><span class="muted small"><?= $graded ?> graded submission<?= $graded === 1 ? '' : 's' ?></span></div>
        <?php if ($graded === 0): ?>
            <div class="empty"><p>Nothing has been graded yet.</p></div>
        <?php else: $bMax = max(1, max($buckets)); ?>
        <div class="hbars">
            <?php foreach ($buckets as $label => $n): ?>
                <div class="hrow"><span class="lbl small"><?= e($label) ?></span><div class="progress"><span style="width:<?= round($n / $bMax * 100) ?>%"></span></div><b><?= $n ?></b></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb">
    <div class="card-head"><h2>Class performance</h2><a class="btn btn-soft btn-sm" href="<?= url('admin/courses.php') ?>">Manage classes</a></div>
    <?php if (!$classes): ?>
        <div class="empty"><p>No classes yet.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Class</th><th>Lecturer</th><th>Students</th><th>Assignments</th><th style="min-width:150px">Submission rate</th><th>Avg. grade</th><th>Open questions</th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
            <tr>
                <td><div class="c-<?= e($c['color']) ?>"><span class="dotc"></span> <b><a href="<?= url('admin/class_view.php?id=' . (int) $c['id']) ?>" style="color:inherit"><?= e($c['code']) ?></a></b></div><small class="muted"><?= e($c['title']) ?></small></td>
                <td><?= e($c['lecturer']) ?></td>
                <td><?= (int) $c['students'] ?></td>
                <td><?= (int) $c['assignments'] ?></td>
                <td><?php if ($c['expected']): ?><div class="rate-cell"><div class="progress"><span style="width:<?= (int) $c['rate'] ?>%"></span></div><b><?= (int) $c['rate'] ?>%</b></div><?php else: ?><span class="muted small">No data</span><?php endif; ?></td>
                <td><?= $c['avg_grade'] === null ? '<span class="muted small">–</span>' : (int) round((float) $c['avg_grade']) . '%' ?></td>
                <td><?= (int) $c['open_doubts'] ? pill('warn', (string) (int) $c['open_doubts']) : '<span class="muted small">0</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="grid split">
    <div class="card">
        <div class="card-head"><h2>Lecturer overview</h2><a class="btn btn-soft btn-sm" href="<?= url('admin/users.php?role=lecturer') ?>">Manage lecturers</a></div>
        <?php if (!$lecturers): ?>
            <div class="empty"><p>No lecturers yet.</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Lecturer</th><th>Classes</th><th>Students</th><th>Assignments</th><th>To grade</th></tr></thead>
            <tbody>
            <?php foreach ($lecturers as $l): ?>
                <tr>
                    <td><div class="person"><?= avatar_html($l['name'], $l['avatar'] ?? null, (int) $l['id']) ?><div><b><?= e($l['name']) ?></b><small><?= $l['is_active'] ? e($l['email']) : 'Deactivated' ?></small></div></div></td>
                    <td><?= (int) $l['classes'] ?></td>
                    <td><?= (int) $l['students'] ?></td>
                    <td><?= (int) $l['assignments'] ?></td>
                    <td><?= (int) $l['to_grade'] ? pill('warn', (string) (int) $l['to_grade']) : '<span class="muted small">0</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Students needing attention</h2></div>
        <?php if (!$atRisk): ?>
            <div class="empty"><div class="em-ico"><?= icon('check', 28) ?></div><p>No overdue work. Everyone is on track!</p></div>
        <?php else: ?>
        <p class="muted small" style="margin-top:0">Most overdue assignments not handed in.</p>
        <div class="list">
            <?php foreach ($atRisk as $s): ?>
                <div class="item">
                    <?= avatar_html($s['name'], $s['avatar'] ?? null, (int) $s['id']) ?>
                    <span class="grow"><b><?= e($s['name']) ?></b><small><?= e($s['email']) ?></small></span>
                    <?= pill('danger', (int) $s['missing'] . ' overdue') ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
