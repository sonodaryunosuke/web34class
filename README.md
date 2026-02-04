# PHP + Nginx + MySQL (Docker Compose)

このリポジトリは **PHP 8.4 (FPM, Alpineベース) + Nginx + MySQL 8.4** の開発環境を Docker Compose で構築するためのサンプルです。  

---

##  ディレクトリ構成

├── Dockerfile
├── compose.yml
├── nginx/
│ └── conf.d/ # Nginxの仮想ホスト設定
├── php.ini # PHP設定ファイル
├── public/ # Webルート (DocumentRoot)
└── README.md


---

##  セットアップ手順

```bash
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker

sudo usermod -a -G docker ec2-user
```
## compose　install方法
```bash
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
```
### ソースコードの配置
https://gitforwindows.org/ にアクセスし、gitをインストール
```bash
git　clone
```
### イメージのビルド & コンテナ起動
```bash
docker compose up -d --build
```
### 1. SQLの作成
```bash
docker exec -it mysql mysql -u root example_db
```
### 2. tableの作成
```sql
CREATE TABLE `access_logs` (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_agent` TEXT NOT NULL,
  `remote_ip` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `bbs_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `image_filename` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `user_relationships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `followee_user_id` INT UNSIGNED NOT NULL,
  `follower_user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `password` TEXT NOT NULL,
  `icon_filename` TEXT DEFAULT NULL,
  `introduction` TEXT DEFAULT NULL,
  `cover_filename` TEXT DEFAULT NULL,
  `birthday` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

phpで作成したloginした人のみが使える掲示板です。









