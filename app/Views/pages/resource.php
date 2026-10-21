<div class="read-progress" aria-hidden="true"></div>
<section class="page-header"><div class="container container-narrow">
    <nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>"><a href="<?= e(url('/resources')) ?>"><?= e(t('nav.resources')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($item['title']) ?></span></nav>
    <h1><?= e($item['title']) ?></h1>
    <p class="lead"><?= e($item['summary']) ?></p>
    <?php if ($item['is_fallback']): ?><div class="alert alert-info"><?= icon('languages', 20) ?><p><?= e(t('content.fallback')) ?></p></div><?php endif; ?>
</div></section>
<section class="section" style="padding-top:16px"><div class="container container-narrow">
    <article class="card"><div class="card-body">
        <?php foreach (preg_split('/\n\s*\n/', (string) $item['body']) as $para): ?><p><?= nl2br(e(trim($para))) ?></p><?php endforeach; ?>
    </div></article>
    <p style="margin-top:24px"><a class="btn btn-primary" href="<?= e(url($authUser ? '/interview/new' : '/register')) ?>"><?= e(t('resources.practice')) ?></a></p>
</div></section>
