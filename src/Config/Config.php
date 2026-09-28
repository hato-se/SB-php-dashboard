<?php

declare(strict_types=1);

namespace SwitchBot\Config;

use RuntimeException;

/**
 * config.php で定義された PHP 定数を読み込み、設定値へのアクセスを提供する
 *
 * .env ファイルのパースは行わない。
 * FTP 経由で config.php を書き換えるだけで設定が反映される。
 */
class Config
{
    /**
     * config.php が読み込まれていることを確認する
     *
     * @throws RuntimeException config.php が require されていない場合
     */
    public function __construct()
    {
        if (!defined('SWITCHBOT_TOKEN')) {
            throw new RuntimeException(
                'config.php が読み込まれていません。' . PHP_EOL
                . 'config.example.php を config.php にコピーして設定してください。'
            );
        }
    }

    /**
     * 定数名で設定値を取得する（汎用）
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return defined($key) ? constant($key) : $default;
    }

    /**
     * SwitchBot Open Token を取得する
     *
     * @throws RuntimeException 未設定またはデフォルト値のままの場合
     */
    public function getToken(): string
    {
        $token = (string)(defined('SWITCHBOT_TOKEN') ? SWITCHBOT_TOKEN : '');
        if ($token === '' || $token === 'your_open_token_here') {
            throw new RuntimeException(
                'config.php の SWITCHBOT_TOKEN が設定されていません。' . PHP_EOL
                . 'SwitchBot アプリのデベロッパーオプションからトークンを取得してください。'
            );
        }
        return $token;
    }

    /**
     * SwitchBot Secret Key を取得する
     *
     * @throws RuntimeException 未設定またはデフォルト値のままの場合
     */
    public function getSecret(): string
    {
        $secret = (string)(defined('SWITCHBOT_SECRET') ? SWITCHBOT_SECRET : '');
        if ($secret === '' || $secret === 'your_secret_key_here') {
            throw new RuntimeException(
                'config.php の SWITCHBOT_SECRET が設定されていません。' . PHP_EOL
                . 'SwitchBot アプリのデベロッパーオプションからシークレットキーを取得してください。'
            );
        }
        return $secret;
    }

    /**
     * キャッシュ TTL（秒）を取得する
     */
    public function getCacheTtl(): int
    {
        return (int)(defined('CACHE_TTL') ? CACHE_TTL : 60);
    }

    /**
     * キャッシュ保存ディレクトリのパスを取得する
     */
    public function getCacheDir(): string
    {
        return (string)(defined('CACHE_DIR') ? CACHE_DIR : sys_get_temp_dir() . '/switchbot_cache');
    }

    /**
     * タイムゾーンを取得する
     */
    public function getTimezone(): string
    {
        return (string)(defined('TIMEZONE') ? TIMEZONE : 'Asia/Tokyo');
    }

    /**
     * デバッグモードの状態を取得する
     */
    public function isDebugMode(): bool
    {
        return (bool)(defined('DEBUG_MODE') ? DEBUG_MODE : false);
    }
}
