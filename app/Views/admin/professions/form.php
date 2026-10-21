<?php use App\Models\Domain; ?>
<nav class="breadcrumb"><a href="<?= e(url('/admin/professions')) ?>"><?= e(t('admin.professions.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($title) ?></span></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($item ? '/admin/professions/' . $item['id'] : '/admin/professions')) ?>" class="card card-body" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="form-group"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= e(old('slug', $item['slug'] ?? '')) ?>" required<?= error_attrs('slug') ?>><p class="help"><?= e(t('admin.slug_help')) ?></p><?= error_message('slug') ?></div>
        <div class="form-group"><label for="category_id"><?= e(t('professions.category')) ?></label><select id="category_id" name="category_id"<?= error_attrs('category_id') ?>><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) old('category_id', $item['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?= error_message('category_id') ?></div>
        <div class="form-group"><label for="level_min"><?= e(t('admin.level_min')) ?></label><select id="level_min" name="level_min"><?php foreach (Domain::LEVELS as $l => $c): ?><option value="<?= $l ?>" <?= (int) old('level_min', $item['level_min'] ?? 1) === $l ? 'selected' : '' ?>><?= e(t('levels.' . $c)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="level_max"><?= e(t('admin.level_max')) ?></label><select id="level_max" name="level_max"<?= error_attrs('level_max') ?>><?php foreach (Domain::LEVELS as $l => $c): ?><option value="<?= $l ?>" <?= (int) old('level_max', $item['level_max'] ?? 5) === $l ? 'selected' : '' ?>><?= e(t('levels.' . $c)) ?></option><?php endforeach; ?></select><?= error_message('level_max') ?></div>
        <div class="form-group"><label for="icon"><?= e(t('fields.icon')) ?></label><input class="input" id="icon" name="icon" value="<?= e(old('icon', $item['icon'] ?? 'briefcase')) ?>" required></div>
        <div class="form-group"><label for="sort_order"><?= e(t('fields.order')) ?></label><input class="input" type="number" id="sort_order" name="sort_order" value="<?= e(old('sort_order', $item['sort_order'] ?? 0)) ?>"></div>
        <div class="form-group full"><label class="check"><input type="checkbox" name="is_active" value="1" <?= old('is_active', $item['is_active'] ?? 1) ? 'checked' : '' ?>><span><?= e(t('common.active')) ?></span></label></div>
    </div>
    <div class="lang-tabs">
        <?php foreach (['fr' => 'Français', 'en' => 'English'] as $loc => $label): ?>
            <div class="lang-col" lang="<?= $loc ?>"><h3><?= icon('languages', 18) ?><?= $label ?></h3>
                <div class="form-group"><label for="name_<?= $loc ?>"><?= e(t('fields.name')) ?></label><input class="input" id="name_<?= $loc ?>" name="name_<?= $loc ?>" value="<?= e(old('name_' . $loc, $tr[$loc]['name'] ?? '')) ?>" required<?= error_attrs('name_' . $loc) ?>><?= error_message('name_' . $loc) ?></div>
                <div class="form-group"><label for="summary_<?= $loc ?>"><?= e(t('fields.summary')) ?></label><textarea id="summary_<?= $loc ?>" name="summary_<?= $loc ?>" style="min-height:80px"><?= e(old('summary_' . $loc, $tr[$loc]['summary'] ?? '')) ?></textarea></div>
                <div class="form-group"><label for="responsibilities_<?= $loc ?>"><?= e(t('professions.responsibilities')) ?></label><textarea id="responsibilities_<?= $loc ?>" name="responsibilities_<?= $loc ?>"><?= e(old('responsibilities_' . $loc, $tr[$loc]['responsibilities'] ?? '')) ?></textarea><p class="help"><?= e(t('admin.one_per_line')) ?></p></div>
            </div>
        <?php endforeach; ?>
    </div>
    <fieldset style="margin-top:1rem"><legend><?= e(t('professions.skills')) ?></legend>
        <div class="choice-grid"><?php foreach ($skills as $s): ?><label class="check"><input type="checkbox" name="skills[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $skillIds, true) ? 'checked' : '' ?>><span><?= e(loc($s)) ?></span></label><?php endforeach; ?></div>
    </fieldset>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button><a class="btn btn-secondary" href="<?= e(url('/admin/professions')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
