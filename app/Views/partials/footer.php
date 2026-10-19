<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-mark" aria-hidden="true">IP</span><span>Interview<span class="pro">Pro</span></span></a>
            <p style="margin-top:.8rem;max-width:30rem"><?= e(t('footer.tagline')) ?></p>
        </div>
        <div>
            <h3><?= e(t('footer.platform')) ?></h3>
            <ul>
                <li><a href="<?= e(url('/simulations')) ?>"><?= e(t('nav.simulations')) ?></a></li>
                <li><a href="<?= e(url('/professions')) ?>"><?= e(t('nav.professions')) ?></a></li>
                <li><a href="<?= e(url('/features')) ?>"><?= e(t('nav.features')) ?></a></li>
            </ul>
        </div>
        <div>
            <h3><?= e(t('footer.resources')) ?></h3>
            <ul>
                <li><a href="<?= e(url('/resources')) ?>"><?= e(t('nav.resources')) ?></a></li>
                <li><a href="<?= e(url('/#faq')) ?>"><?= e(t('home.faq.title')) ?></a></li>
            </ul>
        </div>
        <div>
            <h3><?= e(t('footer.legal')) ?></h3>
            <ul>
                <li><a href="<?= e(url('/privacy')) ?>"><?= e(t('privacy.title')) ?></a></li>
            </ul>
        </div>
    </div>
    <div class="container"><p style="margin:1.5rem 0 0">&copy; <?= date('Y') ?> InterviewPro. <?= e(t('footer.disclaimer')) ?></p></div>
</footer>
