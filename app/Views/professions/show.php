<?php use App\Models\Domain; ?>
<section class="page-header"><div class="container">
    <nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>"><a href="<?= e(url('/professions')) ?>"><?= e(t('nav.professions')) ?></a><?= icon('chevron-right', 14) ?><a href="<?= e(url('/professions', ['category' => $profession['category_id']])) ?>"><?= e($profession['category_name']) ?></a></nav>
    <div class="row" style="align-items:flex-start;gap:16px">
        <div class="card-icon" style="margin:0"><?= icon($profession['icon'], 24) ?></div>
        <div style="flex:1;min-width:240px">
            <h1><?= e($profession['name']) ?></h1>
            <p class="lead"><?= e($profession['summary']) ?></p>
            <?php if ($profession['is_fallback']): ?><div class="alert alert-info"><?= icon('languages', 20) ?><p><?= e(t('content.fallback')) ?></p></div><?php endif; ?>
        </div>
    </div>
    <div class="row" style="margin-top:8px">
        <?php if ($accessState === 'locked'): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/checkout/' . $profession['slug'])) ?>"><?= icon('lock', 18) ?><?= e(t('access.unlock_for', ['price' => $price])) ?></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('/interview/new', ['profession' => $profession['slug']])) ?>"><?= icon('play', 18) ?><?= e(t('access.try_free')) ?></a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/interview/new', ['profession' => $profession['slug']])) ?>"><?= icon('play', 18) ?><?= e(t('professions.start')) ?></a>
            <?php if ($accessState === 'unlocked'): ?>
                <a class="btn btn-secondary btn-lg" href="<?= e(url('/my-resources/' . $profession['slug'])) ?>"><?= icon('book-open', 18) ?><?= e(t('my_resources.open')) ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($accessState === 'locked'): ?><p class="small muted" style="margin-top:.6rem"><?= e(t('access.profession_note', ['price' => $price])) ?> <a href="<?= e(url('/pricing')) ?>"><?= e(t('access.see_pricing')) ?></a></p><?php endif; ?>
    <?php if ($accessState === 'unlocked'): ?><p class="small" style="margin-top:.6rem;color:var(--success-text)"><?= icon('check-circle', 16) ?> <?= e(t('access.profession_unlocked')) ?></p><?php endif; ?>
</div></section>
<section class="section" style="padding-top:24px"><div class="container grid grid-sidebar">
    <div class="stack">
        <div class="card"><div class="card-header"><h2><?= e(t('professions.responsibilities')) ?></h2></div><div class="card-body">
            <ul class="feature-list"><?php foreach (array_filter(explode("\n", (string) $profession['responsibilities'])) as $r): ?><li><?= icon('check', 18) ?><span><?= e($r) ?></span></li><?php endforeach; ?></ul>
        </div></div>
        <div class="card"><div class="card-header"><h2><?= e(t('professions.sample_questions')) ?></h2></div><div class="card-body">
            <?php if (!$samples): ?><p class="muted"><?= e(t('professions.no_specific')) ?></p><?php endif; ?>
            <ul class="feature-list">
                <?php foreach ($samples as $s): ?><li><?= icon('message-square', 18) ?><span><?= e($s['body']) ?> <span class="badge"><?= e(t('question_types.' . $s['question_type'])) ?></span></span></li><?php endforeach; ?>
            </ul>
        </div></div>
        <?php if ($scenarios): ?>
        <div class="card"><div class="card-header"><h2><?= e(t('professions.scenarios')) ?></h2></div><div class="card-body stack">
            <?php foreach ($scenarios as $s): ?>
                <div class="row row-between" style="align-items:flex-start">
                    <div style="flex:1;min-width:220px">
                        <h3 style="margin:0"><?= e($s['title']) ?></h3>
                        <p class="muted small" style="margin:.2rem 0"><?= e($s['description']) ?></p>
                        <span class="badge"><?= e(t('interview_types.' . $s['interview_type'])) ?></span>
                        <span class="badge"><?= e(t('professions.scenario_questions', ['count' => $s['question_count']])) ?></span>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('/interview/new', ['profession' => $profession['slug'], 'scenario' => $s['id']])) ?>"><?= e(t('professions.use_scenario')) ?></a>
                </div>
            <?php endforeach; ?>
        </div></div>
        <?php endif; ?>
    </div>
    <aside class="stack">
        <div class="card"><div class="card-body">
            <h2 style="font-size:1.05rem"><?= e(t('professions.skills')) ?></h2>
            <ul class="pill-list"><?php foreach ($skills as $s): ?><li class="badge badge-primary"><?= e(loc($s)) ?></li><?php endforeach; ?></ul>
            <h2 style="font-size:1.05rem;margin-top:1.2rem"><?= e(t('professions.levels')) ?></h2>
            <p class="muted"><?= e(Domain::levelLabel((int) $profession['level_min'])) ?> &rarr; <?= e(Domain::levelLabel((int) $profession['level_max'])) ?></p>
            <h2 style="font-size:1.05rem;margin-top:1.2rem"><?= e(t('professions.bank')) ?></h2>
            <ul class="feature-list small">
                <?php foreach ($stats as $st): ?><li><span class="badge"><?= (int) $st['n'] ?></span><span><?= e(t('question_types.' . $st['question_type'])) ?></span></li><?php endforeach; ?>
            </ul>
        </div></div>
        <?php if ($related): ?>
        <div class="card"><div class="card-body">
            <h2 style="font-size:1.05rem"><?= e(t('professions.related')) ?></h2>
            <ul class="feature-list"><?php foreach ($related as $r): ?><li><?= icon('chevron-right', 16) ?><a href="<?= e(url('/professions/' . $r['slug'])) ?>"><?= e($r['name']) ?></a></li><?php endforeach; ?></ul>
        </div></div>
        <?php endif; ?>
    </aside>
</div></section>
