<?php
$icons = ['success' => 'check-circle', 'error' => 'alert-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
foreach (($flash ?? []) as $f):
    $type = isset($icons[$f['type']]) ? $f['type'] : 'info'; ?>
    <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
        <?= icon($icons[$type], 20) ?><p><?= e($f['message']) ?></p>
    </div>
<?php endforeach; ?>
