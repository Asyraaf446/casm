<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Opening the home page always means signing in again: if someone is still logged in, sign them out first.
if (user()) {
    log_activity('LOGOUT', 'Signed out (opened the home page)');
    end_session();
}
$me = null;
$dashboard = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> · Learning Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= asset('assets/img/logo-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="home">

<header class="home-nav">
    <div class="home-wrap home-nav-in">
        <a class="brand brand-full" href="<?= url('index.php') ?>" style="padding:0">
            <?= logo_full_img(170) ?>
        </a>
        <nav class="home-links">
            <a href="#features">Features</a>
            <a href="#roles">Who it is for</a>
        </nav>
        <?php if ($me): ?>
            <a class="btn btn-sm" href="<?= url($dashboard) ?>">Go to dashboard</a>
        <?php else: ?>
            <a class="btn btn-sm" href="<?= url('login.php') ?>">Sign in</a>
        <?php endif; ?>
    </div>
</header>

<main>
    <!-- Hero -->
    <section class="home-hero">
        <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
        <div class="home-wrap home-hero-in">
            <div class="hero-text">
                <span class="pill pill-primary">Learning Management System</span>
                <h1>Everything your class needs, in one calm place.</h1>
                <p>Notes, assignments, deadlines, online meetings and questions, organised together so nothing gets lost and nobody gets overloaded.</p>
                <div class="hero-cta">
                    <?php if ($me): ?>
                        <a class="btn btn-lg" href="<?= url($dashboard) ?>">Go to my dashboard <?= icon('right', 18) ?></a>
                    <?php else: ?>
                        <a class="btn btn-lg" href="<?= url('login.php') ?>">Sign in <?= icon('right', 18) ?></a>
                    <?php endif; ?>
                    <a class="btn btn-soft btn-lg" href="#features">See what you can do</a>
                </div>
            </div>

            <div class="hero-cards" aria-hidden="true">
                <div class="hc hc-1"><span class="hc-ico c-peach"><?= icon('file', 20) ?></span><div><b>Assignment due</b><small>Database Systems · Friday</small></div></div>
                <div class="hc hc-2"><span class="hc-ico c-mint"><?= icon('video', 20) ?></span><div><b>Meeting is live</b><small>Tap Join now</small></div></div>
                <div class="hc hc-3"><span class="hc-ico c-sky"><?= icon('help', 20) ?></span><div><b>New reply</b><small>Your lecturer answered</small></div></div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="home-section" id="features">
        <div class="home-wrap">
            <div class="home-head">
                <h2>Everything in one place</h2>
                <p>The tools a class uses every week, without juggling five different apps.</p>
            </div>
            <div class="feature-grid">
                <div class="fcard"><span class="fico lav"><?= icon('book', 24) ?></span><h3>Notes &amp; materials</h3><p>Lecturers share notes and files with the class. Students download them any time.</p></div>
                <div class="fcard"><span class="fico peach"><?= icon('file', 24) ?></span><h3>Assignments &amp; grading</h3><p>Set deadlines, collect submissions online, give marks and feedback.</p></div>
                <div class="fcard"><span class="fico sky"><?= icon('calendar', 24) ?></span><h3>Calendar &amp; timetable</h3><p>Deadlines, quizzes, exams and weekly classes appear together, so nothing is missed.</p></div>
                <div class="fcard"><span class="fico mint"><?= icon('video', 24) ?></span><h3>Online meetings</h3><p>Hold a class with Google Meet. Students see a Join button when it goes live.</p></div>
                <div class="fcard"><span class="fico rose"><?= icon('help', 24) ?></span><h3>Discussion</h3><p>Ask a question, get replies from classmates and the lecturer.</p></div>
                <div class="fcard"><span class="fico sun"><?= icon('bell', 24) ?></span><h3>Notifications</h3><p>New work and updates reach you inside CASM, and by email too.</p></div>
            </div>
        </div>
    </section>

    <!-- Roles -->
    <section class="home-section alt" id="roles">
        <div class="home-wrap">
            <div class="home-head">
                <h2>Made for everyone at school</h2>
                <p>Each person sees only what they need.</p>
            </div>
            <div class="role-grid">
                <div class="rcard">
                    <span class="fico lav"><?= icon('users', 24) ?></span>
                    <h3>Students</h3>
                    <ul>
                        <li>Join classes and see every deadline</li>
                        <li>Submit homework and read feedback</li>
                        <li>Ask questions in the Discussion</li>
                    </ul>
                </div>
                <div class="rcard">
                    <span class="fico sky"><?= icon('user', 24) ?></span>
                    <h3>Lecturers</h3>
                    <ul>
                        <li>Share notes and set assignments</li>
                        <li>Grade work and track who submitted</li>
                        <li>Schedule meetings and answer questions in the Discussion</li>
                    </ul>
                </div>
                <div class="rcard">
                    <span class="fico rose"><?= icon('key', 24) ?></span>
                    <h3>Administrators</h3>
                    <ul>
                        <li>Manage accounts and classes</li>
                        <li>See statistics and analytics</li>
                        <li>Review the activity log</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to action -->
    <section class="home-section">
        <div class="home-wrap">
            <div class="home-cta">
                <h2><?= $me ? 'Welcome back!' : 'Ready to get started?' ?></h2>
                <p><?= $me ? 'Pick up where you left off.' : 'Sign in with your school account to open your classes.' ?></p>
                <a class="btn btn-lg" href="<?= url($me ? $dashboard : 'login.php') ?>"><?= $me ? 'Go to my dashboard' : 'Sign in' ?> <?= icon('right', 18) ?></a>
            </div>
        </div>
    </section>
</main>

<footer class="home-foot">
    <div class="home-wrap">
        <span>© <?= date('Y') ?> <?= e(APP_NAME) ?> · Learning Management System</span>
    </div>
</footer>

</body>
</html>
