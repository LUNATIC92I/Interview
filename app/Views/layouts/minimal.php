<!doctype html>
<html lang="<?= e(locale()) ?>" class="no-js">
<head>
<?= partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<header class="site-header"><div class="container nav">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-mark" aria-hidden="true">IP</span><span>Interview<span class="pro">Pro</span></span></a>
    <div class="nav-actions"><?= partial('partials/lang_switch') ?></div>
</div></header>
<main id="main" tabindex="-1"><?= $content ?></main>
</body>
</html>
