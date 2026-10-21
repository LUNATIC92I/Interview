<?php use App\Core\View; ?>
<?php $current = View::shared('request'); $path = $current ? $current->fullPath() : '/'; ?>
<nav class="lang-switch" aria-label="<?= e(t('nav.language')) ?>">
    <a href="<?= e(url('/lang/fr', ['redirect' => $path])) ?>" lang="fr" hreflang="fr" <?= locale() === 'fr' ? 'aria-current="true"' : '' ?> data-lang-switch>FR</a>
    <a href="<?= e(url('/lang/en', ['redirect' => $path])) ?>" lang="en" hreflang="en" <?= locale() === 'en' ? 'aria-current="true"' : '' ?> data-lang-switch>EN</a>
</nav>
