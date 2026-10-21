<?php use App\Models\Domain; ?>
<div class="row row-between">
    <h1><?= e(t('admin.questions.title')) ?></h1>
    <div class="row">
        <a class="btn btn-secondary" href="<?= e(url('/admin/questions/import')) ?>"><?= icon('upload', 18) ?><?= e(t('admin.import.title')) ?></a>
        <a class="btn btn-secondary" href="<?= e(url('/admin/questions/export')) ?>"><?= icon('download', 18) ?><?= e(t('admin.questions.export')) ?></a>
        <a class="btn btn-primary" href="<?= e(url('/admin/questions/new')) ?>"><?= icon('plus', 18) ?><?= e(t('admin.questions.new')) ?></a>
    </div>
</div>
<form class="filters filters-5 card card-body" method="get" action="<?= e(url('/admin/questions')) ?>" role="search">
    <div class="form-group"><label for="q"><?= e(t('common.search')) ?></label><input class="input" type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>"></div>
    <div class="form-group"><label for="type"><?= e(t('admin.questions.type')) ?></label><select id="type" name="type"><option value=""><?= e(t('common.all')) ?></option><?php foreach (Domain::QUESTION_TYPES as $ty): ?><option value="<?= $ty ?>" <?= ($filters['type'] ?? '') === $ty ? 'selected' : '' ?>><?= e(t('question_types.' . $ty)) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="profession"><?= e(t('fields.profession')) ?></label><select id="profession" name="profession"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($professions as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (string) ($filters['profession'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="missing"><?= e(t('admin.questions.missing_filter')) ?></label><select id="missing" name="missing"><option value=""><?= e(t('common.all')) ?></option><option value="fr" <?= ($filters['missing'] ?? '') === 'fr' ? 'selected' : '' ?>><?= e(t('admin.questions.missing_fr')) ?></option><option value="en" <?= ($filters['missing'] ?? '') === 'en' ? 'selected' : '' ?>><?= e(t('admin.questions.missing_en')) ?></option></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
</form>
<p class="muted"><?= e(t('admin.results', ['count' => $pager->total])) ?></p>
<div class="table-wrap"><table class="table table-responsive">
    <thead><tr><th><?= e(t('admin.questions.question')) ?></th><th><?= e(t('admin.questions.type')) ?></th><th><?= e(t('fields.difficulty')) ?></th><th><?= e(t('admin.questions.languages')) ?></th><th><?= e(t('fields.status')) ?></th><th><span class="sr-only"><?= e(t('common.actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($items as $q): ?>
        <tr>
            <td data-label="<?= e(t('admin.questions.question')) ?>"><span class="code"><?= e($q['code']) ?></span><br><?= e(mb_strimwidth((string) $q['body'], 0, 140, '…')) ?></td>
            <td data-label="<?= e(t('admin.questions.type')) ?>"><?= e(t('question_types.' . $q['question_type'])) ?></td>
            <td data-label="<?= e(t('fields.difficulty')) ?>"><?= (int) $q['difficulty'] ?></td>
            <td data-label="<?= e(t('admin.questions.languages')) ?>"><?php foreach (['fr', 'en'] as $l): ?><span class="badge <?= str_contains((string) $q['locales'], $l) ? 'badge-success' : 'badge-danger' ?>"><?= strtoupper($l) ?><span class="sr-only"> <?= e(str_contains((string) $q['locales'], $l) ? t('admin.questions.present') : t('admin.questions.absent')) ?></span></span> <?php endforeach; ?></td>
            <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= $q['is_active'] ? 'badge-success' : '' ?>"><?= e(t($q['is_active'] ? 'common.active' : 'common.inactive')) ?></span></td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/questions/' . $q['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?><?= e(t('common.edit')) ?></a>
                <form method="post" action="<?= e(url('/admin/questions/' . $q['id'] . '/delete')) ?>" style="display:inline" data-confirm="<?= e(t('admin.questions.delete_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?><span class="sr-only"><?= e(t('common.delete')) ?></span></button></form></td>
        </tr>
    <?php endforeach; ?></tbody>
</table></div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
