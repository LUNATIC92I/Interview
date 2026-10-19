<h1><?= e(t('admin.rubrics.title')) ?></h1>
<p class="lead"><?= e(t('admin.rubrics.lead')) ?></p>
<div class="grid grid-2">
    <?php foreach ($items as $r): ?>
        <div class="card"><div class="card-header"><h2><?= e(loc($r)) ?></h2><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/rubrics/' . $r['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?><?= e(t('common.edit')) ?></a></div><div class="card-body">
            <p class="small muted"><?= e(loc($r, 'description')) ?></p>
            <?php foreach ($r['criteria'] as $c): ?><div class="meter"><span class="meter-label"><?= e(loc($c)) ?> <span class="code"><?= e($c['evaluator']) ?></span></span><span class="meter-value"><?= (int) $c['weight'] ?></span><div class="progress"><div class="progress-bar" style="--value:<?= (int) $c['weight'] ?>%"></div></div></div><?php endforeach; ?>
            <p class="small muted" style="margin:0"><?= e(t('admin.rubrics.usage', ['n' => $r['usage']])) ?></p>
        </div></div>
    <?php endforeach; ?>
</div>
