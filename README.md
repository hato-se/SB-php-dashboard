# SwitchBot PHP ダッシュボード

SwitchBot 温湿度計のリアルタイムデータを表示する、シンプルな PHP Web アプリ。

**FTP のみのレンタルサーバー（SSH 不可）でそのまま動作します。**  
`composer` や CLI ツールは一切不要です。

## 必要な環境

- PHP 8.1 以上（cURL 拡張が有効であること）
- レンタルサーバーの FTP / SFTP アクセス
- SwitchBot アカウント + 温湿度計（Meter / Meter Plus / Meter Pro 等）

## セットアップ

### 1. 設定ファイルを作成する

`config.example.php` を `config.php` という名前でコピーし、テキストエディタで開きます。

```php
define('SWITCHBOT_TOKEN',  'ここにトークンを貼り付け');
define('SWITCHBOT_SECRET', 'ここにシークレットキーを貼り付け');
```

#### SwitchBot トークンの取得方法

1. SwitchBot アプリ（V9.0 以上）を開く
2. **プロフィール → 設定 → アプリについて** を開く
3. **バージョン番号を 10 回タップ** → デベロッパーオプションが表示される
4. **デベロッパーオプション → トークン取得**
5. Token と Secret Key をコピーして `config.php` に貼り付ける

### 2. ファイルを FTP でアップロードする

リポジトリのフォルダ構造をそのままサーバーの **公開領域**（`public_html/` 等）にアップロードします。

```
【リポジトリ側】              →   【サーバー公開領域】

config.php                   →   public_html/config.php
.htaccess                    →   public_html/.htaccess
public/                      →   public_html/public/
  index.php                  →     public_html/public/index.php
  api/devices.php            →     public_html/public/api/devices.php
  assets/css/style.css       →     public_html/public/assets/css/style.css
  assets/js/dashboard.js     →     public_html/public/assets/js/dashboard.js
src/                         →   public_html/src/
templates/                   →   public_html/templates/
cache/                       →   public_html/cache/
```

サーバーの **ドキュメントルート** を `public_html/public/` に設定すると、
`index.php` がトップページとして機能します（さくらインターネット等はコントロールパネルで変更可能）。

ドキュメントルートを変更できないサーバーでは、`public/` の中身（`index.php` / `api/` / `assets/`）を
`public_html/` 直下に展開してください。その場合、`config.php`・`src/`・`templates/`・`cache/` も
`public_html/` に置いておけば各 PHP ファイル内のパス参照がそのまま機能します。

> ⚠ `config.php` は事前にローカルで作成・編集してからアップロードしてください。  
> ⚠ `config.example.php`・`.kiro/`・`README.md` 等の開発用ファイルはアップロード不要です。

### 3. `cache/` ディレクトリのパーミッションを設定する

FTP クライアントで `cache/` ディレクトリを右クリック →「パーミッション変更」で
**755** または **777** に設定してください。

> キャッシュが使えない場合でも動作しますが、API の呼び出し頻度が増えます。

### 4. 動作確認

ブラウザでサーバーの URL にアクセスすると温湿度データが表示されます。

---

## ローカル開発（PHP ビルトインサーバー）

```bash
# 1. config.php を作成
cp config.example.php config.php
# config.php を編集してトークンを記入

# 2. リポジトリルートから public/ をドキュメントルートとして起動
php -S localhost:8080 -t public/
```

ブラウザで `http://localhost:8080` を開きます。

> `public/` をドキュメントルートにすることで、`index.php` や `api/devices.php` などの  
> パスが正しく解決されます。リポジトリルート（`config.php` がある階層）から実行してください。

---

## ディレクトリ構成

```
switchbot-php-dashboard/         ← リポジトリルート
├── config.php                   # ★設定ファイル（gitignore 対象）
├── config.example.php           # 設定テンプレート
├── .htaccess                    # config.php / cache/ を外部アクセスから保護
├── public/                      # ← ドキュメントルートに設定する
│   ├── index.php                #   ダッシュボード エントリポイント
│   ├── api/
│   │   └── devices.php          #   Ajax API エンドポイント（JSON 返却）
│   └── assets/
│       ├── css/style.css
│       └── js/dashboard.js
├── src/
│   ├── autoload.php             # 自前クラスローダー（composer 不要）
│   ├── Api/SwitchBotClient.php  # SwitchBot API クライアント
│   ├── Config/Config.php        # 設定管理
│   └── Helper/Auth.php          # HMAC-SHA256 署名生成
├── templates/
│   ├── layout.php
│   └── dashboard.php
└── cache/                       # API レスポンスキャッシュ（755/777 権限が必要）
```

## セキュリティ注意事項

- `config.php` は `.gitignore` に含まれています。**絶対にコミットしないでください。**
- `.htaccess` が `config.php` への直接 HTTP アクセスを遮断しています。
- 本番環境では HTTPS を使用してください。
- SwitchBot API は **個人利用のみ**対応です（1日 10,000 回上限）。

## 参考

- [SwitchBot OpenAPI v1.1 ドキュメント](https://github.com/OpenWonderLabs/SwitchBotAPI)
