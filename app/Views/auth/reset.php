<h1><?= e(t('auth.reset_title')) ?></h1>
<form method="post" action="<?= e(url('/password/reset')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="form-group">
        <label for="password"><?= e(t('fields.new_password')) ?></label>
        <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required aria-describedby="pw-help"<?= error_attrs('password') ?>>
        <p class="help" id="pw-help"><?= e(t('auth.password_rules')) ?></p>
        <?= error_message('password') ?>
    </div>
    <div class="form-group">
        <label for="password_confirmation"><?= e(t('fields.password_confirmation')) ?></label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('auth.reset_button')) ?></button>
</form>
