<!doctype html>
<html lang="<?= e(locale()) ?>" class="no-js<?= !empty($reduceMotion) ? ' reduce-motion' : '' ?>">
<head>
<?= partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('nav.skip')) ?></a>
<?= partial('partials/header') ?>
<main id="main" tabindex="-1">
    <?php $notice = \App\Services\Settings::get('maintenance_message'); if ($notice): ?><div class="container" style="padding-top:16px"><div class="alert alert-info" role="status"><?= icon('info', 20) ?><p><?= e($notice) ?></p></div></div><?php endif; ?>
    <?php if (!empty($flash)): ?><div class="container" style="padding-top:16px"><?= partial('partials/flash') ?></div><?php endif; ?>
    <?= $content ?>
</main>
<?= partial('partials/footer') ?>
<?= partial('partials/confirm_dialog') ?>
</body>
</html>
