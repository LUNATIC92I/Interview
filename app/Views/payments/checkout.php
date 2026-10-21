<section class="page-header"><div class="container">
    <nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>"><a href="<?= e(url('/professions/' . $profession['slug'])) ?>"><?= e($profession['name']) ?></a><?= icon('chevron-right', 14) ?><span><?= e(t('checkout.breadcrumb')) ?></span></nav>
    <p class="eyebrow"><?= e(t('checkout.eyebrow')) ?></p>
    <h1><?= e(t('checkout.heading', ['profession' => $profession['name']])) ?></h1>
    <p class="lead"><?= e(t('checkout.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container grid grid-sidebar">
    <div class="card"><div class="card-header"><h2><?= e(t('checkout.included')) ?></h2></div><div class="card-body">
        <ul class="feature-list">
            <li><?= icon('play', 18) ?><span><?= e(t('checkout.item_simulations')) ?></span></li>
            <li><?= icon('message-square', 18) ?><span><?= e(t('checkout.item_library', ['count' => $libraryCount])) ?></span></li>
            <li><?= icon('lightbulb', 18) ?><span><?= e(t('checkout.item_answers')) ?></span></li>
            <li><?= icon('layers', 18) ?><span><?= e(t('checkout.item_scenarios')) ?></span></li>
            <li><?= icon('file-text', 18) ?><span><?= e(t('checkout.item_reports')) ?></span></li>
            <li><?= icon('clock', 18) ?><span><?= e(t('checkout.item_unlimited')) ?></span></li>
        </ul>
        <?php if ($skills): ?>
            <h3 style="font-size:1rem;margin-top:1.2rem"><?= e(t('professions.skills')) ?></h3>
            <div class="row" style="gap:6px"><?php foreach ($skills as $s): ?><span class="badge badge-primary"><?= e(locale() === 'fr' ? $s['name_fr'] : $s['name_en']) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
    </div></div>
    <aside class="card"><div class="card-body stack">
        <div>
            <div class="stat-label"><?= e(t('checkout.price_label')) ?></div>
            <div class="stat-value" data-price><?= e($price) ?></div>
            <p class="small muted" style="margin:.2rem 0 0"><?= e(t('checkout.price_note')) ?></p>
        </div>
        <form method="post" action="<?= e(url('/checkout/' . $profession['slug'])) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-lg" type="submit" style="width:100%" data-loading-text="<?= e(t('checkout.redirecting')) ?>"><?= icon('lock', 18) ?><?= e(t('checkout.pay', ['price' => $price])) ?></button>
        </form>
        <p class="small muted" style="margin:0"><?= icon('shield-check', 16) ?> <?= e(t('checkout.secure')) ?></p>
        <p class="small muted" style="margin:0"><?= e(t('checkout.methods')) ?></p>
        <?php if ($sandbox): ?><div class="alert alert-warning"><?= icon('alert-triangle', 20) ?><p><?= e(t('checkout.sandbox_notice')) ?></p></div><?php endif; ?>
        <?php if ($trialsLeft > 0): ?>
            <a class="btn btn-ghost" href="<?= e(url('/interview/new', ['profession' => $profession['slug']])) ?>"><?= icon('play', 18) ?><?= e(t('access.try_free')) ?></a>
        <?php endif; ?>
    </div></aside>
</div></section>
