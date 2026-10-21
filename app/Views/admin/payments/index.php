<?php use App\Controllers\Admin\PaymentController; ?>
<h1><?= e(t('admin.payments.title')) ?></h1>
<p class="muted"><?= e(t('admin.payments.lead')) ?></p>
<div class="grid grid-4" style="margin:16px 0">
    <?php if (!$totals): ?>
        <div class="card stat"><div class="stat-label"><?= icon('landmark', 16) ?><?= e(t('admin.payments.completed_total')) ?></div><div class="stat-value">0</div></div>
    <?php endif; ?>
    <?php foreach ($totals as $row): ?>
        <div class="card stat"><div class="stat-label"><?= icon('landmark', 16) ?><?= e(t('admin.payments.completed_total')) ?></div><div class="stat-value"><?= e(format_price((int) $row['total'], $row['currency'])) ?></div><div class="small muted"><?= e(t('admin.payments.completed_count', ['count' => (int) $row['n']])) ?></div></div>
    <?php endforeach; ?>
</div>
<form class="filters filters-4 card card-body" method="get" action="<?= e(url('/admin/payments')) ?>" role="search">
    <div class="form-group"><label for="q"><?= e(t('common.search')) ?></label><input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('admin.payments.search_placeholder')) ?>"></div>
    <div class="form-group"><label for="status"><?= e(t('fields.status')) ?></label><select id="status" name="status"><option value=""><?= e(t('common.all')) ?></option><?php foreach (PaymentController::STATUSES as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(t('payment.status.' . $s)) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
</form>
<?php if (!$items): ?>
    <div class="empty card"><?= icon('inbox', 32) ?><p><?= e(t('admin.payments.empty')) ?></p></div>
<?php else: ?>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('admin.payments.user')) ?></th><th><?= e(t('fields.profession')) ?></th><th><?= e(t('payment.amount')) ?></th><th><?= e(t('fields.status')) ?></th><th><?= e(t('payment.reference')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $p): ?>
        <tr>
            <td data-label="<?= e(t('fields.date')) ?>"><?= e(format_date($p['created_at'], true)) ?></td>
            <td data-label="<?= e(t('admin.payments.user')) ?>" class="wrap-anywhere"><?php if ($p['user_id']): ?><a href="<?= e(url('/admin/users/' . $p['user_id'])) ?>"><?= e($p['email']) ?></a><?php else: ?><span class="muted"><?= e(t('admin.payments.deleted_user')) ?></span><?php endif; ?></td>
            <td data-label="<?= e(t('fields.profession')) ?>"><?= e($p['profession_name']) ?></td>
            <td data-label="<?= e(t('payment.amount')) ?>"><?= e(format_price((int) $p['amount'], $p['currency'])) ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= e(payment_badge($p['status'])) ?>"><?= e(t('payment.status.' . $p['status'])) ?></span><?php if ($p['failure_reason']): ?> <span class="small muted"><?= e(t('payment.failure.' . $p['failure_reason'])) ?></span><?php endif; ?></td>
            <td data-label="<?= e(t('payment.reference')) ?>"><span class="code"><?= e($p['provider_reference'] ?? '-') ?></span> <span class="small muted"><?= e($p['provider']) ?></span></td>
            <td class="actions"><?php if ($p['provider_reference'] && in_array($p['status'], ['pending', 'processing', 'completed'], true)): ?>
                <form method="post" action="<?= e(url('/admin/payments/' . $p['id'] . '/refresh')) ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= icon('rotate-ccw', 16) ?><?= e(t('admin.payments.refresh')) ?></button></form>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?></tbody>
</table></div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
<?php endif; ?>
