<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/forum.php';
require_role('lecturer');
$me = user();
if (!forum_ready()) {
    flash('error', 'Please run database/update_add_forum.sql first.');
    redirect('lecturer/forum.php');
}

$id = (int) ($_GET['id'] ?? 0);
$topic = null;
if ($id) {
    $topic = forum_topic_for_user($id, $me);
    if (!$topic) {
        http_response_code(404);
        exit('Topic not found.');
    }
}
$courses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);
$form = [
    'class_id' => $topic['class_id'] ?? ($_GET['class_id'] ?? ''),
    'title'     => $topic['title'] ?? '',
    'body'      => $topic['body'] ?? '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    foreach (['class_id', 'title', 'body'] as $k) $form[$k] = post($k);
    $course = qone('SELECT * FROM classes WHERE id = ? AND lecturer_id = ?', [(int) $form['class_id'], $me['id']]);
    if (!$course) $errors[] = 'Please choose one of your classes.';
    if ($form['title'] === '' || mb_strlen($form['title']) > 150) $errors[] = 'Please enter a title (up to 150 characters).';
    if (mb_strlen($form['body']) < 2 || mb_strlen($form['body']) > 3000) $errors[] = 'Please write the topic text (up to 3000 characters).';

    if (!$errors) {
        if ($id) {
            qrun('UPDATE forum_topics SET class_id = ?, title = ?, body = ? WHERE id = ?', [$course['id'], $form['title'], $form['body'], $id]);
            log_activity('UPDATE_FORUM_TOPIC', 'Edited forum topic "' . $form['title'] . '" (' . $course['code'] . ')');
            flash('success', 'Topic updated.');
            redirect('forum_topic.php?id=' . $id);
        }
        qrun('INSERT INTO forum_topics (class_id, created_by, title, body, created_at) VALUES (?,?,?,?,?)',
            [$course['id'], $me['id'], $form['title'], $form['body'], date('Y-m-d H:i:s')]);
        $newId = last_id();
        log_activity('CREATE_FORUM_TOPIC', 'Started forum topic "' . $form['title'] . '" in ' . $course['code']);
        foreach (enrolled_student_ids((int) $course['id']) as $sid) {
            notify($sid, 'New forum topic', $course['code'] . ' · ' . $form['title'], 'forum_topic.php?id=' . $newId);
        }
        flash('success', 'Topic posted and your students were notified.');
        redirect('forum_topic.php?id=' . $newId);
    }
}

$pageTitle = $id ? 'Edit Topic' : 'New Topic';
$active = 'forum';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/forum.php') ?>">Forum</a> / <?= $id ? 'Edit' : 'New' ?></div>
        <h1><?= $id ? 'Edit topic' : 'Start a new topic' ?></h1>
    </div>
</div>
<div class="card" style="max-width:820px">
    <?php if (!$courses): ?>
        <div class="empty"><p>You need a class first before you can start a topic.</p></div>
    <?php else: ?>
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full"><label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <option value="">Choose a class</option>
                    <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $form['class_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field full"><label for="title">Topic title</label><input type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="150" placeholder="Week 5 revision: what is still unclear?" required></div>
            <div class="field full"><label for="body">Topic text</label><textarea id="body" name="body" rows="7" maxlength="3000" placeholder="Explain the topic or ask the question you want your students to answer." required><?= e($form['body']) ?></textarea></div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Post topic' ?></button>
            <a class="btn btn-ghost" href="<?= url($id ? 'forum_topic.php?id=' . $id : 'lecturer/forum.php') ?>">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
