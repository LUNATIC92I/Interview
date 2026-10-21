<h1><?= e(t('auth.forgot_title')) ?></h1>
<p class="muted"><?= e(t('auth.forgot_lead')) ?></p>
<?php if (!$mailConfigured): ?><div class="alert alert-warning"><?= icon('alert-triangle', 20) ?><p><?= e(t('auth.mail_not_configured')) ?></p></div><?php endif; ?>
<form method="post" action="<?= e(url('/password/forgot')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="email"><?= e(t('fields.email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required<?= error_attrs('email') ?>>
        <?= error_message('email') ?>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('mail', 18) ?><?= e(t('auth.forgot_button')) ?></button>
</form>
<p style="margin-top:1.5rem;text-align:center"><a href="<?= e(url('/login')) ?>"><?= icon('arrow-left', 16) ?> <?= e(t('auth.back_login')) ?></a></p>
