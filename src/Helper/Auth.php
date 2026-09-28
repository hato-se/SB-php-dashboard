<?php

declare(strict_types=1);

namespace SwitchBot\Helper;

/**
 * SwitchBot OpenAPI v1.1 の認証ヘッダーを生成する
 *
 * 署名アルゴリズム:
 *   data = token + timestamp(13桁ms) + nonce(UUID v4)
 *   sign = strtoupper(base64_encode(hash_hmac('sha256', data, secret, true)))
 */
class Auth
{
    public function __construct(
        private readonly string $token,
        private readonly string $secret
    ) {}

    /**
     * API リクエストに必要な全認証ヘッダーを生成して返す
     *
     * @return array<string, string>
     */
    public function generateHeaders(): array
    {
        $timestamp = (int)(microtime(true) * 1000); // 13桁ミリ秒タイムスタンプ
        $nonce     = $this->generateNonce();
        $sign      = $this->generateSign($timestamp, $nonce);

        return [
            'Authorization' => $this->token,
            'sign'          => $sign,
            't'             => (string)$timestamp,
            'nonce'         => $nonce,
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * UUID v4 を生成する
     */
    public function generateNonce(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // バージョン: 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // バリアント: RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * HMAC-SHA256 署名を生成する
     */
    public function generateSign(int $timestamp, string $nonce): string
    {
        $data = $this->token . $timestamp . $nonce;
        return strtoupper(
            base64_encode(
                hash_hmac('sha256', $data, $this->secret, true)
            )
        );
    }
}
