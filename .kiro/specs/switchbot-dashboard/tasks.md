# 実装タスク — SwitchBot PHP ダッシュボード

## 前提条件

- SSH アクセスなし、FTP/SFTP によるファイルアップロードのみ
- `composer` コマンド実行不可 → オートローダーは自前実装
- 全ファイルをレンタルサーバーの公開領域（`public_html/` 等）に配置する

---

## タスク一覧

---

### Task 1: プロジェクト基盤セットアップ

**依存**: なし

- [ ] 1-1. ディレクトリ構造を作成する
  ```
  assets/css/
  assets/js/
  api/
  src/Api/
  src/Config/
  src/Helper/
  templates/
  cache/
  ```
- [ ] 1-2. `cache/.gitkeep` を作成する（空ディレクトリをリポジトリに含めるため）
- [ ] 1-3. `.gitignore` を更新する（`config.php`・`cache/*.json` を除外）
- [ ] 1-4. `config.example.php` を作成する（設定テンプレート）

**完了条件**: ディレクトリ構造が設計書通りに作成されていること

---

### Task 2: 設定ファイルの作成

**依存**: Task 1

- [ ] 2-1. `config.php` を `config.example.php` からコピーして作成し、実際の値を記入する
- [ ] 2-2. `.htaccess` を作成して `config.php` と `cache/` への直接 HTTP アクセスを拒否する
  ```apache
  <Files "config.php">
      Require all denied
  </Files>
  <FilesMatch "\.json$">
      Require all denied
  </FilesMatch>
  ```

**完了条件**: `http://example.com/config.php` にアクセスすると 403 が返ること

---

### Task 3: 自前クラスローダーの実装

**依存**: Task 1

- [ ] 3-1. `src/autoload.php` を実装する
  - `spl_autoload_register` で `SwitchBot\` 名前空間を `src/` にマッピング
  - `composer` 不要で PSR-4 風の自動ロードを実現

**完了条件**: `require 'src/autoload.php'` 後に各クラスが `new` できること

---

### Task 4: 設定管理クラスの実装

**依存**: Task 2, Task 3

- [ ] 4-1. `src/Config/Config.php` を実装する
  - `.env` パースではなく PHP 定数（`define`）から値を取得する
  - `getToken()` / `getSecret()` / `getCacheTtl()` / `getCacheDir()` / `getTimezone()`
  - 未設定（デフォルト値のまま）の場合は `RuntimeException` をスロー

**完了条件**: `config.php` の値が正しく読み込めること、未設定時に例外が出ること

---

### Task 5: 認証ヘルパーの実装

**依存**: Task 4

- [ ] 5-1. `src/Helper/Auth.php` を実装する
  - UUID v4 生成（`random_bytes()` 使用）
  - HMAC-SHA256 署名生成
  - `generateHeaders()` で全認証ヘッダーを一括生成

**完了条件**: 生成されたヘッダーで SwitchBot API に接続できること

---

### Task 6: API クライアントの実装

**依存**: Task 5

- [ ] 6-1. `src/Api/SwitchBotClient.php` の `request()` メソッドを実装する
  - cURL で GET/POST リクエスト送信
  - JSON デコードとエラーチェック
- [ ] 6-2. `getDevices()` メソッドを実装する
- [ ] 6-3. `getDeviceStatus()` メソッドを実装する
- [ ] 6-4. ファイルキャッシュ機能を実装する（`CACHE_DIR` / `CACHE_TTL`）
  - キャッシュディレクトリが存在しない場合は `mkdir()` で自動作成
  - 書き込み失敗時はキャッシュなしで動作継続（例外を出さない）

**完了条件**: `getDevices()` がデバイス配列を返すこと、`getDeviceStatus()` が温度・湿度を返すこと

---

### Task 7: Ajax API エンドポイントの実装

**依存**: Task 6

- [ ] 7-1. `api/devices.php` を実装する
  - `require '../config.php'` と `require '../src/autoload.php'` で依存を解決
  - 温湿度計デバイスのフィルタリング（定数 `METER_DEVICE_TYPES`）
  - 各デバイスのステータス取得と JSON レスポンス組み立て
  - エラーレスポンスの統一フォーマット対応

**完了条件**: ブラウザで `/api/devices.php` にアクセスすると正常な JSON が返ること

---

### Task 8: HTML テンプレート・レイアウトの実装

**依存**: Task 1

- [ ] 8-1. `templates/layout.php` を実装する（共通 head/body 構造）
- [ ] 8-2. `templates/dashboard.php` を実装する（カード一覧の初期 HTML）
- [ ] 8-3. `index.php` を実装する
  - `require 'config.php'` と `require 'src/autoload.php'` を冒頭に配置
  - テンプレートをインクルードして HTML を出力

**完了条件**: ブラウザでページが表示されること（データなし状態でもレイアウトが崩れないこと）

---

### Task 9: CSS スタイリング

**依存**: Task 8

- [ ] 9-1. `assets/css/style.css` を実装する
  - CSS カスタムプロパティでカラーパレット定義
  - デバイスカードのグリッドレイアウト
  - 温度・湿度の大きな数値表示
  - ローディングスピナー
  - レスポンシブ対応（モバイル: 1列、タブレット以上: 2〜3列）

**完了条件**: モバイル（375px）〜デスクトップ（1280px）でレイアウトが崩れないこと

---

### Task 10: JavaScript フロントエンド実装

**依存**: Task 7, Task 8

- [ ] 10-1. `assets/js/dashboard.js` を実装する
  - `fetchDevices()`: `/api/devices.php` を fetch API で呼び出す
  - `renderDevices(data)`: デバイスカードを動的に生成・更新
  - `showLoading(visible)`: ローディングインジケータ制御
  - `showError(message)`: エラーバナー表示
  - ページロード時に自動実行
- [ ] 10-2. 「更新」ボタンのクリックイベントを実装する

**完了条件**: ページロード時にデータが表示され、更新ボタンで再取得できること

---

### Task 11: エラーハンドリングの強化

**依存**: Task 6, Task 7, Task 10

- [ ] 11-1. API 401 エラー時のメッセージ表示を実装する
- [ ] 11-2. デバイスオフライン（statusCode 190）時のカード表示を実装する
- [ ] 11-3. cURL タイムアウト設定を追加する（接続: 5秒、読み込み: 10秒）
- [ ] 11-4. `error_log()` によるサーバーサイドログ出力を実装する
- [ ] 11-5. `cache/` ディレクトリへの書き込み失敗時のフォールバック処理を実装する

**完了条件**: 各エラーシナリオで適切なメッセージが表示されること

---

### Task 12: FTP デプロイ準備・ドキュメント整備

**依存**: Task 1〜11 すべて

- [ ] 12-1. `README.md` を更新する
  - FTP によるデプロイ手順（ファイル一覧 → アップロード → パーミッション設定）
  - `config.php` の設定方法（SwitchBot トークン取得手順含む）
  - `cache/` ディレクトリのパーミッション設定方法（755/777）
  - PHP バージョンの確認・変更方法（コントロールパネルでの操作）
- [ ] 12-2. `config.example.php` の内容を最終確認する
- [ ] 12-3. アップロードするファイル一覧をまとめた `DEPLOY.md` を作成する
  - `config.php` は除外し `config.example.php` をコピーして使う手順を明記

**完了条件**: README の手順通りに FTP でアップロードすれば誰でも動かせること

---

## 優先度まとめ

| 優先度 | タスク | 備考 |
|--------|--------|------|
| 高 | Task 1, 2, 3 | 基盤（ローダー・設定・アクセス保護） |
| 高 | Task 4, 5, 6 | コア機能（API 接続） |
| 高 | Task 7, 10 | データ表示 |
| 中 | Task 8, 9 | UI/UX |
| 中 | Task 11 | エラー耐性 |
| 低 | Task 12 | デプロイドキュメント |
