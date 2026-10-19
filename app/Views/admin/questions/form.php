<?php
use App\Models\Domain;
use App\Services\QuestionService;
$val = static fn (string $k, $d = '') => old($k, $d);
$fups = old('followups', null) ?? $followups;
if (!$fups) { $fups = []; }
?>
<nav class="breadcrumb"><a href="<?= e(url('/admin/questions')) ?>"><?= e(t('admin.questions.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e($title) ?></span></nav>
<h1><?= e($title) ?></h1>
<?php if (!empty($item['usage'])): ?><div class="alert alert-info"><?= icon('info', 20) ?><p><?= e(t('admin.questions.in_use', ['n' => $item['usage']])) ?></p></div><?php endif; ?>
<form method="post" action="<?= e(url($item ? '/admin/questions/' . $item['id'] : '/admin/questions')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="card card-body">
        <div class="form-grid">
            <div class="form-group"><label for="code"><?= e(t('admin.questions.code')) ?></label><input class="input" id="code" name="code" value="<?= e($val('code', $item['code'] ?? '')) ?>" required<?= error_attrs('code') ?>><p class="help"><?= e(t('admin.slug_help')) ?></p><?= error_message('code') ?></div>
            <div class="form-group"><label for="question_type"><?= e(t('admin.questions.type')) ?></label><select id="question_type" name="question_type"<?= error_attrs('question_type') ?>><?php foreach (Domain::QUESTION_TYPES as $ty): ?><option value="<?= $ty ?>" <?= $val('question_type', $item['question_type'] ?? 'behavioral') === $ty ? 'selected' : '' ?>><?= e(t('question_types.' . $ty)) ?></option><?php endforeach; ?></select><?= error_message('question_type') ?></div>
            <div class="form-group"><label for="profession_id"><?= e(t('fields.profession')) ?></label><select id="profession_id" name="profession_id"<?= error_attrs('profession_id') ?>><option value=""><?= e(t('admin.questions.no_profession')) ?></option><?php foreach ($professions as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (string) $val('profession_id', $item['profession_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select><?= error_message('profession_id') ?></div>
            <div class="form-group"><label for="category_id"><?= e(t('professions.category')) ?></label><select id="category_id" name="category_id"><option value=""><?= e(t('admin.questions.no_category')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $val('category_id', $item['category_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><p class="help"><?= e(t('admin.questions.scope_help')) ?></p></div>
            <div class="form-group"><label for="level_min"><?= e(t('admin.level_min')) ?></label><select id="level_min" name="level_min"><?php foreach (Domain::LEVELS as $l => $c): ?><option value="<?= $l ?>" <?= (int) $val('level_min', $item['level_min'] ?? 1) === $l ? 'selected' : '' ?>><?= e(t('levels.' . $c)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="level_max"><?= e(t('admin.level_max')) ?></label><select id="level_max" name="level_max"<?= error_attrs('level_max') ?>><?php foreach (Domain::LEVELS as $l => $c): ?><option value="<?= $l ?>" <?= (int) $val('level_max', $item['level_max'] ?? 5) === $l ? 'selected' : '' ?>><?= e(t('levels.' . $c)) ?></option><?php endforeach; ?></select><?= error_message('level_max') ?></div>
            <div class="form-group"><label for="difficulty"><?= e(t('fields.difficulty')) ?></label><select id="difficulty" name="difficulty"><?php foreach (\App\Models\Difficulty::all() as $id => $d): ?><option value="<?= (int) $id ?>" <?= (int) $val('difficulty', $item['difficulty'] ?? 2) === (int) $id ? 'selected' : '' ?>><?= (int) $id ?> · <?= e(loc($d)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="rubric_id"><?= e(t('admin.questions.rubric')) ?></label><select id="rubric_id" name="rubric_id"<?= error_attrs('rubric_id') ?>><?php foreach ($rubrics as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $val('rubric_id', $item['rubric_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e(loc($r)) ?></option><?php endforeach; ?></select><?= error_message('rubric_id') ?></div>
            <div class="form-group"><label for="recommended_seconds"><?= e(t('admin.questions.recommended')) ?></label><input class="input" type="number" min="30" max="1800" id="recommended_seconds" name="recommended_seconds" value="<?= e($val('recommended_seconds', $item['recommended_seconds'] ?? 180)) ?>"<?= error_attrs('recommended_seconds') ?>><?= error_message('recommended_seconds') ?></div>
            <div class="form-group"><label class="check" style="margin-top:2rem"><input type="checkbox" name="is_active" value="1" <?= $val('is_active', $item['is_active'] ?? 1) ? 'checked' : '' ?>><span><?= e(t('common.active')) ?></span></label></div>
        </div>
        <fieldset><legend><?= e(t('admin.questions.skills')) ?></legend>
            <div class="choice-grid"><?php $sel = array_map('intval', (array) old('skills', $skillIds)); foreach ($skills as $s): ?><label class="check"><input type="checkbox" name="skills[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $sel, true) ? 'checked' : '' ?>><span><?= e(loc($s)) ?></span></label><?php endforeach; ?></div>
        </fieldset>
    </div>
    <div class="lang-tabs">
        <?php foreach (['fr' => 'Français', 'en' => 'English'] as $loc => $label): $t = $tr[$loc] ?? []; ?>
            <div class="lang-col card" lang="<?= $loc ?>"><h2 style="font-size:1.1rem" class="row"><?= icon('languages', 18) ?><?= $label ?></h2>
                <div class="form-group"><label for="body_<?= $loc ?>"><?= e(t('admin.questions.body')) ?></label><textarea id="body_<?= $loc ?>" name="body_<?= $loc ?>" style="min-height:90px"<?= error_attrs('body_' . $loc) ?>><?= e($val('body_' . $loc, $t['body'] ?? '')) ?></textarea><?= error_message('body_' . $loc) ?></div>
                <div class="form-group"><label for="guidance_<?= $loc ?>"><?= e(t('admin.questions.guidance')) ?></label><textarea id="guidance_<?= $loc ?>" name="guidance_<?= $loc ?>" style="min-height:70px"><?= e($val('guidance_' . $loc, $t['guidance'] ?? '')) ?></textarea></div>
                <div class="form-group"><label for="criteria_<?= $loc ?>"><?= e(t('admin.questions.criteria')) ?></label><textarea id="criteria_<?= $loc ?>" name="criteria_<?= $loc ?>" style="min-height:70px"><?= e($val('criteria_' . $loc, $t['evaluation_criteria'] ?? '')) ?></textarea></div>
                <div class="form-group"><label for="expected_<?= $loc ?>"><?= e(t('admin.questions.expected')) ?></label><textarea id="expected_<?= $loc ?>" name="expected_<?= $loc ?>" aria-describedby="exp-help-<?= $loc ?>"><?= e($val('expected_' . $loc, QuestionService::elementsToText($t['expected_elements'] ?? []))) ?></textarea><p class="help" id="exp-help-<?= $loc ?>"><?= e(t('admin.questions.expected_help')) ?></p></div>
                <div class="form-group"><label for="mistakes_<?= $loc ?>"><?= e(t('admin.questions.mistakes')) ?></label><textarea id="mistakes_<?= $loc ?>" name="mistakes_<?= $loc ?>" style="min-height:80px"><?= e($val('mistakes_' . $loc, implode("\n", $t['common_mistakes'] ?? []))) ?></textarea><p class="help"><?= e(t('admin.one_per_line')) ?></p></div>
                <div class="form-group"><label for="example_<?= $loc ?>"><?= e(t('admin.questions.example')) ?></label><textarea id="example_<?= $loc ?>" name="example_<?= $loc ?>"><?= e($val('example_' . $loc, $t['example_answer'] ?? '')) ?></textarea></div>
                <div class="form-group"><label for="keywords_<?= $loc ?>"><?= e(t('admin.questions.keywords')) ?></label><input class="input" id="keywords_<?= $loc ?>" name="keywords_<?= $loc ?>" value="<?= e($val('keywords_' . $loc, $t['keywords'] ?? '')) ?>"><p class="help"><?= e(t('admin.questions.keywords_help')) ?></p></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card card-body">
        <div class="row row-between"><h2 style="font-size:1.1rem;margin:0"><?= e(t('admin.questions.followups')) ?></h2><button type="button" class="btn btn-secondary btn-sm" data-add-row="followup"><?= icon('plus', 16) ?><?= e(t('admin.questions.add_followup')) ?></button></div>
        <p class="help"><?= e(t('admin.questions.followups_help')) ?></p>
        <?= error_message('followups') ?>
        <div data-rows="followup">
            <?php foreach (array_values($fups) as $i => $f): ?><?= partial('admin/questions/followup_row', ['i' => $i, 'f' => $f]) ?><?php endforeach; ?>
        </div>
        <template data-template="followup"><?= partial('admin/questions/followup_row', ['i' => '__INDEX__', 'f' => []]) ?></template>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button><a class="btn btn-secondary" href="<?= e(url('/admin/questions')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
