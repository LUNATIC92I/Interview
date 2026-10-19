<?php use App\Models\Domain; $f = is_array($f) ? $f : []; ?>
<div class="repeat-row" data-row>
    <div class="form-grid">
        <div class="form-group"><label for="fu-<?= e($i) ?>-trigger"><?= e(t('admin.questions.trigger')) ?></label>
            <select id="fu-<?= e($i) ?>-trigger" name="followups[<?= e($i) ?>][trigger_type]"><?php foreach (Domain::FOLLOWUP_TRIGGERS as $tr): ?><option value="<?= $tr ?>" <?= ($f['trigger_type'] ?? '') === $tr ? 'selected' : '' ?>><?= e(t('admin.triggers.' . $tr)) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="fu-<?= e($i) ?>-min"><?= e(t('admin.questions.min_words')) ?></label><input class="input" type="number" min="0" id="fu-<?= e($i) ?>-min" name="followups[<?= e($i) ?>][min_words]" value="<?= e($f['min_words'] ?? 0) ?>"></div>
        <div class="form-group"><label for="fu-<?= e($i) ?>-fr"><?= e(t('admin.questions.followup_text')) ?> (FR)</label><textarea id="fu-<?= e($i) ?>-fr" name="followups[<?= e($i) ?>][fr]" style="min-height:70px"><?= e($f['fr'] ?? '') ?></textarea></div>
        <div class="form-group"><label for="fu-<?= e($i) ?>-en"><?= e(t('admin.questions.followup_text')) ?> (EN)</label><textarea id="fu-<?= e($i) ?>-en" name="followups[<?= e($i) ?>][en]" style="min-height:70px"><?= e($f['en'] ?? '') ?></textarea></div>
        <div class="form-group"><label for="fu-<?= e($i) ?>-kwfr"><?= e(t('admin.questions.trigger_keywords')) ?> (FR)</label><input class="input" id="fu-<?= e($i) ?>-kwfr" name="followups[<?= e($i) ?>][kw_fr]" value="<?= e($f['kw_fr'] ?? '') ?>"></div>
        <div class="form-group"><label for="fu-<?= e($i) ?>-kwen"><?= e(t('admin.questions.trigger_keywords')) ?> (EN)</label><input class="input" id="fu-<?= e($i) ?>-kwen" name="followups[<?= e($i) ?>][kw_en]" value="<?= e($f['kw_en'] ?? '') ?>"></div>
    </div>
    <button type="button" class="btn btn-ghost btn-sm" data-remove-row><?= icon('trash', 16) ?><?= e(t('common.remove')) ?></button>
</div>
