<?php use App\Models\Domain; ?>
<section class="page-header"><div class="container">
    <h1><?= e(t('history.title')) ?></h1>
    <p class="lead"><?= e(t('history.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container">
    <form class="filters filters-4 card card-body" method="get" action="<?= e(url('/history')) ?>">
        <div class="form-group">
            <label for="status"><?= e(t('fields.status')) ?></label>
            <select id="status" name="status"><option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (['completed', 'in_progress', 'paused'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(t('status.' . $st)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="type"><?= e(t('fields.interview_type')) ?></label>
            <select id="type" name="type"><option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (array_keys(Domain::INTERVIEW_TYPES) as $ty): ?><option value="<?= $ty ?>" <?= $type === $ty ? 'selected' : '' ?>><?= e(t('interview_types.' . $ty)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div></div>
        <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
    </form>
    <?php if (!$items): ?><div class="card empty"><?= icon('history', 32) ?><p><?= e(t('history.empty')) ?></p></div><?php else: ?>
    <form method="get" action="<?= e(url('/reports/compare')) ?>" data-compare-form>
    <div class="table-wrap"><table class="table table-responsive">
        <thead><tr><th><span class="sr-only"><?= e(t('history.select')) ?></span></th><th><?= e(t('fields.date')) ?></th><th><?= e(t('fields.profession')) ?></th><th><?= e(t('fields.interview_type')) ?></th><th><?= e(t('fields.mode')) ?></th><th><?= e(t('fields.status')) ?></th><th><?= e(t('fields.score')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
        <tbody><?php foreach ($items as $s): ?>
            <tr>
                <td data-label="<?= e(t('history.select')) ?>"><?php if ($s['status'] === 'completed'): ?><input type="checkbox" value="<?= e($s['public_id']) ?>" data-compare-pick aria-label="<?= e(t('history.select_for_compare')) ?>"><?php endif; ?></td>
                <td data-label="<?= e(t('fields.date')) ?>"><?= e(format_date($s['completed_at'] ?? $s['created_at'], true)) ?></td>
                <td data-label="<?= e(t('fields.profession')) ?>"><?= e($s['profession_name']) ?></td>
                <td data-label="<?= e(t('fields.interview_type')) ?>"><?= e(t('interview_types.' . $s['interview_type'])) ?></td>
                <td data-label="<?= e(t('fields.mode')) ?>"><?= e(t('modes.' . $s['mode'] . '.name')) ?></td>
                <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $s['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>"><?= e(t('status.' . $s['status'])) ?></span></td>
                <td data-label="<?= e(t('fields.score')) ?>"><?= $s['overall_score'] !== null ? (int) round((float) $s['overall_score']) . '/100' : '&ndash;' ?></td>
                <td class="actions">
                    <?php if ($s['status'] === 'completed'): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('/reports/' . $s['public_id'])) ?>"><?= e(t('dashboard.view_report')) ?></a>
                    <?php else: ?><a class="btn btn-primary btn-sm" href="<?= e(url('/interview/' . $s['public_id'])) ?>"><?= e(t('dashboard.resume')) ?></a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <input type="hidden" name="a" data-compare-a><input type="hidden" name="b" data-compare-b>
    <div class="row" style="margin-top:12px"><button class="btn btn-secondary" type="submit" data-compare-submit disabled><?= icon('compare', 18) ?><?= e(t('history.compare')) ?></button><span class="muted small"><?= e(t('history.compare_help')) ?></span></div>
    </form>
    <?= partial('partials/pagination', ['pager' => $pager]) ?>
    <?php endif; ?>
</div></section>
