<?php use App\Models\Domain; ?>
<section class="page-header"><div class="container container-narrow">
    <h1><?= e(t('account.title')) ?></h1>
    <p class="lead"><?= e(t('account.lead')) ?></p>
</div></section>
<section class="section" style="padding-top:16px"><div class="container container-narrow stack">
    <div class="card" id="profile"><div class="card-header"><h2><?= icon('user', 20) ?> <?= e(t('account.profile')) ?></h2></div><div class="card-body">
        <form method="post" action="<?= e(url('/account/profile')) ?>" class="form-grid" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-group"><label for="display_name"><?= e(t('fields.display_name')) ?></label><input class="input" id="display_name" name="display_name" value="<?= e(old('display_name', $authUser['display_name'])) ?>" maxlength="120" required<?= error_attrs('display_name') ?>><?= error_message('display_name') ?></div>
            <div class="form-group"><label for="email_ro"><?= e(t('fields.email')) ?></label><input class="input" id="email_ro" value="<?= e($authUser['email']) ?>" readonly aria-describedby="email-help"><p class="help" id="email-help"><?= e(t('account.email_help')) ?></p></div>
            <div class="form-group"><label for="headline"><?= e(t('fields.headline')) ?></label><input class="input" id="headline" name="headline" maxlength="160" value="<?= e(old('headline', $profile['headline'] ?? '')) ?>"></div>
            <div class="form-group"><label for="current_position"><?= e(t('fields.current_position')) ?></label><input class="input" id="current_position" name="current_position" maxlength="160" value="<?= e(old('current_position', $profile['current_position'] ?? '')) ?>"></div>
            <div class="form-group"><label for="target_profession_id"><?= e(t('fields.target_profession')) ?></label>
                <select id="target_profession_id" name="target_profession_id"<?= error_attrs('target_profession_id') ?>><option value=""><?= e(t('common.none')) ?></option>
                    <?php foreach ($professions as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) old('target_profession_id', $profile['target_profession_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                </select><?= error_message('target_profession_id') ?></div>
            <div class="form-group"><label for="experience_level"><?= e(t('fields.experience_level')) ?></label>
                <select id="experience_level" name="experience_level"><option value=""><?= e(t('common.none')) ?></option>
                    <?php foreach (Domain::LEVELS as $lvl => $code): ?><option value="<?= $lvl ?>" <?= (int) old('experience_level', $profile['experience_level'] ?? 0) === $lvl ? 'selected' : '' ?>><?= e(t('levels.' . $code)) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label for="country"><?= e(t('fields.country')) ?></label><input class="input" id="country" name="country" maxlength="80" value="<?= e(old('country', $profile['country'] ?? '')) ?>"></div>
            <div class="form-group full"><label for="bio"><?= e(t('fields.bio')) ?></label><textarea id="bio" name="bio" maxlength="2000"><?= e(old('bio', $profile['bio'] ?? '')) ?></textarea><p class="help"><?= e(t('account.bio_help')) ?></p></div>
            <div class="full"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button></div>
        </form>
    </div></div>

    <div class="card" id="preferences"><div class="card-header"><h2><?= icon('sliders', 20) ?> <?= e(t('account.preferences')) ?></h2></div><div class="card-body">
        <form method="post" action="<?= e(url('/account/preferences')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <div class="form-group"><label for="pref_locale"><?= e(t('fields.language')) ?></label><select id="pref_locale" name="locale"><?php foreach (['fr', 'en'] as $l): ?><option value="<?= $l ?>" <?= ($prefs['locale'] ?? locale()) === $l ? 'selected' : '' ?>><?= e(t('languages.' . $l)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="default_mode"><?= e(t('fields.default_mode')) ?></label><select id="default_mode" name="default_mode"><?php foreach (array_keys(Domain::MODES) as $m): ?><option value="<?= $m ?>" <?= ($prefs['default_mode'] ?? 'training') === $m ? 'selected' : '' ?>><?= e(t('modes.' . $m . '.name')) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="default_question_count"><?= e(t('fields.question_count')) ?></label><select id="default_question_count" name="default_question_count"><?php foreach (Domain::QUESTION_COUNTS as $n): ?><option value="<?= $n ?>" <?= (int) ($prefs['default_question_count'] ?? 6) === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
            <div class="form-group full">
                <label class="check"><input type="checkbox" name="reduce_motion" value="1" <?= !empty($prefs['reduce_motion']) ? 'checked' : '' ?>><span><?= e(t('account.reduce_motion')) ?></span></label>
                <label class="check" style="margin-top:.6rem"><input type="checkbox" name="email_notifications" value="1" <?= !empty($prefs['email_notifications']) ? 'checked' : '' ?>><span><?= e(t('account.email_notifications')) ?></span></label>
            </div>
            <div class="full"><button class="btn btn-primary" type="submit"><?= icon('save', 18) ?><?= e(t('common.save')) ?></button></div>
        </form>
    </div></div>

    <div class="card" id="security"><div class="card-header"><h2><?= icon('lock', 20) ?> <?= e(t('account.security')) ?></h2></div><div class="card-body">
        <form method="post" action="<?= e(url('/account/password')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-group"><label for="current_password"><?= e(t('fields.current_password')) ?></label><input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required<?= error_attrs('current_password') ?>><?= error_message('current_password') ?></div>
            <div class="form-grid">
                <div class="form-group"><label for="password"><?= e(t('fields.new_password')) ?></label><input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required<?= error_attrs('password') ?>><p class="help"><?= e(t('auth.password_rules')) ?></p><?= error_message('password') ?></div>
                <div class="form-group"><label for="password_confirmation"><?= e(t('fields.password_confirmation')) ?></label><input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
            </div>
            <button class="btn btn-primary" type="submit"><?= e(t('account.change_password')) ?></button>
        </form>
        <h3 style="margin-top:2rem"><?= e(t('account.login_history')) ?></h3>
        <div class="table-wrap"><table class="table table-responsive">
            <thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('account.event')) ?></th><th><?= e(t('account.ip')) ?></th><th><?= e(t('account.device')) ?></th></tr></thead>
            <tbody><?php foreach ($logins as $l): ?>
                <tr><td data-label="<?= e(t('fields.date')) ?>"><?= e(format_date($l['created_at'], true)) ?></td><td data-label="<?= e(t('account.event')) ?>"><?= e(t('account.events.' . $l['event'])) ?></td><td data-label="<?= e(t('account.ip')) ?>"><span class="code"><?= e($l['ip_masked']) ?></span></td><td data-label="<?= e(t('account.device')) ?>" class="small wrap-anywhere"><?= e(mb_substr((string) $l['user_agent'], 0, 80)) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <p class="help"><?= e(t('account.ip_note')) ?></p>
    </div></div>

    <div class="card" id="payments"><div class="card-header"><h2><?= icon('landmark', 20) ?> <?= e(t('account.payments')) ?></h2></div><div class="card-body">
        <?php if (!$payments): ?><p class="muted"><?= e(t('account.no_payments')) ?></p><?php else: ?>
        <div class="table-wrap"><table class="table table-responsive"><thead><tr><th><?= e(t('fields.date')) ?></th><th><?= e(t('fields.profession')) ?></th><th><?= e(t('payment.amount')) ?></th><th><?= e(t('fields.status')) ?></th></tr></thead><tbody>
            <?php foreach ($payments as $p): ?><tr>
                <td data-label="<?= e(t('fields.date')) ?>"><a href="<?= e(url('/payments/' . $p['public_id'])) ?>"><?= e(format_date($p['created_at'], true)) ?></a></td>
                <td data-label="<?= e(t('fields.profession')) ?>"><?= e($p['profession_name']) ?></td>
                <td data-label="<?= e(t('payment.amount')) ?>"><?= e(format_price((int) $p['amount'], $p['currency'])) ?></td>
                <td data-label="<?= e(t('fields.status')) ?>"><span class="badge <?= e(payment_badge($p['status'])) ?>"><?= e(t('payment.status.' . $p['status'])) ?></span></td>
            </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </div></div>
    <div class="card" id="danger" style="border-color:#F3B8BF"><div class="card-header"><h2><?= icon('trash', 20) ?> <?= e(t('account.delete_title')) ?></h2></div><div class="card-body">
        <p class="muted"><?= e(t('account.delete_text')) ?></p>
        <form method="post" action="<?= e(url('/account/delete')) ?>" data-confirm="<?= e(t('account.delete_confirm')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-group"><label for="delete_password"><?= e(t('fields.current_password')) ?></label><input class="input" type="password" id="delete_password" name="current_password" autocomplete="current-password" required<?= error_attrs('delete_password') ?>><?= error_message('delete_password') ?></div>
                <div class="form-group"><label for="confirm"><?= e(t('account.delete_type', ['word' => t('account.delete_word')])) ?></label><input class="input" id="confirm" name="confirm" required autocomplete="off"<?= error_attrs('confirm') ?>><?= error_message('confirm') ?></div>
            </div>
            <button class="btn btn-danger" type="submit"><?= icon('trash', 18) ?><?= e(t('account.delete_button')) ?></button>
        </form>
    </div></div>
</div></section>
