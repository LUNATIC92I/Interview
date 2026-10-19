<nav class="breadcrumb"><a href="<?= e(url('/admin/users')) ?>"><?= e(t('admin.users.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($user['display_name']) ?></span></nav>
<h1><?= e($user['display_name']) ?></h1>
<div class="grid grid-2">
    <div class="card"><div class="card-header"><h2><?= e(t('admin.users.account')) ?></h2></div><div class="card-body">
        <dl class="kv">
            <dt><?= e(t('fields.email')) ?></dt><dd class="wrap-anywhere"><?= e($user['email']) ?></dd>
            <dt><?= e(t('fields.status')) ?></dt><dd><?= e(t('admin.users.status.' . $user['status'])) ?></dd>
            <dt><?= e(t('fields.language')) ?></dt><dd><?= e(t('languages.' . $user['locale'])) ?></dd>
            <dt><?= e(t('admin.users.created')) ?></dt><dd><?= e(format_date($user['created_at'], true)) ?></dd>
            <dt><?= e(t('admin.users.last_login')) ?></dt><dd><?= e(format_date($user['last_login_at'], true)) ?: '-' ?></dd>
            <dt><?= e(t('admin.users.verified')) ?></dt><dd><?= $user['email_verified_at'] ? e(format_date($user['email_verified_at'])) : e(t('common.no')) ?></dd>
        </dl>
        <div class="row" style="margin-top:1rem">
            <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/status')) ?>" data-confirm="<?= e(t('admin.users.status_confirm')) ?>">
                <?= csrf_field() ?><input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                <button class="btn <?= $user['status'] === 'active' ? 'btn-outline-danger' : 'btn-secondary' ?> btn-sm" type="submit"><?= e(t($user['status'] === 'active' ? 'admin.users.suspend' : 'admin.users.activate')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/roles')) ?>" data-confirm="<?= e(t('admin.users.role_confirm')) ?>">
                <?= csrf_field() ?><input type="hidden" name="admin" value="<?= in_array('admin', $user['roles'], true) ? '0' : '1' ?>">
                <button class="btn btn-secondary btn-sm" type="submit"><?= e(t(in_array('admin', $user['roles'], true) ? 'admin.users.remove_admin' : 'admin.users.make_admin')) ?></button>
            </form>
        </div>
    </div></div>
    <div class="card"><div class="card-header"><h2><?= e(t('admin.users.activity')) ?></h2></div><div class="card-body">
        <dl class="kv">
            <dt><?= e(t('dashboard.total')) ?></dt><dd><?= (int) $activity['total'] ?></dd>
            <dt><?= e(t('dashboard.completed')) ?></dt><dd><?= (int) $activity['completed'] ?></dd>
            <dt><?= e(t('dashboard.average')) ?></dt><dd><?= $activity['avg_score'] !== null ? (int) round((float) $activity['avg_score']) . '/100' : '-' ?></dd>
        </dl>
        <p class="disclaimer" style="margin-top:1rem"><?= icon('lock', 16) ?><span><?= e(t('admin.users.privacy')) ?></span></p>
    </div></div>
</div>
<div class="card" style="margin-top:20px"><div class="card-header"><h2><?= e(t('admin.access.title')) ?></h2></div><div class="card-body">
    <?php if (!$access): ?><p class="muted"><?= e(t('admin.access.none')) ?></p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th><?= e(t('fields.profession')) ?></th><th><?= e(t('admin.access.source')) ?></th><th><?= e(t('fields.date')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead><tbody>
        <?php foreach ($access as $a): ?><tr>
            <td><?= e($a['profession_name']) ?></td>
            <td><?= e(t('admin.access.sources.' . $a['source'])) ?><?php if ($a['provider_reference']): ?> <span class="code"><?= e($a['provider_reference']) ?></span><?php endif; ?></td>
            <td><?= e(format_date($a['granted_at'], true)) ?></td>
            <td style="text-align:right"><form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/access/' . $a['profession_id'] . '/revoke')) ?>" data-confirm="<?= e(t('admin.access.revoke_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit"><?= e(t('admin.access.revoke')) ?></button></form></td>
        </tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/access')) ?>" class="row" style="margin-top:1rem;align-items:flex-end">
        <?= csrf_field() ?>
        <div class="form-group" style="margin:0;flex:1;min-width:220px"><label for="grant_profession"><?= e(t('admin.access.grant_label')) ?></label>
            <select id="grant_profession" name="profession_id" required><option value=""><?= e(t('common.choose')) ?></option>
                <?php foreach ($professions as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
        <button class="btn btn-secondary" type="submit"><?= icon('plus', 18) ?><?= e(t('admin.access.grant')) ?></button>
    </form>
    <p class="help" style="margin-top:.5rem"><?= e(t('admin.access.grant_help')) ?></p>
</div></div>
<div class="card" style="margin-top:20px"><div class="card-header"><h2><?= e(t('account.login_history')) ?></h2></div>
    <div class="table-wrap" style="border:0"><table class="table"><thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('account.event')) ?></th><th><?= e(t('account.ip')) ?></th></tr></thead><tbody>
        <?php foreach ($logins as $l): ?><tr><td><?= e(format_date($l['created_at'], true)) ?></td><td><?= e(t('account.events.' . $l['event'])) ?></td><td><span class="code"><?= e($l['ip_masked']) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
