<nav class="breadcrumb"><a href="<?= e(url('/admin/questions')) ?>"><?= e(t('admin.questions.title')) ?></a><?= icon('chevron-right', 14) ?><span><?= e(t('admin.import.title')) ?></span></nav>
<h1><?= e(t('admin.import.title')) ?></h1>
<?php if ($result !== null): ?>
    <?php if ($result['errors']): ?>
        <div class="alert alert-error" role="alert"><?= icon('alert-circle', 20) ?><div><p><strong><?= e(t('admin.import.rejected', ['n' => count($result['errors'])])) ?></strong></p><ul><?php foreach (array_slice($result['errors'], 0, 50) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div></div>
    <?php elseif ($result['dry_run']): ?>
        <div class="alert alert-success"><?= icon('check-circle', 20) ?><p><?= e(t('admin.import.valid')) ?></p></div>
    <?php else: ?>
        <div class="alert alert-success"><?= icon('check-circle', 20) ?><p><?= e(t('admin.import.done', ['created' => $result['created'], 'updated' => $result['updated']])) ?></p></div>
    <?php endif; ?>
<?php endif; ?>
<div class="grid grid-sidebar">
    <form method="post" action="<?= e(url('/admin/questions/import')) ?>" enctype="multipart/form-data" class="card card-body">
        <?= csrf_field() ?>
        <div class="form-group"><label for="file"><?= e(t('admin.import.file')) ?></label><input class="input" type="file" id="file" name="file" accept=".csv,text/csv" required><p class="help"><?= e(t('admin.import.limits')) ?></p></div>
        <div class="form-group"><label class="check"><input type="checkbox" name="dry_run" value="1" checked><span><?= e(t('admin.import.dry_run')) ?></span></label></div>
        <button class="btn btn-primary" type="submit" data-loading-text="<?= e(t('common.loading')) ?>"><?= icon('upload', 18) ?><?= e(t('admin.import.submit')) ?></button>
    </form>
    <div class="card card-body">
        <h2 style="font-size:1.05rem"><?= e(t('admin.import.format')) ?></h2>
        <p class="small muted"><?= e(t('admin.import.format_text')) ?></p>
        <p class="small"><span class="code"><?= e(implode(',', $columns)) ?></span></p>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('/admin/questions/export')) ?>"><?= icon('download', 16) ?><?= e(t('admin.import.template')) ?></a>
    </div>
</div>
