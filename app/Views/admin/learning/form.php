<?php use App\Models\Domain; ?>
<nav class="breadcrumb"><a href="<?= e(url('/admin/learning')) ?>"><?= e(t('admin.learning.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($title) ?></span></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($item ? '/admin/learning/' . $item['id'] : '/admin/learning')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="card card-body"><div class="form-grid">
        <div class="form-group"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= e(old('slug', $item['slug'] ?? '')) ?>" required<?= error_attrs('slug') ?>><?= error_message('slug') ?></div>
        <div class="form-group"><label for="content_type"><?= e(t('admin.learning.type')) ?></label><select id="content_type" name="content_type"><?php foreach (['article', 'tip', 'checklist'] as $ct): ?><option value="<?= $ct ?>" <?= old('content_type', $item['content_type'] ?? 'article') === $ct ? 'selected' : '' ?>><?= e(t('resources.types.' . $ct)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="question_type"><?= e(t('admin.learning.question_type')) ?></label><select id="question_type" name="question_type"><option value=""><?= e(t('common.none')) ?></option><?php foreach (Domain::QUESTION_TYPES as $ty): ?><option value="<?= $ty ?>" <?= old('question_type', $item['question_type'] ?? '') === $ty ? 'selected' : '' ?>><?= e(t('question_types.' . $ty)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="sort_order"><?= e(t('fields.order')) ?></label><input class="input" type="number" id="sort_order" name="sort_order" value="<?= e(old('sort_order', $item['sort_order'] ?? 0)) ?>"></div>
        <div class="form-group full"><label class="check"><input type="checkbox" name="is_published" value="1" <?= old('is_published', $item['is_published'] ?? 1) ? 'checked' : '' ?>><span><?= e(t('admin.learning.published')) ?></span></label></div>
    </div></div>
    <div class="lang-tabs">
        <?php foreach (['fr' => 'Français', 'en' => 'English'] as $loc => $label): ?>
            <div class="lang-col card" lang="<?= $loc ?>"><h2 style="font-size:1.1rem" class="row"><?= icon('languages', 18) ?><?= $label ?></h2>
                <div class="form-group"><label for="title_<?= $loc ?>"><?= e(t('fields.title')) ?></label><input class="input" id="title_<?= $loc ?>" name="title_<?= $loc ?>" value="<?= e(old('title_' . $loc, $tr[$loc]['title'] ?? '')) ?>" required<?= error_attrs('title_' . $loc) ?>><?= error_message('title_' . $loc) ?></div>
                <div class="form-group"><label for="summary_<?= $loc ?>"><?= e(t('fields.summary')) ?></label><input class="input" id="summary_<?= $loc ?>" name="summary_<?= $loc ?>" value="<?= e(old('summary_' . $loc, $tr[$loc]['summary'] ?? '')) ?>" maxlength="500"></div>
                <div class="form-group"><label for="body_<?= $loc ?>"><?= e(t('admin.learning.body')) ?></label><textarea id="body_<?= $loc ?>" name="body_<?= $loc ?>" style="min-height:240px"<?= error_attrs('body_' . $loc) ?>><?= e(old('body_' . $loc, $tr[$loc]['body'] ?? '')) ?></textarea><p class="help"><?= e(t('admin.learning.body_help')) ?></p><?= error_message('body_' . $loc) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button><a class="btn btn-secondary" href="<?= e(url('/admin/learning')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
