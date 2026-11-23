# Kiến trúc ứng dụng Coffee Shop

## 1. Nền tảng và điểm vào

Ứng dụng Laravel monolith dùng Blade cho layout, Livewire cho form và CRUD có tương tác, Tailwind CSS qua Vite. MySQL là datastore nghiệp vụ, Laragon trên Windows là môi trường local. Fortify xử lý phiên xác thực. MVC, Eloquent và quy ước Laravel là nền tảng tổ chức.

| Khu vực | Điểm vào dự kiến | Trách nhiệm |
| --- | --- | --- |
| Cửa hàng và tài khoản | Menu công khai, trang tài khoản, giỏ, checkout, đơn của tôi | Hiển thị tiếng Việt và điều phối yêu cầu của khách. |
| Nhân viên | Khu vận hành đơn có middleware phù hợp | Tiếp nhận, cập nhật pha chế, bàn giao và ghi nhận thanh toán. |
| Quản trị | Khu `/admin` yêu cầu gate `access-admin` | Cấu hình, quản lý danh mục và vai trò, quản trị đơn, tra cứu và báo cáo. |
| Laravel application | Controller hoặc action Livewire, Form Request/validation, Policy | Kiểm tra dữ liệu và quyền phía máy chủ, kể cả gọi action trực tiếp. |
| Nghiệp vụ và dữ liệu | Service/Action cần dùng chung, Eloquent, MySQL | Tính tổng, checkout, chuyển trạng thái và truy vấn; quản lý transaction và snapshot. |

## 2. Ranh giới nghiệp vụ

- Controller hoặc component quản lý request, form và phản hồi. Service/Action xử lý checkout, pricing, trạng thái và thanh toán khi có quy tắc dùng chung. Đọc đơn theo ownership, không lấy bản ghi theo ID rồi chỉ ẩn nút trên giao diện.
- Checkout và mọi điểm hiển thị tổng tiền dùng cùng cách tính phía máy chủ. Thanh toán thủ công được biểu diễn bằng method và trạng thái dữ liệu. Tích hợp thật chỉ bổ sung adapter khi UC24 hoặc UC25 được chọn.
- Session, CSRF và rate limiting dùng cơ chế Laravel. Tài khoản tự đăng ký nhận quyền khách hàng. Vai trò và trạng thái hoạt động được kiểm tra trên mỗi thao tác bảo vệ. Lịch sử đơn và payment hạn chế hiển thị theo vai trò.
- Vite bundle assets. File ảnh công khai đi qua Laravel filesystem, giới hạn loại, dung lượng và tên file do server tạo. Bí mật chuyển khoản tích hợp tương lai, mail và cấu hình truy cập đặt trong môi trường.

### Nền tảng phân quyền TECH02

- `users.role` nhận `customer`, `staff` hoặc `admin`. Mặc định của bản ghi cũ và đăng ký mới là `customer` đang hoạt động. Laravel chỉ nạp tài khoản đang hoạt động khi đăng nhập, đọc phiên, đọc cookie ghi nhớ hoặc tra tài khoản để đặt lại mật khẩu. Khi tài khoản đang khóa, phiên đã có cũng bị từ chối ở yêu cầu bảo vệ kế tiếp, kể cả cập nhật Livewire.
- Dùng `access-admin` cho khu quản trị và `operate-orders` cho luồng vận hành dành cho staff hoặc admin đang hoạt động. Các UC tiếp theo kiểm tra thêm chủ sở hữu, bản ghi và trạng thái nghiệp vụ bằng policy phù hợp.
- Sau khi chạy `php artisan migrate`, người vận hành có quyền terminal có thể chạy `php artisan admin:bootstrap "Tên quản trị viên" "admin@example.test"` với email chưa đăng ký. Lệnh hỏi và xác nhận mật khẩu ẩn tối thiểu 12 ký tự. Lệnh dùng kho cache cơ sở dữ liệu để khóa lần khởi tạo và từ chối khi hệ thống đã có admin, kể cả admin đang khóa. Luồng phân quyền tài khoản sau khởi tạo thuộc UC04.

## 3. Quyết định thiết kế

| Quyết định | Lý do và hệ quả |
| --- | --- |
| Monolith theo module nghiệp vụ | Phù hợp một cửa hàng và stack hiện có. Dùng service cho phần chung khi có nhu cầu. |
| Tách trạng thái đơn khỏi payment | Đơn có thể được xác nhận khi đang chờ chuyển khoản. Khi hủy đơn đã thu tiền, trạng thái payment vẫn có thể chờ hoàn. |
| Snapshot đơn và lịch sử append-only | Cho phép đối chiếu mua hàng, thanh toán và doanh thu khi catalog hoặc địa chỉ đổi. |
| Đếm lượt mã giảm từ đơn còn hiệu lực | Tránh bộ đếm trôi sau từ chối hoặc hủy. Khóa mã khi cấp lượt và đánh chỉ mục truy vấn. |
| Kiểm thử qua hai lớp | Dùng PHPUnit cho validation, policy, luồng và tính toán. Dùng MySQL thử nghiệm cho transaction, FK, unique và concurrency. |

## 4. Triển khai và nghiệm thu

Mỗi UC lấy phạm vi và phụ thuộc từ [sổ công việc](USE_CASE_TASKS.md), hướng dẫn tác nghiệp từ Markdown. Bản Word là bản trình bày và chỉ đồng bộ khi thay đổi thiết kế quan trọng hoặc theo yêu cầu. Nhánh UC từ `develop`, có kiểm thử và commit. Người dùng nghiệm thu và yêu cầu tích hợp trước khi UC đạt `ACCEPTED`.
