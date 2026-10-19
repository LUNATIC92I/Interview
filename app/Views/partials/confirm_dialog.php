<dialog class="modal" id="confirm-dialog" aria-labelledby="confirm-title">
    <div class="modal-body">
        <h2 id="confirm-title"><?= e(t('js.confirm_title')) ?></h2>
        <p id="confirm-message" class="muted"></p>
    </div>
    <div class="modal-actions">
        <button type="button" class="btn btn-secondary" data-confirm-cancel><?= e(t('common.cancel')) ?></button>
        <button type="button" class="btn btn-primary" data-confirm-ok><?= e(t('common.confirm')) ?></button>
    </div>
</dialog>
<div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="false"></div>
