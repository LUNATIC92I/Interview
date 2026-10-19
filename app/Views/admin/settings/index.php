<h1><?= e(t('admin.settings.title')) ?></h1>
<div class="grid grid-sidebar">
<form method="post" action="<?= e(url('/admin/settings')) ?>" class="card card-body" novalidate>
    <?= csrf_field() ?>
    <div class="form-group"><label class="check"><input type="checkbox" name="registration_open" value="1" <?= $values['registration_open'] === '1' ? 'checked' : '' ?>><span><?= e(t('admin.settings.registration_open')) ?></span></label></div>
    <div class="form-group"><label class="check"><input type="checkbox" name="require_email_verification" value="1" <?= $values['require_email_verification'] === '1' ? 'checked' : '' ?>><span><?= e(t('admin.settings.require_email_verification')) ?></span></label><p class="help"><?= e(t('admin.settings.require_email_help')) ?></p></div>
    <div class="form-group"><label class="check"><input type="checkbox" name="ai_evaluation_enabled" value="1" <?= $values['ai_evaluation_enabled'] === '1' ? 'checked' : '' ?>><span><?= e(t('admin.settings.ai_enabled')) ?></span></label><p class="help"><?= e(t('admin.settings.ai_help')) ?></p></div>
    <div class="form-grid">
        <div class="form-group"><label for="max_sessions_per_day"><?= e(t('admin.settings.max_sessions')) ?></label><input class="input" type="number" id="max_sessions_per_day" name="max_sessions_per_day" min="1" max="500" value="<?= e($values['max_sessions_per_day']) ?>"<?= error_attrs('max_sessions_per_day') ?>><?= error_message('max_sessions_per_day') ?></div>
        <div class="form-group"><label for="default_question_count"><?= e(t('admin.settings.default_count')) ?></label><input class="input" type="number" id="default_question_count" name="default_question_count" min="3" max="15" value="<?= e($values['default_question_count']) ?>"<?= error_attrs('default_question_count') ?>><?= error_message('default_question_count') ?></div>
        <div class="form-group full"><label for="contact_email"><?= e(t('admin.settings.contact_email')) ?></label><input class="input" type="email" id="contact_email" name="contact_email" value="<?= e($values['contact_email']) ?>"<?= error_attrs('contact_email') ?>><?= error_message('contact_email') ?></div>
        <div class="form-group full"><label for="maintenance_message"><?= e(t('admin.settings.maintenance_message')) ?></label><input class="input" id="maintenance_message" name="maintenance_message" maxlength="500" value="<?= e($values['maintenance_message']) ?>"></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button>
</form>
<div class="card card-body">
    <h2 style="font-size:1.05rem"><?= e(t('admin.settings.services')) ?></h2>
    <dl class="kv">
        <dt><?= e(t('admin.settings.mail')) ?></dt><dd><span class="badge <?= $mailConfigured ? 'badge-success' : 'badge-warning' ?>"><?= e(t($mailConfigured ? 'admin.settings.configured' : 'admin.settings.not_configured')) ?></span></dd>
        <dt><?= e(t('admin.settings.llm')) ?></dt><dd><span class="badge <?= $llmActive ? 'badge-success' : 'badge-warning' ?>"><?= e(t($llmActive ? 'admin.settings.active' : ($llmConfigured ? 'admin.settings.disabled' : 'admin.settings.not_configured'))) ?></span></dd>
    </dl>
    <p class="small muted" style="margin-top:1rem"><?= e(t('admin.settings.env_note')) ?></p>
</div>
</div>
