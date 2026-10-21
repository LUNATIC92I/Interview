<h1><?= e(t('auth.register_title')) ?></h1>
<p class="muted"><?= e(t('auth.register_lead')) ?></p>
<?php if (!$open): ?><div class="alert alert-warning"><?= icon('alert-triangle', 20) ?><p><?= e(t('auth.registration_closed')) ?></p></div><?php endif; ?>
<form method="post" action="<?= e(url('/register')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="display_name"><?= e(t('fields.display_name')) ?></label>
        <input class="input" type="text" id="display_name" name="display_name" value="<?= e(old('display_name')) ?>" autocomplete="name" maxlength="120" required<?= error_attrs('display_name') ?>>
        <?= error_message('display_name') ?>
    </div>
    <div class="form-group">
        <label for="email"><?= e(t('fields.email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required<?= error_attrs('email') ?>>
        <?= error_message('email') ?>
    </div>
    <div class="form-group">
        <label for="password"><?= e(t('fields.password')) ?></label>
        <div class="input-group">
            <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required aria-describedby="pw-help"<?= error_attrs('password') ?>>
            <button type="button" class="reveal-password" data-reveal="password" aria-label="<?= e(t('auth.show_password')) ?>"><?= icon('eye', 18) ?></button>
        </div>
        <p class="help" id="pw-help"><?= e(t('auth.password_rules')) ?></p>
        <?= error_message('password') ?>
    </div>
    <div class="form-group">
        <label for="password_confirmation"><?= e(t('fields.password_confirmation')) ?></label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <div class="form-group">
        <label class="check"><input type="checkbox" name="terms" value="1" required<?= error_attrs('terms') ?>><span><?= e(t('auth.terms_before')) ?> <a href="<?= e(url('/privacy')) ?>" target="_blank" rel="noopener"><?= e(t('privacy.title')) ?></a></span></label>
        <?= error_message('terms') ?>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit" <?= $open ? '' : 'disabled' ?>><?= e(t('auth.register_button')) ?></button>
</form>
<p class="muted" style="margin-top:1.5rem;text-align:center"><?= e(t('auth.have_account')) ?> <a href="<?= e(url('/login')) ?>"><?= e(t('nav.login')) ?></a></p>
