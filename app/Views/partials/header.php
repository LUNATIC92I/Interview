<?php
$links = [
    '/' => t('nav.home'),
    '/simulations' => t('nav.simulations'),
    '/professions' => t('nav.professions'),
    '/features' => t('nav.features'),
    '/resources' => t('nav.resources'),
];
$initial = $authUser ? mb_strtoupper(mb_substr($authUser['display_name'], 0, 1)) : '';
?>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="InterviewPro, <?= e(t('nav.home')) ?>">
            <span class="brand-mark" aria-hidden="true">IP</span><span>Interview<span class="pro">Pro</span></span>
        </a>
        <ul class="nav-links">
            <?php foreach ($links as $href => $label): ?>
                <li><a href="<?= e(url($href)) ?>" <?= is_active_path($href) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="nav-actions">
            <?= partial('partials/lang_switch') ?>
            <?php if ($authUser): ?>
                <a class="btn btn-primary btn-sm btn-desktop" href="<?= e(url('/interview/new')) ?>"><?= icon('play', 16) ?><?= e(t('nav.start')) ?></a>
                <details class="user-menu">
                    <summary aria-label="<?= e(t('nav.account_menu')) ?>"><span class="avatar" aria-hidden="true"><?= e($initial) ?></span><span class="sr-only"><?= e($authUser['display_name']) ?></span><?= icon('chevron-down', 16) ?></summary>
                    <div class="menu">
                        <a href="<?= e(url('/dashboard')) ?>"><?= icon('layout-dashboard', 18) ?><?= e(t('nav.dashboard')) ?></a>
                        <a href="<?= e(url('/history')) ?>"><?= icon('history', 18) ?><?= e(t('nav.history')) ?></a>
                        <a href="<?= e(url('/account')) ?>"><?= icon('user', 18) ?><?= e(t('nav.account')) ?></a>
                        <?php if (in_array('admin', $authUser['roles'], true)): ?>
                            <a href="<?= e(url('/admin')) ?>"><?= icon('sliders', 18) ?><?= e(t('nav.admin')) ?></a>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('log-out', 18) ?><?= e(t('nav.logout')) ?></button></form>
                    </div>
                </details>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm btn-desktop" href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
                <a class="btn btn-primary btn-sm btn-desktop" href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a>
            <?php endif; ?>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" data-nav-toggle>
                <?= icon('menu', 22) ?><span class="sr-only"><?= e(t('nav.menu')) ?></span>
            </button>
        </div>
    </div>
    <div class="mobile-menu" id="mobile-menu">
        <div class="container">
            <ul>
                <?php foreach ($links as $href => $label): ?>
                    <li><a href="<?= e(url($href)) ?>" <?= is_active_path($href) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
                <?php if ($authUser): ?>
                    <li><a href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a></li>
                    <li><a href="<?= e(url('/history')) ?>"><?= e(t('nav.history')) ?></a></li>
                    <li><a href="<?= e(url('/account')) ?>"><?= e(t('nav.account')) ?></a></li>
                    <?php if (in_array('admin', $authUser['roles'], true)): ?><li><a href="<?= e(url('/admin')) ?>"><?= e(t('nav.admin')) ?></a></li><?php endif; ?>
                    <li><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('log-out', 18) ?><?= e(t('nav.logout')) ?></button></form></li>
                <?php endif; ?>
            </ul>
            <div class="row">
                <?php if ($authUser): ?>
                    <a class="btn btn-primary" href="<?= e(url('/interview/new')) ?>"><?= e(t('nav.start')) ?></a>
                <?php else: ?>
                    <a class="btn btn-secondary" href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a>
                    <a class="btn btn-primary" href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
