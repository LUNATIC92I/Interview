<section class="section"><div class="container container-narrow">
    <div class="card"><div class="card-body stack">
        <div class="alert alert-warning"><?= icon('alert-triangle', 20) ?><p><?= e(t('sandbox.notice')) ?></p></div>
        <h1 style="font-size:1.5rem"><?= e(t('sandbox.title')) ?></h1>
        <dl class="kv">
            <dt><?= e(t('fields.profession')) ?></dt><dd><?= e($profession['name'] ?? '-') ?></dd>
            <dt><?= e(t('payment.amount')) ?></dt><dd><?= e(format_price((int) $payment['amount'], $payment['currency'])) ?></dd>
            <dt><?= e(t('payment.reference')) ?></dt><dd><span class="code"><?= e($payment['provider_reference']) ?></span></dd>
        </dl>
        <form method="post" action="<?= e(url('/payments/sandbox/' . $payment['public_id'])) ?>" class="row">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit" name="outcome" value="completed"><?= icon('check', 18) ?><?= e(t('sandbox.succeed')) ?></button>
            <button class="btn btn-secondary" type="submit" name="outcome" value="failed"><?= icon('x', 18) ?><?= e(t('sandbox.fail')) ?></button>
            <button class="btn btn-ghost" type="submit" name="outcome" value="cancelled"><?= e(t('sandbox.cancel')) ?></button>
        </form>
    </div></div>
</div></section>
