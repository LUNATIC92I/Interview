<section class="page-header"><div class="container container-narrow">
    <h1><?= e(t('privacy.title')) ?></h1>
    <p class="lead"><?= e(t('privacy.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container container-narrow">
    <div class="card"><div class="card-body stack">
        <?php foreach (['data', 'purpose', 'evaluation', 'retention', 'rights', 'security', 'payments', 'ai'] as $k): ?>
            <section><h2 style="font-size:1.15rem"><?= e(t('privacy.' . $k . '.title')) ?></h2><p class="muted"><?= e(t('privacy.' . $k . '.text')) ?></p></section>
        <?php endforeach; ?>
    </div></div>
</div></section>
