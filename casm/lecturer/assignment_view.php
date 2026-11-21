<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$id = (int) ($_GET['id'] ?? 0);
$a  = qone(
    'SELECT a.*, c.code, c.title AS course_title, c.color, c.lecturer_id
       FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.id = ?',
    [$id]
);
if (!$a || (int) $a['lecturer_id'] !== (int) $me['id']) {
    flash('error', 'That assignment could not be found.');
    redirect('lecturer/assignments.php');
}
$self = 'lecturer/assignment_view.php?id=' . $id;

/* ---------------- actions ---------------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'grade') {
        $s = qone('SELECT s.*, u.name AS student FROM submissions s JOIN users u ON u.id = s.student_id WHERE s.id = ? AND s.assignment_id = ?', [(int) ($_POST['submission_id'] ?? 0), $id]);
        $marksRaw = post('marks');
        if (!$s) {
            flash('error', 'Submission not found.');
        } elseif ($marksRaw === '' || !is_numeric($marksRaw) || (float) $marksRaw < 0 || (float) $marksRaw > $a['max_marks']) {
            flash('error', 'Marks must be a number between 0 and ' . $a['max_marks'] . '.');
        } else {
            $marks = round((float) $marksRaw, 2);
            qrun('UPDATE submissions SET marks = ?, feedback = ?, graded_at = ? WHERE id = ?', [$marks, post('feedback'), date('Y-m-d H:i:s'), $s['id']]);
            log_activity('GRADE_SUBMISSION', 'Graded ' . $s['student'] . ' for "' . $a['title'] . '" (' . $marks . '/' . $a['max_marks'] . ')');
            notify((int) $s['student_id'], 'Assignment graded', 'Your "' . $a['title'] . '" was graded: ' . rtrim(rtrim(number_format($marks, 2), '0'), '.') . ' / ' . $a['max_marks'] . '.', 'student/assignment_view.php?id=' . $id);
            flash('success', 'Saved the grade for ' . $s['student'] . '.');
        }
        redirect($self);
    }

    if ($action === 'remind_one' || $action === 'remind_all') {
        $pending = qall(
            'SELECT u.id, u.name FROM enrollments e JOIN users u ON u.id = e.student_id
              WHERE e.class_id = ? AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = ? AND s.student_id = u.id)',
            [$a['class_id'], $id]
        );
        if ($action === 'remind_one') {
            $pending = array_values(array_filter($pending, function ($p) { return (int) $p['id'] === (int) ($_POST['student_id'] ?? 0); }));
        }
        $past = strtotime($a['due_date']) < time();
        foreach ($pending as $p) {
            notify(
                (int) $p['id'],
                'Reminder: ' . $a['title'],
                $past ? 'Your submission for "' . $a['title'] . '" (' . $a['code'] . ') is overdue. Please submit as soon as possible.'
                      : 'You have not submitted "' . $a['title'] . '" (' . $a['code'] . ') yet. It is due ' . fmt_datetime($a['due_date']) . '.',
                'student/assignment_view.php?id=' . $id
            );
        }
        if ($pending) {
            log_activity('SEND_REMINDER', 'Reminded ' . count($pending) . ' student(s) about "' . $a['title'] . '" (' . $a['code'] . ')');
            flash('success', count($pending) === 1 ? 'Reminder sent to ' . $pending[0]['name'] . '.' : 'Reminder sent to ' . count($pending) . ' students.');
        } else {
            flash('info', 'There is nobody to remind. Everyone has submitted.');
        }
        redirect($self);
    }

    if ($action === 'delete_assignment') {
        foreach (qall('SELECT file_path FROM submissions WHERE assignment_id = ?', [$id]) as $f) delete_upload($f['file_path']);
        qrun('DELETE FROM assignments WHERE id = ?', [$id]);
        log_activity('DELETE_ASSIGNMENT', 'Deleted "' . $a['title'] . '" from ' . $a['code']);
        flash('success', 'Assignment deleted.');
        redirect('lecturer/assignments.php');
    }
}

/* ---------------- data ---------------- */
$rows = qall(
    'SELECT u.id AS student_id, u.name, u.email, u.avatar,
            s.id AS sub_id, s.original_name, s.submitted_at, s.note, s.marks, s.feedback
       FROM enrollments e
       JOIN users u ON u.id = e.student_id
       LEFT JOIN submissions s ON s.student_id = u.id AND s.assignment_id = ?
      WHERE e.class_id = ? ORDER BY u.name',
    [$id, $a['class_id']]
);

$counts = ['submitted' => 0, 'late' => 0, 'pending' => 0];
$graded = 0;
foreach ($rows as &$r) {
    $r['status'] = submission_status($r['submitted_at'], $a['due_date']);
    $counts[$r['status']]++;
    if ($r['marks'] !== null) $graded++;
}
unset($r);
$total     = count($rows);
$received  = $counts['submitted'] + $counts['late'];
$pct       = function (int $n) use ($total) { return $total ? round($n / $total * 100) : 0; };
$pendingRows = array_values(array_filter($rows, function ($r) { return $r['status'] === 'pending'; }));
$isPast = strtotime($a['due_date']) < time();

$pageTitle = $a['title'];
$active = 'assignments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/assignments.php') ?>">Assignments</a> / <?= e($a['code']) ?></div>
        <h1><?= e($a['title']) ?></h1>
        <p class="sub"><?= e($a['code'] . ' · ' . $a['course_title']) ?> · Due <b><?= e(fmt_datetime($a['due_date'])) ?></b>
            <span class="due <?= $isPast ? 'over' : 'soon' ?>">(<?= e(due_in_text($a['due_date'])) ?>)</span> · <?= (int) $a['max_marks'] ?> marks</p>
    </div>
    <div class="actions">
        <a class="btn btn-soft" href="<?= url('lecturer/assignment_form.php?id=' . $id) ?>"><?= icon('edit', 17) ?> Edit</a>
        <form method="post" class="inline-form" data-confirm="Delete this assignment and all its submissions?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_assignment">
            <button class="btn btn-danger" type="submit"><?= icon('trash', 17) ?> Delete</button>
        </form>
    </div>
</div>

<?php if ($a['description']): ?>
<div class="card flat mb"><h4>Instructions</h4><p class="desc" style="margin:0"><?= e($a['description']) ?></p></div>
<?php endif; ?>

<div class="grid cols-2 mb">
    <!-- Submission audit & analytics -->
    <div class="card">
        <div class="card-head"><h2>Submission analytics</h2><span class="pill pill-primary"><?= $total ?> student<?= $total === 1 ? '' : 's' ?></span></div>
        <?php if ($total === 0): ?>
            <div class="empty"><p>No students are enrolled in this class yet.</p></div>
        <?php else: ?>
        <div class="analytics">
            <?= donut_svg([
                ['Submitted', $counts['submitted'], '#2563eb'],
                ['Late',      $counts['late'],      '#38bdf8'],
                ['Pending',   $counts['pending'],   '#94a3b8'],
            ], $pct($received) . '%', 'handed in') ?>
            <div class="legend">
                <div class="legend-row"><span class="sw" style="background:#2563eb"></span><b><?= $counts['submitted'] ?></b><span>Submitted on time <span class="muted">(<?= $pct($counts['submitted']) ?>%)</span></span></div>
                <div class="legend-row"><span class="sw" style="background:#38bdf8"></span><b><?= $counts['late'] ?></b><span>Late <span class="muted">(<?= $pct($counts['late']) ?>%)</span></span></div>
                <div class="legend-row"><span class="sw" style="background:#94a3b8"></span><b><?= $counts['pending'] ?></b><span>Pending <span class="muted">(<?= $pct($counts['pending']) ?>%)</span></span></div>
                <div>
                    <div class="progress"><span style="width:<?= $received ? round($graded / $received * 100) : 0 ?>%"></span></div>
                    <small class="muted">Graded <?= $graded ?> of <?= $received ?> submission<?= $received === 1 ? '' : 's' ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Flagged: not submitted -->
    <div class="card">
        <div class="card-head">
            <h2>Not submitted yet</h2>
            <?php if ($pendingRows): ?>
                <form method="post" class="inline-form" data-confirm="Send a reminder to all <?= count($pendingRows) ?> pending student(s)?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="remind_all">
                    <button class="btn btn-sm" type="submit"><?= icon('send', 15) ?> Remind all</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$pendingRows): ?>
            <div class="empty"><div class="em-ico"><?= icon('check', 28) ?></div><p>Everyone has submitted. Well done, class!</p></div>
        <?php else: ?>
            <div class="list" style="max-height:290px;overflow:auto">
                <?php foreach ($pendingRows as $p): ?>
                    <div class="item">
                        <?= avatar_html($p['name'], $p['avatar'] ?? null, (int) $p['student_id'], 'width:34px;height:34px;font-size:.75rem') ?>
                        <span class="grow"><b><?= e($p['name']) ?></b><small><?= $isPast ? 'Overdue' : 'Not yet handed in' ?></small></span>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="remind_one"><input type="hidden" name="student_id" value="<?= (int) $p['student_id'] ?>">
                            <button class="btn btn-soft btn-sm" type="submit"><?= icon('bell', 14) ?> Notify</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Submissions &amp; grading</h2></div>
    <?php if (!$rows): ?>
        <div class="empty"><p>No students enrolled.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Status</th><th>Submitted</th><th>File</th><th>Marks &amp; feedback</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><div class="person"><?= avatar_html($r['name'], $r['avatar'] ?? null, (int) $r['student_id']) ?><div><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></div></div></td>
                <td><?= status_pill($r['status']) ?><?= $r['marks'] !== null ? ' ' . status_pill('graded') : '' ?></td>
                <td class="small nowrap"><?= $r['submitted_at'] ? e(fmt_datetime($r['submitted_at'])) : '<span class="muted">-</span>' ?></td>
                <td class="small">
                    <?php if ($r['sub_id']): ?>
                        <a href="<?= url('download.php?type=submission&id=' . (int) $r['sub_id']) ?>"><?= icon('download', 14) ?> <?= e(mb_strimwidth($r['original_name'], 0, 26, '…')) ?></a>
                        <?php if ($r['note']): ?><br><span class="muted">“<?= e(mb_strimwidth($r['note'], 0, 60, '…')) ?>”</span><?php endif; ?>
                    <?php else: ?><span class="muted">-</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($r['sub_id']): ?>
                        <form method="post" class="grade-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="grade"><input type="hidden" name="submission_id" value="<?= (int) $r['sub_id'] ?>">
                            <input type="number" name="marks" step="0.5" min="0" max="<?= (int) $a['max_marks'] ?>" value="<?= $r['marks'] !== null ? e(rtrim(rtrim((string) $r['marks'], '0'), '.')) : '' ?>" placeholder="/<?= (int) $a['max_marks'] ?>" required>
                            <input type="text" name="feedback" value="<?= e($r['feedback']) ?>" placeholder="Feedback" maxlength="500">
                            <button class="btn btn-soft btn-sm" type="submit">Save</button>
                        </form>
                    <?php else: ?><span class="muted small">Waiting for submission</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
