<?php

declare(strict_types=1);

/**
 * 自前 PSR-4 風クラスローダー
 *
 * SwitchBot\ 名前空間を src/ ディレクトリにマッピングする。
 * composer を使用しないレンタルサーバー環境向け。
 *
 * 例: SwitchBot\Api\SwitchBotClient → src/Api/SwitchBotClient.php
 */
spl_autoload_register(function (string $class): void {
    $prefix  = 'SwitchBot\\';
    // __DIR__ は src/ ディレクトリ自身を指すため、
    // SwitchBot\Config\Config → src/Config/Config.php となるよう
    // $baseDir を src/ の中（= __DIR__ + '/'）に設定する
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    // 'SwitchBot\Config\Config' → 'Config/Config'
    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
