<?php

declare(strict_types=1);

// ルートから1階層下（public/）
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use SwitchBot\Config\Config;

try {
    $config = new Config();
} catch (\RuntimeException $e) {
    // config.php が未設定の場合は分かりやすいセットアップ画面を表示
    http_response_code(500);
    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>セットアップが必要です — SwitchBot ダッシュボード</title>
        <style>
            body { font-family: sans-serif; display: flex; justify-content: center;
                   align-items: center; min-height: 100vh; margin: 0;
                   background: #f0f4f8; color: #1e293b; }
            .card { background: #fff; border-radius: 14px; padding: 2rem 2.5rem;
                    box-shadow: 0 4px 24px rgba(0,0,0,.1); max-width: 480px; width: 90%; }
            h1 { font-size: 1.25rem; margin-bottom: 1rem; color: #dc2626; }
            pre { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
                  padding: .75rem 1rem; font-size: .8125rem; white-space: pre-wrap;
                  word-break: break-all; }
            ol { padding-left: 1.25rem; line-height: 2; font-size: .9375rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⚠ セットアップが必要です</h1>
            <pre>{$message}</pre>
            <ol>
                <li><code>config.example.php</code> を <code>config.php</code> にコピーする</li>
                <li>SwitchBot アプリでトークンを取得して記入する</li>
                <li>FTP でサーバーにアップロードする</li>
            </ol>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

// タイムゾーン・エラー表示を設定
date_default_timezone_set($config->getTimezone());
ini_set('display_errors', $config->isDebugMode() ? '1' : '0');
error_reporting($config->isDebugMode() ? E_ALL : 0);

$pageTitle = 'SwitchBot ダッシュボード';

// dashboard.php をバッファリングして $content に格納し、layout.php でラップする
ob_start();
require dirname(__DIR__) . '/templates/dashboard.php';
$content = (string)ob_get_clean();

require dirname(__DIR__) . '/templates/layout.php';
