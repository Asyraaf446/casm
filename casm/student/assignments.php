<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me  = user();
$now = date('Y-m-d H:i:s');

$filter = $_GET['show'] ?? 'todo';
if (!in_array($filter, ['todo', 'overdue', 'done', 'all'], true)) $filter = 'todo';

$rows = qall(
    'SELECT a.id, a.title, a.due_date, a.max_marks, c.code, c.color, s.id AS sub_id, s.submitted_at, s.marks
       FROM assignments a
       JOIN classes c ON c.id = a.class_id
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
       LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
      ORDER BY a.due_date',
    [$me['id'], $me['id']]
);
$nowStr = date('Y-m-d H:i:s');
$counts = ['todo' => 0, 'overdue' => 0, 'done' => 0, 'all' => count($rows)];
foreach ($rows as $r) {
    if ($r['sub_id']) { $counts['done']++; continue; }
    $counts['todo']++;
    if ($r['due_date'] < $nowStr) $counts['overdue']++;
}
$rows = array_values(array_filter($rows, function ($r) use ($filter, $nowStr) {
    if ($filter === 'all') return true;
    if ($filter === 'overdue') return !$r['sub_id'] && $r['due_date'] < $nowStr;
    return $filter === 'todo' ? !$r['sub_id'] : (bool) $r['sub_id'];
}));

$pageTitle = 'My Assignments';
$active = 'assignments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>My Assignments</h1></div>
</div>

<div class="tabs">
    <a class="<?= $filter === 'todo' ? 'active' : '' ?>" href="?show=todo">To do (<?= $counts['todo'] ?>)</a>
    <a class="<?= $filter === 'overdue' ? 'active' : '' ?>" href="?show=overdue">Overdue (<?= $counts['overdue'] ?>)</a>
    <a class="<?= $filter === 'done' ? 'active' : '' ?>" href="?show=done">Handed in (<?= $counts['done'] ?>)</a>
    <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="?show=all">All (<?= $counts['all'] ?>)</a>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty"><div class="em-ico"><?= icon('check', 28) ?></div><p><?= $filter === 'todo' ? 'Nothing left to do. Great job!' : 'Nothing to show here yet.' ?></p></div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($rows as $a): $st = submission_status($a['submitted_at'], $a['due_date']); $over = !$a['sub_id'] && $a['due_date'] < $now; ?>
            <a class="item c-<?= e($a['color']) ?>" href="<?= url('student/assignment_view.php?id=' . (int) $a['id']) ?>" style="color:inherit;text-decoration:none">
                <span class="ico"><?= icon('file', 20) ?></span>
                <span class="grow"><b><?= e($a['title']) ?></b><small><?= e($a['code']) ?> · due <?= e(fmt_datetime($a['due_date'])) ?></small></span>
                <?php if ($a['marks'] !== null): ?><?= status_pill('graded') ?> <b><?= e(rtrim(rtrim(number_format((float) $a['marks'], 2), '0'), '.')) ?>/<?= (int) $a['max_marks'] ?></b>
                <?php elseif ($a['sub_id']): ?><?= status_pill($st) ?>
                <?php elseif ($over): ?><?= pill('danger', 'Overdue') ?>
                <?php else: ?><span class="due <?= strtotime($a['due_date']) - time() < 172800 ? 'soon' : '' ?>"><?= e(due_in_text($a['due_date'])) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
