<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me = user();

$ttCourses = qall(
    'SELECT c.*, u.name AS extra FROM classes c
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
       JOIN users u ON u.id = c.lecturer_id
      ORDER BY c.day_of_week, c.start_time',
    [$me['id']]
);

$pageTitle = 'My Timetable';
$active = 'timetable';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>My timetable</h1></div>
</div>
<?php require __DIR__ . '/../includes/timetable_view.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
