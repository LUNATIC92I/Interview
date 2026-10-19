<?php
$r = $report;
$s = $r['session'];
$score = (float) $r['score'];
?>
<section class="page-header"><div class="container">
    <nav class="breadcrumb no-print" aria-label="<?= e(t('nav.breadcrumb')) ?>"><a href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a><?= icon('chevron-right', 14) ?><span><?= e(t('report.title')) ?></span></nav>
    <div class="row row-between">
        <div>
            <p class="eyebrow"><?= e(t('report.eyebrow')) ?></p>
            <h1><?= e($r['meta']['profession']) ?></h1>
            <p class="muted" style="margin:0"><?= e($r['meta']['type']) ?><?= $r['scenario'] ? ' &middot; ' . e($r['scenario']['title']) : '' ?></p>
        </div>
        <div class="row no-print">
            <a class="btn btn-primary" href="<?= e(url('/reports/' . $s['public_id'] . '/pdf')) ?>"><?= icon('download', 18) ?><?= e(t('report.pdf')) ?></a>
            <button class="btn btn-secondary" type="button" data-print><?= icon('printer', 18) ?><?= e(t('report.print')) ?></button>
        </div>
    </div>
</div></section>

<section class="section" style="padding-top:16px"><div class="container stack">
    <div class="card reveal"><div class="card-body report-hero">
        <div class="score-ring <?= score_class($score) ?>" style="--value:<?= (int) round($score) ?>" role="img" aria-label="<?= e(t('report.score_aria', ['score' => (int) round($score)])) ?>">
            <div class="score-ring-value"><strong><?= (int) round($score) ?></strong><span>/100</span></div>
        </div>
        <div>
            <h2 style="margin-bottom:.3rem"><?= e(t('report.overall_score')) ?> : <?= e(score_label($score)) ?></h2>
            <dl class="meta-list">
                <div><dt><?= e(t('report.meta.date')) ?></dt><dd><?= e($r['meta']['date']) ?></dd></div>
                <div><dt><?= e(t('report.meta.mode')) ?></dt><dd><?= e($r['meta']['mode']) ?></dd></div>
                <div><dt><?= e(t('report.meta.level')) ?></dt><dd><?= e($r['meta']['level']) ?></dd></div>
                <div><dt><?= e(t('report.meta.language')) ?></dt><dd><?= e($r['meta']['language']) ?></dd></div>
                <div><dt><?= e(t('report.meta.difficulty')) ?></dt><dd><?= e($r['meta']['difficulty']) ?></dd></div>
                <div><dt><?= e(t('report.meta.duration')) ?></dt><dd><?= e(format_duration((int) $r['elapsed'])) ?></dd></div>
                <div><dt><?= e(t('report.meta.answered')) ?></dt><dd><?= (int) $r['answered'] ?> / <?= count($r['answers']) ?><?= $r['skipped'] ? ' (' . e(t('report.skipped_n', ['n' => $r['skipped']])) . ')' : '' ?></dd></div>
            </dl>
        </div>
    </div>
    <div class="card-footer"><p class="disclaimer" style="margin:0"><?= icon('info', 16) ?><span><?= e(t('report.disclaimer')) ?> <?= e($r['uses_llm'] ? t('report.engine_llm_note') : t('report.engine_rules_note')) ?></span></p></div>
    </div>

    <div class="grid grid-2">
        <div class="card reveal"><div class="card-header"><h2><?= icon('bar-chart', 20) ?> <?= e(t('report.by_criterion')) ?></h2></div><div class="card-body">
            <?php foreach ($r['criteria'] as $c): ?>
                <div class="meter"><span class="meter-label"><?= e($c['name']) ?></span><span class="meter-value"><?= (int) $c['score'] ?>/100</span><div class="progress"><div class="progress-bar <?= score_class((float) $c['score']) ?>" style="--value:<?= (int) $c['score'] ?>%"></div></div></div>
            <?php endforeach; ?>
        </div></div>
        <div class="card reveal"><div class="card-header"><h2><?= icon('award', 20) ?> <?= e(t('report.by_skill')) ?></h2></div><div class="card-body">
            <?php if (!$r['skills']): ?><p class="muted"><?= e(t('report.no_skills')) ?></p><?php endif; ?>
            <?php foreach ($r['skills'] as $c): ?>
                <div class="meter"><span class="meter-label"><?= e($c['name']) ?></span><span class="meter-value"><?= (int) $c['score'] ?>/100</span><div class="progress"><div class="progress-bar <?= score_class((float) $c['score']) ?>" style="--value:<?= (int) $c['score'] ?>%"></div></div></div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="grid grid-3">
        <div class="card reveal"><div class="card-body"><h2 style="font-size:1.05rem"><?= icon('check-circle', 20) ?> <?= e(t('report.strengths')) ?></h2>
            <ul class="fb-list good"><?php foreach ($r['strengths'] ?: [t('report.none_yet')] as $x): ?><li><?= icon('check', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul></div></div>
        <div class="card reveal"><div class="card-body"><h2 style="font-size:1.05rem"><?= icon('target', 20) ?> <?= e(t('report.improvements')) ?></h2>
            <ul class="fb-list gap"><?php foreach ($r['improvements'] ?: [t('report.none_yet')] as $x): ?><li><?= icon('alert-circle', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul></div></div>
        <div class="card reveal"><div class="card-body"><h2 style="font-size:1.05rem"><?= icon('flag', 20) ?> <?= e(t('report.priorities')) ?></h2>
            <ol class="fb-list reco" style="padding-left:0"><?php foreach ($r['priorities'] ?: [t('report.none_yet')] as $x): ?><li><?= icon('arrow-right', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ol></div></div>
    </div>

    <div class="card"><div class="card-header"><h2><?= icon('message-square', 20) ?> <?= e(t('report.answers')) ?></h2></div><div class="card-body">
        <?php foreach ($r['answers'] as $a): $f = $a['feedback']; ?>
            <article class="answer-review">
                <div class="row row-between">
                    <p class="eyebrow" style="margin:0"><?= e(t('report.question_n', ['n' => $a['position']])) ?> &middot; <?= e(t('question_types.' . ($a['type'] ?: 'hr'))) ?><?= $a['is_followup'] ? ' &middot; ' . e(t('interview.followup_label')) : '' ?></p>
                    <?php if ($f): ?><span class="badge badge-<?= e($f['score_class']) ?>"><?= (int) round($f['score']) ?>/100 &middot; <?= e($f['score_label']) ?></span><?php endif; ?>
                </div>
                <h3><?= e($a['question']) ?></h3>
                <p class="small muted"><?= e(t('answer_status.' . $a['status'])) ?> &middot; <?= e(format_duration($a['time_spent'])) ?> &middot; <?= e(t('report.words_n', ['n' => $a['word_count']])) ?></p>
                <?php if ($a['answer'] !== ''): ?><p class="label"><?= e(t('report.your_answer')) ?></p><div class="answer-text"><?= e($a['answer']) ?></div><?php endif; ?>
                <?php if ($f): ?>
                    <?php if ($f['summary'] !== ''): ?><p style="margin-top:1rem"><?= e($f['summary']) ?></p><?php endif; ?>
                    <div class="grid grid-2" style="margin-top:1rem">
                        <div>
                            <?php if ($f['strengths']): ?><h4><?= e(t('report.strengths')) ?></h4><ul class="fb-list good"><?php foreach ($f['strengths'] as $x): ?><li><?= icon('check', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                            <?php if ($f['gaps']): ?><h4><?= e(t('report.gaps')) ?></h4><ul class="fb-list gap"><?php foreach ($f['gaps'] as $x): ?><li><?= icon('alert-circle', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                            <?php if ($f['skills']): ?><h4><?= e(t('report.skills_shown')) ?></h4><ul class="pill-list"><?php foreach ($f['skills'] as $x): ?><li class="badge badge-primary"><?= e($x) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        </div>
                        <div>
                            <?php if ($f['missing_elements']): ?><h4><?= e(t('report.missing')) ?></h4><ul class="fb-list gap"><?php foreach ($f['missing_elements'] as $x): ?><li><?= icon('alert-circle', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                            <?php if ($f['recommendations']): ?><h4><?= e(t('report.recommendations')) ?></h4><ul class="fb-list reco"><?php foreach ($f['recommendations'] as $x): ?><li><?= icon('arrow-right', 18) ?><span><?= e($x) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($f['next_tip'] !== ''): ?><p class="guidance"><strong><?= e(t('report.next_tip')) ?> :</strong> <?= e($f['next_tip']) ?></p><?php endif; ?>
                    <details>
                        <summary><?= e(t('report.details')) ?></summary>
                        <div class="table-wrap" style="margin:.8rem 0"><table class="table criteria-table">
                            <thead><tr><th><?= e(t('report.criterion')) ?></th><th><?= e(t('report.points')) ?></th><th><?= e(t('report.level')) ?></th></tr></thead>
                            <tbody><?php foreach ($f['criteria'] as $c): ?>
                                <tr><td><?= e($c['name']) ?><br><span class="small muted"><?= e($c['description']) ?></span></td><td><?= e(format_number($c['points'], 1)) ?> / <?= (int) $c['weight'] ?></td><td><div class="progress"><div class="progress-bar <?= score_class($c['ratio'] * 100) ?>" style="--value:<?= (int) round($c['ratio'] * 100) ?>%"></div></div></td></tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                        <?php if ($f['evaluation_criteria'] !== ''): ?><p class="small"><strong><?= e(t('report.evaluation_criteria')) ?> :</strong> <?= e($f['evaluation_criteria']) ?></p><?php endif; ?>
                        <?php if ($f['common_mistakes']): ?><p class="small"><strong><?= e(t('report.common_mistakes')) ?> :</strong> <?= e(implode(' ; ', $f['common_mistakes'])) ?></p><?php endif; ?>
                        <?php if ($f['example_answer'] !== ''): ?><p class="label"><?= e(t('report.example_answer')) ?></p><p class="small muted"><?= e(t('js.example_note')) ?></p><div class="example-answer"><?= e($f['example_answer']) ?></div><?php endif; ?>
                        <p class="small muted" style="margin-top:.6rem"><?= e($f['evaluator_label']) ?> &middot; <?= e(t('report.confidence')) ?> : <?= e($f['confidence_label']) ?></p>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div></div>

    <?php if ($attempts): ?>
    <div class="card no-print"><div class="card-header"><h2><?= icon('compare', 20) ?> <?= e(t('report.other_attempts')) ?></h2></div><div class="card-body">
        <ul class="feature-list">
            <?php foreach ($attempts as $at): ?>
                <li><?= icon('history', 18) ?><span><?= e(format_date($at['completed_at'], true)) ?> &middot; <?= (int) round((float) $at['overall_score']) ?>/100 &middot;
                    <a href="<?= e(url('/reports/compare', ['a' => $at['public_id'], 'b' => $s['public_id']])) ?>"><?= e(t('report.compare_with')) ?></a></span></li>
            <?php endforeach; ?>
        </ul>
    </div></div>
    <?php endif; ?>

    <div class="row no-print">
        <form method="post" action="<?= e(url('/interview')) ?>">
            <?= csrf_field() ?>
            <?php foreach (['profession_id', 'scenario_id', 'experience_level', 'interview_type', 'locale', 'difficulty', 'duration_minutes', 'question_count', 'mode', 'allow_skip', 'allow_back', 'allow_pause', 'followups_enabled'] as $field): ?>
                <input type="hidden" name="<?= $field === 'followups_enabled' ? 'followups' : $field ?>" value="<?= e((string) ($s[$field] ?? '')) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="use_timer" value="<?= $s['per_question_seconds'] ? '1' : '' ?>">
            <button class="btn btn-primary" type="submit" data-loading-text="<?= e(t('setup.preparing')) ?>"><?= icon('rotate-ccw', 18) ?><?= e(t('report.restart')) ?></button>
        </form>
        <a class="btn btn-secondary" href="<?= e(url('/dashboard')) ?>"><?= icon('layout-dashboard', 18) ?><?= e(t('report.back_dashboard')) ?></a>
    </div>
</div></section>
