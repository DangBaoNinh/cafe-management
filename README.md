# Coffee Shop

Coffee Shop là đồ án môn Mã nguồn mở về website đặt cà phê trực tuyến. Giai đoạn 01 cung cấp trang chủ, xác thực khách hàng, trang tài khoản và layout quản trị dành cho giai đoạn phân quyền.

## Công nghệ và môi trường

- Laravel 13, Laravel Fortify, Blade, Livewire 4 và Flux UI từ bộ khởi tạo Livewire chính thức của Laravel.
- Tailwind CSS 4, Vite qua Vite+, font Be Vietnam Pro, Composer 2, npm và Git.
- MySQL cho ứng dụng. PHPUnit dùng SQLite trong bộ nhớ cho bộ mặc định và MySQL 8.4 cho kiểm thử tích hợp.
- Môi trường đã dùng gồm Windows 11, Laragon 8.9, Apache 2.4, PHP 8.3.37, MySQL 8.4.3 và Node.js 22.23.5.
- Cần PHP 8.3 trở lên với các extension Laravel, gồm `pdo_mysql` cho MySQL, `pdo_sqlite` cho PHPUnit và `zip` hoặc công cụ giải nén tương thích cho Composer. Khuyến nghị Node.js 22.18 trở lên trong dòng 22.

## Cài đặt trên Laragon

1. Đặt mã nguồn trong thư mục `www/cafe_management` của Laragon. Mở terminal Laragon tại thư mục dự án và khởi động Apache, MySQL.
2. Tạo database local bằng MySQL CLI hoặc HeidiSQL.

   ```sql
   CREATE DATABASE IF NOT EXISTS coffee_shop
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Tạo `.env` từ `.env.example` cho lần cài mới. Kiểm tra thông tin kết nối trước khi chạy migration. Cấu hình mẫu dùng MySQL local qua `127.0.0.1:3306` với schema `coffee_shop`, tài khoản `root` và mật khẩu trống. Đổi `DB_*` nếu môi trường của bạn dùng thông số khác.

   ```powershell
   Copy-Item .env.example .env
   composer install
   php artisan key:generate
   php artisan migrate
   npm ci
   npm run build
   ```

   Chỉ tạo `.env` và chạy `key:generate` cho lần cài mới. Nếu Composer trong Laragon báo thiếu ZIP, có thể chạy lệnh theo ví dụ sau mà chỉ bật extension cho tiến trình hiện tại.

   ```powershell
   php -d extension=zip D:/Applications/laragon/bin/composer/composer.phar install
   ```

4. Mở [cafe_management.test](http://cafe_management.test). Đặt `APP_URL` theo domain Laragon thực tế nếu thư mục hoặc hậu tố domain khác. Khi virtual host trỏ vào gốc dự án, `.htaccess` ở gốc chuyển yêu cầu vào `public/`. Nếu tự đặt document root, trỏ thẳng tới `public/`.
5. Khi chỉnh frontend, chạy `npm run dev` song song với Apache. Có thể dùng `php artisan serve` để chạy HTTP riêng trong lúc phát triển. Nếu dùng cách này, sửa `APP_URL` theo URL của server để liên kết đặt lại mật khẩu sử dụng đúng địa chỉ.

## Xác thực và kiểm thử

- `/` là trang chủ. `/dashboard` là khu vực tài khoản, có liên kết tới hồ sơ Livewire tại `/settings/profile`.
- `/register`, `/login` và `/forgot-password` cung cấp xác thực bằng email và mật khẩu. Phiên đăng nhập local được lưu trong MySQL.
- `MAIL_MAILER=log` ghi liên kết đặt lại mật khẩu vào `storage/logs/laravel.log`. Cấu hình SMTP trong `.env` nếu cần gửi email thật.
- `/admin` yêu cầu tài khoản admin đang hoạt động. Xem cách tạo admin đầu tiên trong [kiến trúc ứng dụng](docs/ARCHITECTURE.md#nền-tảng-phân-quyền-tech02).

```powershell
php artisan migrate:status
php artisan route:list
php artisan test
```

### Xem email local bằng Mailpit

Khởi động Mailpit của Laragon. Nếu chạy từ terminal, dùng `mailpit --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025`. Đặt các biến sau trong `.env` local và chạy `php artisan config:clear`.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
```

Yêu cầu đặt lại mật khẩu cho tài khoản thử rồi mở hộp thư Mailpit để xem email. Dùng `MAIL_MAILER=log` nếu chạy Laragon mà không bật Mailpit. [Hộp thư Mailpit](http://127.0.0.1:8025)

### Kiểm thử tích hợp MySQL

Tạo schema và tài khoản thử riêng trên MySQL 8.4. Với cấu hình MySQL mặc định, dùng dấu thoát cho ký tự gạch dưới để quyền chỉ áp dụng cho schema thử. Nếu `@@partial_revokes` đang bật, dùng tên `coffee_shop_test` trực tiếp trong `GRANT`. Thay giá trị mật khẩu mẫu bằng mật khẩu local của bạn. [Quyền trên schema MySQL](https://dev.mysql.com/doc/refman/8.4/en/grant.html)

```sql
CREATE DATABASE coffee_shop_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'coffee_test'@'127.0.0.1' IDENTIFIED BY 'thay-bang-mat-khau-local';
GRANT ALL PRIVILEGES ON `coffee\_shop\_test`.* TO 'coffee_test'@'127.0.0.1';
```

Sao chép `.env.mysql-testing.example` thành `.env.mysql-testing` và điền mật khẩu thử. Git bỏ qua file local này. Có thể đặt các biến `MYSQL_TEST_*` trực tiếp trong môi trường chạy, như trong CI.

```powershell
php artisan test                  # Unit và Feature trên SQLite trong bộ nhớ
php artisan test --testsuite=MySQL # Transaction và khóa hàng trên MySQL
```

Bộ MySQL tự chạy migration tiến trên `coffee_shop_test`. Các bản ghi kiểm tra rollback nằm trong transaction và bài kiểm tra khóa dùng một dòng migration có sẵn. Bộ mặc định dùng SQLite với mailer `array`. GitHub Actions chạy cả hai bộ với một MySQL service dùng riêng cho lần chạy CI.

### Lịch nhận tại quán

Chạy `php artisan schedule:work` cùng ứng dụng khi thử trên Laragon. Trên máy triển khai, cấu hình trình lập lịch gọi `php artisan schedule:run` mỗi phút. Tác vụ `bookings:expire` xử lý lịch nhận đồ uống và lịch đặt chỗ quá hạn theo thứ tự thời điểm vắng mặt.

Kênh thu phụ phí qua ứng dụng dùng cấu hình chuyển khoản hiện có. Khách xem số tiền và nội dung chuyển khoản riêng của khoản phí trên phiếu. Nhân viên căn cứ vào sao kê để xác nhận tiền đã nhận hoặc ghi nhận kết quả cuối của giao dịch trước khi chuyển sang thu tại quầy.

### Đặt bàn và phòng họp

Khách mở **Đặt chỗ**, chọn giờ, loại chỗ và số người để xem sơ đồ hai tầng. Tại **Lịch đặt bàn và phòng**, nhân viên tra mã, check-in, đối chiếu phụ phí và ghi nhận khi khách rời đi. Sơ đồ vận hành hỗ trợ ghi khách trực tiếp và trả chỗ.

Quản trị viên có thể bật hoặc ngừng phục vụ từng chỗ đang trống và thu hẹp giờ nhận đặt bàn, phòng trong khung 07:00–22:30. Điều chỉnh giờ áp dụng cho lịch tạo mới hoặc đổi giờ. Lịch đã xác nhận vẫn check-in theo giờ đã hẹn.

- [Kế hoạch triển khai](docs/IMPLEMENTATION_PLAN.md)
- [Tiến độ và kết quả kiểm tra](docs/PROGRESS.md)
