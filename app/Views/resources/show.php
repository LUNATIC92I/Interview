<?php use App\Models\Domain; ?>
<section class="page-header"><div class="container">
    <nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>"><a href="<?= e(url('/my-resources')) ?>"><?= e(t('my_resources.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($profession['name']) ?></span></nav>
    <div class="row" style="align-items:flex-start;gap:16px">
        <div class="card-icon" style="margin:0"><?= icon($profession['icon'], 24) ?></div>
        <div style="flex:1;min-width:240px">
            <h1><?= e($profession['name']) ?></h1>
            <p class="lead"><?= e(t('my_resources.show_lead')) ?></p>
            <?php if ($access): ?><p class="small" style="color:var(--success-text)"><?= icon('check-circle', 16) ?> <?= e(t('my_resources.since', ['date' => format_date($access['granted_at'])])) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="row" style="margin-top:8px">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/interview/new', ['profession' => $profession['slug']])) ?>"><?= icon('play', 18) ?><?= e(t('professions.start')) ?></a>
        <a class="btn btn-secondary btn-lg" href="<?= e(url('/professions/' . $profession['slug'])) ?>"><?= icon('briefcase', 18) ?><?= e(t('my_resources.profile')) ?></a>
    </div>
</div></section>
<section class="section" style="padding-top:24px"><div class="container grid grid-sidebar">
    <div class="stack">
        <div class="card"><div class="card-header"><h2><?= e(t('my_resources.library', ['count' => $questionCount])) ?></h2></div><div class="card-body stack">
            <p class="muted small" style="margin:0"><?= e(t('my_resources.library_help')) ?></p>
            <?php foreach ($library as $type => $questions): ?>
                <h3 style="font-size:1.05rem;margin:.6rem 0 0"><?= e(t('question_types.' . $type)) ?> <span class="badge"><?= count($questions) ?></span></h3>
                <div class="faq">
                    <?php foreach ($questions as $q): ?>
                        <details>
                            <summary><span><?= e($q['body']) ?><?php if ($q['is_specific']): ?> <span class="badge badge-primary"><?= e(t('my_resources.specific')) ?></span><?php elseif ($q['is_sector']): ?> <span class="badge"><?= e(t('my_resources.sector')) ?></span><?php endif; ?></span><?= icon('chevron-down', 18) ?></summary>
                            <div class="faq-body stack">
                                <p class="small" style="margin:0"><?= e(t('my_resources.meta', ['difficulty' => (int) $q['difficulty'], 'duration' => format_duration((int) $q['recommended_seconds']), 'levels' => t('levels.' . Domain::levelCode((int) $q['level_min'])) . ' - ' . t('levels.' . Domain::levelCode((int) $q['level_max']))])) ?></p>
                                <?php if ($q['guidance']): ?><p style="margin:0"><strong><?= e(t('my_resources.guidance')) ?></strong> <?= e($q['guidance']) ?></p><?php endif; ?>
                                <?php if ($q['expected_elements']): ?>
                                    <div><strong><?= e(t('my_resources.expected')) ?></strong>
                                    <ul class="feature-list" style="margin-top:.4rem"><?php foreach ($q['expected_elements'] as $el): ?><li><?= icon('check', 16) ?><span><?= e(is_array($el) ? ($el['label'] ?? '') : $el) ?></span></li><?php endforeach; ?></ul></div>
                                <?php endif; ?>
                                <?php if ($q['common_mistakes']): ?>
                                    <div><strong><?= e(t('my_resources.mistakes')) ?></strong>
                                    <ul class="feature-list" style="margin-top:.4rem"><?php foreach ($q['common_mistakes'] as $m): ?><li><?= icon('x', 16) ?><span><?= e($m) ?></span></li><?php endforeach; ?></ul></div>
                                <?php endif; ?>
                                <?php if ($q['example_answer']): ?><div class="card card-body" style="background:var(--primary-light);border-color:transparent"><strong><?= e(t('my_resources.example')) ?></strong><p style="margin:.4rem 0 0"><?= e($q['example_answer']) ?></p></div><?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div></div>
        <?php if ($scenarios): ?>
        <div class="card"><div class="card-header"><h2><?= e(t('professions.scenarios')) ?></h2></div><div class="card-body stack">
            <?php foreach ($scenarios as $s): ?>
                <div class="row row-between" style="align-items:flex-start">
                    <div style="flex:1;min-width:220px">
                        <h3 style="margin:0;font-size:1rem"><?= e($s['title']) ?></h3>
                        <p class="muted small" style="margin:.2rem 0"><?= e($s['description']) ?></p>
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
            <h2 style="font-size:1.05rem"><?= e(t('my_resources.your_results')) ?></h2>
            <?php if ((int) $results['sessions'] === 0): ?>
                <p class="muted small"><?= e(t('my_resources.no_results')) ?></p>
            <?php else: ?>
                <dl class="kv">
                    <dt><?= e(t('dashboard.completed')) ?></dt><dd><?= (int) $results['sessions'] ?></dd>
                    <dt><?= e(t('dashboard.average')) ?></dt><dd><?= (int) $results['average'] ?>/100</dd>
                    <dt><?= e(t('dashboard.best')) ?></dt><dd><?= (int) round((float) $results['best']) ?>/100</dd>
                </dl>
            <?php endif; ?>
            <p class="disclaimer" style="margin-top:.8rem"><?= icon('info', 16) ?><span><?= e(t('dashboard.disclaimer')) ?></span></p>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 style="font-size:1.05rem"><?= e(t('professions.skills')) ?></h2>
            <div class="row" style="gap:6px"><?php foreach ($skills as $sk): ?><span class="badge badge-primary"><?= e(locale() === 'fr' ? $sk['name_fr'] : $sk['name_en']) ?></span><?php endforeach; ?></div>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 style="font-size:1.05rem"><?= e(t('my_resources.guides')) ?></h2>
            <ul class="feature-list"><?php foreach ($guides as $g): ?><li><?= icon('book-open', 16) ?><a href="<?= e(url('/resources/' . $g['slug'])) ?>"><?= e($g['title']) ?></a></li><?php endforeach; ?></ul>
        </div></div>
    </aside>
</div></section>
