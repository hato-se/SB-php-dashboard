<?php

declare(strict_types=1);

/**
 * SwitchBot 温湿度データパーサー プロパティベーステスト
 *
 * getMeterStatus() が返す配列の型・範囲・不変条件を
 * 100件のランダム入力で検証する。外部 API・ファイルキャッシュには依存しない。
 *
 * 実行方法:
 *   php tests/PropertyTest.php
 */

// ─── オートローダー ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../src/autoload.php';

use SwitchBot\Api\SwitchBotClient;
use SwitchBot\Helper\Auth;

// ─── テストランナー（軽量） ──────────────────────────────────────────────────

/** @var array{passed: int, failed: int, errors: list<string>} */
$report = ['passed' => 0, 'failed' => 0, 'errors' => []];

/**
 * アサーション関数
 *
 * @param  bool   $condition   真であればパス
 * @param  string $label       テスト説明（失敗時に表示）
 * @param  mixed  $actual      実際の値（失敗メッセージ用）
 */
function assert_that(bool $condition, string $label, mixed $actual = null): void
{
    global $report;
    if ($condition) {
        $report['passed']++;
    } else {
        $report['failed']++;
        $detail = $actual !== null ? ' — 実際の値: ' . var_export($actual, true) : '';
        $report['errors'][] = "FAIL: {$label}{$detail}";
    }
}

// ─── テスト用スタブクラス ────────────────────────────────────────────────────

/**
 * 実際の HTTP 通信を行わず、任意のボディを返す SwitchBotClient サブクラス。
 * getDeviceStatus() をオーバーライドして getMeterStatus() のパース処理のみを検証する。
 */
class StubSwitchBotClient extends SwitchBotClient
{
    /** @var array<string, mixed> 次の getDeviceStatus() 呼び出しで返すデータ */
    private array $stubbedBody = [];
    private bool  $stubbedOnline = true;
    private bool  $stubbedError  = false;
    private string $stubbedErrorMsg = '';

    public function __construct()
    {
        // Auth はダミー文字列で初期化（ネットワーク通信は発生しない）
        parent::__construct(
            new Auth('dummy_token_for_testing', 'dummy_secret_for_testing'),
            0,  // cacheTtl = 0 でキャッシュ無効
            ''  // cacheDir 空でキャッシュ無効
        );
    }

    /**
     * テスト用ボディを注入する（オンライン正常応答）
     *
     * @param array<string, mixed> $body
     */
    public function injectOnlineResponse(array $body): void
    {
        $this->stubbedBody     = $body;
        $this->stubbedOnline   = true;
        $this->stubbedError    = false;
        $this->stubbedErrorMsg = '';
    }

    /** テスト用オフライン応答を注入する */
    public function injectOfflineResponse(): void
    {
        $this->stubbedBody     = [];
        $this->stubbedOnline   = false;
        $this->stubbedError    = false;
        $this->stubbedErrorMsg = '';
    }

    /** テスト用エラー応答を注入する */
    public function injectErrorResponse(string $errorMessage): void
    {
        $this->stubbedBody     = [];
        $this->stubbedOnline   = false;
        $this->stubbedError    = true;
        $this->stubbedErrorMsg = $errorMessage;
    }

    /**
     * HTTP 通信なしでスタブデータを返す
     *
     * @return array{success: bool, online?: bool, body?: array<string, mixed>, error?: string}
     */
    public function getDeviceStatus(string $deviceId): array
    {
        if ($this->stubbedError) {
            return ['error' => $this->stubbedErrorMsg];
        }
        if (!$this->stubbedOnline) {
            return ['success' => true, 'online' => false, 'body' => []];
        }
        return ['success' => true, 'online' => true, 'body' => $this->stubbedBody];
    }
}

// ─── ヘルパー ────────────────────────────────────────────────────────────────

/**
 * ランダムな有効デバイス ID を生成する（12桁の大文字16進数）
 */
function randomDeviceId(): string
{
    return strtoupper(bin2hex(random_bytes(6)));
}

/**
 * SwitchBot Meter が返す現実的な温度を生成する
 * 範囲: -20.0 〜 60.0 °C、0.1°C 刻み
 */
function randomTemperature(): float
{
    return round(mt_rand(-200, 600) / 10.0, 1);
}

/**
 * SwitchBot Meter が返す現実的な湿度を生成する
 * 範囲: 0 〜 99 %
 */
function randomHumidity(): int
{
    return mt_rand(0, 99);
}

/**
 * SwitchBot デバイスのバッテリー残量を生成する
 * 範囲: 0 〜 100 %
 */
function randomBattery(): int
{
    return mt_rand(0, 100);
}

/**
 * METER_DEVICE_TYPES からランダムなデバイスタイプを返す
 */
function randomDeviceType(): string
{
    $types = SwitchBotClient::METER_DEVICE_TYPES;
    return $types[array_rand($types)];
}

// ─── テスト本体 ──────────────────────────────────────────────────────────────

$client = new StubSwitchBotClient();
$iterations = 100;

echo "=== SwitchBot パーサー プロパティベーステスト ({$iterations} 件) ===\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 1: オンライン正常応答
// 100 件のランダムな温湿度入力を与え、パース結果の型・範囲・キーを検証する
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 1: オンライン正常応答パース ({$iterations} 件)\n";

for ($i = 0; $i < $iterations; $i++) {
    $deviceId   = randomDeviceId();
    $tempRaw    = randomTemperature();
    $humidRaw   = randomHumidity();
    $battRaw    = randomBattery();
    $deviceType = randomDeviceType();

    $client->injectOnlineResponse([
        'deviceId'   => $deviceId,
        'deviceType' => $deviceType,
        'temperature' => $tempRaw,
        'humidity'    => $humidRaw,
        'battery'     => $battRaw,
    ]);

    $result = $client->getMeterStatus($deviceId);

    // P1-a: success が true であること
    assert_that(
        $result['success'] === true,
        "P1-a [{$i}] success === true",
        $result['success'] ?? null
    );

    // P1-b: online が true であること
    assert_that(
        $result['online'] === true,
        "P1-b [{$i}] online === true",
        $result['online'] ?? null
    );

    // P1-c: deviceId が入力と一致すること
    assert_that(
        $result['deviceId'] === $deviceId,
        "P1-c [{$i}] deviceId が保持される",
        $result['deviceId'] ?? null
    );

    // P1-d: temperature が float 型であること
    assert_that(
        is_float($result['temperature']),
        "P1-d [{$i}] temperature は float 型",
        $result['temperature'] ?? null
    );

    // P1-e: temperature の値が入力と一致すること（(float) キャストの同一性）
    assert_that(
        $result['temperature'] === (float)$tempRaw,
        "P1-e [{$i}] temperature 値が保持される (input={$tempRaw})",
        $result['temperature'] ?? null
    );

    // P1-f: temperature が現実的な範囲内であること
    assert_that(
        $result['temperature'] >= -20.0 && $result['temperature'] <= 60.0,
        "P1-f [{$i}] temperature が -20〜60 の範囲内",
        $result['temperature'] ?? null
    );

    // P1-g: humidity が int 型であること
    assert_that(
        is_int($result['humidity']),
        "P1-g [{$i}] humidity は int 型",
        $result['humidity'] ?? null
    );

    // P1-h: humidity の値が入力と一致すること
    assert_that(
        $result['humidity'] === (int)$humidRaw,
        "P1-h [{$i}] humidity 値が保持される (input={$humidRaw})",
        $result['humidity'] ?? null
    );

    // P1-i: humidity が 0〜99 の範囲内であること
    assert_that(
        $result['humidity'] >= 0 && $result['humidity'] <= 99,
        "P1-i [{$i}] humidity が 0〜99 の範囲内",
        $result['humidity'] ?? null
    );

    // P1-j: battery が int 型であること
    assert_that(
        is_int($result['battery']),
        "P1-j [{$i}] battery は int 型",
        $result['battery'] ?? null
    );

    // P1-k: battery が 0〜100 の範囲内であること
    assert_that(
        $result['battery'] >= 0 && $result['battery'] <= 100,
        "P1-k [{$i}] battery が 0〜100 の範囲内",
        $result['battery'] ?? null
    );

    // P1-l: deviceType が文字列であること
    assert_that(
        is_string($result['deviceType']),
        "P1-l [{$i}] deviceType は string 型",
        $result['deviceType'] ?? null
    );

    // P1-m: deviceType が METER_DEVICE_TYPES のいずれかであること
    assert_that(
        in_array($result['deviceType'], SwitchBotClient::METER_DEVICE_TYPES, true),
        "P1-m [{$i}] deviceType が既知の Meter タイプ",
        $result['deviceType'] ?? null
    );

    // P1-n: error キーが存在しないこと
    assert_that(
        !array_key_exists('error', $result),
        "P1-n [{$i}] 正常時に error キーは存在しない"
    );
}

echo "  完了\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 2: temperature の型強制（文字列・int 等の入力でも float になること）
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 2: temperature 型強制（文字列数値・int 入力）\n";

$typeCases = [];
for ($i = 0; $i < $iterations; $i++) {
    // 偶数インデックスは文字列、奇数は整数を注入してキャスト動作を確認
    if ($i % 2 === 0) {
        $raw  = (string)randomTemperature();  // 文字列として注入
        $typeCases[] = ['raw' => $raw, 'expected' => (float)$raw, 'label' => "string '{$raw}'"];
    } else {
        $raw  = mt_rand(-200, 600);           // int として注入
        $typeCases[] = ['raw' => $raw, 'expected' => (float)$raw, 'label' => "int {$raw}"];
    }
}

foreach ($typeCases as $idx => $case) {
    $deviceId = randomDeviceId();
    $client->injectOnlineResponse([
        'deviceId'    => $deviceId,
        'deviceType'  => 'Meter',
        'temperature' => $case['raw'],
        'humidity'    => 50,
        'battery'     => 80,
    ]);
    $result = $client->getMeterStatus($deviceId);

    assert_that(
        is_float($result['temperature']),
        "P2-a [{$idx}] temperature は float ({$case['label']})",
        $result['temperature'] ?? null
    );
    assert_that(
        $result['temperature'] === $case['expected'],
        "P2-b [{$idx}] temperature 値が正しくキャストされる ({$case['label']})",
        $result['temperature'] ?? null
    );
}

echo "  完了\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 3: humidity の型強制（float 入力でも int になること）
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 3: humidity 型強制（float 入力）\n";

for ($i = 0; $i < $iterations; $i++) {
    // float として注入（例: 52.9 → (int) キャストで 52 になるべき）
    $floatHumidity = (float)mt_rand(0, 99) + (mt_rand(0, 9) / 10.0);
    $deviceId      = randomDeviceId();

    $client->injectOnlineResponse([
        'deviceId'    => $deviceId,
        'deviceType'  => 'Meter',
        'temperature' => 25.0,
        'humidity'    => $floatHumidity,
        'battery'     => 80,
    ]);
    $result = $client->getMeterStatus($deviceId);

    assert_that(
        is_int($result['humidity']),
        "P3-a [{$i}] humidity は int 型 (input={$floatHumidity})",
        $result['humidity'] ?? null
    );
    assert_that(
        $result['humidity'] === (int)$floatHumidity,
        "P3-b [{$i}] humidity が (int) キャストと一致 (input={$floatHumidity})",
        $result['humidity'] ?? null
    );
}

echo "  完了\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 4: null フィールド（温度・湿度・バッテリーが欠けている応答）
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 4: 欠落フィールドは null になること\n";

$missingCombinations = [
    [],                                                           // すべて欠落
    ['temperature' => 25.0],                                      // humidity/battery 欠落
    ['humidity'    => 60],                                        // temperature/battery 欠落
    ['battery'     => 90],                                        // temperature/humidity 欠落
    ['temperature' => 25.0, 'humidity' => 60],                    // battery のみ欠落
    ['temperature' => 25.0, 'battery'  => 90],                    // humidity のみ欠落
    ['humidity'    => 60,   'battery'  => 90],                    // temperature のみ欠落
    ['temperature' => 25.0, 'humidity' => 60, 'battery' => 90],  // すべて揃っている
];

// 各パターンを約 12〜13 回ずつ繰り返して合計 100 件に近づける
$nullIterations = 0;
while ($nullIterations < $iterations) {
    foreach ($missingCombinations as $bodyFields) {
        if ($nullIterations >= $iterations) {
            break;
        }
        $deviceId = randomDeviceId();
        $body     = array_merge(['deviceId' => $deviceId, 'deviceType' => 'Meter'], $bodyFields);
        $client->injectOnlineResponse($body);
        $result = $client->getMeterStatus($deviceId);

        $expectTemp    = array_key_exists('temperature', $bodyFields);
        $expectHumid   = array_key_exists('humidity', $bodyFields);
        $expectBattery = array_key_exists('battery', $bodyFields);

        assert_that(
            $expectTemp ? is_float($result['temperature']) : $result['temperature'] === null,
            "P4-a [{$nullIterations}] temperature: " . ($expectTemp ? 'float' : 'null'),
            $result['temperature'] ?? 'null'
        );
        assert_that(
            $expectHumid ? is_int($result['humidity']) : $result['humidity'] === null,
            "P4-b [{$nullIterations}] humidity: " . ($expectHumid ? 'int' : 'null'),
            $result['humidity'] ?? 'null'
        );
        assert_that(
            $expectBattery ? is_int($result['battery']) : $result['battery'] === null,
            "P4-c [{$nullIterations}] battery: " . ($expectBattery ? 'int' : 'null'),
            $result['battery'] ?? 'null'
        );

        $nullIterations++;
    }
}

echo "  完了\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 5: オフライン応答
// すべてのフィールドが null / false になること
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 5: オフライン応答\n";

for ($i = 0; $i < $iterations; $i++) {
    $deviceId = randomDeviceId();
    $client->injectOfflineResponse();
    $result = $client->getMeterStatus($deviceId);

    assert_that($result['success'] === true,  "P5-a [{$i}] オフライン: success === true",  $result['success'] ?? null);
    assert_that($result['online']  === false, "P5-b [{$i}] オフライン: online === false", $result['online']  ?? null);
    assert_that($result['temperature'] === null, "P5-c [{$i}] オフライン: temperature === null", $result['temperature'] ?? 'not-null');
    assert_that($result['humidity']    === null, "P5-d [{$i}] オフライン: humidity === null",    $result['humidity']    ?? 'not-null');
    assert_that($result['battery']     === null, "P5-e [{$i}] オフライン: battery === null",     $result['battery']     ?? 'not-null');
    assert_that($result['deviceId'] === $deviceId, "P5-f [{$i}] オフライン: deviceId が保持される", $result['deviceId'] ?? null);
    assert_that(!array_key_exists('error', $result), "P5-g [{$i}] オフライン: error キーは存在しない");
}

echo "  完了\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 6: エラー応答
// success === false, error キーが文字列, 数値フィールドが null になること
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 6: エラー応答\n";

$errorMessages = [
    '通信エラーが発生しました。ネットワーク接続を確認してください。',
    '認証エラー: config.php の SWITCHBOT_TOKEN / SWITCHBOT_SECRET を確認してください。',
    'HTTPエラーが発生しました (HTTP 500)',
    'APIレスポンスの解析に失敗しました。',
    'SwitchBot APIエラー (code: 160)',
];

for ($i = 0; $i < $iterations; $i++) {
    $deviceId    = randomDeviceId();
    $errorMsg    = $errorMessages[$i % count($errorMessages)];
    $client->injectErrorResponse($errorMsg);
    $result = $client->getMeterStatus($deviceId);

    assert_that($result['success'] === false, "P6-a [{$i}] エラー: success === false",   $result['success'] ?? null);
    assert_that($result['online']  === false, "P6-b [{$i}] エラー: online === false",    $result['online']  ?? null);
    assert_that(is_string($result['error'] ?? null), "P6-c [{$i}] エラー: error は string", $result['error'] ?? null);
    assert_that($result['error'] === $errorMsg,  "P6-d [{$i}] エラー: error メッセージが保持される", $result['error'] ?? null);
    assert_that($result['temperature'] === null, "P6-e [{$i}] エラー: temperature === null", $result['temperature'] ?? 'not-null');
    assert_that($result['humidity']    === null, "P6-f [{$i}] エラー: humidity === null",    $result['humidity']    ?? 'not-null');
    assert_that($result['battery']     === null, "P6-g [{$i}] エラー: battery === null",     $result['battery']     ?? 'not-null');
    assert_that($result['deviceId'] === $deviceId, "P6-h [{$i}] エラー: deviceId が保持される", $result['deviceId'] ?? null);
}

echo "  完了\n\n";

// ────────────────────────────────────────────────────────────────────────────
// プロパティ 7: 戻り値の構造不変条件
// getMeterStatus() は常に必須キーをすべて含んでいること
// ────────────────────────────────────────────────────────────────────────────
echo "■ プロパティ 7: 戻り値の構造不変条件\n";

$requiredKeys  = ['success', 'online', 'deviceId', 'temperature', 'humidity', 'battery', 'deviceType'];
$scenarios     = ['online', 'offline', 'error'];

for ($i = 0; $i < $iterations; $i++) {
    $deviceId = randomDeviceId();
    $scenario = $scenarios[$i % count($scenarios)];

    match ($scenario) {
        'online'  => $client->injectOnlineResponse([
            'deviceId'    => $deviceId,
            'deviceType'  => randomDeviceType(),
            'temperature' => randomTemperature(),
            'humidity'    => randomHumidity(),
            'battery'     => randomBattery(),
        ]),
        'offline' => $client->injectOfflineResponse(),
        'error'   => $client->injectErrorResponse('テストエラー'),
    };

    $result = $client->getMeterStatus($deviceId);

    foreach ($requiredKeys as $key) {
        assert_that(
            array_key_exists($key, $result),
            "P7-a [{$i}] シナリオ={$scenario}: キー '{$key}' が存在する"
        );
    }
}

echo "  完了\n\n";

// ─── 最終レポート ────────────────────────────────────────────────────────────

$total = $report['passed'] + $report['failed'];
echo str_repeat('─', 60) . "\n";
echo "テスト結果: {$report['passed']} / {$total} パス\n";

if ($report['failed'] > 0) {
    echo "\n失敗したアサーション ({$report['failed']} 件):\n";
    foreach ($report['errors'] as $err) {
        echo "  {$err}\n";
    }
    echo "\n";
    exit(1);
}

echo "すべてのアサーションがパスしました。\n";
exit(0);
