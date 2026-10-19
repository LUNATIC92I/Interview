<?php
/** @var string $title */
use App\Core\Translator;
$jsStrings = [];
foreach (Translator::lines(locale()) as $k => $v) {
    if (str_starts_with($k, 'js.')) {
        $jsStrings[substr($k, 3)] = $v;
    }
}
$config = ['csrf' => csrf_token(), 'locale' => locale(), 'base' => url('/'), 'i18n' => $jsStrings];
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') !== '' ? $title . ' | InterviewPro' : 'InterviewPro') ?></title>
<meta name="description" content="<?= e(t('meta.description')) ?>">
<meta name="theme-color" content="#16B8B0">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script type="application/json" id="app-config"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
