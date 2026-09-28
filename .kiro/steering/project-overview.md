# SwitchBot PHP ダッシュボード — プロジェクト概要ステアリング

このステアリングファイルは、すべての Kiro セッションに自動的に読み込まれ、
プロジェクトの背景・技術スタック・コーディング規約を共有します。

## プロジェクト概要

SwitchBot 温湿度計（Meter / Meter Plus 等）のデータを SwitchBot OpenAPI v1.1 経由で
取得し、ブラウザ上のシンプルな PHP ダッシュボードに表示する個人向け Web アプリ。

**対象環境**: SSH アクセスなし・FTP のみのレンタルサーバー（さくらインターネット・ロリポップ等）

## 技術スタック

| レイヤー | 採用技術 |
|--------|---------|
| バックエンド | PHP 8.1 以上 |
| フロントエンド | HTML5 / CSS3 / 素の JavaScript (フレームワークなし) |
| API 通信 | cURL (PHP) |
| 認証 | SwitchBot OpenAPI v1.1 — HMAC-SHA256 署名 |
| 設定管理 | `config.php`（PHP 定数 `define`）— FTP で編集してアップロード |
| クラスローダー | `src/autoload.php`（自前 PSR-4 風ローダー、composer 不要） |
| Web サーバー | Apache（レンタルサーバー標準）または PHP ビルトインサーバー（ローカル開発） |

## ディレクトリ構成

全ファイルをレンタルサーバーの **公開領域**（`public_html/` 等）に直接配置する。

```
public_html/                      ← レンタルサーバーの公開領域ルート
├── index.php                     # ダッシュボードエントリポイント
├── config.php                    # ★設定ファイル（トークン等を記入・gitignore）
├── config.example.php            # 設定テンプレート（リポジトリに含める）
├── .htaccess                     # config.php / cache/ への直接アクセスを拒否
├── api/
│   └── devices.php               # Ajax API エンドポイント
├── assets/
│   ├── css/style.css
│   └── js/dashboard.js
├── src/
│   ├── autoload.php              # 自前クラスローダー（composer 不要）
│   ├── Api/SwitchBotClient.php
│   ├── Config/Config.php
│   └── Helper/Auth.php
├── templates/
│   ├── layout.php
│   └── dashboard.php
└── cache/                        # API キャッシュ（755/777 権限が必要）
    └── .gitkeep
```

## コーディング規約

詳細なルールは **[`php-coding-standards.md`](./php-coding-standards.md)** を参照すること。
以下はそのサマリー。

| ルール | 内容 |
|--------|------|
| 厳密な型指定 | すべての PHP ファイルに `declare(strict_types=1);` を記述する（必須） |
| コーディング規約 | PSR-12 準拠（インデント 4 スペース、型宣言必須、命名規則等） |
| エラーハンドリング | すべての API 呼び出しを try-catch で囲む（必須） |
| 機密情報 | トークン・シークレットキーはコード直書き禁止、`config.php` の定数から読み込む |
| 依存管理 | `composer` / `vendor/` は使用しない |
| コメント言語 | 日本語可（API キーや URL は英語のまま） |

## デプロイ方針

1. `config.example.php` をコピーして `config.php` を作成し、トークンを記入する
2. 全ファイルを FTP クライアントでサーバーにアップロードする
3. `cache/` ディレクトリのパーミッションを 755 または 777 に設定する
4. ブラウザでアクセスして動作確認する

## SwitchBot OpenAPI v1.1 メモ

### ベース URL
```
https://api.switch-bot.com
```

### 認証ヘッダー（全リクエスト必須）
| ヘッダー | 内容 |
|--------|------|
| `Authorization` | Open Token |
| `sign` | `strtoupper(base64_encode(hash_hmac('sha256', token.t.nonce, secret, true)))` |
| `t` | 13桁 Unix ミリ秒タイムスタンプ |
| `nonce` | UUID v4 |

### 主要エンドポイント
| 操作 | メソッド | パス |
|-----|--------|------|
| デバイス一覧取得 | GET | `/v1.1/devices` |
| デバイスステータス取得 | GET | `/v1.1/devices/{deviceId}/status` |

### Meter ステータスレスポンス例
```json
{
  "statusCode": 100,
  "body": {
    "deviceId": "C271111EC0AB",
    "deviceType": "Meter",
    "hubDeviceId": "FA7310762361",
    "humidity": 52,
    "temperature": 26.1
  },
  "message": "success"
}
```

### API 制限
- 1日あたりのコール上限: **10,000 回**
- 個人利用のみ。商用利用には別途申請が必要

## セキュリティ注意事項

- `config.php` を `.gitignore` に必ず追加する（`config.example.php` だけリポジトリに含める）
- API トークン・シークレットキーを公開リポジトリにコミットしない
- `.htaccess` で `config.php` と `cache/` への直接 HTTP アクセスを遮断する
- 本番環境では HTTPS を使用する
- `display_errors` を本番で OFF にする（`config.php` 内で設定可能）
