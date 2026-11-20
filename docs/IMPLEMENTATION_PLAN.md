# Kế hoạch triển khai Coffee Shop

## Giai đoạn 01 — Khởi tạo dự án

- Khởi tạo Laravel và Livewire, cấu hình MySQL, build frontend và khởi tạo Git.
- Thiết lập xác thực, trang chủ, khu vực tài khoản và route quản trị với kiểm tra quyền.
- Hoàn thành khi migration chạy trên MySQL, frontend build thành công, PHPUnit chạy qua và các URL chính phản hồi đúng trên Laragon.

## Giai đoạn 02 — Phân quyền và danh mục

- Thiết lập vai trò Admin, Staff, Customer và quyền thao tác cho từng vai trò.
- Quản trị danh mục và sản phẩm, bao gồm ảnh, giá và trạng thái hiển thị. Hiển thị menu sản phẩm cho khách hàng.
- Hoàn thành khi tài khoản được cấp đúng quyền, CRUD có validation và menu phản ánh đúng trạng thái sản phẩm.

## Giai đoạn 03 — Giỏ hàng và đặt cà phê

- Tạo giỏ hàng, cập nhật số lượng và tính tổng tiền phía máy chủ.
- Thiết lập checkout, thông tin nhận hàng, đơn hàng và lịch sử đặt hàng của khách.
- Hoàn thành khi khách có thể đặt đơn từ menu, lịch sử chỉ hiển thị đơn của chính khách và gửi lại yêu cầu không tạo trùng đơn.

## Giai đoạn 04 — Vận hành và thanh toán

- Xây dựng quản lý đơn hàng, trạng thái xử lý, tra cứu khách hàng và quản lý khuyến mãi.
- Thiết lập phương thức thanh toán và tích hợp thanh toán trực tuyến theo lựa chọn của đồ án.
- Hoàn thành khi nhân viên có thể xử lý đơn theo quyền, trạng thái thanh toán đối chiếu được với đơn và các tình huống thất bại có kiểm thử.

## Giai đoạn 05 — Báo cáo và bàn giao

- Xây dựng báo cáo doanh thu theo khoảng thời gian và trạng thái đơn, hoàn thiện responsive và tài liệu vận hành.
- Hoàn thành khi số liệu báo cáo khớp với dữ liệu kiểm thử và luồng từ đặt đến xử lý đơn vượt qua kiểm thử tích hợp.
