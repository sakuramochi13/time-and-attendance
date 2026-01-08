# COACHTECH time-and-attendance （勤怠管理）

概要：

- ある企業が開発した独自の勤怠管理アプリです。

## 環境構築

### 前提条件

- Docker Desktop がインストールされていること
- Git がインストールされていること

### 1.リポジトリのクローン

```bash
git clone git@github.com:sakuramochi13/time-and-attendance.git
cd time-and-attendance
```

### 2.Docker コンテナの起動

```bash
docker-compose up -d --build
```

起動するサービス：

- nginx : Web サーバ (ポート 80)
- php : Laravel アプリケーション用コンテナ
- mysql : MySQL 8.0.33
- phpmyadmin : DB 管理ツール (ポート 8080)
- mailhog : メール動作確認用 (ポート 8025)
  本リポジトリの docker-compose.yml では、Apple シリコン(M1/M2/M3/M4) 対応のため
  mysql サービスにあらかじめ platform: linux/amd64 を指定しています。

### 3.Laravel セットアップ

```bash
docker-compose exec php bash
```

プロジェクトディレクトリへ移動：

```bash
cd /var/www
```

### 3-1. Composer インストール

```bash
composer install
```

### 3-2. 環境変数ファイルを作成

```bash
cp .env.example .env
```

.env の DB 接続情報は以下を使用：

```bash
 ・DB_CONNECTION=mysql
 ・DB_HOST=mysql
 ・DB_PORT=3306
 ・DB_DATABASE=laravel_db
 ・DB_USERNAME=laravel_user
 ・DB_PASSWORD=laravel_pass
```

### 3-3. アプリケーションキー作成

```bash
php artisan key:generate
```

### 4. マイグレーション & シーディング

```bash
php artisan migrate
php artisan db:seed
```

## 使用技術(実行環境)

- PHP 8.1.33
- Laravel 8.83.8
- MySQL 8.0.33
- nginx 1.21.1
- phpMyAdmin (latest)
- MailHog v1.0.1
- Docker / docker-compose

## ER 図

![ER図](images/er-TandA.drawio.png)

## URL 一覧

- 開発環境:http://localhost/
- ユーザー登録:http://localhost/register
- phpMyAdmin:http://localhost:8080/
- MailHog (メール確認): http://localhost:8025

## メール認証について

- MailHog を使用しています。
- 認証誘導画面の「認証はこちらから」ボタンを押下し、
  MailHog に届いたメール内のリンクから認証を完了してください。

## ログイン情報

管理者ユーザーのテストアカウントは以下の通りです。

- **メールアドレス**：tetsuko.k@coachtech.com
- **パスワード**：password123

一般ユーザーのテストアカウントは以下の通りです。

- **メールアドレス**：reina.n@coachtech.com
- **パスワード**：password123
