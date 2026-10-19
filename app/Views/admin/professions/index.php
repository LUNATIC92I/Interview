<div class="row row-between"><h1><?= e(t('admin.professions.title')) ?></h1><a class="btn btn-primary" href="<?= e(url('/admin/professions/new')) ?>"><?= icon('plus', 18) ?><?= e(t('admin.professions.new')) ?></a></div>
<form class="filters card card-body" method="get" action="<?= e(url('/admin/professions')) ?>" role="search">
    <div class="form-group"><label for="q"><?= e(t('common.search')) ?></label><input class="input" type="search" id="q" name="q" value="<?= e($q) ?>"></div>
    <div class="form-group"><label for="category"><?= e(t('professions.category')) ?></label><select id="category" name="category"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $category === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
</form>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.name')) ?></th><th><?= e(t('professions.category')) ?></th><th><?= e(t('admin.nav.questions')) ?></th><th><?= e(t('fields.status')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $p): ?>
        <tr><td data-label="<?= e(t('fields.name')) ?>"><?= e($p['name']) ?><br><span class="code"><?= e($p['slug']) ?></span></td><td data-label="<?= e(t('professions.category')) ?>"><?= e($p['category_name']) ?></td><td data-label="<?= e(t('admin.nav.questions')) ?>"><?= (int) $p['question_count'] ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $p['is_active'] ? 'badge-success' : '' ?>"><?= e(t($p['is_active'] ? 'common.active' : 'common.inactive')) ?></span></td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/professions/' . $p['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?><?= e(t('common.edit')) ?></a>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/questions', ['profession' => $p['id']])) ?>"><?= e(t('admin.nav.questions')) ?></a>
                <form method="post" action="<?= e(url('/admin/professions/' . $p['id'] . '/delete')) ?>" style="display:inline" data-confirm="<?= e(t('admin.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?><span class="sr-only"><?= e(t('common.delete')) ?></span></button></form></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
