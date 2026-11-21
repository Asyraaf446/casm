<?php
/**
 * Draws one meeting as a card.  $role is 'lecturer' or 'student'.
 * The Join / Start button is a small POST form, so it is protected by the CSRF token.
 */
function render_meeting_card(array $m, string $role): void
{
    $state = meeting_state($m);
    $isLive = $state === 'live';
    $hasLink = meeting_link($m) !== '';
    ?>
    <div class="meet-card <?= $isLive ? 'is-live' : '' ?>">
        <span class="ico"><?= icon('video', 24) ?></span>
        <div class="grow">
            <b><?= e($m['title']) ?></b>
            <small><?= e($m['code']) ?> · <?= e($m['course_title']) ?></small>
            <small><?= icon('clock', 14) ?> <?= e(fmt_datetime($m['scheduled_at'])) ?><?= $hasLink ? ' · Google Meet' : ' · no Meet link yet' ?></small>
        </div>
        <?= meeting_pill($state) ?>
        <div class="actions">
            <?php if ($role === 'lecturer'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= url('lecturer/meeting_view.php?id=' . (int) $m['id']) ?>">Details</a>
                <?php if (!$hasLink && $state !== 'ended'): ?>
                    <a class="btn btn-sm btn-soft" href="<?= url('lecturer/meeting_form.php?id=' . (int) $m['id']) ?>">Add Meet link</a>
                <?php elseif ($state === 'upcoming' || $state === 'missed' || $isLive): ?>
                    <form class="inline-form js-join" method="post" action="<?= url('meeting_join.php') ?>" target="_blank">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm <?= $isLive ? '' : 'btn-mint' ?>" type="submit"><?= icon('video', 16) ?> <?= $isLive ? 'Join' : 'Start now' ?></button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($isLive && $hasLink): ?>
                    <form class="inline-form js-join" method="post" action="<?= url('meeting_join.php') ?>" target="_blank">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm" type="submit"><?= icon('video', 16) ?> Join now</button>
                    </form>
                <?php elseif ($state === 'upcoming'): ?>
                    <span class="muted" style="font-size:.85rem;font-weight:700">Waiting for lecturer</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
