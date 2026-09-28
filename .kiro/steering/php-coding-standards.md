# PHP コーディング規約

このステアリングファイルはすべての Kiro セッションに自動的に読み込まれ、
PHP コードを生成・編集するすべての場面に適用されます。

---

## 1. 厳密な型指定（必須）

すべての PHP ファイルの **先頭** に `declare(strict_types=1);` を記述すること。
`<?php` タグの直後、名前空間宣言より前に置く。

```php
<?php

declare(strict_types=1);

namespace SwitchBot\Api;
```

- `declare` を省略したファイルは規約違反とみなす
- 既存ファイルを編集する場合も、`declare` が欠けていれば追加する

---

## 2. PSR-12 コーディング規約

[PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/) に完全準拠すること。
主要ルールを以下に示す。

### ファイル形式
- 文字コード: **UTF-8**（BOM なし）
- 改行コード: **LF**（`\n`）
- ファイル末尾: **改行 1 つ**

### インデント・スペーシング
- インデント: **スペース 4 つ**（タブ禁止）
- 1行の最大文字数: **120 文字**（推奨）/ **160 文字**（上限）

### 名前付け規則
| 対象 | スタイル | 例 |
|------|----------|-----|
| クラス名 | PascalCase | `SwitchBotClient` |
| メソッド名 | camelCase | `getDeviceStatus()` |
| 変数名 | camelCase | `$deviceId` |
| 定数名 | UPPER_SNAKE_CASE | `CACHE_TTL` |
| プロパティ名 | camelCase | `$cacheTtl` |

### クラス構造
```php
class SwitchBotClient
{
    // 定数
    private const BASE_URL = 'https://api.switch-bot.com/v1.1';

    // プロパティ（visibility 必須）
    private readonly Auth $auth;

    // コンストラクタ
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    // メソッド（戻り値型を必ず宣言する）
    public function getDevices(): array
    {
        // ...
    }
}
```

### 型宣言
- すべてのメソッドに **引数型** と **戻り値型** を宣言する
- nullable は `?string` 記法を使う
- `mixed` は型が本当に不定の場合のみ使用する

```php
// ✅ 良い例
public function get(string $key, mixed $default = null): mixed

// ❌ 悪い例（型宣言なし）
public function get($key, $default = null)
```

### 制御構文
```php
// if-else
if ($condition) {
    // ...
} elseif ($other) {
    // ...
} else {
    // ...
}

// 波括弧は省略しない
// ❌ if ($x) doSomething();
// ✅ if ($x) { doSomething(); }
```

---

## 3. API 呼び出しのエラーハンドリング（必須）

SwitchBot API への呼び出し（cURL リクエスト）は **必ず try-catch で囲む**。
エラーをサイレントに握りつぶしてはならない。

### エントリポイント（`index.php` / `api/devices.php`）

```php
try {
    $config = new Config();
    $auth   = new Auth($config->getToken(), $config->getSecret());
    $client = new SwitchBotClient($auth, $config->getCacheTtl(), $config->getCacheDir());

    $result = $client->getDevices();

    if (!($result['success'] ?? false)) {
        // エラーレスポンスを返す（JSON エンドポイントの場合）
        echo json_encode(['success' => false, 'error' => $result['error'] ?? '不明なエラー']);
        exit;
    }
    // 正常処理...
} catch (\RuntimeException $e) {
    // 設定エラー（トークン未設定など）— ユーザーに原因を伝える
    error_log('[MyScript] 設定エラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    // 予期しないエラー — 詳細はログのみ、ユーザーには汎用メッセージ
    error_log('[MyScript] 予期しないエラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'サーバー内部エラーが発生しました。']);
}
```

### 内部クラス（`SwitchBotClient` 等）

内部クラスの API 呼び出しメソッドは **例外を外に投げない**。
エラーは配列に包んで返し、呼び出し元で判定させる。

```php
private function request(string $method, string $path): array
{
    $ch = curl_init();
    // curl_setopt_array(...);

    $body      = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // ① cURL 自体のエラー
    if ($curlError !== '') {
        error_log("[SwitchBotClient] cURL エラー ({$path}): {$curlError}");
        return ['error' => '通信エラーが発生しました。'];
    }

    // ② HTTP エラー
    if ($httpCode === 401) {
        return ['error' => '認証エラー: config.php のトークンを確認してください。', 'code' => 401];
    }

    // ③ JSON パースエラー
    $decoded = json_decode((string)$body, true);
    if ($decoded === null) {
        error_log("[SwitchBotClient] JSON デコード失敗 ({$path})");
        return ['error' => 'APIレスポンスの解析に失敗しました。'];
    }

    // ④ API レベルのエラーコード
    $statusCode = $decoded['statusCode'] ?? 0;
    if ($statusCode !== 100 && $statusCode !== 190) {
        error_log("[SwitchBotClient] APIエラー statusCode={$statusCode}");
        return ['error' => "APIエラー (code: {$statusCode})"];
    }

    return $decoded;
}
```

### エラーハンドリングのチェックリスト

新しい API 呼び出しコードを書く際は以下を確認する。

- [ ] cURL の `curl_error()` 戻り値を確認している
- [ ] HTTP ステータスコード（特に 401, 4xx, 5xx）を確認している
- [ ] `json_decode()` の戻り値が `null` でないか確認している
- [ ] SwitchBot 独自の `statusCode` フィールドを確認している
- [ ] エラー内容を `error_log()` でサーバーログに記録している
- [ ] ユーザーへのエラーメッセージに内部情報（スタックトレース等）を含めていない
- [ ] エントリポイントは try-catch で囲んでいる

---

## 4. コメント規約

### DocBlock（クラス・メソッド）
```php
/**
 * 指定デバイスのステータスを取得する
 *
 * @param  string $deviceId  SwitchBot デバイス ID
 * @return array{success: bool, online?: bool, body?: array<string, mixed>, error?: string}
 */
public function getDeviceStatus(string $deviceId): array
```

- public メソッドには DocBlock を必ず付ける
- `@return` の型は PHPStan/Psalm 形式の配列シェイプを推奨する
- private メソッドは複雑な場合のみ DocBlock を付ける

### インラインコメント
- 「何をしているか」より「**なぜそうしているか**」を書く
- 自明なコードにコメントを付けない

```php
// ✅ 良い例: 理由が明確
// SwitchBot API は13桁のミリ秒タイムスタンプを要求する
$timestamp = (int)(microtime(true) * 1000);

// ❌ 悪い例: 自明すぎる
// タイムスタンプを取得する
$timestamp = time();
```

---

## 5. 違反例と修正例

### 違反例 1: `declare` 欠落
```php
<?php
// ❌ declare(strict_types=1) がない
namespace SwitchBot\Helper;

class Auth { ... }
```

```php
<?php
// ✅ 修正後
declare(strict_types=1);

namespace SwitchBot\Helper;

class Auth { ... }
```

### 違反例 2: 型宣言なし
```php
// ❌
public function generateSign($timestamp, $nonce) {
    return strtoupper(base64_encode(hash_hmac('sha256', ...)));
}
```

```php
// ✅
public function generateSign(int $timestamp, string $nonce): string
{
    return strtoupper(base64_encode(hash_hmac('sha256', ...)));
}
```

### 違反例 3: try-catch なしの API 呼び出し
```php
// ❌ エラーが握りつぶされる
$result = $client->getDevices();
echo json_encode($result['body']);
```

```php
// ✅
try {
    $result = $client->getDevices();
    if (!($result['success'] ?? false)) {
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit;
    }
    echo json_encode(['success' => true, 'devices' => $result['devices']]);
} catch (\Throwable $e) {
    error_log('[devices.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました。']);
}
```
