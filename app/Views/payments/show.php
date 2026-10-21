<?php $s = $payment['status']; ?>
<section class="section"><div class="container container-narrow">
    <div class="card"><div class="card-body stack" style="text-align:center;align-items:center">
        <?php if ($s === 'completed'): ?>
            <div class="card-icon" style="margin:0 auto;background:var(--success-bg, #E8F5EE);color:var(--success-text)"><?= icon('check-circle', 28) ?></div>
            <h1 style="font-size:1.6rem"><?= e(t('payment.success_title')) ?></h1>
            <p class="lead" style="margin:0"><?= e(t('payment.success_text', ['profession' => $profession['name'] ?? ''])) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-primary" href="<?= e(url('/my-resources/' . ($profession['slug'] ?? ''))) ?>"><?= icon('book-open', 18) ?><?= e(t('my_resources.open')) ?></a>
                <a class="btn btn-secondary" href="<?= e(url('/interview/new', ['profession' => $profession['slug'] ?? ''])) ?>"><?= icon('play', 18) ?><?= e(t('professions.start')) ?></a>
            </div>
        <?php elseif (in_array($s, ['pending', 'processing', 'created'], true)): ?>
            <div class="card-icon" style="margin:0 auto"><?= icon('clock', 28) ?></div>
            <h1 style="font-size:1.6rem"><?= e(t('payment.pending_title')) ?></h1>
            <p class="lead" style="margin:0"><?= e(t('payment.pending_text')) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-primary" href="<?= e(url('/payments/' . $payment['public_id'])) ?>"><?= icon('rotate-ccw', 18) ?><?= e(t('payment.check_again')) ?></a>
                <?php if ($payment['checkout_url']): ?><a class="btn btn-secondary" href="<?= e($payment['checkout_url']) ?>" rel="noopener"><?= e(t('payment.resume_checkout')) ?></a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-icon" style="margin:0 auto;background:var(--danger-bg);color:var(--danger-text)"><?= icon('alert-circle', 28) ?></div>
            <h1 style="font-size:1.6rem"><?= e(t('payment.failed_title')) ?></h1>
            <p class="lead" style="margin:0"><?= e(t('payment.failed_text')) ?></p>
            <?php if ($profession): ?><a class="btn btn-primary" href="<?= e(url('/checkout/' . $profession['slug'])) ?>"><?= icon('rotate-ccw', 18) ?><?= e(t('payment.retry')) ?></a><?php endif; ?>
        <?php endif; ?>
        <dl class="kv" style="text-align:left;width:100%;max-width:420px;margin-top:1rem">
            <dt><?= e(t('fields.profession')) ?></dt><dd><?= e($profession['name'] ?? '-') ?></dd>
            <dt><?= e(t('payment.amount')) ?></dt><dd><?= e(format_price((int) $payment['amount'], $payment['currency'])) ?></dd>
            <dt><?= e(t('fields.status')) ?></dt><dd><span class="badge <?= e(payment_badge($s)) ?>" data-payment-status="<?= e($s) ?>"><?= e(t('payment.status.' . $s)) ?></span></dd>
            <?php if ($payment['provider_reference']): ?><dt><?= e(t('payment.reference')) ?></dt><dd><span class="code"><?= e($payment['provider_reference']) ?></span></dd><?php endif; ?>
            <dt><?= e(t('fields.date')) ?></dt><dd><?= e(format_date($payment['completed_at'] ?? $payment['created_at'], true)) ?></dd>
        </dl>
    </div></div>
</div></section>
