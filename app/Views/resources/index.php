<section class="page-header"><div class="container">
    <p class="eyebrow"><?= e(t('my_resources.eyebrow')) ?></p>
    <h1><?= e(t('my_resources.title')) ?></h1>
    <p class="lead"><?= e(t('my_resources.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container">
    <?php if (!$paywall): ?>
        <div class="alert alert-info"><?= icon('info', 20) ?><p><?= e(t('my_resources.all_open')) ?> <a href="<?= e(url('/professions')) ?>"><?= e(t('nav.professions')) ?></a></p></div>
    <?php elseif (!$unlocked): ?>
        <div class="empty card">
            <?= icon('book-open', 32) ?>
            <p><?= e(t('my_resources.empty', ['price' => $price])) ?></p>
            <?php if ($trialsLeft > 0): ?><p class="small"><?= e(t('my_resources.trial_left')) ?></p><?php endif; ?>
            <a class="btn btn-primary" href="<?= e(url('/professions')) ?>"><?= icon('briefcase', 18) ?><?= e(t('my_resources.choose')) ?></a>
        </div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($unlocked as $p): ?>
                <article class="card card-hover"><div class="card-body">
                    <div class="card-icon"><?= icon($p['icon'], 22) ?></div>
                    <h2 style="font-size:1.1rem"><?= e($p['name']) ?></h2>
                    <p class="muted small"><?= e($p['summary']) ?></p>
                    <p class="small" style="color:var(--success-text)"><?= icon('check-circle', 14) ?> <?= e(t('my_resources.since', ['date' => format_date($p['granted_at'])])) ?></p>
                    <div class="row">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('/my-resources/' . $p['slug'])) ?>"><?= icon('book-open', 16) ?><?= e(t('my_resources.open')) ?></a>
                        <a class="btn btn-secondary btn-sm" href="<?= e(url('/interview/new', ['profession' => $p['slug']])) ?>"><?= icon('play', 16) ?><?= e(t('professions.start')) ?></a>
                    </div>
                </div></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></section>
