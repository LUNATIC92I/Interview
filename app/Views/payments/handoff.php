<section class="section"><div class="container container-narrow">
    <div class="card"><div class="card-body stack" style="text-align:center;align-items:center">
        <div class="card-icon" style="margin:0 auto"><?= icon('lock', 28) ?></div>
        <h1 style="font-size:1.5rem"><?= e(t('checkout.handoff_title')) ?></h1>
        <p class="lead" style="margin:0"><?= e(t('checkout.handoff_text', ['price' => format_price((int) $payment['amount'], $payment['currency']), 'profession' => $profession['name'] ?? ''])) ?></p>
        <a class="btn btn-primary btn-lg" href="<?= e($payment['checkout_url']) ?>" rel="noopener" data-auto-redirect><?= icon('arrow-right', 18) ?><?= e(t('checkout.handoff_button')) ?></a>
        <p class="small muted" style="margin:0"><?= e(t('checkout.secure')) ?></p>
    </div></div>
</div></section>
