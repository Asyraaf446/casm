<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('student');
$me = user();

$id = (int) ($_GET['id'] ?? 0);
$a  = qone(
    'SELECT a.*, c.code, c.title AS course_title, c.color, c.id AS cid, u.name AS lecturer_name, c.lecturer_id
       FROM assignments a
       JOIN classes c ON c.id = a.class_id
       JOIN users u ON u.id = c.lecturer_id
       JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
      WHERE a.id = ?',
    [$me['id'], $id]
);
if (!$a) {
    flash('error', 'That assignment could not be found in your classes.');
    redirect('student/assignments.php');
}
$self = 'student/assignment_view.php?id=' . $id;
$sub  = qone('SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?', [$id, $me['id']]);

if (is_post()) {
    csrf_verify();

    // submissions are closed once the due date has passed (the lecturer can reopen by moving the due date)
    if (in_array(post('action'), ['submit', 'unsubmit'], true) && strtotime($a['due_date']) < time()) {
        flash('error', 'The deadline has passed, so submissions are closed. Please speak to your lecturer if you need more time.');
        redirect($self);
    }

    if (post('action') === 'submit') {
        try {
            $stored = save_upload($_FILES['file'] ?? [], 'submissions');
            $now = date('Y-m-d H:i:s');
            $note = post('note');
            $late = strtotime($now) > strtotime($a['due_date']);

            if ($sub) {
                // updating homework: replace the old file, grade is reset because the work changed
                delete_upload($sub['file_path']);
                qrun('UPDATE submissions SET file_path=?, original_name=?, note=?, submitted_at=?, marks=NULL, feedback=NULL, graded_at=NULL WHERE id=?',
                    [$stored[0], $stored[1], $note ?: null, $now, $sub['id']]);
                log_activity('UPDATE_SUBMISSION', 'Updated submission for "' . $a['title'] . '" (' . $a['code'] . ')' . ($late ? ' - late' : ''));
                flash('success', 'Your homework was updated.');
            } else {
                qrun('INSERT INTO submissions (assignment_id, student_id, file_path, original_name, note, submitted_at) VALUES (?,?,?,?,?,?)',
                    [$id, $me['id'], $stored[0], $stored[1], $note ?: null, $now]);
                log_activity('SUBMIT_ASSIGNMENT', 'Submitted "' . $a['title'] . '" (' . $a['code'] . ')' . ($late ? ' - late' : ''));
                notify((int) $a['lecturer_id'], 'New submission', $me['name'] . ' submitted "' . $a['title'] . '"' . ($late ? ' (late)' : '') . '.', 'lecturer/assignment_view.php?id=' . $id);
                flash($late ? 'warn' : 'success', $late ? 'Submitted, but after the deadline, so it is marked as late.' : 'Your homework was submitted. Well done!');
            }
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect($self);
    }

    if (post('action') === 'unsubmit' && $sub && $sub['marks'] === null) {
        delete_upload($sub['file_path']);
        qrun('DELETE FROM submissions WHERE id = ?', [$sub['id']]);
        log_activity('DELETE_SUBMISSION', 'Removed submission for "' . $a['title'] . '" (' . $a['code'] . ')');
        flash('success', 'Your submission was removed. You can upload a new file any time.');
        redirect($self);
    }
}

$status  = submission_status($sub['submitted_at'] ?? null, $a['due_date']);
$isPast  = strtotime($a['due_date']) < time();
$graded  = $sub && $sub['marks'] !== null;

$pageTitle = $a['title'];
$active = 'assignments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('student/assignments.php') ?>">Assignments</a> / <a href="<?= url('student/class_view.php?id=' . (int) $a['cid'] . '&tab=assignments') ?>"><?= e($a['code']) ?></a></div>
        <h1><?= e($a['title']) ?></h1>
        <p class="sub"><?= e($a['code'] . ' · ' . $a['course_title']) ?> · <?= e($a['lecturer_name']) ?></p>
    </div>
    <div class="actions"><?= $sub ? status_pill($graded ? 'graded' : $status) : ($isPast ? pill('danger', 'Overdue') : pill('muted', 'Not submitted')) ?></div>
</div>

<div class="grid split">
    <div style="display:grid;gap:20px">
        <div class="card">
            <h3>Instructions</h3>
            <p class="desc"><?= $a['description'] ? e($a['description']) : '<span class="muted">No extra instructions.</span>' ?></p>
            <hr>
            <dl class="kv" style="margin:0">
                <dt>Due</dt><dd><b><?= e(fmt_datetime($a['due_date'])) ?></b> <span class="due <?= $isPast ? 'over' : 'soon' ?>">(<?= e(due_in_text($a['due_date'])) ?>)</span></dd>
                <dt>Marks</dt><dd><?= (int) $a['max_marks'] ?></dd>
            </dl>
        </div>

        <?php if ($graded): ?>
        <div class="card" style="background:linear-gradient(135deg,#e3e9f7,#fff);border-color:#c9d6ef">
            <h3><?= icon('star', 20) ?> Your result</h3>
            <p style="font-size:2rem;font-weight:800;margin:4px 0"><?= e(rtrim(rtrim(number_format((float) $sub['marks'], 2), '0'), '.')) ?> <span class="muted" style="font-size:1.1rem">/ <?= (int) $a['max_marks'] ?></span></p>
            <div class="progress" style="margin-bottom:12px"><span style="width:<?= round($sub['marks'] / $a['max_marks'] * 100) ?>%;background:linear-gradient(90deg,#60a5fa,#1e3a8a)"></span></div>
            <?php if ($sub['feedback']): ?><b>Feedback from your lecturer</b><p class="desc" style="margin:4px 0 0"><?= e($sub['feedback']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3><?= $sub ? 'Your submission' : 'Hand in your work' ?></h3>

        <?php if ($sub): ?>
            <div class="item mb">
                <span class="ico"><?= icon('file', 20) ?></span>
                <span class="grow"><b><?= e($sub['original_name']) ?></b><small>Submitted <?= e(fmt_datetime($sub['submitted_at'])) ?></small></span>
                <a class="btn btn-soft btn-sm" href="<?= url('download.php?type=submission&id=' . (int) $sub['id']) ?>"><?= icon('download', 15) ?></a>
            </div>
            <?php if ($sub['note']): ?><p class="small muted">Your note: “<?= e($sub['note']) ?>”</p><?php endif; ?>
            <?php if ($status === 'late'): ?><div class="notice"><?= icon('alert', 20) ?><div>This was handed in after the deadline.</div></div><?php endif; ?>
        <?php elseif ($isPast): ?>
            <div class="notice"><?= icon('alert', 20) ?><div><b>The deadline has passed.</b>Submissions are now closed, so this work can no longer be handed in. Please speak to your lecturer if you need more time.</div></div>
        <?php endif; ?>

        <?php if ($graded): ?>
            <p class="muted small">This work has been graded. Ask your lecturer if you need to resubmit.</p>
        <?php elseif ($isPast): ?>
            <?php if ($sub): ?><p class="muted small">The deadline has passed, so your submission can no longer be changed or removed.</p><?php endif; ?>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" style="margin-top:6px">
                <?= csrf_field() ?><input type="hidden" name="action" value="submit">
                <div class="field mb">
                    <label for="file"><?= $sub ? 'Upload a new file to replace it' : 'Choose your file' ?></label>
                    <input type="file" id="file" name="file" required>
                    <span class="hint">Maximum <?= MAX_UPLOAD_MB ?> MB. Allowed: <?= e(implode(', ', ALLOWED_EXT)) ?>.</span>
                </div>
                <div class="field mb"><label for="note">Note for your lecturer (optional)</label><textarea id="note" name="note" style="min-height:70px" maxlength="500"></textarea></div>
                <button class="btn btn-block" type="submit"><?= icon('upload', 18) ?> <?= $sub ? 'Update homework' : 'Submit homework' ?></button>
            </form>
            <?php if ($sub): ?>
                <form method="post" data-confirm="Remove your submitted file?" style="margin-top:10px">
                    <?= csrf_field() ?><input type="hidden" name="action" value="unsubmit">
                    <button class="btn btn-ghost btn-block btn-sm" type="submit">Remove my submission</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
