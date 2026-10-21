<section class="page-header"><div class="container container-narrow">
    <p class="eyebrow"><?= e(t('pricing.eyebrow')) ?></p>
    <h1><?= e(t('pricing.title')) ?></h1>
    <p class="lead"><?= e(t('pricing.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container container-narrow">
    <?php if (!$paywall): ?>
        <div class="alert alert-info"><?= icon('info', 20) ?><p><?= e(t('pricing.free_now')) ?></p></div>
    <?php endif; ?>
    <div class="grid grid-2">
        <article class="card"><div class="card-body stack">
            <span class="badge" style="align-self:flex-start"><?= e(t('pricing.trial_badge')) ?></span>
            <h2 style="font-size:1.3rem;margin:0"><?= e(t('pricing.trial_title')) ?></h2>
            <div class="stat-value"><?= e(t('pricing.free')) ?></div>
            <ul class="feature-list">
                <li><?= icon('check', 18) ?><span><?= e(t('pricing.trial_1', ['count' => $trials, 'questions' => $trialQuestions])) ?></span></li>
                <li><?= icon('check', 18) ?><span><?= e(t('pricing.trial_2')) ?></span></li>
                <li><?= icon('check', 18) ?><span><?= e(t('pricing.trial_3')) ?></span></li>
            </ul>
            <a class="btn btn-secondary" href="<?= e(url($trialsLeft === null ? '/register' : '/interview/new')) ?>"><?= icon('play', 18) ?><?= e(t('access.try_free')) ?></a>
            <?php if ($trialsLeft === 0): ?><p class="small muted" style="margin:0"><?= e(t('pricing.trial_used')) ?></p><?php endif; ?>
        </div></article>
        <article class="card" style="border-color:var(--primary);box-shadow:var(--shadow)"><div class="card-body stack">
            <span class="badge badge-primary" style="align-self:flex-start"><?= e(t('pricing.access_badge')) ?></span>
            <h2 style="font-size:1.3rem;margin:0"><?= e(t('pricing.access_title')) ?></h2>
            <div><span class="stat-value"><?= e($price) ?></span> <span class="muted"><?= e(t('pricing.per_profession')) ?></span></div>
            <ul class="feature-list">
                <?php foreach ([1, 2, 3, 4, 5] as $i): ?><li><?= icon('check', 18) ?><span><?= e(t('pricing.access_' . $i)) ?></span></li><?php endforeach; ?>
            </ul>
            <a class="btn btn-primary" href="<?= e(url('/professions')) ?>"><?= icon('briefcase', 18) ?><?= e(t('pricing.choose_profession', ['count' => $professionCount])) ?></a>
        </div></article>
    </div>
    <div class="card" style="margin-top:24px"><div class="card-body">
        <h2 style="font-size:1.15rem"><?= e(t('pricing.how_title')) ?></h2>
        <ol class="steps steps-stack" style="margin-top:1rem">
            <?php foreach ([1, 2, 3] as $i): ?><li class="step"><h3 style="font-size:1rem;margin:0"><?= e(t('pricing.how_' . $i . '_title')) ?></h3><p class="muted small" style="margin:.2rem 0 0"><?= e(t('pricing.how_' . $i)) ?></p></li><?php endforeach; ?>
        </ol>
    </div></div>
    <div class="faq" style="margin-top:24px">
        <?php foreach ([1, 2, 3, 4] as $i): ?>
            <details><summary><?= e(t('pricing.faq_' . $i . '_q')) ?><?= icon('chevron-down', 18) ?></summary><div class="faq-body"><p><?= e(t('pricing.faq_' . $i . '_a', ['price' => $price])) ?></p></div></details>
        <?php endforeach; ?>
    </div>
</div></section>
