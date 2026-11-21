<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me  = user();
$uid = $me['id'];
$now = date('Y-m-d H:i:s');

$enrolled = (int) qval('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$uid]);

/* all assignments in my classes with my submission state */
$all = qall(
    'SELECT a.id, a.title, a.due_date, a.max_marks, c.code, c.color, s.id AS sub_id, s.submitted_at, s.marks
       FROM assignments a
       JOIN classes c ON c.id = a.class_id
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
       LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
      ORDER BY a.due_date',
    [$uid, $uid]
);
$todo = $done = $overdue = [];
foreach ($all as $a) {
    if ($a['sub_id']) { $done[] = $a; continue; }
    if ($a['due_date'] < $now) $overdue[] = $a; else $todo[] = $a;
}

$todayClasses = qall(
    'SELECT c.*, u.name AS lecturer_name FROM classes c
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
       JOIN users u ON u.id = c.lecturer_id
      WHERE c.day_of_week = ? ORDER BY c.start_time',
    [$uid, (int) date('N')]
);

$events = qall(
    'SELECT ev.*, c.code FROM events ev
       JOIN classes c ON c.id = ev.class_id
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
      WHERE ev.event_date >= ? ORDER BY ev.event_date, ev.start_time LIMIT 4',
    [$uid, date('Y-m-d')]
);


$pageTitle = 'My Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Dashboard</h1></div>
</div>

<div class="grid cols-4 mb">
    <a class="stat lav" href="<?= url('student/courses.php') ?>"><span class="ico"><?= icon('book', 24) ?></span><div><b><?= $enrolled ?></b><span>My classes</span></div></a>
    <a class="stat sky" href="<?= url('student/assignments.php?show=todo') ?>"><span class="ico"><?= icon('file', 24) ?></span><div><b><?= count($todo) ?></b><span>To do</span></div></a>
    <a class="stat rose" href="<?= url('student/assignments.php?show=overdue') ?>"><span class="ico"><?= icon('alert', 24) ?></span><div><b><?= count($overdue) ?></b><span>Overdue</span></div></a>
    <a class="stat mint" href="<?= url('student/assignments.php?show=done') ?>"><span class="ico"><?= icon('check', 24) ?></span><div><b><?= count($done) ?></b><span>Handed in</span></div></a>
</div>

<div class="grid split mb">
    <div class="card">
        <div class="card-head"><h2>Upcoming &amp; overdue</h2><a class="btn btn-soft btn-sm" href="<?= url('student/assignments.php') ?>">See all</a></div>
        <?php $list = array_merge($overdue, $todo); if (!$list): ?>
            <div class="empty"><div class="em-ico"><?= icon('check', 28) ?></div><p>Nothing pending. Great job!</p></div>
        <?php else: ?>
            <div class="list">
                <?php foreach (array_slice($list, 0, 6) as $a): $late = $a['due_date'] < $now; ?>
                    <a class="item c-<?= e($a['color']) ?>" href="<?= url('student/assignment_view.php?id=' . (int) $a['id']) ?>" style="color:inherit;text-decoration:none">
                        <span class="ico"><?= icon('file', 20) ?></span>
                        <span class="grow"><b><?= e($a['title']) ?></b><small><?= e($a['code']) ?> · <?= e(fmt_datetime($a['due_date'])) ?></small></span>
                        <span class="due <?= $late ? 'over' : (strtotime($a['due_date']) - time() < 172800 ? 'soon' : '') ?>"><?= $late ? 'Overdue' : e(due_in_text($a['due_date'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Today's classes</h2><a class="btn btn-soft btn-sm" href="<?= url('student/timetable.php') ?>">Timetable</a></div>
        <?php if (!$todayClasses): ?>
            <div class="empty"><div class="em-ico"><?= icon('clock', 28) ?></div><p>No classes today.</p></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($todayClasses as $c): ?>
                    <div class="item c-<?= e($c['color']) ?>">
                        <span class="ico"><?= icon('book', 20) ?></span>
                        <span class="grow"><b><?= e($c['code']) ?> · <?= e($c['title']) ?></b>
                            <small><?= e(fmt_time($c['start_time'])) ?> – <?= e(fmt_time($c['end_time'])) ?><?= $c['room'] ? ' · ' . e($c['room']) : '' ?></small></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

    <div class="card">
        <div class="card-head"><h2>Coming up</h2><a class="btn btn-soft btn-sm" href="<?= url('student/calendar.php') ?>">Calendar</a></div>
        <?php if (!$events): ?>
            <div class="empty"><p>No quizzes or exams scheduled.</p></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($events as $ev): ?>
                    <div class="item">
                        <span class="pill pill-info"><?= e(date('d M', strtotime($ev['event_date']))) ?></span>
                        <span class="grow"><b><?= e($ev['title']) ?></b><small><?= e($ev['code']) ?> · <?= e(ucfirst($ev['event_type'])) ?><?= $ev['start_time'] ? ' · ' . e(fmt_time($ev['start_time'])) : '' ?></small></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
