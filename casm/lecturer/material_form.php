<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$id  = (int) ($_GET['id'] ?? 0);
$cid = (int) ($_GET['class_id'] ?? 0);
$material = null;

if ($id) {
    $material = qone('SELECT m.* FROM materials m JOIN classes c ON c.id = m.class_id WHERE m.id = ? AND c.lecturer_id = ?', [$id, $me['id']]);
    if (!$material) {
        flash('error', 'That material could not be found.');
        redirect('lecturer/courses.php');
    }
    $cid = (int) $material['class_id'];
}
$course = lecturer_course($cid);

$form = ['title' => $material['title'] ?? '', 'description' => $material['description'] ?? ''];
$errors = [];

if (is_post()) {
    csrf_verify();
    $form['title'] = post('title');
    $form['description'] = post('description');
    $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($form['title'] === '') $errors[] = 'Please give the material a title.';
    if (!$id && !$hasFile) $errors[] = 'Please choose a file to upload.';

    $stored = null;
    if (!$errors && $hasFile) {
        try {
            $stored = save_upload($_FILES['file'], 'materials');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        if ($id) {
            if ($stored) {
                delete_upload($material['file_path']);
                qrun('UPDATE materials SET title=?, description=?, file_path=?, original_name=? WHERE id=?',
                    [$form['title'], $form['description'], $stored[0], $stored[1], $id]);
            } else {
                qrun('UPDATE materials SET title=?, description=? WHERE id=?', [$form['title'], $form['description'], $id]);
            }
            log_activity('UPDATE_MATERIAL', 'Updated "' . $form['title'] . '" in ' . $course['code']);
            flash('success', 'Material updated.');
        } else {
            qrun('INSERT INTO materials (class_id, title, description, file_path, original_name, uploaded_by, created_at) VALUES (?,?,?,?,?,?,?)',
                [$cid, $form['title'], $form['description'], $stored[0], $stored[1], $me['id'], date('Y-m-d H:i:s')]);
            log_activity('UPLOAD_MATERIAL', 'Uploaded "' . $form['title'] . '" to ' . $course['code']);
            foreach (enrolled_student_ids($cid) as $sid) {
                notify($sid, 'New material in ' . $course['code'], '"' . $form['title'] . '" was uploaded.', 'student/class_view.php?id=' . $cid);
            }
            flash('success', 'Material uploaded and students were notified.');
        }
        redirect('lecturer/class_view.php?id=' . $cid . '&tab=materials');
    }
}

$pageTitle = $id ? 'Edit Material' : 'Upload Material';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('lecturer/courses.php') ?>">My Courses</a> / <a href="<?= url('lecturer/class_view.php?id=' . $cid) ?>"><?= e($course['code']) ?></a> / <?= $id ? 'Edit material' : 'Upload' ?></div>
        <h1><?= $id ? 'Edit material' : 'Upload notes or materials' ?></h1>
    </div>
</div>

<div class="card" style="max-width:720px">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field mb"><label for="title">Title</label><input type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="150" placeholder="Week 3 - Normalisation" required></div>
        <div class="field mb"><label for="description">Short description (optional)</label><textarea id="description" name="description" style="min-height:90px"><?= e($form['description']) ?></textarea></div>
        <div class="field">
            <label for="file"><?= $id ? 'Replace file (optional)' : 'File' ?></label>
            <input type="file" id="file" name="file" <?= $id ? '' : 'required' ?>>
            <span class="hint">
                <?php if ($id): ?>Current file: <b><?= e($material['original_name']) ?></b>. <?php endif; ?>
                Maximum <?= MAX_UPLOAD_MB ?> MB. Allowed: <?= e(implode(', ', ALLOWED_EXT)) ?>.
            </span>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= icon('upload', 18) ?> <?= $id ? 'Save changes' : 'Upload' ?></button>
            <a class="btn btn-ghost" href="<?= url('lecturer/class_view.php?id=' . $cid) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
