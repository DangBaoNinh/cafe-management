# Tiến độ Coffee Shop

## Giai đoạn 01 — Đã triển khai

- Khởi tạo bộ Livewire chính thức của Laravel trong thư mục dự án. Đã cài dependency và lưu `composer.lock`, `package-lock.json`.
- Cấu hình tên Coffee Shop, application key, locale `vi`, múi giờ `Asia/Ho_Chi_Minh` và phiên đăng nhập lưu trong MySQL.
- Tạo schema `coffee_shop` trên MySQL 8.4.3 với `utf8mb4`. Đã chạy ba migration nền cho người dùng, cache và hàng đợi, gồm bảng session và token đặt lại mật khẩu.
- Có trang chủ, layout khách hàng, khu vực tài khoản với hồ sơ Livewire và layout riêng tại `/admin`. Route quản trị hiện yêu cầu đăng nhập và từ chối quyền truy cập của tài khoản khách hàng.
- Dùng Fortify cho xác thực bằng email và mật khẩu. Email đặt lại mật khẩu được ghi vào log ở môi trường local.
- Commit nền tảng Laravel trên `main` và `develop` tại `571f166`. Git bỏ qua `.env`, biến thể cục bộ của `.env`, `vendor`, `node_modules`, file build và log.

## Giai đoạn 02 — Thiết kế đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu toàn bộ thiết kế Task 02, xác nhận các phương án nghiệp vụ MVP Q01–Q05 cùng cách xử lý tài khoản theo BR20 và cho phép tích hợp `docs/system-design` vào `develop`. Bản Word là bản trình bày của bộ thiết kế tại thời điểm bàn giao. Trạng thái nghiệm thu được ghi trong Markdown.

- Đã soạn [phân tích nghiệp vụ](BUSINESS_ANALYSIS.md), [quy tắc](BUSINESS_RULES.md), [thiết kế dữ liệu](DATABASE_DESIGN.md), [kiến trúc](ARCHITECTURE.md) và [luồng đơn hàng](ORDER_WORKFLOW.md).
- [Sổ công việc](USE_CASE_TASKS.md) có 25 UC, trong đó 23 UC MVP và 2 UC mở rộng. Có 3 nhiệm vụ kỹ thuật. UC01 và UC02 dùng lại Starter Kit và cần rà soát theo tiêu chí mới.
- ERD có 18 bảng nghiệp vụ. Tính 7 bảng hạ tầng, thiết kế có 25 bảng, ngoài bảng theo dõi migration của Laravel.
- Đã thiết lập lệnh ngắn trong `AGENTS.md` và bản trình bày [Word](reports/PHAN_TICH_THIET_KE_HE_THONG.docx). Các UC và schema nghiệp vụ đang ở giai đoạn thiết kế.
- Đã kiểm tra tính duy nhất của mã, thứ tự phụ thuộc, liên kết Markdown, tên bảng ERD và nội dung đặc tả trong Word. Microsoft Word mở được báo cáo và cập nhật mục lục. Lượt kiểm thử hồi quy nền tảng chạy 29 bài với 69 assertion.

## Giai đoạn 03 — Nền tảng

### TECH01 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu TECH01. Commit `3745c63` từ `chore/database-foundation` đã được tích hợp vào `develop`. Laravel và phiên MySQL dùng UTC, MySQL tạo bảng mới bằng InnoDB. [Thiết kế dữ liệu](DATABASE_DESIGN.md) ghi đối chiếu 8 bảng hiện có với ERD và quy ước VND, thời gian, FK, chỉ mục, constraint và migration theo UC.
- MySQL 8.4.3 chạy đủ 3 migration trên schema thử trống. Kiểm tra đạt cho InnoDB, `utf8mb4`, khóa và chỉ mục nền, từ chối email trùng và ghi thời điểm UTC.
- Trên schema thử có dữ liệu mô phỏng theo cấu hình cũ UTC+7, chuyển sang UTC giữ nguyên bản ghi của cả 8 bảng và thời điểm lưu qua mốc 00:00 giờ Việt Nam. Token đặt lại mật khẩu cũ vẫn hợp lệ. Schema `coffee_shop` hiện có chỉ được đọc để đối chiếu metadata.
- Kiểm thử hồi quy đạt 29 bài với 69 assertion trên SQLite. Pint cho hai file cấu hình và PHPStan đều đạt.

### TECH02 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu TECH02 tại commit `fdf23fc`. Nhánh `feature/authorization-foundation` đã được tích hợp vào `develop` tại merge commit `a81b91c`. Đã thêm vai trò và trạng thái tài khoản, gate quản trị và vận hành đơn, kiểm tra khóa tài khoản ở tầng xác thực và lệnh khởi tạo admin đầu tiên. [Kiến trúc ứng dụng](ARCHITECTURE.md#nền-tảng-phân-quyền-tech02)
- SQLite đạt 39 bài kiểm thử và 114 assertion, gồm quyền của guest, customer, staff, admin, từ chối phiên và yêu cầu Livewire của tài khoản khóa, kiểm soát dữ liệu đăng ký và khởi tạo admin. Pint và PHPStan đạt.
- MySQL 8.4.3 thử nghiệm xác nhận migration giữ nguyên các trường của tài khoản mô phỏng và gán mặc định customer đang hoạt động. Kiểm tra mật khẩu cũ, token ghi nhớ, từ chối vai trò sai và tranh chấp khóa khởi tạo đều đạt.
- Đã chạy migration bổ sung trên Laragon local. Bảng `users` trống trước migration. Việc khởi tạo admin trên schema `coffee_shop` đang chờ thông tin tài khoản của người vận hành.

### TECH03 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu TECH03 tại commit `412c7a9`. Nhánh `chore/integration-test-foundation` đã được tích hợp vào `develop` tại merge commit `2664f00`. Bộ mặc định chạy SQLite trong bộ nhớ. Bộ tích hợp MySQL dùng hai kết nối riêng đến `coffee_shop_test`, chạy migration tiến và kiểm tra rollback nhiều bảng cùng tranh chấp khóa hàng. Tài khoản MySQL thử bị từ chối khi đọc `coffee_shop`. Cấu hình thử trỏ đến `coffee_shop` bị từ chối trước khi kết nối.
- `composer ci:check` đạt Pint, PHPStan và 29 bài SQLite với 69 assertion. MySQL 8.4.3 đạt 2 bài với 26 assertion. Mailpit trên loopback nhận thư mẫu từ Laravel. Thông tin đăng nhập MySQL thử nằm trong file local được Git bỏ qua.
- Workflow GitHub Actions đã thêm service MySQL 8.4 và bước chạy bộ tích hợp. `actionlint` đã xác nhận cú pháp workflow.

### Kiểm tra sau tích hợp TECH02 và TECH03

- Trên `develop`, `composer ci:check` đạt Pint, PHPStan và 39 bài SQLite với 114 assertion. `php artisan test --testsuite=MySQL` đạt 2 bài với 26 assertion. `actionlint` xác nhận workflow và `php artisan migrate:status` xác nhận đủ 4 migration trên Laragon.
- Đã cấu hình `origin` trên GitHub. Workflow `tests` trên `develop` tại `f805aef` chạy thành công. [Kết quả GitHub Actions](https://github.com/DangBaoNinh/cafe-management/actions/runs/44754477616)
- Đã tích hợp `chore/git-workflow` và cập nhật tài liệu theo quy ước phát triển từ `develop`. [Quy trình Git](GIT_WORKFLOW.md)

## Kết quả kiểm tra nền tảng ban đầu

- Laravel 13.43.0 chạy trên PHP 8.3.37 của Laragon. Composer xác nhận các yêu cầu nền tảng và file cấu hình hợp lệ.
- `php artisan migrate:status` xác nhận cả ba migration đã chạy. Đã kiểm tra các route xác thực và route quản trị.
- `npm run build` thành công. Apache phục vụ trang chủ, CSS, JavaScript, script Livewire và file font Be Vietnam Pro có dấu tiếng Việt.
- PHPUnit chạy 29 bài kiểm thử với 69 assertion trên SQLite trong bộ nhớ. Pint và PHPStan đều đạt.
- Kiểm tra HTTP trên [cafe_management.test](http://cafe_management.test) thành công cho trang chủ, trang xác thực, đăng ký, đăng nhập, từ chối mật khẩu sai, đăng xuất, session MySQL, hồ sơ Livewire và bảo vệ route quản trị.
- Kiểm tra HTTP đặt lại mật khẩu thành công từ liên kết trong log. Đã đăng nhập lại bằng mật khẩu mới và xóa tài khoản kiểm thử. Các yêu cầu trực tiếp tới `.env` và `.git/config` bị Apache chặn.
- Log Laravel ghi email đặt lại mật khẩu local. Không thấy mục lỗi trong lượt kiểm tra cuối.

## Việc còn lại

- Nhánh `chore/gitflow-setup` chờ review.
- UC01 đã đủ phụ thuộc để triển khai từ `develop`.
- Điền thông số giao hàng và tài khoản nhận chuyển khoản khi cấu hình cửa hàng.
- Việc gửi email thật cần thông tin SMTP. Kiểm tra hiện tại dùng mailer `log`.
- Quản lý vai trò và các module menu, sản phẩm, danh mục, giỏ hàng, checkout, đơn hàng, thanh toán, khách hàng, khuyến mãi và doanh thu thuộc các giai đoạn tiếp theo.
