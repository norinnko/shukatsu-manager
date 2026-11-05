# 就活マネージャー

選考・予定・ESをひとつの場所で管理する、自分専用の就活管理アプリです。

- **ダッシュボード**：期限切れ / 今日 / 2週間以内の予定、結果待ち、第一志望、選考状況
- **企業**：カンバン（ドラッグで段階移動）とリスト。マイページURL・ログインID・メモ
- **カレンダー**：月表示＋日別一覧。予定はGoogleカレンダーへ自動登録（任意）
- **ES回答**：設問と回答のライブラリ。文字数カウント、コピー、別企業用に複製
- **基本情報**：学歴・資格を各社マイページの形式（`2024年4月` / `2024/04` など）でワンタップコピー

> マイページの**パスワードは保存しません**。パスワードマネージャーで管理してください。

## 技術構成

| 役割 | 技術 |
|---|---|
| バックエンド | Laravel 13（PHP 8.4） |
| DB | MySQL / MariaDB |
| 画面 | React 19 + TypeScript + Tailwind（Inertia.js） |
| ログイン | Google（`ALLOWED_LOGIN_EMAILS` のアカウントのみ） |
| 外部連携 | Google Calendar API（段階2で Gmail API・LINE を追加予定） |

## ローカル開発（Windows）

前提：PHP 8.4（`%USERPROFILE%\tools\php84`）、Composer、Node.js、XAMPP の MySQL。

```bash
# 1. MySQL を起動（XAMPP コントロールパネル、またはコマンド）
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone

# 2. 初回のみ
composer install
npm install
cp .env.example .env          # DB_* / ALLOWED_LOGIN_EMAILS を設定
php artisan key:generate
php artisan migrate --seed    # database/seed-data/shukatsu.json があれば取り込む

# 3. 起動
npm run build                 # または開発中は npm run dev
php artisan serve             # http://localhost:8000
```

Google OAuth を設定するまでは `.env` の `DEV_LOGIN_ENABLED=true` で「開発用ログイン」ボタンが使えます（`APP_ENV=local` のときだけ有効）。

### Smart App Control について

このPCでは Windows の Smart App Control が有効で、PHP 8.4 の `php_zip.dll` / `php_sqlite3.dll` がブロックされます。そのため Composer は XAMPP の PHP 8.2 で動かし（`%USERPROFILE%\tools\composer\composer.bat`）、`composer.json` の `config.platform.php` を 8.4 に固定しています。アプリ本体・artisan・テストは PHP 8.4 で動きます。

### テスト

```bash
php artisan test
npx tsc --noEmit
```

## 初期データ

`database/seed-data/shukatsu.json` に個人データ（企業・予定・ES回答・学歴・資格）を置くと `php artisan db:seed` または `php artisan shukatsu:import` で取り込みます。形式は `shukatsu.example.json` を参照してください。

- 既にあるデータは上書きしないので、何度実行しても安全です
- 過去の日付の予定は「完了」として取り込みます
- **このファイルは `.gitignore` 済み**です。コミットしないでください

## Google Cloud の設定

1. [Google Cloud Console](https://console.cloud.google.com/) で新しいプロジェクトを作成
2. 「APIとサービス」→「ライブラリ」で **Google Calendar API** を有効化
3. 「Google Auth Platform」（OAuth同意画面）
   - ユーザーの種類：外部
   - アプリ名・サポートメールを入力
   - スコープ：`.../auth/calendar.events`
   - **公開ステータスを「本番環境」にする**（「テスト」のままだとリフレッシュトークンが7日で失効します。自分専用なので審査は不要で、ログイン時に「未確認のアプリ」と出たら「詳細」→「移動」で進めます）
4. 「認証情報」→「OAuthクライアントID」→ 種類「ウェブアプリケーション」
   - 承認済みリダイレクトURI（ローカルと本番の両方）
     - `http://localhost:8000/auth/google/callback`
     - `http://localhost:8000/settings/google/callback`
     - `https://<本番ドメイン>/auth/google/callback`
     - `https://<本番ドメイン>/settings/google/callback`
5. `.env` に `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` を設定
6. アプリで「Googleでログイン」→「設定 → 連携 → Googleカレンダーと連携」

## コアサーバー（V2）への公開（2026-10-01 公開済み：https://shukatsu.norinko.jp）

サーバー上の構成（ユーザー `<user>`、`<server>`、SSH鍵でログイン可）

| 項目 | 場所・内容 |
|---|---|
| アプリ本体 | `~/domains/shukatsu.norinko.jp/shukatsu-app` |
| 公開フォルダー | `~/domains/shukatsu.norinko.jp/public_html` → `shukatsu-app/public` へのシンボリックリンク（元の初期ページは `public_html.default` に退避） |
| PHP（Web） | `public/.htaccess` 先頭の `AddHandler x-httpd-php84 .php` で 8.4 を使う（拡張は PHP Selector の php8.4 設定） |
| PHP（コマンド） | `~/bin/php84`（`/opt/alt/php84` に Laravel 用の拡張を読み込ませるラッパー）。既定の `php` は 7.4 なので使わない |
| DB | MariaDB 10.6、DB・ユーザーとも `<db_name>`（パスワードはサーバーの `.env` のみ） |
| cron | `* * * * * cd …/shukatsu-app && PHP_BINARY=/home/<user>/bin/php84 /home/<user>/bin/php84 artisan schedule:run`（`PHP_BINARY` が無いとスケジューラーの子プロセスが拡張なしのPHPで動く） |

### 更新のしかた（手元のPCから）

```bash
npm run build
tar --force-local -czf app.tar.gz --exclude=./node_modules --exclude=./vendor --exclude=./.env --exclude=./.claude --exclude=./tests --exclude=./public/hot --exclude=./database/seed-data/shukatsu.json --exclude=./storage/inertia-devtools --exclude='./storage/logs/*' --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' --exclude='./storage/framework/cache/data/*' --exclude='./storage/app/*' --exclude='./bootstrap/cache/*.php' --exclude=./HANDOFF.md .
scp app.tar.gz <user>@<server>:tmp/
```

サーバーで：

```bash
cd ~/domains/shukatsu.norinko.jp/shukatsu-app
tar -xzf ~/tmp/app.tar.gz && rm ~/tmp/app.tar.gz
~/bin/php84 ~/bin/composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
~/bin/php84 artisan package:discover
~/bin/php84 artisan migrate --force
~/bin/php84 artisan optimize
```

注意：
- `storage/inertia-devtools`（手元の画面データ。メール本文などを含む）と `bootstrap/cache/*.php`（開発用パッケージの一覧）は絶対にアップロードしない
- `crontab` を編集するときは、先に `crontab -l > ~/tmp/crontab.backup` を取り、ファイルを作ってから `crontab ファイル名` で入れる（パイプで書き換えて全消ししたことがある）
- `php artisan db` はTTYが必要なのでSSH越しのインポートに使えない。`mysql --defaults-extra-file=…` を使う

## ロードマップ

- [x] 段階1：ログイン・基本情報・企業ボード・予定＋Googleカレンダー・ES回答・初期データ
- [ ] 段階2：Gmail取り込み（AIで予定抽出）＋ LINE のボタン承認＋アプリ内受信箱、リマインド
- [ ] 段階3：面接記録・想定質問、ES添削
- [ ] 段階4：通過率の分析、Webテスト・交通費の管理
