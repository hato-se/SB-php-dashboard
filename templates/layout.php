<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SwitchBot 温湿度計のリアルタイムデータを表示するダッシュボード">
    <title><?= htmlspecialchars($pageTitle ?? 'SwitchBot ダッシュボード', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="site-header__left">
        <span aria-hidden="true">🌡</span>
        <h1 class="site-title">SwitchBot ダッシュボード</h1>
    </div>
    <span id="meta-updated" class="site-header__meta" aria-live="polite"></span>
    <div class="site-header__actions">
        <button id="btn-settings" class="btn-settings" type="button" aria-label="表示設定を開く">
            <span aria-hidden="true">⚙</span>
            表示設定
        </button>
        <button id="btn-refresh" class="btn-refresh" type="button" aria-label="データを更新">
            <span class="btn-refresh__icon" aria-hidden="true">↻</span>
            更新
        </button>
    </div>
</header>

<main class="main-content">
    <?= $content ?? '' ?>
</main>

<footer class="site-footer">
    <p>SwitchBot OpenAPI v1.1 を使用 &nbsp;|&nbsp; キャッシュ <?= htmlspecialchars((string)(defined('CACHE_TTL') ? CACHE_TTL : 60), ENT_QUOTES, 'UTF-8') ?> 秒</p>
</footer>

<script src="./assets/js/dashboard.js"></script>
</body>
</html>
