<?php
/**
 * Discussion helpers shared by student/doubts.php and lecturer/doubts.php
 */

/** Load every reply for the given doubt ids, grouped by doubt id (oldest first). */
function doubt_replies_for(array $doubtIds): array
{
    $doubtIds = array_values(array_filter(array_map('intval', $doubtIds)));
    if (!$doubtIds) return [];
    $rows = qall(
        'SELECT r.id, r.doubt_id, r.user_id, r.body, r.created_at, u.name, u.role, u.avatar
           FROM doubt_replies r JOIN users u ON u.id = r.user_id
          WHERE r.doubt_id IN (' . implode(',', $doubtIds) . ')
          ORDER BY r.created_at ASC, r.id ASC'
    );
    $out = [];
    foreach ($rows as $r) $out[(int) $r['doubt_id']][] = $r;
    return $out;
}

/**
 * Renders the replies of one doubt plus the reply box.
 * $canDeleteAny = true for the lecturer of the class (can remove any reply).
 */
function doubt_thread_html(int $doubtId, array $replies, int $meId, bool $canDeleteAny = false): string
{
    $h = '<div class="replies" id="d' . $doubtId . '">';
    $n = count($replies);
    if ($n) {
        $h .= '<div class="replies-title">' . $n . ' ' . ($n === 1 ? 'reply' : 'replies') . '</div>';
    }
    foreach ($replies as $r) {
        $isLect = $r['role'] === 'lecturer';
        $h .= '<div class="reply' . ($isLect ? ' by-lecturer' : '') . '">';
        $h .= avatar_html($r['name'], $r['avatar'] ?: null, (int) $r['user_id']);
        $h .= '<div class="rb"><div class="rh"><b>' . e($r['name']) . '</b> '
            . ($isLect ? pill('primary', 'Lecturer') : '')
            . ' <small class="muted">' . e(time_ago($r['created_at'])) . '</small></div>'
            . '<p>' . nl2br(e($r['body'])) . '</p></div>';
        if ($canDeleteAny || (int) $r['user_id'] === $meId) {
            $h .= '<form method="post" class="inline-form" data-confirm="Remove this reply?">'
                . csrf_field()
                . '<input type="hidden" name="action" value="delete_reply">'
                . '<input type="hidden" name="reply_id" value="' . (int) $r['id'] . '">'
                . '<button class="btn btn-ghost btn-sm" type="submit" title="Remove reply">' . icon('trash', 14) . '</button>'
                . '</form>';
        }
        $h .= '</div>';
    }
    $h .= '<form method="post" class="reply-form">'
        . csrf_field()
        . '<input type="hidden" name="action" value="reply">'
        . '<input type="hidden" name="doubt_id" value="' . $doubtId . '">'
        . '<textarea name="body" maxlength="600" rows="1" placeholder="Write a reply..." required></textarea>'
        . '<button class="btn btn-soft btn-sm" type="submit">' . icon('send', 15) . ' Reply</button>'
        . '</form>';
    $h .= '</div>';
    return $h;
}

/** Validate + store a reply. Returns an error string or '' on success. */
function doubt_add_reply(array $doubt, array $me, string $body): string
{
    if (mb_strlen($body) < 2) return 'Please write something before replying.';
    if (mb_strlen($body) > 600) return 'Please keep your reply under 600 characters.';

    qrun('INSERT INTO doubt_replies (doubt_id, user_id, body, created_at) VALUES (?,?,?,?)',
        [$doubt['id'], $me['id'], $body, date('Y-m-d H:i:s')]);

    // Tell the person who asked (unless they replied to themselves)
    if ((int) $doubt['student_id'] !== (int) $me['id']) {
        $link = 'student/doubts.php?course=' . (int) $doubt['class_id'] . '#d' . (int) $doubt['id'];
        notify((int) $doubt['student_id'], 'New reply on your question', $me['name'] . ' replied: ' . mb_substr($body, 0, 120), $link);
    }
    return '';
}
