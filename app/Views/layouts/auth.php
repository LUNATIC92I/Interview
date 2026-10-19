<!doctype html>
<html lang="<?= e(locale()) ?>" class="no-js<?= !empty($reduceMotion) ? ' reduce-motion' : '' ?>">
<head>
<?= partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('nav.skip')) ?></a>
<div class="auth-shell">
    <aside class="auth-aside">
        <a class="brand" href="<?= e(url('/')) ?>" style="color:#fff;margin-bottom:2rem"><span class="brand-mark" aria-hidden="true">IP</span><span>InterviewPro</span></a>
        <h2><?= e(t('auth.aside_title')) ?></h2>
        <ul class="feature-list">
            <li><?= icon('check-circle', 20) ?><span><?= e(t('auth.aside_1')) ?></span></li>
            <li><?= icon('check-circle', 20) ?><span><?= e(t('auth.aside_2')) ?></span></li>
            <li><?= icon('check-circle', 20) ?><span><?= e(t('auth.aside_3')) ?></span></li>
        </ul>
    </aside>
    <div class="auth-main">
        <div class="auth-top">
            <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-mark" aria-hidden="true">IP</span><span>Interview<span class="pro">Pro</span></span></a>
            <?= partial('partials/lang_switch') ?>
        </div>
        <main id="main" class="auth-card" tabindex="-1">
            <?= partial('partials/flash') ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?= partial('partials/confirm_dialog') ?>
</body>
</html>
