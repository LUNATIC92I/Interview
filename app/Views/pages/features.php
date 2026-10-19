<section class="page-header"><div class="container">
    <p class="eyebrow"><?= e(t('nav.features')) ?></p>
    <h1><?= e(t('features.title')) ?></h1>
    <p class="lead"><?= e(t('features.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:24px"><div class="container">
    <div class="grid grid-2">
        <?php foreach ([['target', 'engine'], ['bar-chart', 'evaluation'], ['line-chart', 'dashboard'], ['file-text', 'reports'], ['languages', 'i18n'], ['shield-check', 'security'], ['sliders', 'admin'], ['lightbulb', 'ai']] as [$ic, $key]): ?>
            <article class="card reveal"><div class="card-body">
                <div class="card-icon"><?= icon($ic, 22) ?></div>
                <h2 style="font-size:1.2rem"><?= e(t('features.' . $key . '.title')) ?></h2>
                <p class="muted"><?= e(t('features.' . $key . '.text')) ?></p>
                <ul class="feature-list">
                    <?php foreach ([1, 2, 3] as $i): ?><li><?= icon('check', 18) ?><span><?= e(t('features.' . $key . '.' . $i)) ?></span></li><?php endforeach; ?>
                </ul>
            </div></article>
        <?php endforeach; ?>
    </div>
</div></section>
