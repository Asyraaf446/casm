<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me = user();

$course = student_course((int) ($_GET['id'] ?? 0));
$cid    = (int) $course['id'];
$tab    = $_GET['tab'] ?? 'materials';
if (!in_array($tab, ['materials', 'assignments'], true)) $tab = 'materials';

$q = trim((string) ($_GET['q'] ?? ''));
$mWhere = 'class_id = ?';
$mParams = [$cid];
if ($q !== '') {
    $mWhere .= ' AND (title LIKE ? OR description LIKE ? OR original_name LIKE ?)';
    array_push($mParams, "%$q%", "%$q%", "%$q%");
}
$materials = qall("SELECT * FROM materials WHERE $mWhere ORDER BY created_at DESC, id DESC", $mParams);

$assignments = qall(
    'SELECT a.*, s.id AS sub_id, s.submitted_at, s.marks
       FROM assignments a LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
      WHERE a.class_id = ? ORDER BY a.due_date',
    [$me['id'], $cid]
);

$pageTitle = $course['code'] . ' ' . $course['title'];
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('student/courses.php') ?>">My Courses</a> / <?= e($course['code']) ?></div>
        <h1><?= e($course['title']) ?> · Class <?= e($course['class_name']) ?></h1>
        <p class="sub"><?= e($course['description']) ?></p>
    </div>
    <div class="actions"><a class="btn btn-soft" href="<?= url('student/doubts.php?course=' . $cid) ?>"><?= icon('help', 17) ?> Ask a doubt</a>
        <a class="btn btn-soft" href="<?= url('student/attendance.php?course=' . $cid) ?>"><?= icon('clipboard', 17) ?> Attendance</a></div>
</div>

<div class="card c-<?= e($course['color']) ?> mb" style="background:linear-gradient(135deg,var(--tint),#fff)">
    <div style="display:flex;gap:26px;flex-wrap:wrap;align-items:center">
        <span class="code-badge" style="margin:0"><?= e($course['code']) ?></span>
        <div class="meta"><?= icon('user', 17) ?> <?= e($course['lecturer_name']) ?></div>
        <div class="meta"><?= icon('clock', 17) ?> <?= $course['day_of_week'] ? e(day_name((int) $course['day_of_week'])) . ', ' . e(fmt_time($course['start_time'])) . ' – ' . e(fmt_time($course['end_time'])) : 'Schedule not set' ?></div>
        <?php if ($course['room']): ?><div class="meta"><?= icon('pin', 17) ?> <?= e($course['room']) ?></div><?php endif; ?>
    </div>
</div>

<div class="tabs">
    <a class="<?= $tab === 'materials' ? 'active' : '' ?>" href="?id=<?= $cid ?>&tab=materials">Notes &amp; materials (<?= count($materials) ?>)</a>
    <a class="<?= $tab === 'assignments' ? 'active' : '' ?>" href="?id=<?= $cid ?>&tab=assignments">Assignments (<?= count($assignments) ?>)</a>
</div>

<?php if ($tab === 'materials'): ?>
<div class="card">
    <div class="card-head">
        <h2>Notes &amp; materials</h2>
        <form method="get" class="inline-form" style="display:flex;gap:8px">
            <input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="tab" value="materials">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search notes…" style="width:200px">
            <button class="btn btn-soft btn-sm" type="submit"><?= icon('search', 15) ?></button>
        </form>
    </div>
    <?php if (!$materials): ?>
        <div class="empty"><div class="em-ico"><?= icon('file', 28) ?></div><p><?= $q !== '' ? 'No notes match your search.' : 'No notes have been shared yet.' ?></p></div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($materials as $m): ?>
                <div class="item c-<?= e($course['color']) ?>">
                    <span class="ico"><?= icon('file', 20) ?></span>
                    <span class="grow"><b><?= e($m['title']) ?></b><small><?= e($m['original_name']) ?> · <?= e(fmt_date($m['created_at'])) ?><?= $m['description'] ? '<br>' . e($m['description']) : '' ?></small></span>
                    <a class="btn btn-soft btn-sm" href="<?= url('download.php?type=material&id=' . (int) $m['id']) ?>"><?= icon('download', 15) ?> Download</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-head"><h2>Assignments</h2></div>
    <?php if (!$assignments): ?>
        <div class="empty"><div class="em-ico"><?= icon('file', 28) ?></div><p>No assignments posted yet.</p></div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($assignments as $a): $st = submission_status($a['submitted_at'], $a['due_date']); $over = !$a['sub_id'] && strtotime($a['due_date']) < time(); ?>
                <a class="item c-<?= e($course['color']) ?>" href="<?= url('student/assignment_view.php?id=' . (int) $a['id']) ?>" style="color:inherit;text-decoration:none">
                    <span class="ico"><?= icon('file', 20) ?></span>
                    <span class="grow"><b><?= e($a['title']) ?></b><small>Due <?= e(fmt_datetime($a['due_date'])) ?></small></span>
                    <?php if ($a['marks'] !== null): ?><?= status_pill('graded') ?> <b><?= e(rtrim(rtrim(number_format((float) $a['marks'], 2), '0'), '.')) ?>/<?= (int) $a['max_marks'] ?></b>
                    <?php elseif ($a['sub_id']): ?><?= status_pill($st) ?>
                    <?php elseif ($over): ?><?= pill('danger', 'Overdue') ?>
                    <?php else: ?><span class="due <?= strtotime($a['due_date']) - time() < 172800 ? 'soon' : '' ?>"><?= e(due_in_text($a['due_date'])) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
