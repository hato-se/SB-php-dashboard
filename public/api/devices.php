<?php

declare(strict_types=1);

// ルートから2階層下（public/api/）なので dirname を2回遡る
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/autoload.php';

use SwitchBot\Api\SwitchBotClient;
use SwitchBot\Config\Config;
use SwitchBot\Helper\Auth;

header('Content-Type: application/json; charset=utf-8');
// ブラウザ・プロキシにキャッシュさせない（常に最新データを返す）
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $config = new Config();
    date_default_timezone_set($config->getTimezone());

    ini_set('display_errors', $config->isDebugMode() ? '1' : '0');
    error_reporting($config->isDebugMode() ? E_ALL : 0);

    $auth   = new Auth($config->getToken(), $config->getSecret());
    $client = new SwitchBotClient($auth, $config->getCacheTtl(), $config->getCacheDir());

    // ── Step 1: デバイス一覧を取得 ──────────────────────────────
    $devicesResult = $client->getDevices();
    if (!($devicesResult['success'] ?? false)) {
        echo json_encode([
            'success' => false,
            'error'   => $devicesResult['error'] ?? 'デバイス一覧の取得に失敗しました。',
        ]);
        exit;
    }

    // ── Step 2: 温湿度計だけフィルタリング ──────────────────────
    $meterDevices = array_values(array_filter(
        $devicesResult['devices'] ?? [],
        static fn(array $d): bool =>
            in_array($d['deviceType'] ?? '', SwitchBotClient::METER_DEVICE_TYPES, true)
    ));

    if (count($meterDevices) === 0) {
        echo json_encode([
            'success'   => true,
            'devices'   => [],
            'updatedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ]);
        exit;
    }

    // ── Step 3: 各デバイスのステータスを getMeterStatus() で取得 ─
    $output = [];
    foreach ($meterDevices as $device) {
        $deviceId = (string)($device['deviceId'] ?? '');

        // getMeterStatus() は温度・湿度・バッテリーを型付きで返す
        $meter = $client->getMeterStatus($deviceId);

        $entry = [
            'deviceId'   => $deviceId,
            // XSS 対策: HTML に埋め込む可能性があるため必ずエスケープ
            'deviceName' => htmlspecialchars(
                (string)($device['deviceName'] ?? '不明'),
                ENT_QUOTES,
                'UTF-8'
            ),
            'deviceType' => htmlspecialchars(
                (string)($device['deviceType'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ),
            'online'      => $meter['online'],
            'temperature' => $meter['temperature'],
            'humidity'    => $meter['humidity'],
            'battery'     => $meter['battery'],
        ];

        // エラーメッセージがあれば含める（デバッグ用途）
        if (isset($meter['error'])) {
            $entry['error'] = $meter['error'];
        }

        $output[] = $entry;
    }

    echo json_encode([
        'success'   => true,
        'updatedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'devices'   => $output,
    ]);

} catch (\RuntimeException $e) {
    // 設定エラー（トークン未設定・config.php 未配置など）
    error_log('[devices.php] 設定エラー: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
} catch (\Throwable $e) {
    // 予期しないエラー — 詳細はログのみ、ユーザーには汎用メッセージ
    error_log('[devices.php] 予期しないエラー: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'サーバー内部エラーが発生しました。',
    ]);
}
