<div class="row row-between"><h1><?= e(t('admin.learning.title')) ?></h1><a class="btn btn-primary" href="<?= e(url('/admin/learning/new')) ?>"><?= icon('plus', 18) ?><?= e(t('admin.learning.new')) ?></a></div>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.title')) ?></th><th><?= e(t('admin.learning.type')) ?></th><th><?= e(t('fields.status')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $c): ?>
        <tr><td data-label="<?= e(t('fields.title')) ?>"><?= e($c['title']) ?><br><span class="code"><?= e($c['slug']) ?></span></td><td data-label="<?= e(t('admin.learning.type')) ?>"><?= e(t('resources.types.' . $c['content_type'])) ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $c['is_published'] ? 'badge-success' : '' ?>"><?= e(t($c['is_published'] ? 'admin.learning.published' : 'admin.learning.draft')) ?></span></td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/learning/' . $c['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?><?= e(t('common.edit')) ?></a>
                <form method="post" action="<?= e(url('/admin/learning/' . $c['id'] . '/delete')) ?>" style="display:inline" data-confirm="<?= e(t('admin.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?><span class="sr-only"><?= e(t('common.delete')) ?></span></button></form></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
