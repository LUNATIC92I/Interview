<div class="row row-between" style="margin-bottom:1rem">
    <div><h1><?= e(t('admin.dashboard.title')) ?></h1><p class="muted" style="margin:0"><?= e(t('admin.dashboard.lead')) ?></p></div>
</div>
<div class="grid grid-4">
    <?php foreach ([['users', 'users', $stats['users'], t('admin.dashboard.new_30', ['n' => $stats['users_30']])], ['layers', 'sessions', $stats['sessions'], t('admin.dashboard.completed_n', ['n' => $stats['completed']])], ['gauge', 'avg', round($stats['avg']), '/100'], ['inbox', 'open_reports', $stats['open_reports'], '']] as [$ic, $k, $v, $sub]): ?>
        <div class="card stat"><div class="stat-label"><?= icon($ic, 16) ?><?= e(t('admin.dashboard.' . $k)) ?></div><div class="stat-value"><?= e((string) $v) ?></div><div class="small muted"><?= e($sub) ?></div></div>
    <?php endforeach; ?>
</div>
<div class="grid grid-3" style="margin-top:20px">
    <div class="card"><div class="card-header"><h2><?= e(t('admin.dashboard.content')) ?></h2></div><div class="card-body">
        <dl class="kv">
            <dt><?= e(t('admin.nav.professions')) ?></dt><dd><?= (int) $stats['professions'] ?></dd>
            <dt><?= e(t('admin.nav.questions')) ?></dt><dd><?= (int) $stats['questions'] ?></dd>
            <dt><?= e(t('admin.nav.scenarios')) ?></dt><dd><?= (int) $stats['scenarios'] ?></dd>
            <dt><?= e(t('admin.dashboard.missing_en')) ?></dt><dd><?= (int) $stats['missing_en'] ?></dd>
            <dt><?= e(t('admin.dashboard.missing_fr')) ?></dt><dd><?= (int) $stats['missing_fr'] ?></dd>
        </dl>
    </div></div>
    <div class="card"><div class="card-header"><h2><?= e(t('admin.dashboard.by_type')) ?></h2></div><div class="card-body">
        <?php if (!$byType): ?><p class="muted"><?= e(t('admin.no_data')) ?></p><?php endif; ?>
        <?php foreach ($byType as $r): ?><div class="meter"><span class="meter-label"><?= e(t('interview_types.' . $r['interview_type'])) ?> <span class="small muted">(<?= (int) $r['n'] ?>)</span></span><span class="meter-value"><?= (int) $r['score'] ?></span><div class="progress"><div class="progress-bar" style="--value:<?= (int) $r['score'] ?>%"></div></div></div><?php endforeach; ?>
    </div></div>
    <div class="card"><div class="card-header"><h2><?= e(t('admin.dashboard.top_professions')) ?></h2></div><div class="card-body">
        <?php if (!$topProfessions): ?><p class="muted"><?= e(t('admin.no_data')) ?></p><?php endif; ?>
        <ul class="feature-list"><?php foreach ($topProfessions as $p): ?><li><span class="badge"><?= (int) $p['n'] ?></span><span><?= e($p['name']) ?></span></li><?php endforeach; ?></ul>
        <h3 style="font-size:.95rem;margin-top:1rem"><?= e(t('admin.dashboard.by_mode')) ?></h3>
        <ul class="pill-list"><?php foreach ($byMode as $m): ?><li class="badge"><?= e(t('modes.' . $m['mode'] . '.name')) ?> : <?= (int) $m['n'] ?></li><?php endforeach; ?></ul>
        <h3 style="font-size:.95rem;margin-top:1rem"><?= e(t('admin.dashboard.evaluators')) ?></h3>
        <ul class="pill-list"><?php foreach ($evaluators as $m): ?><li class="badge"><?= e(t('eval.engine_short.' . $m['evaluator'])) ?> : <?= (int) $m['n'] ?></li><?php endforeach; ?></ul>
    </div></div>
</div>
<div class="card" style="margin-top:20px"><div class="card-header"><h2><?= e(t('admin.dashboard.recent_audit')) ?></h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/audit')) ?>"><?= e(t('common.see_all')) ?></a></div>
    <div class="table-wrap" style="border:0"><table class="table table-responsive"><thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('admin.audit.action')) ?></th><th><?= e(t('admin.audit.user')) ?></th><th><?= e(t('admin.audit.entity')) ?></th></tr></thead><tbody>
    <?php foreach ($audit as $a): ?><tr><td data-label="<?= e(t('fields.date')) ?>"><?= e(format_date($a['created_at'], true)) ?></td><td data-label="<?= e(t('admin.audit.action')) ?>"><span class="code"><?= e($a['action']) ?></span></td><td data-label="<?= e(t('admin.audit.user')) ?>"><?= e($a['display_name'] ?? '-') ?></td><td data-label="<?= e(t('admin.audit.entity')) ?>"><?= e(($a['entity_type'] ?? '') . ' ' . ($a['entity_id'] ?? '')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<p class="disclaimer" style="margin-top:16px"><?= icon('shield', 16) ?><span><?= e(t('admin.dashboard.privacy_note')) ?></span></p>
