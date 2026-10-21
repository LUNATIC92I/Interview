<h1><?= e(t('admin.moderation.title')) ?></h1>
<nav class="row" style="margin-bottom:16px" aria-label="<?= e(t('fields.status')) ?>">
    <?php foreach (['open', 'resolved', 'dismissed', 'all'] as $st): ?><a class="btn btn-sm <?= $status === $st ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/admin/reports', ['status' => $st])) ?>" <?= $status === $st ? 'aria-current="page"' : '' ?>><?= e(t('admin.moderation.status.' . $st)) ?></a><?php endforeach; ?>
</nav>
<?php if (!$items): ?><div class="card empty"><?= icon('inbox', 32) ?><p><?= e(t('admin.moderation.empty')) ?></p></div><?php endif; ?>
<div class="stack">
<?php foreach ($items as $r): ?>
    <div class="card card-body">
        <div class="row row-between"><span class="badge badge-warning"><?= e(t('report_question.reasons.' . $r['reason'])) ?></span><span class="small muted"><?= e(format_date($r['created_at'], true)) ?></span></div>
        <p style="margin:.6rem 0"><span class="code"><?= e($r['code']) ?></span> <?= e($r['body']) ?></p>
        <?php if ($r['message']): ?><blockquote class="answer-text"><?= e($r['message']) ?></blockquote><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/reports/' . $r['id'])) ?>" class="form-grid" style="margin-top:.8rem">
            <?= csrf_field() ?>
            <div class="form-group"><label for="st<?= $r['id'] ?>"><?= e(t('fields.status')) ?></label><select id="st<?= $r['id'] ?>" name="status"><?php foreach (['open', 'resolved', 'dismissed'] as $st): ?><option value="<?= $st ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= e(t('admin.moderation.status.' . $st)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="note<?= $r['id'] ?>"><?= e(t('admin.moderation.note')) ?></label><input class="input" id="note<?= $r['id'] ?>" name="admin_note" value="<?= e($r['admin_note']) ?>" maxlength="1000"></div>
            <div class="full row"><button class="btn btn-secondary btn-sm" type="submit"><?= e(t('common.save')) ?></button><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/questions/' . $r['question_id'] . '/edit')) ?>"><?= e(t('admin.moderation.edit_question')) ?></a></div>
        </form>
    </div>
<?php endforeach; ?>
</div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
