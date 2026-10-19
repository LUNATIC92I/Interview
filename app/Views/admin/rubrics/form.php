<nav class="breadcrumb"><a href="<?= e(url('/admin/rubrics')) ?>"><?= e(t('admin.rubrics.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e(loc($item)) ?></span></nav>
<h1><?= e(t('admin.rubrics.edit')) ?></h1>
<form method="post" action="<?= e(url('/admin/rubrics/' . $item['id'])) ?>" class="stack" novalidate data-rubric-form>
    <?= csrf_field() ?>
    <div class="card card-body"><div class="form-grid">
        <div class="form-group"><label for="name_fr"><?= e(t('fields.name')) ?> (FR)</label><input class="input" id="name_fr" name="name_fr" value="<?= e(old('name_fr', $item['name_fr'])) ?>" required></div>
        <div class="form-group"><label for="name_en"><?= e(t('fields.name')) ?> (EN)</label><input class="input" id="name_en" name="name_en" value="<?= e(old('name_en', $item['name_en'])) ?>" required></div>
        <div class="form-group"><label for="description_fr"><?= e(t('fields.description')) ?> (FR)</label><textarea id="description_fr" name="description_fr" style="min-height:80px"><?= e(old('description_fr', $item['description_fr'])) ?></textarea></div>
        <div class="form-group"><label for="description_en"><?= e(t('fields.description')) ?> (EN)</label><textarea id="description_en" name="description_en" style="min-height:80px"><?= e(old('description_en', $item['description_en'])) ?></textarea></div>
        <div class="form-group full"><label class="check"><input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?>><span><?= e(t('common.active')) ?></span></label></div>
    </div></div>
    <div class="card card-body">
        <div class="row row-between"><h2 style="font-size:1.1rem;margin:0"><?= e(t('admin.rubrics.criteria')) ?></h2><span class="badge badge-primary" data-weight-total aria-live="polite"></span></div>
        <p class="help"><?= e(t('admin.rubrics.criteria_help')) ?></p>
        <?= error_message('criteria') ?>
        <?php foreach ($criteria as $c): $p = 'criteria[' . $c['id'] . ']'; ?>
            <div class="repeat-row"><div class="form-grid">
                <div class="form-group"><label for="c<?= $c['id'] ?>nf"><?= e(t('fields.name')) ?> (FR)</label><input class="input" id="c<?= $c['id'] ?>nf" name="<?= $p ?>[name_fr]" value="<?= e($c['name_fr']) ?>" required></div>
                <div class="form-group"><label for="c<?= $c['id'] ?>ne"><?= e(t('fields.name')) ?> (EN)</label><input class="input" id="c<?= $c['id'] ?>ne" name="<?= $p ?>[name_en]" value="<?= e($c['name_en']) ?>" required></div>
                <div class="form-group"><label for="c<?= $c['id'] ?>df"><?= e(t('fields.description')) ?> (FR)</label><input class="input" id="c<?= $c['id'] ?>df" name="<?= $p ?>[description_fr]" value="<?= e($c['description_fr']) ?>"></div>
                <div class="form-group"><label for="c<?= $c['id'] ?>de"><?= e(t('fields.description')) ?> (EN)</label><input class="input" id="c<?= $c['id'] ?>de" name="<?= $p ?>[description_en]" value="<?= e($c['description_en']) ?>"></div>
                <div class="form-group"><label for="c<?= $c['id'] ?>e"><?= e(t('admin.rubrics.evaluator')) ?></label><select id="c<?= $c['id'] ?>e" name="<?= $p ?>[evaluator]"><?php foreach ($evaluators as $ev): ?><option value="<?= $ev ?>" <?= $c['evaluator'] === $ev ? 'selected' : '' ?>><?= e(t('criteria.' . $ev)) ?> (<?= $ev ?>)</option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="c<?= $c['id'] ?>w"><?= e(t('admin.rubrics.weight')) ?></label><input class="input" type="number" min="0" max="100" id="c<?= $c['id'] ?>w" name="<?= $p ?>[weight]" value="<?= (int) $c['weight'] ?>" data-weight></div>
            </div></div>
        <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button><a class="btn btn-secondary" href="<?= e(url('/admin/rubrics')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
