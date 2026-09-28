<?php

declare(strict_types=1);

namespace SwitchBot\Api;

use SwitchBot\Helper\Auth;

/**
 * SwitchBot OpenAPI v1.1 クライアント
 *
 * 認証ヘッダーの付与・cURL リクエスト・レスポンス検証・キャッシュを一括管理する。
 * エラーは例外を投げず配列で返すため、呼び出し元は `['error']` キーで判定する。
 */
class SwitchBotClient
{
    private const BASE_URL = 'https://api.switch-bot.com/v1.1';

    /**
     * 温湿度計として扱う deviceType 一覧
     *
     * @var list<string>
     */
    public const METER_DEVICE_TYPES = [
        'Meter',        // SwitchBot 温湿度計
        'MeterPlus',    // SwitchBot 温湿度計プラス
        'MeterPro',     // SwitchBot 温湿度計プロ
        'MeterProCO2',  // SwitchBot 温湿度計プロ (CO2)
        'WoIOSensor',   // SwitchBot アウトドア温湿度計
        'Hub2',         // SwitchBot Hub 2（温湿度センサー内蔵）
    ];

    private bool $cacheEnabled;

    public function __construct(
        private readonly Auth   $auth,
        private readonly int    $cacheTtl = 60,
        private readonly string $cacheDir = ''
    ) {
        $this->cacheEnabled = $this->initCacheDir();
    }

    // =========================================================
    // Public API
    // =========================================================

    /**
     * アカウントに登録されたデバイス一覧を取得する
     *
     * @return array{success: bool, devices?: list<array<string, mixed>>, error?: string}
     */
    public function getDevices(): array
    {
        $result = $this->request('GET', '/devices');
        if (isset($result['error'])) {
            return $result;
        }

        return [
            'success' => true,
            'devices' => $result['body']['deviceList'] ?? [],
        ];
    }

    /**
     * 指定デバイスの生ステータスを取得する（汎用・キャッシュあり）
     *
     * @return array{success: bool, online?: bool, body?: array<string, mixed>, error?: string}
     */
    public function getDeviceStatus(string $deviceId): array
    {
        $cached = $this->readCache($deviceId);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->request('GET', "/devices/{$deviceId}/status");

        if (isset($result['error'])) {
            return $result;
        }

        // statusCode 190 はデバイスオフライン（エラーではない）
        if (($result['statusCode'] ?? 0) === 190) {
            return ['success' => true, 'online' => false, 'body' => []];
        }

        $response = ['success' => true, 'online' => true, 'body' => $result['body'] ?? []];
        $this->writeCache($deviceId, $response);

        return $response;
    }

    /**
     * 特定の温湿度計デバイスから温度・湿度・バッテリーを取得する
     *
     * getDeviceStatus() のラッパーで、温湿度に特化した構造化データを返す。
     * デバイス ID を直接指定することで、デバイス一覧取得を省略できる。
     *
     * @param  string $deviceId  SwitchBot デバイス ID（例: "C271111EC0AB"）
     * @return array{
     *     success: bool,
     *     online: bool,
     *     deviceId: string,
     *     temperature: float|null,
     *     humidity: int|null,
     *     battery: int|null,
     *     deviceType: string,
     *     error?: string
     * }
     */
    public function getMeterStatus(string $deviceId): array
    {
        $result = $this->getDeviceStatus($deviceId);

        // エラー時
        if (isset($result['error'])) {
            return [
                'success'     => false,
                'online'      => false,
                'deviceId'    => $deviceId,
                'temperature' => null,
                'humidity'    => null,
                'battery'     => null,
                'deviceType'  => '',
                'error'       => $result['error'],
            ];
        }

        // オフライン時
        if (!($result['online'] ?? false)) {
            return [
                'success'     => true,
                'online'      => false,
                'deviceId'    => $deviceId,
                'temperature' => null,
                'humidity'    => null,
                'battery'     => null,
                'deviceType'  => '',
            ];
        }

        $body = $result['body'] ?? [];

        // temperature は float、humidity / battery は int として正規化する
        $temperature = isset($body['temperature']) ? (float)$body['temperature'] : null;
        $humidity    = isset($body['humidity'])    ? (int)$body['humidity']       : null;
        $battery     = isset($body['battery'])     ? (int)$body['battery']        : null;

        return [
            'success'     => true,
            'online'      => true,
            'deviceId'    => $deviceId,
            'temperature' => $temperature,
            'humidity'    => $humidity,
            'battery'     => $battery,
            'deviceType'  => (string)($body['deviceType'] ?? ''),
        ];
    }

    // =========================================================
    // HTTP リクエスト
    // =========================================================

    /**
     * SwitchBot API に HTTP リクエストを送信し、デコード済み配列を返す
     *
     * cURL エラー・HTTP エラー・JSON パースエラー・API エラーコードを
     * すべて配列の `error` キーに統一して返す。例外は投げない。
     *
     * @param  array<string, mixed> $body  POST ボディ（GET 時は空）
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url         = self::BASE_URL . $path;
        $headers     = $this->auth->generateHeaders();
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        // ① cURL 自体のエラー（接続失敗・タイムアウト等）
        if ($curlError !== '') {
            error_log("[SwitchBotClient] cURL エラー ({$path}): {$curlError}");
            return ['error' => '通信エラーが発生しました。ネットワーク接続を確認してください。'];
        }

        // ② HTTP 401: 認証失敗
        if ($httpCode === 401) {
            return [
                'error' => '認証エラー: config.php の SWITCHBOT_TOKEN / SWITCHBOT_SECRET を確認してください。',
                'code'  => 401,
            ];
        }

        // ③ その他の HTTP エラー
        if ($httpCode >= 400) {
            error_log("[SwitchBotClient] HTTP {$httpCode} ({$path})");
            return ['error' => "HTTPエラーが発生しました (HTTP {$httpCode})", 'code' => $httpCode];
        }

        // ④ JSON パース失敗
        $decoded = json_decode((string)$responseBody, true);
        if (!is_array($decoded)) {
            error_log("[SwitchBotClient] JSON デコード失敗 ({$path}): {$responseBody}");
            return ['error' => 'APIレスポンスの解析に失敗しました。'];
        }

        // ⑤ SwitchBot API レベルのエラーコード
        $statusCode = (int)($decoded['statusCode'] ?? 0);
        if ($statusCode !== 100 && $statusCode !== 190) {
            error_log("[SwitchBotClient] APIエラー ({$path}) statusCode={$statusCode}");
            return ['error' => "SwitchBot APIエラー (code: {$statusCode})", 'code' => $statusCode];
        }

        return $decoded;
    }

    // =========================================================
    // ファイルキャッシュ
    // =========================================================

    /**
     * キャッシュディレクトリを初期化する
     * 失敗してもキャッシュなしで動作継続（例外を出さない）
     */
    private function initCacheDir(): bool
    {
        if ($this->cacheDir === '' || $this->cacheTtl <= 0) {
            return false;
        }
        if (is_dir($this->cacheDir)) {
            return is_writable($this->cacheDir);
        }
        return @mkdir($this->cacheDir, 0755, true);
    }

    /**
     * キャッシュからデータを読む。TTL 切れ・存在しない場合は null を返す
     *
     * @return array<string, mixed>|null
     */
    private function readCache(string $deviceId): array|null
    {
        if (!$this->cacheEnabled) {
            return null;
        }
        $path = $this->cachePath($deviceId);
        if (!file_exists($path)) {
            return null;
        }
        // TTL チェック
        if ((time() - (int)filemtime($path)) >= $this->cacheTtl) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /**
     * レスポンスをキャッシュに書き込む（失敗しても継続）
     *
     * @param array<string, mixed> $data
     */
    private function writeCache(string $deviceId, array $data): void
    {
        if (!$this->cacheEnabled) {
            return;
        }
        @file_put_contents($this->cachePath($deviceId), json_encode($data));
    }

    /**
     * デバイス ID からキャッシュファイルパスを生成する
     */
    private function cachePath(string $deviceId): string
    {
        return rtrim($this->cacheDir, '/') . '/' . md5($deviceId) . '.json';
    }
}
