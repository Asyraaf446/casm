<?php
/**
 * Shared page layout (top). Set $pageTitle and $active before including.
 */
require_once __DIR__ . '/bootstrap.php';
require_login();

$me        = user();
$pageTitle = $pageTitle ?? APP_NAME;
$active    = $active ?? '';
$unread    = (int) qval('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$me['id']]);
$meRow     = qone('SELECT avatar, email FROM users WHERE id = ?', [$me['id']]) ?: [];
$meAvatar  = $meRow['avatar'] ?? null ?: null;
$meEmail   = $meRow['email'] ?? '';

/* number of meetings happening right now (for the LIVE badge in the menu) */
$liveMeetings = 0;
if (in_array($me['role'], ['lecturer', 'student'], true) && meetings_ready()) {
    try {
        expire_old_meetings();
        $liveMeetings = $me['role'] === 'lecturer'
            ? (int) qval("SELECT COUNT(*) FROM meetings m JOIN classes c ON c.id = m.class_id WHERE m.status = 'live' AND c.lecturer_id = ?", [$me['id']])
            : (int) qval("SELECT COUNT(*) FROM meetings m JOIN enrollments e ON e.class_id = m.class_id WHERE m.status = 'live' AND e.student_id = ?", [$me['id']]);
    } catch (Throwable $t) {
        $liveMeetings = 0;
    }
}

/* number of attendance sessions a student can tick right now (for the OPEN badge in the menu) */
$openAttendance = 0;
if ($me['role'] === 'student' && attendance_ready()) {
    try {
        expire_old_attendance();
        $openAttendance = (int) qval(
            "SELECT COUNT(*) FROM attendance_sessions s
               JOIN enrollments e ON e.class_id = s.class_id AND e.student_id = ?
               LEFT JOIN attendance_records r ON r.session_id = s.id AND r.student_id = e.student_id
              WHERE s.status = 'open' AND r.id IS NULL",
            [$me['id']]
        );
    } catch (Throwable $t) {
        $openAttendance = 0;
    }
}

$navs = [
    'admin' => [
        ['dashboard', 'Dashboard',     'grid',     'admin/dashboard.php'],
        ['users',     'Users',         'users',    'admin/users.php'],
        ['courses',   'Courses',       'book',     'admin/courses.php'],
        ['attendance', 'Attendance',   'clipboard', 'admin/attendance.php'],
        ['analytics', 'Analytics',     'chart',    'admin/analytics.php'],
        ['logs',      'Activity Logs', 'activity', 'admin/logs.php'],
        ['search',    'Search',        'search',   'search.php'],
    ],
    'lecturer' => [
        ['dashboard',   'Dashboard',    'grid',     'lecturer/dashboard.php'],
        ['courses',     'My Courses',   'book',     'lecturer/courses.php'],
        ['assignments', 'Assignments',  'file',     'lecturer/assignments.php'],
        ['calendar',    'Calendar',     'calendar', 'lecturer/calendar.php'],
        ['meetings',    'Meetings',     'video',    'lecturer/meetings.php'],
        ['attendance',  'Attendance',   'clipboard', 'lecturer/attendance.php'],
        ['timetable',   'Timetable',    'clock',    'lecturer/timetable.php'],
        ['doubts',      'Discussion',  'help',     'lecturer/doubts.php'],
        ['forum',       'Forum',       'forum',    'lecturer/forum.php'],
        ['search',      'Search',       'search',   'search.php'],
    ],
    'student' => [
        ['dashboard',   'Dashboard',    'grid',     'student/dashboard.php'],
        ['courses',     'My Courses',   'book',     'student/courses.php'],
        ['assignments', 'Assignments',  'file',     'student/assignments.php'],
        ['timetable',   'Timetable',    'clock',    'student/timetable.php'],
        ['calendar',    'Calendar',     'calendar', 'student/calendar.php'],
        ['meetings',    'Meetings',     'video',    'student/meetings.php'],
        ['attendance',  'Attendance',   'clipboard', 'student/attendance.php'],
        ['doubts',     'Discussion',  'help',     'student/doubts.php'],
        ['forum',      'Forum',       'forum',    'student/forum.php'],
        ['search',      'Search',       'search',   'search.php'],
    ],
];
$roleLabel = ['admin' => 'Administrator', 'lecturer' => 'Lecturer', 'student' => 'Student'][$me['role']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= asset('assets/img/logo-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
<script>try { if (localStorage.getItem('casmNav') === 'closed' && window.innerWidth > 860) document.body.classList.add('nav-collapsed'); } catch (e) {}</script>
<div class="overlay" id="overlay"></div>
<div class="app">

    <aside class="sidebar" id="sidebar">
        <a class="brand brand-full" href="<?= url(home_for($me['role'])) ?>">
            <?= logo_full_img(215) ?>
        </a>

        <a class="side-profile" href="<?= url('profile.php') ?>" title="My profile">
            <?= avatar_html($me['name'], $meAvatar, (int) $me['id'], 'width:68px;height:68px;font-size:1.4rem') ?>
            <span class="who">
                <span class="welcome">Welcome</span>
                <b><?= e($me['name']) ?></b>
                <small><?= e($roleLabel) ?></small>
                <small><?= e($meEmail) ?></small>
            </span>
        </a>

        <div class="nav-heading">Navigation</div>
        <nav class="nav">
            <?php foreach ($navs[$me['role']] as [$key, $label, $ico, $href]): ?>
                <a href="<?= url($href) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <?= icon($ico, 20) ?><span><?= e($label) ?></span>
                    <?php if ($key === 'meetings' && $liveMeetings > 0): ?><em class="nav-live">LIVE</em><?php endif; ?>
                    <?php if ($key === 'attendance' && $openAttendance > 0): ?><em class="nav-live">OPEN</em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="nav-heading" style="padding-top:0">Account</div>
            <a class="nav-logout" href="<?= url('profile.php') ?>"><?= icon('user', 18) ?><span>My profile</span></a>
            <a class="nav-logout" href="<?= url('logout.php') ?>"><?= icon('logout', 18) ?><span>Sign out</span></a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menuToggle" type="button" aria-label="Open or close the menu" title="Open / close menu" aria-expanded="true"><?= icon('menu', 22) ?></button>
            <?php if ($active !== 'dashboard'): ?>
            <a class="back-btn" id="backBtn" href="<?= url(home_for($me['role'])) ?>" title="Go back">
                <?= icon('back', 18) ?><span>Back</span>
            </a>
            <?php endif; ?>
            <form class="search-box" action="<?= url('search.php') ?>" method="get">
                <?= icon('search', 18) ?>
                <input type="search" name="q" placeholder="Search courses, notes, assignments…" value="<?= e($_GET['q'] ?? '') ?>">
            </form>
            <div class="top-actions">
                <a class="icon-btn" href="<?= url('notifications.php') ?>" title="Notifications" aria-label="Notifications">
                    <?= icon('bell', 20) ?>
                    <?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                </a>
            </div>
        </header>

        <section class="content">
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
