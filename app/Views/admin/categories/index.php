<div class="row row-between"><h1><?= e(t('admin.categories.title')) ?></h1><a class="btn btn-primary" href="<?= e(url('/admin/categories/new')) ?>"><?= icon('plus', 18) ?><?= e(t('admin.categories.new')) ?></a></div>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.name')) ?></th><th>Slug</th><th><?= e(t('admin.nav.professions')) ?></th><th><?= e(t('fields.order')) ?></th><th><?= e(t('fields.status')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $c): ?>
        <tr><td data-label="<?= e(t('fields.name')) ?>"><?= icon($c['icon'], 18) ?> <?= e($c['name']) ?></td><td data-label="Slug"><span class="code"><?= e($c['slug']) ?></span></td><td data-label="<?= e(t('admin.nav.professions')) ?>"><?= (int) $c['profession_count'] ?></td><td data-label="<?= e(t('fields.order')) ?>"><?= (int) $c['sort_order'] ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $c['is_active'] ? 'badge-success' : '' ?>"><?= e(t($c['is_active'] ? 'common.active' : 'common.inactive')) ?></span></td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/categories/' . $c['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?><?= e(t('common.edit')) ?></a>
                <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/delete')) ?>" style="display:inline" data-confirm="<?= e(t('admin.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?><span class="sr-only"><?= e(t('common.delete')) ?></span></button></form></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
