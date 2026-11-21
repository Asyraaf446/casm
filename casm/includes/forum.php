<?php
/**
 * Forum helpers. The lecturer of a class starts topics; students of the class reply.
 */

/** True when the forum tables exist (so pages still work before the database update is run). */
function forum_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            $ok = count(qall("SHOW TABLES LIKE 'forum%'")) >= 2;
        } catch (Throwable $t) {
            $ok = false;
        }
    }
    return $ok;
}

/** The topic, but only if this lecturer owns the class or this student is enrolled in it. */
function forum_topic_for_user(int $id, array $me): ?array
{
    $t = qone(
        'SELECT t.*, c.code, c.title AS course_title, c.lecturer_id, u.name AS author, u.avatar AS author_avatar
           FROM forum_topics t JOIN classes c ON c.id = t.class_id JOIN users u ON u.id = t.created_by
          WHERE t.id = ?',
        [$id]
    );
    if (!$t) {
        return null;
    }
    if ($me['role'] === 'lecturer') {
        return (int) $t['lecturer_id'] === (int) $me['id'] ? $t : null;
    }
    if ($me['role'] === 'student') {
        return qval('SELECT COUNT(*) FROM enrollments WHERE class_id = ? AND student_id = ?', [$t['class_id'], $me['id']]) ? $t : null;
    }
    return null;
}

/** One card in a topic list. */
function forum_card(array $t, string $role): void
{
    $n = (int) $t['replies'];
    ?>
    <a class="forum-card" href="<?= url('forum_topic.php?id=' . (int) $t['id']) ?>">
        <span class="ico"><?= icon('forum', 22) ?></span>
        <span class="grow">
            <b><?= e($t['title']) ?></b>
            <span class="snip"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $t['body']), 0, 140, '...')) ?></span>
            <small><?= pill('primary', $t['code']) ?> <?= e($t['author']) ?> · <?= e(time_ago($t['created_at'])) ?></small>
        </span>
        <span class="count"><b><?= $n ?></b><small><?= $n === 1 ? 'reply' : 'replies' ?></small></span>
    </a>
    <?php
}
