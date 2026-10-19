<?php
use App\Models\Domain;
$selected = array_map('intval', (array) old('questions', $questionIds));
$byId = array_column($questions, null, 'id');
?>
<nav class="breadcrumb"><a href="<?= e(url('/admin/scenarios')) ?>"><?= e(t('admin.scenarios.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($title) ?></span></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($item ? '/admin/scenarios/' . $item['id'] : '/admin/scenarios')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="card card-body"><div class="form-grid">
        <div class="form-group"><label for="slug">Slug</label><input class="input" id="slug" name="slug" value="<?= e(old('slug', $item['slug'] ?? '')) ?>" required<?= error_attrs('slug') ?>><?= error_message('slug') ?></div>
        <div class="form-group"><label for="interview_type"><?= e(t('fields.interview_type')) ?></label><select id="interview_type" name="interview_type"><?php foreach (array_keys(Domain::INTERVIEW_TYPES) as $ty): ?><option value="<?= $ty ?>" <?= old('interview_type', $item['interview_type'] ?? '') === $ty ? 'selected' : '' ?>><?= e(t('interview_types.' . $ty)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="profession_id"><?= e(t('fields.profession')) ?></label><select id="profession_id" name="profession_id"><option value=""><?= e(t('admin.scenarios.any_profession')) ?></option><?php foreach ($professions as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (string) old('profession_id', $item['profession_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="category_id"><?= e(t('professions.category')) ?></label><select id="category_id" name="category_id"><option value=""><?= e(t('admin.scenarios.any_category')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) old('category_id', $item['category_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><p class="help"><?= e(t('admin.scenarios.scope_help')) ?></p></div>
        <div class="form-group"><label for="experience_level"><?= e(t('fields.experience_level')) ?></label><select id="experience_level" name="experience_level"><?php foreach (Domain::LEVELS as $l => $c): ?><option value="<?= $l ?>" <?= (int) old('experience_level', $item['experience_level'] ?? 2) === $l ? 'selected' : '' ?>><?= e(t('levels.' . $c)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="difficulty"><?= e(t('fields.difficulty')) ?></label><select id="difficulty" name="difficulty"><?php foreach (\App\Models\Difficulty::all() as $id => $d): ?><option value="<?= (int) $id ?>" <?= (int) old('difficulty', $item['difficulty'] ?? 2) === (int) $id ? 'selected' : '' ?>><?= e(loc($d)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group full"><label class="check"><input type="checkbox" name="is_active" value="1" <?= old('is_active', $item['is_active'] ?? 1) ? 'checked' : '' ?>><span><?= e(t('common.active')) ?></span></label></div>
    </div></div>
    <div class="lang-tabs">
        <?php foreach (['fr' => 'Français', 'en' => 'English'] as $loc => $label): ?>
            <div class="lang-col card" lang="<?= $loc ?>"><h2 style="font-size:1.1rem" class="row"><?= icon('languages', 18) ?><?= $label ?></h2>
                <div class="form-group"><label for="title_<?= $loc ?>"><?= e(t('fields.title')) ?></label><input class="input" id="title_<?= $loc ?>" name="title_<?= $loc ?>" value="<?= e(old('title_' . $loc, $tr[$loc]['title'] ?? '')) ?>" required<?= error_attrs('title_' . $loc) ?>><?= error_message('title_' . $loc) ?></div>
                <div class="form-group"><label for="description_<?= $loc ?>"><?= e(t('fields.description')) ?></label><textarea id="description_<?= $loc ?>" name="description_<?= $loc ?>"><?= e(old('description_' . $loc, $tr[$loc]['description'] ?? '')) ?></textarea></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card card-body" data-ordered-picker>
        <h2 style="font-size:1.1rem"><?= e(t('admin.scenarios.questions')) ?></h2>
        <p class="help"><?= e(t('admin.scenarios.questions_help')) ?></p>
        <?= error_message('questions') ?>
        <div class="row" style="flex-wrap:nowrap;align-items:flex-end">
            <div class="form-group" style="flex:1;margin:0"><label for="question_picker"><?= e(t('admin.scenarios.add_question')) ?></label>
                <select id="question_picker" data-picker><?php foreach ($questions as $q): ?><option value="<?= (int) $q['id'] ?>"><?= e($q['code'] . ' · ' . mb_strimwidth((string) $q['body'], 0, 90, '…')) ?></option><?php endforeach; ?></select></div>
            <button type="button" class="btn btn-secondary" data-picker-add><?= icon('plus', 18) ?><?= e(t('common.add')) ?></button>
        </div>
        <ol class="sortable-list" data-picker-list style="margin-top:12px">
            <?php foreach ($selected as $qid): if (!isset($byId[$qid])) { continue; } $q = $byId[$qid]; ?>
                <li data-id="<?= $qid ?>"><input type="hidden" name="questions[]" value="<?= $qid ?>"><span class="grow"><span class="code"><?= e($q['code']) ?></span> <?= e(mb_strimwidth((string) $q['body'], 0, 110, '…')) ?></span>
                    <button type="button" class="btn btn-ghost btn-sm" data-move="up" aria-label="<?= e(t('admin.move_up')) ?>">&uarr;</button><button type="button" class="btn btn-ghost btn-sm" data-move="down" aria-label="<?= e(t('admin.move_down')) ?>">&darr;</button><button type="button" class="btn btn-ghost btn-sm" data-move="remove" aria-label="<?= e(t('common.remove')) ?>"><?= icon('x', 16) ?></button></li>
            <?php endforeach; ?>
        </ol>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button><a class="btn btn-secondary" href="<?= e(url('/admin/scenarios')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
