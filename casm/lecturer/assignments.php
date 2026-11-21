<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$show = $_GET['show'] ?? 'upcoming';
if (!in_array($show, ['upcoming', 'past', 'all', 'tograde'], true)) $show = 'upcoming';
$cid = (int) ($_GET['course'] ?? 0);

$where = ['c.lecturer_id = ?'];
$params = [$me['id']];
if ($cid) { $where[] = 'c.id = ?'; $params[] = $cid; }
if ($show === 'upcoming') { $where[] = 'a.due_date >= ?'; $params[] = date('Y-m-d H:i:s'); }
if ($show === 'past')     { $where[] = 'a.due_date < ?';  $params[] = date('Y-m-d H:i:s'); }
if ($show === 'tograde')  { $where[] = 'EXISTS (SELECT 1 FROM submissions sg WHERE sg.assignment_id = a.id AND sg.marks IS NULL)'; }

$rows = qall(
    'SELECT a.*, c.code, c.color, c.title AS course_title,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = a.class_id) AS total,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submitted,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.marks IS NOT NULL) AS graded
       FROM assignments a JOIN classes c ON c.id = a.class_id
      WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . ($show === 'past' ? 'a.due_date DESC' : 'a.due_date ASC'),
    $params
);
$myCourses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);

$pageTitle = 'Assignments';
$active = 'assignments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Assignments</h1></div>
    <div class="actions"><a class="btn" href="<?= url('lecturer/assignment_form.php') ?>"><?= icon('plus', 18) ?> New assignment</a></div>
</div>

<div class="filters">
    <div class="tabs filter" style="margin:0">
        <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'tograde' => 'To grade', 'all' => 'All'] as $k => $label): ?>
            <a class="<?= $show === $k ? 'active' : '' ?>" href="?show=<?= $k ?><?= $cid ? '&course=' . $cid : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" class="inline-form">
        <input type="hidden" name="show" value="<?= e($show) ?>">
        <select name="course" onchange="this.form.submit()">
            <option value="0">All classes</option>
            <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty"><div class="em-ico"><?= icon('file', 28) ?></div><p>No assignments here yet.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Assignment</th><th>Class</th><th>Due</th><th style="min-width:160px">Submitted</th><th>Graded</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $a): $pct = $a['total'] ? round($a['submitted'] / $a['total'] * 100) : 0; $over = strtotime($a['due_date']) < time(); ?>
            <tr>
                <td><b><a href="<?= url('lecturer/assignment_view.php?id=' . (int) $a['id']) ?>" style="color:inherit"><?= e($a['title']) ?></a></b></td>
                <td class="c-<?= e($a['color']) ?>"><span class="dotc"></span> <?= e($a['code']) ?></td>
                <td class="small nowrap"><?= e(fmt_datetime($a['due_date'])) ?><br><span class="due <?= $over ? 'over' : '' ?>"><?= e(due_in_text($a['due_date'])) ?></span></td>
                <td><div class="progress"><span style="width:<?= $pct ?>%"></span></div><small class="muted"><?= (int) $a['submitted'] ?> of <?= (int) $a['total'] ?> (<?= $pct ?>%)</small></td>
                <td><?= (int) $a['graded'] ?>/<?= (int) $a['submitted'] ?></td>
                <td><div class="row-actions">
                    <a class="btn btn-soft btn-sm" href="<?= url('lecturer/assignment_view.php?id=' . (int) $a['id']) ?>">Open</a>
                    <a class="btn btn-soft btn-sm" href="<?= url('lecturer/assignment_form.php?id=' . (int) $a['id']) ?>"><?= icon('edit', 15) ?></a>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
