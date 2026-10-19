<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="eyebrow"><?= e(t('home.eyebrow')) ?></p>
            <h1><?= e(t('home.title_1')) ?> <span class="accent"><?= e(t('home.title_2')) ?></span> <?= e(t('home.title_3')) ?></h1>
            <p class="lead"><?= e(t('home.subtitle')) ?></p>
            <div class="row">
                <a class="btn btn-primary btn-lg" href="<?= e(url($authUser ? '/interview/new' : '/register')) ?>"><?= icon('play', 18) ?><?= e(t('home.cta_primary')) ?></a>
                <a class="btn btn-secondary btn-lg" href="<?= e(url('/professions')) ?>"><?= icon('briefcase', 18) ?><?= e(t('home.cta_secondary')) ?></a>
            </div>
            <ul class="hero-points">
                <li><?= icon('check-circle', 18) ?><?= e(t('home.point_1', ['count' => $counts['professions']])) ?></li>
                <li><?= icon('check-circle', 18) ?><?= e(t('home.point_2', ['count' => $counts['questions']])) ?></li>
                <li><?= icon('check-circle', 18) ?><?= e(t('home.point_3')) ?></li>
            </ul>
        </div>
        <div class="mock hero-visual" aria-hidden="true">
            <div class="mock-skills"><?= icon('check-circle', 16) ?><?= e(t('home.mock.saved')) ?></div>
            <div class="mock-window">
                <div class="mock-bar"><span class="mock-dots"><i></i><i></i><i></i></span><span><?= e(t('home.mock.window')) ?></span></div>
                <div class="mock-body">
                    <div class="mock-meta">
                        <span class="badge badge-primary"><?= e(t('question_types.technical')) ?></span>
                        <span class="badge"><?= e(t('home.mock.profession')) ?></span>
                        <span class="badge"><?= e(t('home.mock.question')) ?></span>
                    </div>
                    <p class="mock-question"><?= e(t('home.mock.text')) ?></p>
                    <div class="mock-lines"><span></span><span></span><span></span><span class="typing"></span></div>
                    <div class="mock-footer">
                        <span class="mock-timer"><?= icon('timer', 16) ?>03:42</span>
                        <span><?= e(t('home.mock.progress')) ?></span>
                    </div>
                    <div class="progress" style="margin-top:10px"><div class="progress-bar" style="--value:38%"></div></div>
                </div>
            </div>
            <div class="mock-score">
                <div class="row">
                    <div class="score-ring sm success" style="--value:78"><div class="score-ring-value"><strong>78</strong></div></div>
                    <div class="mock-mini" style="flex:1">
                        <div class="meter"><span class="meter-label"><?= e(t('criteria.relevance')) ?></span><span class="meter-value">84</span><div class="progress"><div class="progress-bar" style="--value:84%"></div></div></div>
                        <div class="meter"><span class="meter-label"><?= e(t('criteria.structure')) ?></span><span class="meter-value">72</span><div class="progress"><div class="progress-bar" style="--value:72%"></div></div></div>
                        <div class="meter"><span class="meter-label"><?= e(t('criteria.results')) ?></span><span class="meter-value">65</span><div class="progress"><div class="progress-bar warning" style="--value:65%"></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow"><?= e(t('home.features.eyebrow')) ?></p>
            <h2><?= e(t('home.features.title')) ?></h2>
            <p class="lead"><?= e(t('home.features.lead')) ?></p>
        </div>
        <div class="grid grid-3">
            <?php foreach ([['target', 'scenarios'], ['message-square', 'bank'], ['bar-chart', 'evaluation'], ['line-chart', 'dashboard'], ['file-text', 'report'], ['languages', 'bilingual']] as [$ic, $key]): ?>
                <article class="card card-hover reveal"><div class="card-body">
                    <div class="card-icon"><?= icon($ic, 22) ?></div>
                    <h3><?= e(t('home.features.' . $key . '.title')) ?></h3>
                    <p class="muted" style="margin:0"><?= e(t('home.features.' . $key . '.text')) ?></p>
                </div></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow"><?= e(t('home.categories.eyebrow')) ?></p>
            <h2><?= e(t('home.categories.title')) ?></h2>
        </div>
        <div class="grid grid-3">
            <?php foreach ($categories as $cat): ?>
                <a class="card card-hover reveal" href="<?= e(url('/professions', ['category' => $cat['id']])) ?>"><div class="card-body">
                    <div class="card-icon"><?= icon($cat['icon'], 22) ?></div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p class="muted small"><?= e($cat['description']) ?></p>
                    <span class="badge badge-primary"><?= e(t('home.categories.count', ['count' => $cat['profession_count']])) ?></span>
                </div></a>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:24px"><a class="btn btn-secondary" href="<?= e(url('/professions')) ?>"><?= e(t('home.categories.all')) ?><?= icon('arrow-right', 18) ?></a></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow"><?= e(t('home.steps.eyebrow')) ?></p>
            <h2><?= e(t('home.steps.title')) ?></h2>
        </div>
        <ol class="steps" style="list-style:none;padding:0;margin:0">
            <?php foreach ([1, 2, 3, 4] as $i): ?>
                <li class="step reveal"><h3><?= e(t('home.steps.' . $i . '.title')) ?></h3><p class="muted" style="margin:0"><?= e(t('home.steps.' . $i . '.text')) ?></p></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section section-alt">
    <div class="container grid grid-2" style="align-items:center">
        <div class="reveal">
            <p class="eyebrow"><?= e(t('home.benefits.eyebrow')) ?></p>
            <h2><?= e(t('home.benefits.title')) ?></h2>
            <ul class="feature-list">
                <?php foreach ([1, 2, 3, 4, 5] as $i): ?><li><?= icon('check', 18) ?><span><?= e(t('home.benefits.' . $i)) ?></span></li><?php endforeach; ?>
            </ul>
        </div>
        <div class="card reveal"><div class="card-body">
            <h3 class="row"><?= icon('shield-check', 22) ?><?= e(t('home.transparency.title')) ?></h3>
            <p class="muted"><?= e(t('home.transparency.text')) ?></p>
            <p class="muted" style="margin:0"><?= e(t('home.transparency.text2')) ?></p>
        </div></div>
    </div>
</section>

<section class="section" id="faq">
    <div class="container container-narrow">
        <div class="section-head reveal"><h2><?= e(t('home.faq.title')) ?></h2></div>
        <div class="faq reveal">
            <?php foreach ([1, 2, 3, 4, 5, 6] as $i): ?>
                <details>
                    <summary><?= e(t('home.faq.' . $i . '.q')) ?><?= icon('chevron-down', 18) ?></summary>
                    <div class="faq-body"><p><?= e(t('home.faq.' . $i . '.a')) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container">
        <div class="cta-band reveal">
            <div><h2><?= e(t('home.final.title')) ?></h2><p><?= e(t('home.final.text')) ?></p></div>
            <a class="btn btn-primary btn-lg" href="<?= e(url($authUser ? '/interview/new' : '/register')) ?>"><?= e(t('home.cta_primary')) ?><?= icon('arrow-right', 18) ?></a>
        </div>
    </div>
</section>
