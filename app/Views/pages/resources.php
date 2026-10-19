<section class="page-header"><div class="container">
    <p class="eyebrow"><?= e(t('nav.resources')) ?></p>
    <h1><?= e(t('resources.title')) ?></h1>
    <p class="lead"><?= e(t('resources.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:24px"><div class="container">
    <?php if (!$items): ?><div class="card empty"><?= icon('book-open', 32) ?><p><?= e(t('resources.empty')) ?></p></div><?php endif; ?>
    <div class="grid grid-3">
        <?php foreach ($items as $item): ?>
            <a class="card card-hover reveal" href="<?= e(url('/resources/' . $item['slug'])) ?>"><div class="card-body">
                <span class="badge badge-primary"><?= e(t('resources.types.' . $item['content_type'])) ?></span>
                <h2 style="font-size:1.1rem;margin-top:.8rem"><?= e($item['title']) ?></h2>
                <p class="muted small" style="margin:0"><?= e($item['summary']) ?></p>
                <?php if ($item['is_fallback']): ?><p class="small muted" style="margin:.5rem 0 0"><?= icon('languages', 14) ?> <?= e(t('content.fallback')) ?></p><?php endif; ?>
            </div></a>
        <?php endforeach; ?>
    </div>
</div></section>
