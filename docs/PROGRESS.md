# Tiến độ Coffee Shop

## Giai đoạn 01 — Đã triển khai

- Khởi tạo bộ Livewire chính thức của Laravel trong thư mục dự án. Đã cài dependency và lưu `composer.lock`, `package-lock.json`.
- Cấu hình tên Coffee Shop, application key, locale `vi`, múi giờ `Asia/Ho_Chi_Minh` và phiên đăng nhập lưu trong MySQL.
- Tạo schema `coffee_shop` trên MySQL 8.4.3 với `utf8mb4`. Đã chạy ba migration nền cho người dùng, cache và hàng đợi, gồm bảng session và token đặt lại mật khẩu.
- Có trang chủ, layout khách hàng, khu vực tài khoản với hồ sơ Livewire và layout riêng tại `/admin`. Route quản trị hiện yêu cầu đăng nhập và từ chối quyền truy cập của tài khoản khách hàng.
- Dùng Fortify cho xác thực bằng email và mật khẩu. Email đặt lại mật khẩu được ghi vào log ở môi trường local.
- Commit nền tảng Laravel trên `main` và `develop` tại `571f166`. Git bỏ qua `.env`, biến thể cục bộ của `.env`, `vendor`, `node_modules`, file build và log.

## Giai đoạn 02 — Thiết kế chờ nghiệm thu

- Đã soạn [phân tích nghiệp vụ](BUSINESS_ANALYSIS.md), [quy tắc](BUSINESS_RULES.md), [thiết kế dữ liệu](DATABASE_DESIGN.md), [kiến trúc](ARCHITECTURE.md) và [luồng đơn hàng](ORDER_WORKFLOW.md).
- [Sổ công việc](USE_CASE_TASKS.md) có 25 UC, trong đó 23 UC MVP và 2 UC mở rộng. Có 3 nhiệm vụ kỹ thuật. UC01 và UC02 dùng lại Starter Kit và cần rà soát theo tiêu chí mới.
- ERD có 18 bảng nghiệp vụ. Tính 7 bảng hạ tầng, thiết kế có 25 bảng, ngoài bảng theo dõi migration của Laravel.
- Đã thiết lập lệnh ngắn trong `AGENTS.md` và bản trình bày [Word](reports/PHAN_TICH_THIET_KE_HE_THONG.docx). Các UC và schema nghiệp vụ đang ở giai đoạn thiết kế.
- Đã kiểm tra tính duy nhất của mã, thứ tự phụ thuộc, liên kết Markdown, tên bảng ERD và nội dung đặc tả trong Word. Microsoft Word mở được báo cáo và cập nhật mục lục. Lượt kiểm thử hồi quy nền tảng chạy 29 bài với 69 assertion.

## Kết quả kiểm tra

- Laravel 13.43.0 chạy trên PHP 8.3.37 của Laragon. Composer xác nhận các yêu cầu nền tảng và file cấu hình hợp lệ.
- `php artisan migrate:status` xác nhận cả ba migration đã chạy. Đã kiểm tra các route xác thực và route quản trị.
- `npm run build` thành công. Apache phục vụ trang chủ, CSS, JavaScript, script Livewire và file font Be Vietnam Pro có dấu tiếng Việt.
- PHPUnit chạy 29 bài kiểm thử với 69 assertion trên SQLite trong bộ nhớ. Pint và PHPStan đều đạt.
- Kiểm tra HTTP trên [cafe_management.test](http://cafe_management.test) thành công cho trang chủ, trang xác thực, đăng ký, đăng nhập, từ chối mật khẩu sai, đăng xuất, session MySQL, hồ sơ Livewire và bảo vệ route quản trị.
- Kiểm tra HTTP đặt lại mật khẩu thành công từ liên kết trong log. Đã đăng nhập lại bằng mật khẩu mới và xóa tài khoản kiểm thử. Các yêu cầu trực tiếp tới `.env` và `.git/config` bị Apache chặn.
- Log Laravel ghi email đặt lại mật khẩu local. Không thấy mục lỗi trong lượt kiểm tra cuối.

## Việc còn lại

- Hai nhánh `chore/gitflow-setup` và `docs/system-design` chờ review và yêu cầu tích hợp vào `develop`. Repository chưa có remote.
- Xác nhận Q01–Q05 và chính sách lưu/xóa tài khoản trước khi triển khai phần liên quan. Sau nghiệm thu thiết kế, bắt đầu từ TECH01.
- Việc gửi email thật cần thông tin SMTP. Kiểm tra hiện tại dùng mailer `log`.
- Phân quyền Admin, Staff, Customer và các module menu, sản phẩm, danh mục, giỏ hàng, checkout, đơn hàng, thanh toán, khách hàng, khuyến mãi và doanh thu thuộc các giai đoạn tiếp theo.
