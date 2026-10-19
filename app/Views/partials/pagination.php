<?php /** @var \App\Helpers\Paginator $pager */ if ($pager->pages > 1): ?>
<nav aria-label="<?= e(t('pagination.label')) ?>">
    <ul class="pagination">
        <?php if ($pager->page > 1): ?><li><a href="<?= e($pager->url($pager->page - 1)) ?>" rel="prev"><?= icon('chevron-left', 16) ?><span class="sr-only"><?= e(t('pagination.previous')) ?></span></a></li><?php endif; ?>
        <?php foreach ($pager->window() as $p): ?>
            <?php if ($p === null): ?><li><span aria-hidden="true">&hellip;</span></li>
            <?php elseif ($p === $pager->page): ?><li><span aria-current="page"><?= $p ?></span></li>
            <?php else: ?><li><a href="<?= e($pager->url($p)) ?>"><span class="sr-only"><?= e(t('pagination.page')) ?> </span><?= $p ?></a></li><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($pager->page < $pager->pages): ?><li><a href="<?= e($pager->url($pager->page + 1)) ?>" rel="next"><?= icon('chevron-right', 16) ?><span class="sr-only"><?= e(t('pagination.next')) ?></span></a></li><?php endif; ?>
    </ul>
    <p class="muted small" style="text-align:center"><?= e(t('pagination.summary', ['total' => $pager->total, 'page' => $pager->page, 'pages' => $pager->pages])) ?></p>
</nav>
<?php endif; ?>
