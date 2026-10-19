<h1><?= e(t('install.title')) ?></h1>
<p class="muted"><?= e(t('install.lead')) ?></p>
<form method="post" action="<?= e(url('/install')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="token"><?= e(t('install.token')) ?></label>
        <input class="input" type="password" id="token" name="token" required autocomplete="off"<?= error_attrs('token') ?>>
        <p class="help"><?= e(t('install.token_help')) ?></p>
        <?= error_message('token') ?>
    </div>
    <div class="form-group">
        <label for="display_name"><?= e(t('fields.display_name')) ?></label>
        <input class="input" type="text" id="display_name" name="display_name" value="<?= e(old('display_name')) ?>" required<?= error_attrs('display_name') ?>>
        <?= error_message('display_name') ?>
    </div>
    <div class="form-group">
        <label for="email"><?= e(t('fields.email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required<?= error_attrs('email') ?>>
        <?= error_message('email') ?>
    </div>
    <div class="form-group">
        <label for="password"><?= e(t('fields.password')) ?></label>
        <input class="input" type="password" id="password" name="password" minlength="10" required<?= error_attrs('password') ?>>
        <p class="help"><?= e(t('auth.password_rules')) ?></p>
        <?= error_message('password') ?>
    </div>
    <div class="form-group">
        <label for="password_confirmation"><?= e(t('fields.password_confirmation')) ?></label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('install.button')) ?></button>
</form>
