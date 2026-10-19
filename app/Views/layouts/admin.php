<?php
$nav = [
    ['group' => t('admin.nav.overview')],
    ['/admin', 'layout-dashboard', t('admin.nav.dashboard')],
    ['/admin/users', 'users', t('admin.nav.users')],
    ['group' => t('admin.nav.catalog')],
    ['/admin/categories', 'folder', t('admin.nav.categories')],
    ['/admin/professions', 'briefcase', t('admin.nav.professions')],
    ['/admin/questions', 'message-square', t('admin.nav.questions')],
    ['/admin/scenarios', 'layers', t('admin.nav.scenarios')],
    ['/admin/rubrics', 'list-checks', t('admin.nav.rubrics')],
    ['/admin/difficulties', 'gauge', t('admin.nav.difficulties')],
    ['/admin/learning', 'book-open', t('admin.nav.learning')],
    ['group' => t('admin.nav.platform')],
    ['/admin/translations', 'languages', t('admin.nav.translations')],
    ['/admin/reports', 'inbox', t('admin.nav.reports')],
    ['/admin/settings', 'sliders', t('admin.nav.settings')],
    ['/admin/audit', 'activity', t('admin.nav.audit')],
];
$req = \App\Core\View::shared('request');
$path = $req ? $req->path : '';
?>
<!doctype html>
<html lang="<?= e(locale()) ?>" class="no-js<?= !empty($reduceMotion) ? ' reduce-motion' : '' ?>">
<head>
<?= partial('partials/head', ['title' => ($title ?? '') . ' | ' . t('admin.title')]) ?>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('nav.skip')) ?></a>
<?= partial('partials/header') ?>
<div class="admin-shell">
    <aside class="admin-sidebar" aria-label="<?= e(t('admin.title')) ?>">
        <nav><ul>
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['group'])): ?><li class="group" aria-hidden="true"><?= e($item['group']) ?></li>
                <?php else: [$href, $ic, $label] = $item; $active = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
                    <li><a href="<?= e(url($href)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= icon($ic, 18) ?><?= e($label) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul></nav>
    </aside>
    <main id="main" class="admin-main" tabindex="-1">
        <?= partial('partials/flash') ?>
        <?= $content ?>
    </main>
</div>
<?= partial('partials/confirm_dialog') ?>
</body>
</html>
