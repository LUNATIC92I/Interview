<nav class="breadcrumb"><a href="<?= e(url('/admin/categories')) ?>"><?= e(t('admin.categories.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($title) ?></span></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($item ? '/admin/categories/' . $item['id'] : '/admin/categories')) ?>" class="card card-body" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="form-group"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= e(old('slug', $item['slug'] ?? '')) ?>" required pattern="[a-z0-9-]+"<?= error_attrs('slug') ?>><p class="help"><?= e(t('admin.slug_help')) ?></p><?= error_message('slug') ?></div>
        <div class="form-group"><label for="icon"><?= e(t('fields.icon')) ?></label><input class="input" id="icon" name="icon" value="<?= e(old('icon', $item['icon'] ?? 'briefcase')) ?>" required<?= error_attrs('icon') ?>><?= error_message('icon') ?></div>
        <div class="form-group"><label for="sort_order"><?= e(t('fields.order')) ?></label><input class="input" type="number" id="sort_order" name="sort_order" value="<?= e(old('sort_order', $item['sort_order'] ?? 0)) ?>"></div>
        <div class="form-group"><label class="check" style="margin-top:2rem"><input type="checkbox" name="is_active" value="1" <?= old('is_active', $item['is_active'] ?? 1) ? 'checked' : '' ?>><span><?= e(t('common.active')) ?></span></label></div>
    </div>
    <div class="lang-tabs">
        <?php foreach (['fr' => 'Français', 'en' => 'English'] as $loc => $label): ?>
            <div class="lang-col" lang="<?= $loc ?>"><h3><?= icon('languages', 18) ?><?= $label ?></h3>
                <div class="form-group"><label for="name_<?= $loc ?>"><?= e(t('fields.name')) ?></label><input class="input" id="name_<?= $loc ?>" name="name_<?= $loc ?>" value="<?= e(old('name_' . $loc, $tr[$loc]['name'] ?? '')) ?>" required<?= error_attrs('name_' . $loc) ?>><?= error_message('name_' . $loc) ?></div>
                <div class="form-group"><label for="description_<?= $loc ?>"><?= e(t('fields.description')) ?></label><textarea id="description_<?= $loc ?>" name="description_<?= $loc ?>"><?= e(old('description_' . $loc, $tr[$loc]['description'] ?? '')) ?></textarea></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button><a class="btn btn-secondary" href="<?= e(url('/admin/categories')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
