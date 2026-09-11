# attendance-app

coachtech 勤怠管理アプリ
ユーザーの勤怠と管理を目的とする

## 作成者

高橋 秀和

## 使用技術

- PHP 8.2
- Laravel 10.x
- MySQL 8.0
- Nginx
- Docker / Docker Compose / Laravel Sail
- Laravel Fortify（認証）
- phpMyAdmin
- mailpit
- Laravel sanctum
- package.jsonに準ずる


## ER図

User ||--o{ AttendanceRecord : 1対多"
User ||--o{ AttendanceCorrectionRequest : 1対多"
AttendanceRecord ||--o{ BreakTime : 1対多"1対1"
AttendanceRecord ||--o{ AttendanceCorrectionRequest : 1対多"
AttendanceCorrectionRequest ||--o{ AttendanceCorrectionRequestBreak : 1対多"

User {
    id 
    name
    email
    password
    remember_token
    admin_status
    is_first_login
    email_verified_at
}

AttendanceRecord {
    id
    user_id 
    date
    clock_in
    clock_out
    comment
    UNIQUE(user_id_id, date)
}

BreakTime {
    id 
    attendance_record_id 
    break_in
    break_out
}

AttendanceCorrectionRequest {
    id 
    attendance_record_id 
    user_id 
    requested_date
    requested_clock_in
    requested_clock_out
    reason
    status
}

AttendanceCorrectionRequestBreak {
    id 
    attendance_correction_request_id 
    requested_break_in
    requested_break_out
}

## 開発環境URL

http://localhost

## 動作環境

- Docker
- Docker Compose

※ Windowsの場合はWSL2の利用を推奨します。

## 環境構築手順

1. **リポジトリをクローン**

    ```bash
    git clone https://github.com/cactus777cheshire55/attendance-app
    ```

2. **.envファイルの準備**

    `.env.example` をコピーして `.env` を作成します。

    ```bash
    cp .env.example .env
    ```

    `.env` ファイル内の以下のDB接続情報を確認・設定します。`.env.example` のデフォルト値はSail向けではないため、以下のように変更してください。

    ```ini
    DB_CONNECTION=mysql
    DB_HOST=mysql
    DB_PORT=3306
    DB_DATABASE=laravel
    DB_USERNAME=sail
    DB_PASSWORD=password

    MAIL_MAILER=smtp
    MAIL_HOST=mailpit
    MAIL_PORT=1025
    ```

3. **Composer依存パッケージのインストール**

    プロジェクトの初回セットアップ時は、`vendor` ディレクトリが存在しないため `sail` コマンドを使用できません。
    以下のDockerコマンドを実行して、コンテナ内で `composer install` を実行します。

    ```bash
       docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```

4. **Laravel Sailの起動**

    以下のコマンドでDockerコンテナを起動します。

    ```bash
    ./vendor/bin/sail up -d
    ```

    > **エイリアスの設定（推奨）**
    >
    > 毎回 `./vendor/bin/sail` と入力するのは手間なので、エイリアスを設定すると便利です。
    >
    > ```bash
    > alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
    > ```

5. **アプリケーションキーの生成**

    ```bash
    sail artisan key:generate
    ```

6. **データベースのマイグレーションと初期データ投入**

    以下のコマンドでテーブルを作成し、ダミーデータを投入します。

    ```bash
    sail artisan migrate:fresh --seed
    ```
    このコマンドの入力後、下記のエラーが表示されることがあります。
    ```bash
       Illuminate\Database\QueryException 
      SQLSTATE[HY000] [1044] Access denied for user 'sail'@'%' to database 'contact-form-app' (Connection: mysql, SQL: select table_name as `name`,         (data_length + index_length) as `size`, table_comment as `comment`, engine as `engine`, table_collation as `collation` from information_schema.tables where table_schema = 'contact-form-app' and table_type in ('BASE TABLE', 'SYSTEM VERSIONED') order by table_name)

      at vendor/laravel/framework/src/Illuminate/Database/Connection.php:829
        825▕                     $this->getName(), $query, $this->prepareBindings($bindings), $e
        826▕                 );
        827▕             }
        828▕ 
      ➜ 829▕             throw new QueryException(
        830▕                 $this->getName(), $query, $this->prepareBindings($bindings), $e
        831▕             );
        832▕         }
        833▕     }

      +43 vendor frames 

      44  artisan:35
          Illuminate\Foundation\Console\Kernel::handle()
    ```
    このエラーはコンテナ内にデータが残っており、エラーが生じているケースなどがあります。
    その場合は、以下のコマンドを順に実行して各コンテナを再起動して下さい。
    ```Bash
    sail down -v
    sail up -d　//コマンド実行後にSQLコンテナが立ち上がるまで時間がかかります。30秒ほどお待ちください。
    sail artisan migrate:fresh --seed
    ```
    

7. **フロントエンドのビルド**

    ```bash
    sail npm install
    sail npm run dev
    ```

    `npm run dev` は開発中は起動したままにしてください。

8. **アプリケーションへのアクセス**

    ブラウザで [http://localhost](http://localhost) にアクセスします。

## テスト実行

```bash
sail artisan test
```

カバレッジ付きで実行する場合:

```bash
sail artisan test --coverage
```

## apiテスト実行時のpost(put,patch)メソッド実行時

ポストマンでURLに http://localhost/api/v1/login
Body タブにrawにしてタイプを JSON に設定し
{
    "email": "user3@example.com",
    "password": "password"
}
で送信してtokenを出してからAuthorizationタブのauth typeをBearer Tokenにしてtokenに張り付ける

*ポストマン以外の挙動はわからないので,その場合は未実装ということでお願いします

*mailに関しても,自動送信後のメール 認証メールを再送するをタップして,メールを送るようにしているのでformタグaタグに変えているので,主旨に沿わないのであれば未実装ということでお願いします
