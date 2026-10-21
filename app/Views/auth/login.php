<h1><?= e(t('auth.login_title')) ?></h1>
<p class="muted"><?= e(t('auth.login_lead')) ?></p>
<form method="post" action="<?= e(url('/login')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="email"><?= e(t('fields.email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required<?= error_attrs('email') ?>>
        <?= error_message('email') ?>
    </div>
    <div class="form-group">
        <label for="password"><?= e(t('fields.password')) ?></label>
        <div class="input-group">
            <input class="input" type="password" id="password" name="password" autocomplete="current-password" required<?= error_attrs('password') ?>>
            <button type="button" class="reveal-password" data-reveal="password" aria-label="<?= e(t('auth.show_password')) ?>"><?= icon('eye', 18) ?></button>
        </div>
        <?= error_message('password') ?>
    </div>
    <p class="small"><a href="<?= e(url('/password/forgot')) ?>"><?= e(t('auth.forgot_link')) ?></a></p>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('log-in', 18) ?><?= e(t('auth.login_button')) ?></button>
</form>
<p class="muted" style="margin-top:1.5rem;text-align:center"><?= e(t('auth.no_account')) ?> <a href="<?= e(url('/register')) ?>"><?= e(t('nav.register')) ?></a></p>
