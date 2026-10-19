<h1><?= e(t('admin.difficulties.title')) ?></h1>
<form method="post" action="<?= e(url('/admin/difficulties')) ?>" class="card card-body" novalidate>
    <?= csrf_field() ?>
    <?= error_message('levels') ?>
    <?php foreach ($items as $id => $d): $p = 'levels[' . (int) $id . ']'; ?>
        <div class="repeat-row"><p class="label"><?= e(t('admin.difficulties.level', ['n' => $id])) ?> <span class="code"><?= e($d['code']) ?></span></p><div class="form-grid">
            <div class="form-group"><label for="d<?= $id ?>nf"><?= e(t('fields.name')) ?> (FR)</label><input class="input" id="d<?= $id ?>nf" name="<?= $p ?>[name_fr]" value="<?= e($d['name_fr']) ?>" required></div>
            <div class="form-group"><label for="d<?= $id ?>ne"><?= e(t('fields.name')) ?> (EN)</label><input class="input" id="d<?= $id ?>ne" name="<?= $p ?>[name_en]" value="<?= e($d['name_en']) ?>" required></div>
            <div class="form-group"><label for="d<?= $id ?>df"><?= e(t('fields.description')) ?> (FR)</label><input class="input" id="d<?= $id ?>df" name="<?= $p ?>[description_fr]" value="<?= e($d['description_fr']) ?>"></div>
            <div class="form-group"><label for="d<?= $id ?>de"><?= e(t('fields.description')) ?> (EN)</label><input class="input" id="d<?= $id ?>de" name="<?= $p ?>[description_en]" value="<?= e($d['description_en']) ?>"></div>
        </div></div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button>
</form>
