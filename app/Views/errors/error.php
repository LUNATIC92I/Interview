<section class="section"><div class="container container-narrow" style="text-align:center">
    <p class="eyebrow"><?= e(t('errors.code', ['code' => $code])) ?></p>
    <h1><?= e(t('errors.' . $code . '.title')) ?></h1>
    <p class="lead" style="margin:0 auto 1.5rem"><?= e(t('errors.' . $code . '.text')) ?></p>
    <div class="row" style="justify-content:center">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= icon('home', 18) ?><?= e(t('errors.home')) ?></a>
        <?php if ($code !== 503): ?><a class="btn btn-secondary" href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a><?php endif; ?>
    </div>
</div></section>
