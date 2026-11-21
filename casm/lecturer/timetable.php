<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$ttCourses = qall(
    "SELECT c.*, CONCAT((SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id), ' students') AS extra
       FROM classes c WHERE c.lecturer_id = ? ORDER BY c.day_of_week, c.start_time",
    [$me['id']]
);

$pageTitle = 'Teaching Timetable';
$active = 'timetable';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Teaching timetable</h1></div>
</div>
<?php require __DIR__ . '/../includes/timetable_view.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
