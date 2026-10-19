<div class="row row-between"><h1><?= e(t('admin.scenarios.title')) ?></h1><a class="btn btn-primary" href="<?= e(url('/admin/scenarios/new')) ?>"><?= icon('plus', 18) ?><?= e(t('admin.scenarios.new')) ?></a></div>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.title')) ?></th><th><?= e(t('fields.interview_type')) ?></th><th><?= e(t('admin.nav.questions')) ?></th><th><?= e(t('admin.scenarios.usage')) ?></th><th><?= e(t('fields.status')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $s): ?>
        <tr><td data-label="<?= e(t('fields.title')) ?>"><?= e($s['title']) ?><br><span class="code"><?= e($s['slug']) ?></span></td><td data-label="<?= e(t('fields.interview_type')) ?>"><?= e(t('interview_types.' . $s['interview_type'])) ?></td><td data-label="<?= e(t('admin.nav.questions')) ?>"><?= (int) $s['question_count'] ?></td><td data-label="<?= e(t('admin.scenarios.usage')) ?>"><?= (int) $s['usage_count'] ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $s['is_active'] ? 'badge-success' : '' ?>"><?= e(t($s['is_active'] ? 'common.active' : 'common.inactive')) ?></span></td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/scenarios/' . $s['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?><?= e(t('common.edit')) ?></a>
                <form method="post" action="<?= e(url('/admin/scenarios/' . $s['id'] . '/delete')) ?>" style="display:inline" data-confirm="<?= e(t('admin.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?><span class="sr-only"><?= e(t('common.delete')) ?></span></button></form></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
