<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me = user();

$calEvents  = calendar_events($me);
$calCanAdd  = false;
$calCourses = [];

$pageTitle = 'Calendar';
$active = 'calendar';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Calendar</h1></div>
</div>
<?php require __DIR__ . '/../includes/calendar_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
