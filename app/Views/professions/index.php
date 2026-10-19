<section class="page-header"><div class="container">
    <p class="eyebrow"><?= e(t('nav.professions')) ?></p>
    <h1><?= e(t('professions.title')) ?></h1>
    <p class="lead"><?= e(t('professions.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:24px"><div class="container">
    <form class="filters card card-body" method="get" action="<?= e(url('/professions')) ?>" role="search">
        <div class="form-group">
            <label for="q"><?= e(t('professions.search')) ?></label>
            <input class="input" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('professions.search_placeholder')) ?>">
        </div>
        <div class="form-group">
            <label for="category"><?= e(t('professions.category')) ?></label>
            <select id="category" name="category">
                <option value=""><?= e(t('professions.all_categories')) ?></option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $category === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['profession_count'] ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.search')) ?></button>
    </form>
    <p class="muted" aria-live="polite"><?= e(t('professions.results', ['count' => $pager->total])) ?></p>
    <?php if (!$items): ?>
        <div class="card empty"><?= icon('search', 32) ?><p><?= e(t('professions.none')) ?></p></div>
    <?php endif; ?>
    <div class="grid grid-3">
        <?php foreach ($items as $p): ?>
            <a class="card card-hover reveal" href="<?= e(url('/professions/' . $p['slug'])) ?>"><div class="card-body">
                <div class="row" style="justify-content:space-between;align-items:flex-start">
                    <div class="card-icon"><?= icon($p['icon'], 22) ?></div>
                    <span class="badge"><?= e($p['category_name']) ?></span>
                </div>
                <h2 style="font-size:1.08rem"><?= e($p['name']) ?></h2>
                <p class="muted small"><?= e($p['summary']) ?></p>
                <div class="row small muted">
                    <span><?= icon('message-square', 16) ?> <?= e(t('professions.question_count', ['count' => $p['question_count']])) ?></span>
                    <span><?= icon('user', 16) ?> <?= e(\App\Models\Domain::levelLabel((int) $p['level_min'])) ?> &rarr; <?= e(\App\Models\Domain::levelLabel((int) $p['level_max'])) ?></span>
                </div>
                <?php if ($p['is_fallback']): ?><p class="small muted" style="margin:.4rem 0 0"><?= e(t('content.fallback')) ?></p><?php endif; ?>
            </div></a>
        <?php endforeach; ?>
    </div>
    <?= partial('partials/pagination', ['pager' => $pager]) ?>
</div></section>
