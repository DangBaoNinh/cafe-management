# Tích hợp Toàn Café & Jardin

## Nguồn và phạm vi

Nguồn giao diện: `Toan_French_Theme_Complete.zip` do nhóm cung cấp. Bản giải nén để đối chiếu nằm trong `docs/ui-preview/french-theme-source/` và được Git bỏ qua. Không chép các trang HTML prototype, catalog JSON, giỏ localStorage hoặc dữ liệu đơn/bàn mẫu vào ứng dụng.

Trang chủ lấy bố cục Jardin mới từ `home-jardin.js` trong gói, thay vì view Blade khởi điểm cũ. Các màn hình nghiệp vụ tiếp tục dùng route, form, validation, Livewire và service Laravel hiện có.

## Đối chiếu màn hình

| Thiết kế trong gói | Tích hợp trong ứng dụng |
| --- | --- |
| Header, logo, footer public | Layout `customer`, component `terrasse.brand` và `terrasse.store-footer` đã chuyển nhận diện sang Toàn |
| Trang chủ Jardin | `customer.home`: banner ảnh, hoa lá có nút dừng, món bán chạy thật, tối đa 8 món gợi ý, lọc nhóm món hiện có, gallery và quán 3D mở theo yêu cầu |
| Thực đơn và chi tiết | `customer.menu.*`, `customer.product-card`: thẻ ảnh vòm, giá thật, tìm/lọc/phân trang và tùy chỉnh Livewire |
| Giỏ và checkout | Giỏ có ảnh món, bố cục tóm tắt riêng; checkout, đơn, lịch nhận và cài đặt dùng palette/component chung, giữ dữ liệu và thao tác hiện có |
| Đặt bàn | `customer.reservations.create`: ảnh không gian, form và sơ đồ hai tầng thật, giữ quy tắc đặt/hủy/đổi lịch |
| Câu chuyện | `/cau-chuyen`, view `customer.story`, nội dung concept từ gói |
| Không gian 3D | `/khong-gian`, view `customer.spaces` và component `theme.seating`, đọc 20 bàn và 2 phòng từ database |
| Admin và vận hành | Layout `admin`: sidebar sáng màu theo quyền hiện có, topbar, menu mobile, focus trap; bảng, form, dashboard và các module hiện có dùng theme chung |
| Đăng nhập, tài khoản | Layout xác thực và các trang tài khoản dùng nhận diện mới; xác thực và chuyển hướng giữ cơ chế Fortify |
| Trợ lý nhân vật | Sprite từ gói gắn vào widget chat hiện có, tiếp tục dùng endpoint Gemini và thêm món thật vào giỏ |

Các trang đặc thù như kho nguyên liệu, đối chiếu thanh toán và gọi món tại bàn không có bản mẫu tương đương đầy đủ trong gói. Chúng kế thừa shell, typography, màu, bảng và form mới; không thay nội dung nghiệp vụ bằng dashboard giả của prototype.

## Tổ chức source

- `resources/css/french/base.css`, `home.css`, `garden.css`, `cafe3d.css`: CSS từ gói. CSS gốc được đặt trong layer components để các utility Tailwind của form hiện hành tiếp tục có hiệu lực.
- `resources/css/french/integration.css`: tokens light/dark và phần kết nối với các class/component hiện có. Giữ tên `terrasse` ở các contract trình bày đang dùng để tránh sửa hàng loạt form.
- `resources/js/french-theme.js`: navigation, sidebar, lọc món trang chủ, lazy-load 3D và lifecycle Livewire. Scene và polling được dispose khi rời trang.
- `public/french-theme/`: ảnh, sprite và các module 3D/read-only seating từ gói. Giữ license Three.js tại `cafe3d/vendor/THREE-LICENSE.txt`.
- Ảnh món, giá, size, danh mục và món bán chạy tiếp tục lấy từ catalog thật. Bộ ảnh/catalog 56 món của prototype không ghi đè dữ liệu cửa hàng.

## Snapshot công khai

`GET /khong-gian/snapshot` có giới hạn tần suất, trả `Cache-Control: no-store, private` và chỉ xuất:

- `observed_at`: thời điểm tính snapshot.
- `areas`: `id`, `name` của tầng.
- `tables`: `id`, `code`, `area_id`, `capacity`, `status`.

Trạng thái được ánh xạ từ `ReservationSeat::availability()` và phiên gọi món đang hoạt động. Không xuất tên khách, điện thoại, email, mã đặt chỗ, mã phiên, đơn hoặc thanh toán. GET không đổi tình trạng chỗ. Mất kết nối hoặc snapshot cũ khiến giao diện chuyển sang chưa xác minh; backend đặt chỗ vẫn quyết định khả năng giữ chỗ trong giao dịch hiện có.

## Kiểm tra và giới hạn

Lệnh tại terminal Laragon: `npm run build`, `php artisan test`, `php artisan view:cache`. Có thể dùng `npm.cmd` khi PowerShell chặn `npm.ps1`.

Lượt xác minh tích hợp đạt 215 bài với 3.465 assertion trên SQLite; build, Blade, Pint, PHPStan và cú pháp JS đều đạt. Chromium headless kiểm tra các màn hình chính tại 320/390/1.440 px, thêm món Livewire, giỏ/checkout, sidebar, Escape, điều hướng cài đặt Livewire, light/dark, đổi góc 3D và lỗi snapshot. Bộ MySQL chưa chạy vì checkout chưa có cấu hình thử riêng; WebGL trên GPU/thiết bị thật chưa được kiểm chứng.

Kiểm tra giao diện tại trang chủ, `/menu`, `/cau-chuyen`, `/khong-gian`, `/cart`, `/checkout`, `/reservations/create`, `/admin` và `/operations/reservations/seats`. Dùng tài khoản đúng vai trò. Kiểm tra menu mobile, Escape, bàn phím, thay size, thêm giỏ, thông báo lỗi và chế độ sáng/tối.

Gói chưa có MP4 13 giây: banner hiện dùng ảnh poster đã cung cấp. Scene 3D là minh họa kiến trúc/sơ đồ, không phải mô hình đo đạc mặt bằng. Font Cormorant Garamond và DM Sans tải từ Google Fonts, có font local/hệ thống dự phòng. Không thay schema hoặc chạy migration trên MySQL của ứng dụng trong task giao diện này.
