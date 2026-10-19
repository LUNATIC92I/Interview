<h1><?= e(t('admin.translations.title')) ?></h1>
<p class="lead"><?= e(t('admin.translations.lead')) ?></p>
<div class="grid grid-4" style="margin-bottom:20px">
    <a class="card stat card-hover" href="<?= e(url('/admin/questions', ['missing' => 'en'])) ?>"><div class="stat-label"><?= e(t('admin.translations.questions_en')) ?></div><div class="stat-value"><?= (int) $missingContent['questions_en'] ?></div></a>
    <a class="card stat card-hover" href="<?= e(url('/admin/questions', ['missing' => 'fr'])) ?>"><div class="stat-label"><?= e(t('admin.translations.questions_fr')) ?></div><div class="stat-value"><?= (int) $missingContent['questions_fr'] ?></div></a>
    <a class="card stat card-hover" href="<?= e(url('/admin/professions')) ?>"><div class="stat-label"><?= e(t('admin.translations.professions')) ?></div><div class="stat-value"><?= (int) $missingContent['professions'] ?></div></a>
    <a class="card stat card-hover" href="<?= e(url('/admin/scenarios')) ?>"><div class="stat-label"><?= e(t('admin.translations.scenarios')) ?></div><div class="stat-value"><?= (int) $missingContent['scenarios'] ?></div></a>
</div>
<form class="filters card card-body" method="get" action="<?= e(url('/admin/translations')) ?>" role="search">
    <div class="form-group"><label for="q"><?= e(t('common.search')) ?></label><input class="input" type="search" id="q" name="q" value="<?= e($q) ?>"></div>
    <div class="form-group"><label class="check" style="margin-top:2rem"><input type="checkbox" name="missing" value="1" <?= $onlyMissing ? 'checked' : '' ?>><span><?= e(t('admin.translations.only_missing')) ?></span></label></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?><?= e(t('common.filter')) ?></button>
</form>
<div class="stack">
<?php foreach ($rows as $row): ?>
    <form method="post" action="<?= e(url('/admin/translations')) ?>" class="card card-body">
        <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($row['key']) ?>">
        <div class="row row-between"><span class="code"><?= e($row['key']) ?></span><?php if ($row['overridden']): ?><span class="badge badge-primary"><?= e(t('admin.translations.overridden')) ?></span><?php endif; ?></div>
        <div class="form-grid" style="margin-top:.6rem">
            <div class="form-group"><label for="fr-<?= e(md5($row['key'])) ?>">FR</label><textarea id="fr-<?= e(md5($row['key'])) ?>" name="value_fr" lang="fr" style="min-height:60px"><?= e($row['fr']) ?></textarea><?php if ($row['fr'] === ''): ?><p class="field-error"><?= e(t('admin.translations.missing')) ?></p><?php endif; ?></div>
            <div class="form-group"><label for="en-<?= e(md5($row['key'])) ?>">EN</label><textarea id="en-<?= e(md5($row['key'])) ?>" name="value_en" lang="en" style="min-height:60px"><?= e($row['en']) ?></textarea><?php if ($row['en'] === ''): ?><p class="field-error"><?= e(t('admin.translations.missing')) ?></p><?php endif; ?></div>
        </div>
        <div><button class="btn btn-secondary btn-sm" type="submit"><?= icon('save', 16) ?><?= e(t('common.save')) ?></button> <span class="help"><?= e(t('admin.translations.reset_help')) ?></span></div>
    </form>
<?php endforeach; ?>
</div>
<?= partial('partials/pagination', ['pager' => $pager]) ?>
