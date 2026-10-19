<?php
use App\Models\Domain;
use App\Models\Difficulty;
$val = static fn (string $k, $d) => old($k, $d);
$grouped = [];
foreach ($professions as $p) { $grouped[$p['category_name']][] = $p; }
$selectedId = (int) $val('profession_id', $selected['id'] ?? 0);
$modeIcons = ['training' => 'lightbulb', 'realistic' => 'briefcase', 'timed' => 'timer', 'intensive' => 'activity', 'custom' => 'sliders'];
?>
<section class="page-header"><div class="container container-narrow">
    <p class="eyebrow"><?= e(t('setup.eyebrow')) ?></p>
    <h1><?= e(t('setup.title')) ?></h1>
    <p class="lead"><?= e(t('setup.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container container-narrow">
<form method="post" action="<?= e(url('/interview')) ?>" class="stack" data-setup-form novalidate>
    <?= csrf_field() ?>
    <div class="card"><div class="card-body">
        <h2 style="font-size:1.15rem"><?= e(t('setup.step_target')) ?></h2>
        <div class="form-group">
            <label for="profession_id"><?= e(t('fields.profession')) ?></label>
            <select id="profession_id" name="profession_id" required data-profession-select<?= error_attrs('profession_id') ?>>
                <option value=""><?= e(t('common.choose')) ?></option>
                <?php foreach ($grouped as $cat => $list): ?>
                    <optgroup label="<?= e($cat) ?>">
                        <?php foreach ($list as $p): ?><option value="<?= (int) $p['id'] ?>" data-category="<?= (int) $p['category_id'] ?>" data-slug="<?= e($p['slug']) ?>" <?= $selectedId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <?= error_message('profession_id') ?>
        </div>
        <?php if ($access['paywall'] && !$access['all']):
            $isUnlocked = in_array($selectedId, $access['unlocked'], true);
            $state = $selectedId === 0 ? 'none' : ($isUnlocked ? 'unlocked' : ($access['trials_left'] > 0 ? 'trial' : 'locked'));
        ?>
        <div data-access-note data-unlocked="<?= e(implode(',', $access['unlocked'])) ?>" data-trials="<?= (int) $access['trials_left'] ?>" data-checkout="<?= e(url('/checkout/')) ?>">
            <div class="alert alert-success" data-access-state="unlocked" <?= $state === 'unlocked' ? '' : 'hidden' ?>><?= icon('check-circle', 20) ?><p><?= e(t('access.setup_unlocked')) ?></p></div>
            <div class="alert alert-info" data-access-state="trial" <?= $state === 'trial' ? '' : 'hidden' ?>><?= icon('info', 20) ?><p><?= e(t('access.setup_trial', ['count' => $access['trial_questions'], 'price' => $access['price']])) ?> <a data-access-link href="<?= e(url('/checkout/' . ($selected['slug'] ?? ''))) ?>"><?= e(t('access.unlock_link')) ?></a></p></div>
            <div class="alert alert-warning" data-access-state="locked" <?= $state === 'locked' ? '' : 'hidden' ?>><?= icon('lock', 20) ?><p><?= e(t('access.setup_locked', ['price' => $access['price']])) ?> <a data-access-link href="<?= e(url('/checkout/' . ($selected['slug'] ?? ''))) ?>"><?= e(t('access.unlock_link')) ?></a></p></div>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="scenario_id"><?= e(t('setup.scenario')) ?></label>
            <select id="scenario_id" name="scenario_id" data-scenario-select<?= error_attrs('scenario_id') ?>>
                <option value=""><?= e(t('setup.no_scenario')) ?></option>
                <?php foreach ($scenarios as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" data-profession="<?= (int) $s['profession_id'] ?>" data-category="<?= (int) $s['category_id'] ?>" data-type="<?= e($s['interview_type']) ?>" <?= (int) $val('scenario_id', $scenarioId) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?> (<?= (int) $s['question_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <p class="help"><?= e(t('setup.scenario_help')) ?></p>
            <?= error_message('scenario_id') ?>
        </div>
        <fieldset>
            <legend><?= e(t('fields.experience_level')) ?></legend>
            <div class="choice-grid">
                <?php foreach (Domain::LEVELS as $lvl => $code): ?>
                    <label class="choice"><input type="radio" name="experience_level" value="<?= $lvl ?>" <?= (int) $val('experience_level', $defaults['experience_level']) === $lvl ? 'checked' : '' ?> required>
                        <span class="choice-body"><span class="choice-title"><?= e(t('levels.' . $code)) ?></span><span class="choice-desc"><?= e(t('levels_desc.' . $code)) ?></span></span></label>
                <?php endforeach; ?>
            </div>
            <?= error_message('experience_level') ?>
        </fieldset>
    </div></div>

    <div class="card"><div class="card-body">
        <h2 style="font-size:1.15rem"><?= e(t('setup.step_format')) ?></h2>
        <div class="form-grid">
            <div class="form-group full" data-type-group>
                <label for="interview_type"><?= e(t('fields.interview_type')) ?></label>
                <select id="interview_type" name="interview_type" required<?= error_attrs('interview_type') ?>>
                    <?php foreach (array_keys(Domain::INTERVIEW_TYPES) as $ty): ?><option value="<?= $ty ?>" <?= $val('interview_type', 'hr') === $ty ? 'selected' : '' ?>><?= e(t('interview_types.' . $ty)) ?></option><?php endforeach; ?>
                </select>
                <p class="help" data-scenario-type-note hidden><?= e(t('setup.type_from_scenario')) ?></p>
                <?= error_message('interview_type') ?>
            </div>
            <fieldset class="full">
                <legend><?= e(t('fields.language')) ?></legend>
                <div class="row">
                    <?php foreach (['fr', 'en'] as $l): ?><label class="check"><input type="radio" name="locale" value="<?= $l ?>" <?= $val('locale', $defaults['locale']) === $l ? 'checked' : '' ?>><span><?= e(t('languages.' . $l)) ?></span></label><?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="full">
                <legend><?= e(t('fields.difficulty')) ?></legend>
                <div class="choice-grid">
                    <?php foreach (Difficulty::all() as $id => $d): ?>
                        <label class="choice"><input type="radio" name="difficulty" value="<?= (int) $id ?>" <?= (int) $val('difficulty', 2) === (int) $id ? 'checked' : '' ?>>
                            <span class="choice-body"><span class="choice-title"><?= e(loc($d)) ?></span><span class="choice-desc"><?= e(loc($d, 'description')) ?></span></span></label>
                    <?php endforeach; ?>
                </div>
                <?= error_message('difficulty') ?>
            </fieldset>
            <div class="form-group">
                <label for="duration_minutes"><?= e(t('fields.duration')) ?></label>
                <select id="duration_minutes" name="duration_minutes">
                    <?php foreach (Domain::DURATIONS as $d): ?><option value="<?= $d ?>" <?= (int) $val('duration_minutes', 30) === $d ? 'selected' : '' ?>><?= e(t('setup.minutes', ['n' => $d])) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="question_count"><?= e(t('fields.question_count')) ?></label>
                <select id="question_count" name="question_count"<?= error_attrs('question_count') ?>>
                    <?php foreach (Domain::QUESTION_COUNTS as $n): ?><option value="<?= $n ?>" <?= (int) $val('question_count', $defaults['question_count']) === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
                </select>
                <p class="help" data-scenario-count-note hidden><?= e(t('setup.count_from_scenario')) ?></p>
            </div>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <h2 style="font-size:1.15rem"><?= e(t('fields.mode')) ?></h2>
        <fieldset>
            <legend class="sr-only"><?= e(t('fields.mode')) ?></legend>
            <div class="choice-grid">
                <?php foreach (Domain::MODES as $mode => $rules): ?>
                    <label class="choice"><input type="radio" name="mode" value="<?= $mode ?>" <?= $val('mode', $defaults['mode']) === $mode ? 'checked' : '' ?> data-mode-radio>
                        <span class="choice-body"><span class="choice-title"><?= icon($modeIcons[$mode], 18) ?><?= e(t('modes.' . $mode . '.name')) ?></span><span class="choice-desc"><?= e(t('modes.' . $mode . '.desc')) ?></span></span></label>
                <?php endforeach; ?>
            </div>
            <?= error_message('mode') ?>
        </fieldset>
        <div class="repeat-row" data-custom-options hidden>
            <p class="label"><?= e(t('setup.custom_title')) ?></p>
            <div class="grid grid-2">
                <?php foreach (['allow_skip' => true, 'allow_back' => true, 'allow_pause' => true, 'use_timer' => false, 'followups' => true, 'instant_feedback' => true] as $opt => $default): ?>
                    <label class="check"><input type="checkbox" name="<?= $opt ?>" value="1" <?= old($opt, $default ? '1' : '') ? 'checked' : '' ?>><span><?= e(t('setup.custom.' . $opt)) ?></span></label>
                <?php endforeach; ?>
            </div>
            <p class="help"><?= e(t('setup.custom_help')) ?></p>
        </div>
    </div></div>

    <p class="disclaimer"><?= icon('info', 16) ?><span><?= e(t('setup.disclaimer')) ?></span></p>
    <div class="form-actions">
        <button class="btn btn-primary btn-lg" type="submit" data-loading-text="<?= e(t('setup.preparing')) ?>"><?= icon('play', 18) ?><?= e(t('setup.start')) ?></button>
        <a class="btn btn-secondary btn-lg" href="<?= e(url('/dashboard')) ?>"><?= e(t('common.cancel')) ?></a>
    </div>
</form>
</div></section>
