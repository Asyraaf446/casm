<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me  = user();
$uid = $me['id'];

$stats = [
    'classes'  => (int) qval('SELECT COUNT(*) FROM courses WHERE lecturer_id = ?', [$uid]),
    'students' => (int) qval('SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN classes c ON c.id = e.class_id WHERE c.lecturer_id = ?', [$uid]),
    'assign'   => (int) qval('SELECT COUNT(*) FROM assignments a JOIN classes c ON c.id = a.class_id WHERE c.lecturer_id = ?', [$uid]),
    'tograde'  => (int) qval('SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN classes c ON c.id = a.class_id WHERE c.lecturer_id = ? AND s.marks IS NULL', [$uid]),
];

/* Latest 3 open questions from students */
$topDoubts = qall(
    'SELECT d.id, d.question, d.created_at, c.code, c.title AS course_title, u.name AS author
       FROM doubts d JOIN classes c ON c.id = d.class_id JOIN users u ON u.id = d.student_id
      WHERE c.lecturer_id = ? AND d.is_resolved = 0
      ORDER BY d.created_at DESC LIMIT 3',
    [$uid]
);

$upcoming = qall(
    'SELECT a.id, a.title, a.due_date, c.code, c.color,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = a.class_id) AS total,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submitted
       FROM assignments a JOIN classes c ON c.id = a.class_id
      WHERE c.lecturer_id = ? AND a.due_date >= ? ORDER BY a.due_date LIMIT 5',
    [$uid, date('Y-m-d H:i:s')]
);

$toGrade = qall(
    'SELECT s.submitted_at, u.name AS student, a.id AS aid, a.title, a.due_date, c.code
       FROM submissions s
       JOIN assignments a ON a.id = s.assignment_id
       JOIN classes c ON c.id = a.class_id
       JOIN users u ON u.id = s.student_id
      WHERE c.lecturer_id = ? AND s.marks IS NULL ORDER BY s.submitted_at DESC LIMIT 5',
    [$uid]
);

$todayClasses = qall(
    'SELECT * FROM classes WHERE lecturer_id = ? AND day_of_week = ? ORDER BY start_time',
    [$uid, (int) date('N')]
);

$events = qall(
    'SELECT ev.*, c.code FROM events ev JOIN classes c ON c.id = ev.class_id
      WHERE c.lecturer_id = ? AND ev.event_date >= ? ORDER BY ev.event_date, ev.start_time LIMIT 4',
    [$uid, date('Y-m-d')]
);

$pageTitle = 'Lecturer Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Dashboard</h1></div>
</div>

<div class="grid cols-4 mb">
    <a class="stat lav" href="<?= url('lecturer/courses.php') ?>"><span class="ico"><?= icon('book', 24) ?></span><div><b><?= $stats['classes'] ?></b><span>My courses</span></div></a>
    <a class="stat sky" href="<?= url('lecturer/students.php') ?>"><span class="ico"><?= icon('users', 24) ?></span><div><b><?= $stats['students'] ?></b><span>Students</span></div></a>
    <a class="stat mint" href="<?= url('lecturer/assignments.php?show=all') ?>"><span class="ico"><?= icon('file', 24) ?></span><div><b><?= $stats['assign'] ?></b><span>Assignments</span></div></a>
    <a class="stat peach" href="<?= url('lecturer/assignments.php?show=tograde') ?>"><span class="ico"><?= icon('edit', 24) ?></span><div><b><?= $stats['tograde'] ?></b><span>Waiting to be graded</span></div></a>
</div>

<div class="grid split mb">
    <div class="card">
        <div class="card-head">
            <h2><?= icon('help', 20) ?> Latest open questions</h2>
            <a class="btn btn-soft btn-sm" href="<?= url('lecturer/doubts.php') ?>">Open Discussion</a>
        </div>
        <p class="muted small">The newest questions from your students that are still waiting for an answer.</p>
        <?php if (!$topDoubts): ?>
            <div class="empty"><div class="em-ico"><?= icon('check', 28) ?></div><p>No open questions. Your students are following along nicely!</p></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($topDoubts as $d): ?>
                    <div class="doubt">
                        <div class="q">
                            <p><?= e($d['question']) ?></p>
                            <small class="muted"><?= e($d['author']) ?> · <?= e($d['code']) ?> · <?= e(time_ago($d['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Needs grading</h2></div>
        <?php if (!$toGrade): ?>
            <div class="empty"><div class="em-ico"><?= icon('check', 28) ?></div><p>All submissions are graded.</p></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($toGrade as $s): $st = submission_status($s['submitted_at'], $s['due_date']); ?>
                    <a class="item" href="<?= url('lecturer/assignment_view.php?id=' . (int) $s['aid']) ?>" style="color:inherit;text-decoration:none">
                        <span class="grow"><b><?= e($s['student']) ?></b><small><?= e($s['code']) ?> · <?= e($s['title']) ?></small></span>
                        <?= status_pill($st) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <div class="card-head"><h2>Upcoming deadlines</h2><a class="btn btn-soft btn-sm" href="<?= url('lecturer/assignment_form.php') ?>"><?= icon('plus', 16) ?> New</a></div>
        <?php if (!$upcoming): ?>
            <div class="empty"><p>No upcoming deadlines.</p></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($upcoming as $a): $pct = $a['total'] ? round($a['submitted'] / $a['total'] * 100) : 0; ?>
                    <a class="item c-<?= e($a['color']) ?>" href="<?= url('lecturer/assignment_view.php?id=' . (int) $a['id']) ?>" style="color:inherit;text-decoration:none">
                        <span class="ico"><?= icon('file', 20) ?></span>
                        <span class="grow">
                            <b><?= e($a['title']) ?></b>
                            <small><?= e($a['code']) ?> · due <?= e(fmt_datetime($a['due_date'])) ?> (<?= e(due_in_text($a['due_date'])) ?>)</small>
                            <div class="progress" style="margin-top:6px"><span style="width:<?= $pct ?>%"></span></div>
                        </span>
                        <small class="nowrap"><?= (int) $a['submitted'] ?>/<?= (int) $a['total'] ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Coming up on the calendar</h2><a class="btn btn-soft btn-sm" href="<?= url('lecturer/calendar.php') ?>">Open calendar</a></div>
        <?php if (!$events): ?>
            <div class="empty"><p>No scheduled events. Click a date in the calendar to add a quiz, task or exam.</p></div>
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
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
