<?php
use App\Helpers\Charts;
$completed = (int) ($totals['completed'] ?? 0);
$avg = $totals['avg_score'] !== null ? round((float) $totals['avg_score']) : null;
$points = array_map(static fn ($r) => ['label' => format_date($r['completed_at']), 'short' => date('d/m', strtotime($r['completed_at'] . ' UTC')), 'value' => (float) $r['overall_score']], $evolution);
?>
<section class="page-header"><div class="container">
    <div class="row row-between">
        <div>
            <p class="eyebrow"><?= e(t('dashboard.eyebrow')) ?></p>
            <h1><?= e(t('dashboard.hello', ['name' => $authUser['display_name']])) ?></h1>
        </div>
        <a class="btn btn-primary btn-lg" href="<?= e(url('/interview/new')) ?>"><?= icon('play', 18) ?><?= e(t('nav.start')) ?></a>
    </div>
    <?php if ($needsVerification): ?>
        <div class="alert alert-warning" style="margin-top:16px"><?= icon('mail', 20) ?>
            <div><p><?= e(t('auth.verify_banner')) ?></p>
            <form method="post" action="<?= e(url('/email/resend')) ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= e(t('auth.verify_resend')) ?></button></form></div>
        </div>
    <?php endif; ?>
</div></section>

<section class="section" style="padding-top:16px"><div class="container stack">
    <?php if ($access['paywall'] && !in_array('admin', $authUser['roles'], true)): ?>
    <div class="card reveal" data-access-summary><div class="card-body row row-between" style="align-items:center">
        <div style="flex:1;min-width:240px">
            <h2 style="font-size:1.05rem;margin:0"><?= icon('book-open', 18) ?> <?= e(t('my_resources.title')) ?></h2>
            <?php if ($access['unlocked']): ?>
                <p class="muted small" style="margin:.3rem 0 0"><?= e(t('dashboard.access_unlocked', ['list' => implode(', ', array_column($access['unlocked'], 'name'))])) ?></p>
            <?php elseif ($access['trials_left'] > 0): ?>
                <p class="muted small" style="margin:.3rem 0 0"><?= e(t('dashboard.access_trial', ['count' => $access['trial_questions'], 'price' => $access['price']])) ?></p>
            <?php else: ?>
                <p class="muted small" style="margin:.3rem 0 0"><?= e(t('dashboard.access_none', ['price' => $access['price']])) ?></p>
            <?php endif; ?>
        </div>
        <div class="row">
            <?php if ($access['unlocked']): ?><a class="btn btn-secondary" href="<?= e(url('/my-resources')) ?>"><?= e(t('my_resources.open')) ?></a><?php endif; ?>
            <a class="btn btn-<?= $access['unlocked'] ? 'ghost' : 'primary' ?>" href="<?= e(url('/professions')) ?>"><?= icon('lock', 16) ?><?= e(t('dashboard.unlock_profession')) ?></a>
        </div>
    </div></div>
    <?php endif; ?>
    <div class="grid grid-4">
        <div class="card stat reveal"><div class="stat-label"><?= icon('layers', 16) ?><?= e(t('dashboard.total')) ?></div><div class="stat-value" data-count="<?= (int) $totals['total'] ?>"><?= (int) $totals['total'] ?></div></div>
        <div class="card stat reveal"><div class="stat-label"><?= icon('check-circle', 16) ?><?= e(t('dashboard.completed')) ?></div><div class="stat-value" data-count="<?= $completed ?>"><?= $completed ?></div></div>
        <div class="card stat reveal"><div class="stat-label"><?= icon('gauge', 16) ?><?= e(t('dashboard.average')) ?></div><div class="stat-value"><?= $avg === null ? '&ndash;' : '<span data-count="' . (int) $avg . '">' . (int) $avg . '</span><small class="muted">/100</small>' ?></div><?php if ($avg !== null): ?><span class="badge badge-<?= score_class($avg) ?>"><?= e(score_label($avg)) ?></span><?php endif; ?></div>
        <div class="card stat reveal"><div class="stat-label"><?= icon('award', 16) ?><?= e(t('dashboard.best')) ?></div><div class="stat-value"><?= $totals['best'] === null ? '&ndash;' : (int) round((float) $totals['best']) . '<small class="muted">/100</small>' ?></div></div>
    </div>
    <p class="disclaimer"><?= icon('info', 16) ?><span><?= e(t('dashboard.disclaimer')) ?></span></p>

    <?php if ($saved): ?>
    <div class="card reveal"><div class="card-header"><h2><?= icon('save', 20) ?> <?= e(t('dashboard.saved')) ?></h2></div>
        <div class="table-wrap" style="border:0;border-radius:0 0 var(--radius) var(--radius)"><table class="table table-responsive">
            <thead><tr><th><?= e(t('fields.profession')) ?></th><th><?= e(t('fields.interview_type')) ?></th><th><?= e(t('fields.progress')) ?></th><th><?= e(t('fields.updated')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
            <tbody><?php foreach ($saved as $s): ?>
                <tr>
                    <td data-label="<?= e(t('fields.profession')) ?>"><?= e($s['profession_name']) ?> <?php if ($s['status'] === 'paused'): ?><span class="badge badge-warning"><?= e(t('status.paused')) ?></span><?php endif; ?></td>
                    <td data-label="<?= e(t('fields.interview_type')) ?>"><?= e(t('interview_types.' . $s['interview_type'])) ?></td>
                    <td data-label="<?= e(t('fields.progress')) ?>"><?= (int) $s['done'] ?> / <?= (int) $s['total_q'] ?></td>
                    <td data-label="<?= e(t('fields.updated')) ?>"><?= e(format_date($s['updated_at'], true)) ?></td>
                    <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(url('/interview/' . $s['public_id'])) ?>"><?= e(t('dashboard.resume')) ?></a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if ($completed === 0): ?>
        <div class="card empty reveal"><?= icon('target', 36) ?><h2 style="font-size:1.2rem"><?= e(t('dashboard.empty_title')) ?></h2><p><?= e(t('dashboard.empty_text')) ?></p><a class="btn btn-primary" href="<?= e(url('/interview/new')) ?>"><?= e(t('nav.start')) ?></a></div>
    <?php else: ?>
    <div class="grid grid-sidebar">
        <div class="card reveal"><div class="card-header"><h2><?= icon('line-chart', 20) ?> <?= e(t('dashboard.evolution')) ?></h2></div><div class="card-body">
            <?= Charts::line($points, t('dashboard.evolution')) ?>
            <details style="margin-top:.6rem"><summary class="small"><?= e(t('dashboard.show_data')) ?></summary>
                <table class="table"><thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('fields.score')) ?></th></tr></thead><tbody>
                <?php foreach ($points as $p): ?><tr><td><?= e($p['label']) ?></td><td><?= (int) round($p['value']) ?>/100</td></tr><?php endforeach; ?>
                </tbody></table>
            </details>
        </div></div>
        <div class="card reveal"><div class="card-header"><h2><?= icon('pie-chart', 20) ?> <?= e(t('dashboard.by_category')) ?></h2></div><div class="card-body">
            <?php foreach ($byCategory as $c): ?>
                <div class="meter"><span class="meter-label"><?= e($c['label']) ?> <span class="muted small">(<?= (int) $c['n'] ?>)</span></span><span class="meter-value"><?= (int) $c['score'] ?></span><div class="progress"><div class="progress-bar <?= score_class((float) $c['score']) ?>" style="--value:<?= (int) $c['score'] ?>%"></div></div></div>
            <?php endforeach; ?>
            <h3 style="font-size:.95rem;margin-top:1.2rem"><?= e(t('dashboard.by_type')) ?></h3>
            <?php foreach ($byType as $c): ?>
                <div class="meter"><span class="meter-label"><?= e(t('interview_types.' . $c['interview_type'])) ?></span><span class="meter-value"><?= (int) $c['score'] ?></span><div class="progress"><div class="progress-bar <?= score_class((float) $c['score']) ?>" style="--value:<?= (int) $c['score'] ?>%"></div></div></div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="grid grid-3">
        <div class="card reveal"><div class="card-header"><h2><?= icon('trending-up', 20) ?> <?= e(t('dashboard.top_skills')) ?></h2></div><div class="card-body">
            <?php if (!$skillsTop): ?><p class="muted"><?= e(t('dashboard.no_skills')) ?></p><?php endif; ?>
            <?php foreach ($skillsTop as $s): ?><div class="meter"><span class="meter-label"><?= e(loc($s)) ?></span><span class="meter-value"><?= (int) round((float) $s['avg_score']) ?></span><div class="progress"><div class="progress-bar <?= score_class((float) $s['avg_score']) ?>" style="--value:<?= (int) $s['avg_score'] ?>%"></div></div></div><?php endforeach; ?>
        </div></div>
        <div class="card reveal"><div class="card-header"><h2><?= icon('target', 20) ?> <?= e(t('dashboard.low_skills')) ?></h2></div><div class="card-body">
            <?php if (!$skillsLow): ?><p class="muted"><?= e(t('dashboard.no_skills')) ?></p><?php endif; ?>
            <?php foreach ($skillsLow as $s): ?><div class="meter"><span class="meter-label"><?= e(loc($s)) ?></span><span class="meter-value"><?= (int) round((float) $s['avg_score']) ?></span><div class="progress"><div class="progress-bar <?= score_class((float) $s['avg_score']) ?>" style="--value:<?= (int) $s['avg_score'] ?>%"></div></div></div><?php endforeach; ?>
        </div></div>
        <div class="card reveal"><div class="card-header"><h2><?= icon('briefcase', 20) ?> <?= e(t('dashboard.professions')) ?></h2></div><div class="card-body">
            <ul class="feature-list"><?php foreach ($professions as $p): ?><li><span class="badge"><?= (int) $p['n'] ?></span><span><?= e($p['name']) ?><?= $p['score'] !== null ? ' <span class="muted small">' . e(t('dashboard.avg_short', ['score' => (int) $p['score']])) . '</span>' : '' ?></span></li><?php endforeach; ?></ul>
        </div></div>
    </div>

    <div class="card reveal"><div class="card-header"><h2><?= icon('history', 20) ?> <?= e(t('dashboard.recent')) ?></h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/history')) ?>"><?= e(t('dashboard.all_history')) ?><?= icon('arrow-right', 16) ?></a></div>
        <div class="table-wrap" style="border:0;border-radius:0 0 var(--radius) var(--radius)"><table class="table table-responsive">
            <thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('fields.profession')) ?></th><th><?= e(t('fields.interview_type')) ?></th><th><?= e(t('fields.score')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
            <tbody><?php foreach ($recent as $s): ?>
                <tr>
                    <td data-label="<?= e(t('fields.date')) ?>"><?= e(format_date($s['completed_at'], true)) ?></td>
                    <td data-label="<?= e(t('fields.profession')) ?>"><?= e($s['profession_name']) ?></td>
                    <td data-label="<?= e(t('fields.interview_type')) ?>"><?= e(t('interview_types.' . $s['interview_type'])) ?></td>
                    <td data-label="<?= e(t('fields.score')) ?>"><span class="badge badge-<?= score_class((float) $s['overall_score']) ?>"><?= (int) round((float) $s['overall_score']) ?>/100 &middot; <?= e(score_label((float) $s['overall_score'])) ?></span></td>
                    <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/reports/' . $s['public_id'])) ?>"><?= e(t('dashboard.view_report')) ?></a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <div class="card reveal" id="goals"><div class="card-header"><h2><?= icon('flag', 20) ?> <?= e(t('goals.title')) ?></h2></div><div class="card-body">
        <?php if (!$goals): ?><p class="muted"><?= e(t('goals.empty')) ?></p><?php endif; ?>
        <div class="grid grid-2">
        <?php foreach ($goals as $g):
            $target = (int) $g['target_value'];
            $pct = $target > 0 ? min(100, (int) round($g['progress'] / $target * 100)) : 0; ?>
            <div class="repeat-row">
                <div class="row row-between">
                    <strong><?= e(t('goals.types.' . $g['goal_type'], ['target' => $target, 'skill' => $g['skill_id'] ? loc($g) : ''])) ?></strong>
                    <?php if ($g['achieved_at']): ?><span class="badge badge-success"><?= icon('check', 14) ?><?= e(t('goals.achieved')) ?></span><?php endif; ?>
                </div>
                <div class="meter" style="margin:.6rem 0 .3rem"><span class="meter-label small muted"><?= e(t('goals.progress', ['value' => round($g['progress']), 'target' => $target])) ?></span><span class="meter-value"><?= $pct ?>%</span><div class="progress"><div class="progress-bar <?= $g['achieved_at'] ? 'success' : '' ?>" style="--value:<?= $pct ?>%"></div></div></div>
                <div class="row row-between small muted">
                    <span><?= $g['deadline'] ? e(t('goals.deadline', ['date' => format_date($g['deadline'])])) : '' ?></span>
                    <form method="post" action="<?= e(url('/goals/' . $g['id'] . '/delete')) ?>" data-confirm="<?= e(t('goals.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?><?= e(t('common.delete')) ?></button></form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <form method="post" action="<?= e(url('/goals')) ?>" class="form-grid" style="margin-top:1rem" data-goal-form>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="goal_type"><?= e(t('goals.type')) ?></label>
                <select id="goal_type" name="goal_type" data-goal-type<?= error_attrs('goal_type') ?>>
                    <?php foreach (['average_score', 'sessions_completed', 'skill_score'] as $gt): ?><option value="<?= $gt ?>" <?= old('goal_type') === $gt ? 'selected' : '' ?>><?= e(t('goals.type_options.' . $gt)) ?></option><?php endforeach; ?>
                </select>
                <?= error_message('goal_type') ?>
            </div>
            <div class="form-group">
                <label for="target_value"><?= e(t('goals.target')) ?></label>
                <input class="input" type="number" id="target_value" name="target_value" min="1" max="100" value="<?= e(old('target_value', '75')) ?>" required<?= error_attrs('target_value') ?>>
                <?= error_message('target_value') ?>
            </div>
            <div class="form-group" data-goal-skill>
                <label for="skill_id"><?= e(t('goals.skill')) ?></label>
                <select id="skill_id" name="skill_id"<?= error_attrs('skill_id') ?>>
                    <option value=""><?= e(t('common.choose')) ?></option>
                    <?php foreach ($skills as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e(loc($s)) ?></option><?php endforeach; ?>
                </select>
                <?= error_message('skill_id') ?>
            </div>
            <div class="form-group">
                <label for="deadline"><?= e(t('goals.deadline_label')) ?></label>
                <input class="input" type="date" id="deadline" name="deadline" value="<?= e(old('deadline')) ?>">
            </div>
            <div class="full"><button class="btn btn-secondary" type="submit"><?= icon('plus', 18) ?><?= e(t('goals.add')) ?></button></div>
        </form>
    </div></div>
</div></section>
