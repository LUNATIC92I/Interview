<h1><?= e(t('admin.users.title')) ?></h1>
<form class="filters filters-4 card card-body" method="get" action="<?= e(url('/admin/users')) ?>" role="search">
    <div class="form-group"><label for="q"><?= e(t('common.search')) ?></label><input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('admin.users.search_placeholder')) ?>"></div>
    <div class="form-group"><label for="status"><?= e(t('fields.status')) ?></label><select id="status" name="status"><option value=""><?= e(t('common.all')) ?></option><?php foreach (['active', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(t('admin.users.status.' . $s)) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="role"><?= e(t('admin.users.role')) ?></label><select id="role" name="role"><option value=""><?= e(t('common.all')) ?></option><?php foreach (['admin', 'candidate'] as $r): ?><option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(t('admin.users.roles.' . $r)) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
</form>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.display_name')) ?></th><th><?= e(t('fields.email')) ?></th><th><?= e(t('admin.users.role')) ?></th><th><?= e(t('fields.status')) ?></th><th><?= e(t('admin.users.completed')) ?></th><th><?= e(t('admin.users.created')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $u): ?>
        <tr>
            <td data-label="<?= e(t('fields.display_name')) ?>"><?= e($u['display_name']) ?></td>
            <td data-label="<?= e(t('fields.email')) ?>" class="wrap-anywhere"><?= e($u['email']) ?><?= $u['email_verified_at'] ? ' ' . icon('check-circle', 14) : '' ?></td>
            <td data-label="<?= e(t('admin.users.role')) ?>"><?php foreach (array_filter(explode(',', (string) $u['roles'])) as $r): ?><span class="badge <?= $r === 'admin' ? 'badge-primary' : '' ?>"><?= e(t('admin.users.roles.' . $r)) ?></span> <?php endforeach; ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e(t('admin.users.status.' . $u['status'])) ?></span></td>
            <td data-label="<?= e(t('admin.users.completed')) ?>"><?= (int) $u['completed'] ?></td>
            <td data-label="<?= e(t('admin.users.created')) ?>"><?= e(format_date($u['created_at'])) ?></td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/users/' . $u['id'])) ?>"><?= e(t('common.view')) ?></a></td>
        </tr>
    <?php endforeach; ?></tbody>
</table></div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
