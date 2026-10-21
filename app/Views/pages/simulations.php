<section class="page-header"><div class="container">
    <p class="eyebrow"><?= e(t('nav.simulations')) ?></p>
    <h1><?= e(t('simulations.title')) ?></h1>
    <p class="lead"><?= e(t('simulations.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:24px"><div class="container">
    <h2><?= e(t('simulations.modes_title')) ?></h2>
    <div class="grid grid-3" style="margin-bottom:48px">
        <?php $modeIcons = ['training' => 'lightbulb', 'realistic' => 'briefcase', 'timed' => 'timer', 'intensive' => 'activity', 'custom' => 'sliders']; ?>
        <?php foreach ($modes as $mode => $rules): ?>
            <article class="card reveal"><div class="card-body">
                <div class="card-icon"><?= icon($modeIcons[$mode], 22) ?></div>
                <h3><?= e(t('modes.' . $mode . '.name')) ?></h3>
                <p class="muted"><?= e(t('modes.' . $mode . '.desc')) ?></p>
                <ul class="pill-list">
                    <li class="badge <?= $rules['instant_feedback'] ? 'badge-primary' : '' ?>"><?= e(t($rules['instant_feedback'] ? 'simulations.rule.instant' : 'simulations.rule.end')) ?></li>
                    <li class="badge"><?= e(t($rules['timer'] ? 'simulations.rule.timer' : 'simulations.rule.no_timer')) ?></li>
                    <li class="badge"><?= e(t($rules['back'] ? 'simulations.rule.back' : 'simulations.rule.no_back')) ?></li>
                </ul>
            </div></article>
        <?php endforeach; ?>
    </div>
    <h2><?= e(t('simulations.types_title')) ?></h2>
    <div class="grid grid-3">
        <?php foreach ($types as $type): ?>
            <article class="card reveal"><div class="card-body">
                <h3><?= e(t('interview_types.' . $type)) ?></h3>
                <p class="muted" style="margin:0"><?= e(t('interview_type_desc.' . $type)) ?></p>
            </div></article>
        <?php endforeach; ?>
    </div>
    <div class="cta-band" style="margin-top:48px">
        <div><h2><?= e(t('simulations.cta_title')) ?></h2><p><?= e(t('simulations.cta_text')) ?></p></div>
        <a class="btn btn-primary btn-lg" href="<?= e(url($authUser ? '/interview/new' : '/register')) ?>"><?= e(t('home.cta_primary')) ?></a>
    </div>
</div></section>
