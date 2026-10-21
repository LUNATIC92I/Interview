<?php
$typeLabel = t('interview_types.' . $session['interview_type']);
?>
<div class="container interview-shell" data-interview data-id="<?= e($session['public_id']) ?>">
    <div class="interview-top">
        <div>
            <p class="eyebrow" style="margin:0"><?= e(t('modes.' . $session['mode'] . '.name')) ?> &middot; <?= e($typeLabel) ?></p>
            <h1 class="interview-title"><?= e($profession['name'] ?? '') ?><?= $scenario ? ' &middot; ' . e($scenario['title']) : '' ?></h1>
        </div>
        <div class="row">
            <span class="timer" data-timer aria-live="off"><?= icon('clock', 16) ?><span data-timer-value>00:00</span><span class="sr-only" data-timer-label></span></span>
            <button type="button" class="btn btn-secondary btn-sm" data-action="pause" hidden><?= icon('pause', 16) ?><?= e(t('interview.pause')) ?></button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="finish-open"><?= icon('flag', 16) ?><?= e(t('interview.finish')) ?></button>
        </div>
    </div>
    <div class="interview-progress">
        <div class="row"><span data-progress-text></span><span data-progress-count></span></div>
        <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-progress aria-label="<?= e(t('interview.progress')) ?>"><div class="progress-bar" data-progress-bar style="--value:0%"></div></div>
    </div>
    <noscript><div class="alert alert-warning noscript-warning"><?= icon('alert-triangle', 20) ?><p><?= e(t('interview.noscript')) ?></p></div></noscript>
    <div class="alert alert-warning" data-offline hidden role="status"><?= icon('wifi-off', 20) ?><p><?= e(t('interview.offline')) ?></p></div>

    <div class="interview-grid">
        <div>
            <article class="card question-card" data-question-card aria-live="polite">
                <div class="card-body">
                    <div class="loading-block" data-loading><?= icon('loader', 22) ?><span><?= e(t('common.loading')) ?></span></div>
                    <div data-question-wrap hidden>
                        <div class="question-meta" data-question-meta></div>
                        <p class="small muted" data-fallback hidden><?= icon('languages', 14) ?> <?= e(t('content.fallback')) ?></p>
                        <h2 class="question-text" id="question-text" data-question-text tabindex="-1"></h2>
                        <div class="guidance" data-guidance hidden></div>
                        <label for="answer" class="label"><?= e(t('interview.your_answer')) ?></label>
                        <textarea id="answer" class="answer-area" data-answer aria-describedby="answer-help" spellcheck="true"></textarea>
                        <div class="answer-tools">
                            <span id="answer-help"><span data-word-count>0</span> <?= e(t('interview.words')) ?> &middot; <span data-recommended></span></span>
                            <span class="save-status" data-save-status aria-live="polite"></span>
                        </div>
                        <div class="timer-bar" data-timer-bar hidden><span></span></div>
                        <div class="interview-actions">
                            <div class="row">
                                <button type="button" class="btn btn-secondary" data-action="prev" hidden><?= icon('arrow-left', 18) ?><?= e(t('interview.previous')) ?></button>
                                <button type="button" class="btn btn-ghost" data-action="skip" hidden><?= icon('skip-forward', 18) ?><?= e(t('interview.skip')) ?></button>
                            </div>
                            <div class="row">
                                <button type="button" class="btn btn-secondary" data-action="next" hidden><?= e(t('interview.next')) ?><?= icon('arrow-right', 18) ?></button>
                                <button type="button" class="btn btn-primary" data-action="submit"><?= icon('check', 18) ?><span data-submit-label><?= e(t('interview.submit')) ?></span></button>
                            </div>
                        </div>
                    </div>
                    <div class="paused-overlay" data-paused hidden>
                        <?= icon('pause', 36) ?>
                        <h2><?= e(t('interview.paused_title')) ?></h2>
                        <p class="muted"><?= e(t('interview.paused_text')) ?></p>
                        <button type="button" class="btn btn-primary" data-action="resume"><?= icon('play', 18) ?><?= e(t('interview.resume')) ?></button>
                    </div>
                    <div class="paused-overlay" data-all-done hidden>
                        <?= icon('check-circle', 36) ?>
                        <h2><?= e(t('interview.all_done_title')) ?></h2>
                        <p class="muted"><?= e(t('interview.all_done_text')) ?></p>
                        <button type="button" class="btn btn-primary btn-lg" data-action="finish-open"><?= icon('flag', 18) ?><?= e(t('interview.finish')) ?></button>
                    </div>
                </div>
            </article>
            <section class="card feedback-panel" data-feedback hidden aria-labelledby="feedback-title"><div class="card-body" data-feedback-body></div></section>
        </div>
        <aside class="interview-aside stack">
            <div class="card"><div class="card-body">
                <h2 style="font-size:1rem"><?= e(t('interview.questions')) ?></h2>
                <ol class="qnav" data-qnav aria-label="<?= e(t('interview.questions')) ?>"></ol>
                <ul class="legend">
                    <li><?= e(t('interview.legend_answered')) ?></li>
                    <li><?= e(t('interview.legend_followup')) ?></li>
                </ul>
            </div></div>
            <div class="card"><div class="card-body small">
                <h2 style="font-size:1rem"><?= e(t('interview.rules')) ?></h2>
                <ul class="feature-list" data-rules></ul>
                <p class="muted" style="margin:.6rem 0 0"><?= e(t('interview.followups_note')) ?></p>
            </div></div>
            <div class="row no-print">
                <button type="button" class="btn btn-ghost btn-sm" data-action="report-open"><?= icon('flag', 16) ?><?= e(t('report_question.open')) ?></button>
                <form method="post" action="<?= e(url('/interview/' . $session['public_id'] . '/abandon')) ?>" data-confirm="<?= e(t('interview.abandon_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('x', 16) ?><?= e(t('interview.abandon')) ?></button></form>
            </div>
        </aside>
    </div>

    <dialog class="modal" id="finish-dialog" aria-labelledby="finish-title">
        <div class="modal-body">
            <h2 id="finish-title"><?= e(t('interview.finish_title')) ?></h2>
            <p class="muted" data-finish-summary></p>
            <p class="muted small"><?= e(t('interview.finish_text')) ?></p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-action="finish-cancel"><?= e(t('interview.continue')) ?></button>
            <button type="button" class="btn btn-primary" data-action="finish-confirm"><?= icon('flag', 18) ?><?= e(t('interview.finish_confirm')) ?></button>
        </div>
    </dialog>
    <dialog class="modal" id="report-dialog" aria-labelledby="report-title">
        <form class="modal-body" data-report-form>
            <h2 id="report-title"><?= e(t('report_question.title')) ?></h2>
            <div class="form-group">
                <label for="report-reason"><?= e(t('report_question.reason')) ?></label>
                <select id="report-reason" name="reason" required>
                    <?php foreach (\App\Models\Domain::REPORT_REASONS as $r): ?><option value="<?= $r ?>"><?= e(t('report_question.reasons.' . $r)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="report-message"><?= e(t('report_question.message')) ?></label>
                <textarea id="report-message" name="message" maxlength="1000" style="min-height:90px"></textarea>
            </div>
        </form>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-action="report-cancel"><?= e(t('common.cancel')) ?></button>
            <button type="button" class="btn btn-primary" data-action="report-send"><?= e(t('report_question.send')) ?></button>
        </div>
    </dialog>
    <script type="application/json" id="interview-state"><?= json_encode($state, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
<script src="<?= e(asset('js/interview.js')) ?>" defer></script>
