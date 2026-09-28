# 設計書 — SwitchBot PHP ダッシュボード

## デプロイ環境の前提

| 項目 | 内容 |
|------|------|
| 対象サーバー | 一般的なレンタルサーバー（さくらインターネット・ロリポップ等） |
| アクセス方法 | FTP / SFTP によるファイルアップロードのみ（SSH 不可） |
| CLI 実行 | 不可（`composer`・`npm` 等のコマンド実行不可） |
| 公開領域 | `public_html/` 等、サーバーによって異なる |
| PHP バージョン | 8.1 以上（コントロールパネルで選択） |

---

## アーキテクチャ概要

```
ブラウザ
  │  初期ロード: HTML/CSS/JS
  │  Ajax: GET /api/devices.php
  │
  ▼
index.php                 ← エントリポイント（HTML レンダリング）
api/devices.php           ← Ajax エンドポイント（JSON 返却）
  │
  ├── require config.php         ← 設定定数（トークン等）
  ├── require src/autoload.php   ← 自前クラスローダー
  ├── SwitchBot\Config\Config    ← config.php のラッパー
  ├── SwitchBot\Helper\Auth      ← HMAC-SHA256 署名生成
  └── SwitchBot\Api\SwitchBotClient ← OpenAPI 呼び出し
        │
        ▼
  SwitchBot Cloud API (https://api.switch-bot.com/v1.1/...)
```

---

## ディレクトリ構成（全ファイルを公開領域に配置）

```
public_html/                      ← レンタルサーバーの公開領域ルート
├── index.php                     ← ダッシュボード エントリポイント
├── config.php                    ← ★ユーザーが編集する設定ファイル
├── .htaccess                     ← config.php への直接アクセスを拒否
├── api/
│   └── devices.php               ← Ajax エンドポイント
├── assets/
│   ├── css/style.css
│   └── js/dashboard.js
├── src/
│   ├── autoload.php              ← 自前クラスローダー（composer 不要）
│   ├── Api/SwitchBotClient.php
│   ├── Config/Config.php
│   └── Helper/Auth.php
├── templates/
│   ├── layout.php
│   └── dashboard.php
└── cache/                        ← API レスポンスキャッシュ（書き込み可能に設定）
    └── .gitkeep
```

> **注意**: `composer.json` / `vendor/` は使用しない。
> クラスの読み込みは `src/autoload.php` で管理する。

---

## 設定ファイル設計

### `config.php`

```php
<?php
// ============================================================
// SwitchBot ダッシュボード 設定ファイル
// この値を書き換えてサーバーにアップロードしてください
// ============================================================

// SwitchBot アプリで取得した Open Token
define('SWITCHBOT_TOKEN', 'your_open_token_here');

// SwitchBot アプリで取得した Secret Key
define('SWITCHBOT_SECRET', 'your_secret_key_here');

// APIレスポンスのキャッシュ保持時間（秒）
define('CACHE_TTL', 60);

// タイムゾーン
define('TIMEZONE', 'Asia/Tokyo');

// キャッシュ保存ディレクトリ（末尾スラッシュなし）
// 書き込み権限(755/777)が必要。サーバーによって変更してください。
define('CACHE_DIR', __DIR__ . '/cache');
```

`.htaccess` による保護:
```apache
# config.php への直接アクセスを拒否
<Files "config.php">
    Require all denied
</Files>
```

---

## 自前クラスローダー設計

### `src/autoload.php`

`composer` を使わない PSR-4 風の簡易クラスローダー。

```php
spl_autoload_register(function (string $class): void {
    // SwitchBot\Api\SwitchBotClient → src/Api/SwitchBotClient.php
    $prefix = 'SwitchBot\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
```

---

## クラス設計

### `src/Config/Config.php`

```
Config
├── __construct()                     // PHP 定数 (define) から読み込む
├── get(string $key, mixed $default): mixed
├── getToken(): string
├── getSecret(): string
├── getCacheTtl(): int
├── getCacheDir(): string
└── getTimezone(): string
```

**責務**: `config.php` で定義された定数をラップし、未設定チェックと型変換を行う。

`.env` ファイルパースは行わない。PHP 定数（`define`）を直接参照するため、
FTP 経由でファイルを書き換えるだけで設定が反映される。

---

### `src/Helper/Auth.php`

```
Auth
├── __construct(string $token, string $secret)
├── generateHeaders(): array
├── generateNonce(): string  // UUID v4
└── generateSign(int $timestamp, string $nonce): string
```

**責務**: SwitchBot v1.1 認証に必要なリクエストヘッダーを生成する。

署名アルゴリズム:
```php
$t     = (int)(microtime(true) * 1000);  // 13桁ミリ秒
$nonce = UUID v4;
$data  = $token . $t . $nonce;
$sign  = strtoupper(base64_encode(hash_hmac('sha256', $data, $secret, true)));
```

返却ヘッダー:
```php
[
    'Authorization' => $token,
    'sign'          => $sign,
    't'             => (string)$t,
    'nonce'         => $nonce,
    'Content-Type'  => 'application/json',
]
```

---

### `src/Api/SwitchBotClient.php`

```
SwitchBotClient
├── __construct(Auth $auth, int $cacheTtl, string $cacheDir)
├── getDevices(): array
├── getDeviceStatus(string $deviceId): array
└── request(string $method, string $path, array $body = []): array
```

**責務**: SwitchBot OpenAPI への HTTP リクエストを送信し、レスポンスを配列で返す。

エラー時の返却形式:
```php
['error' => 'エラーメッセージ', 'code' => 401]
```

キャッシュ戦略:
- `CACHE_DIR` 配下のファイルキャッシュ（`CACHE_TTL` 秒 TTL）
- キャッシュキー: `{md5(deviceId)}.json`
- `CACHE_DIR` が存在しない場合は自動作成を試みる（失敗してもキャッシュなしで動作継続）

---

## API エンドポイント設計

### `api/devices.php` (Ajax エンドポイント)

**リクエスト**: `GET /api/devices.php`

**処理フロー**:
1. `config.php` を `require` して定数をロード
2. `src/autoload.php` をロードしてクラスを利用可能にする
3. `Config` でトークン・シークレット等の設定値を取得
4. `Auth` でヘッダー生成
5. `SwitchBotClient::getDevices()` で全デバイス取得
6. 温湿度計デバイスをフィルタリング（定数 `METER_DEVICE_TYPES`）
7. 各デバイスに対して `getDeviceStatus()` を呼び出し
8. JSON レスポンス返却

**レスポンス（成功）**:
```json
{
  "success": true,
  "updatedAt": "2026-09-26T15:30:00+09:00",
  "devices": [
    {
      "deviceId": "C271111EC0AB",
      "deviceName": "リビング温湿度計",
      "deviceType": "Meter",
      "temperature": 26.1,
      "humidity": 52,
      "online": true
    }
  ]
}
```

**レスポンス（エラー）**:
```json
{
  "success": false,
  "error": "認証エラー: トークンを確認してください"
}
```

---

## フロントエンド設計

### `index.php`

- `config.php` と `src/autoload.php` を require してからテンプレートを出力
- 初期ロード時に JavaScript から自動で `/api/devices.php` を呼び出す

### `assets/css/style.css`

- CSS カスタムプロパティ（変数）でカラーパレット管理
- CSS Grid でカード一覧をレスポンシブ配置
- カード構造:

```
┌─────────────────────────┐
│  デバイス名              │
├─────────────────────────┤
│   🌡 26.1℃              │
│   💧 52%                │
├─────────────────────────┤
│  更新: 2026-09-26 15:30 │
└─────────────────────────┘
```

### `assets/js/dashboard.js`

主要関数:
```javascript
async function fetchDevices()    // /api/devices.php を呼び出してカードを描画
function renderDevices(data)     // カード DOM を生成
function showLoading(visible)    // ローディング表示切り替え
function showError(message)      // エラーバナー表示
```

---

## ファイルキャッシュ設計

| 項目 | 内容 |
|-----|------|
| 保存場所 | `CACHE_DIR`（`config.php` で設定、デフォルト: `__DIR__ . '/cache'`） |
| ファイル名 | `{md5(deviceId)}.json` |
| TTL | `CACHE_TTL` 秒（デフォルト 60秒） |
| 無効化 | TTL 超過時に自動削除 & 再取得 |
| パーミッション | `cache/` ディレクトリに 755 または 777 の書き込み権限が必要 |

キャッシュ使用理由: API 上限 10,000 回/日 の節約と画面更新の高速化。

---

## エラーハンドリングフロー

```
API 呼び出し
    │
    ├─ config.php 未設定    → RuntimeException → エラーページ表示
    ├─ cURL エラー          → error_log() 記録 + ['error' => '通信エラー']
    ├─ HTTP 401             → ['error' => '認証エラー', 'code' => 401]
    ├─ statusCode 190       → ['online' => false]
    ├─ statusCode 100       → 正常データ返却
    └─ その他               → ['error' => 'APIエラー: {statusCode}']
```

---

## セキュリティ考慮事項

1. `config.php` は `.htaccess` の `Require all denied` で直接アクセスを遮断する
2. `cache/` ディレクトリも `.htaccess` で直接アクセスを遮断する
3. `api/devices.php` はヘッダーで `Content-Type: application/json` を必ず返す
4. 出力する値は `htmlspecialchars()` でエスケープする（XSS 対策）
5. `error_reporting` と `display_errors` は本番で無効化する（`config.php` で設定）

---

## FTP デプロイ手順（概要）

1. `config.php` の定数値（TOKEN / SECRET）を書き換える
2. 全ファイルを FTP クライアントでサーバーの公開領域にアップロードする
3. `cache/` ディレクトリのパーミッションを 755 または 777 に変更する
4. ブラウザでアクセスして動作確認する
