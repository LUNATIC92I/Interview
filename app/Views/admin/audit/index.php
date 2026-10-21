<h1><?= e(t('admin.audit.title')) ?></h1>
<form class="filters card card-body" method="get" action="<?= e(url('/admin/audit')) ?>" role="search">
    <div class="form-group"><label for="action"><?= e(t('admin.audit.action')) ?></label><input class="input" type="search" id="action" name="action" value="<?= e($action) ?>" placeholder="admin., user.login"></div>
    <div></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
</form>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('admin.audit.action')) ?></th><th><?= e(t('admin.audit.user')) ?></th><th><?= e(t('admin.audit.entity')) ?></th><th><?= e(t('admin.audit.details')) ?></th><th><?= e(t('account.ip')) ?></th></tr></thead>
    <tbody><?php foreach ($items as $a): ?>
        <tr><td data-label="<?= e(t('fields.date')) ?>"><?= e(format_date($a['created_at'], true)) ?></td><td data-label="<?= e(t('admin.audit.action')) ?>"><span class="code"><?= e($a['action']) ?></span></td><td data-label="<?= e(t('admin.audit.user')) ?>"><?= e($a['display_name'] ?? '-') ?></td><td data-label="<?= e(t('admin.audit.entity')) ?>"><?= e(trim(($a['entity_type'] ?? '') . ' ' . ($a['entity_id'] ?? ''))) ?></td><td data-label="<?= e(t('admin.audit.details')) ?>" class="small wrap-anywhere"><?= e((string) $a['details']) ?></td><td data-label="<?= e(t('account.ip')) ?>"><span class="code"><?= e($a['ip_masked']) ?></span></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
