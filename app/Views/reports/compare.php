<?php
$delta = static function ($a, $b) {
    if ($a === null || $b === null) { return ''; }
    $d = (int) $b - (int) $a;
    return $d === 0 ? '<span class="muted">=</span>' : '<span class="' . ($d > 0 ? 'delta-up' : 'delta-down') . '">' . ($d > 0 ? '+' : '') . $d . '</span>';
};
?>
<section class="page-header"><div class="container">
    <nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>"><a href="<?= e(url('/history')) ?>"><?= e(t('nav.history')) ?></a><?= icon('chevron-right', 14) ?><span><?= e(t('compare.title')) ?></span></nav>
    <h1><?= e(t('compare.title')) ?></h1>
    <p class="lead"><?= e(t('compare.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container stack">
    <div class="compare-cols">
        <?php foreach (['a' => $a, 'b' => $b] as $k => $r): ?>
            <div class="card"><div class="card-body">
                <p class="eyebrow"><?= e(t('compare.attempt_' . $k)) ?></p>
                <h2 style="font-size:1.1rem"><?= e($r['meta']['profession']) ?> &middot; <?= e($r['meta']['type']) ?></h2>
                <p class="muted small"><?= e($r['meta']['date']) ?> &middot; <?= e($r['meta']['mode']) ?></p>
                <div class="row"><div class="score-ring sm <?= score_class((float) $r['score']) ?>" style="--value:<?= (int) round((float) $r['score']) ?>"><div class="score-ring-value"><strong><?= (int) round((float) $r['score']) ?></strong></div></div><a class="btn btn-secondary btn-sm" href="<?= e(url('/reports/' . $r['session']['public_id'])) ?>"><?= e(t('dashboard.view_report')) ?></a></div>
            </div></div>
        <?php endforeach; ?>
    </div>
    <div class="card"><div class="card-header"><h2><?= e(t('report.by_criterion')) ?></h2></div>
        <div class="table-wrap" style="border:0"><table class="table">
            <thead><tr><th><?= e(t('report.criterion')) ?></th><th><?= e(t('compare.attempt_a')) ?></th><th><?= e(t('compare.attempt_b')) ?></th><th><?= e(t('compare.change')) ?></th></tr></thead>
            <tbody>
                <tr><td><strong><?= e(t('report.overall_score')) ?></strong></td><td><?= (int) round((float) $a['score']) ?></td><td><?= (int) round((float) $b['score']) ?></td><td><?= $delta(round((float) $a['score']), round((float) $b['score'])) ?></td></tr>
                <?php foreach ($criteria as $c): ?>
                    <tr><td><?= e($c['name']) ?></td><td><?= $c['a'] ?? '&ndash;' ?></td><td><?= $c['b'] ?? '&ndash;' ?></td><td><?= $delta($c['a'], $c['b']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="compare-cols">
        <?php foreach (['a' => $a, 'b' => $b] as $k => $r): ?>
            <div class="card"><div class="card-body">
                <h3><?= e(t('report.strengths')) ?></h3>
                <ul class="fb-list good"><?php foreach ($r['strengths'] ?: [t('report.none_yet')] as $x): ?><li><?= icon('check', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul>
                <h3><?= e(t('report.improvements')) ?></h3>
                <ul class="fb-list gap"><?php foreach ($r['improvements'] ?: [t('report.none_yet')] as $x): ?><li><?= icon('alert-circle', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul>
            </div></div>
        <?php endforeach; ?>
    </div>
</div></section>
