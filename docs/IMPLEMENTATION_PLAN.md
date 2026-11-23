# Kế hoạch triển khai Coffee Shop

## Giai đoạn 01 — Base Project

Laravel, Livewire, MySQL, Fortify và kiểm thử nền đã hoạt động. Kết quả thực tế nằm trong [tiến độ](PROGRESS.md).

## Giai đoạn 02 — Phân tích và thiết kế

Review [phân tích nghiệp vụ](BUSINESS_ANALYSIS.md), [quy tắc](BUSINESS_RULES.md), [ERD và schema](DATABASE_DESIGN.md), [kiến trúc](ARCHITECTURE.md), [luồng đơn](ORDER_WORKFLOW.md) và [sổ công việc](USE_CASE_TASKS.md). Nghiệm thu thiết kế khi phạm vi, phụ thuộc, tiêu chí và quyết định nghiệp vụ liên quan đã rõ. Nhánh giao tài liệu là `docs/system-design`. Word trình bày lại nguồn Markdown.

## Giai đoạn 03 — Nền tảng, tài khoản và catalog

- Làm TECH01 trước để chốt quy ước dữ liệu và kiểm tra schema nền. Sau đó làm TECH02 (phân quyền) và TECH03 (môi trường, kiểm thử tích hợp).
- Rà soát và tận dụng xác thực/hồ sơ Starter Kit (UC01–UC02), rồi triển khai địa chỉ, vai trò, catalog và tùy chọn (UC03–UC10).
- Chốt khi kiểm chứng quyền khách, staff và admin, và menu chỉ hiện món đủ điều kiện bán.

## Giai đoạn 04 — Giỏ và checkout

- Làm UC11–UC14 theo phụ thuộc, gồm phí/vùng, mã giảm, giao hoặc nhận tại quán, COD hoặc chuyển khoản thủ công.
- Chốt khi tổng tiền bốn tổ hợp nhận/thanh toán đúng, giá thay đổi được xử lý, transaction không ghi dở và retry không tạo hai đơn.

## Giai đoạn 05 — Vận hành, thanh toán và doanh thu

- Làm UC15–UC23, đặt UC20 trước UC18 và UC19 theo phụ thuộc. Bao gồm theo dõi, hủy, tiếp nhận, pha chế, bàn giao, đối chiếu, quản trị và báo cáo.
- Chốt khi vai trò thao tác đúng ma trận, trạng thái và thanh toán đồng bộ, báo cáo khớp bộ đối chiếu và kiểm thử hồi quy đạt.

## Mở rộng sau MVP

- UC24–UC25 hỗ trợ mô phỏng tích hợp. Kết nối nhà cung cấp thật cần yêu cầu và thông tin cấu hình riêng.
- [UC26](use-cases/UC26.md) mở rộng checkout và bàn giao tại quán. Kiểm chứng biên giờ, không giới hạn đơn theo giờ nhận, phí, hiệu lực phiếu và hồi quy nhận ngay/giao hàng.
- [UC27](use-cases/UC27.md) dùng cấu hình 10 bàn và 1 phòng mỗi tầng. Kiểm chứng sơ đồ từng tầng, chống đặt chỗ đã giữ/đang sử dụng, đổi lịch, check-in và trả chỗ trên MySQL thử nghiệm.
- Hai UC dùng chung tính phí, theo dõi vắng mặt và kiểm tra quyền đặt lịch. Kiểm thử chuỗi không đến xen kẽ hai loại lịch và mua hàng bình thường trong thời gian hạn chế.
- Khi triển khai UC26, xây dựng phần dùng chung cho giờ hẹn, ghi nhận đến, không đến và đối chiếu phụ phí. UC27 tái sử dụng phần này cho sơ đồ tầng, giữ chỗ và check-in. Kiểm thử mốc 22:30/22:35, hạn 23:00, xác nhận giao dịch lặp và chuyển kênh thu đúng điều kiện.

Mỗi UC có nhánh riêng từ `develop`, kiểm thử, commit và báo cáo. Chờ nghiệm thu và yêu cầu merge trước khi cập nhật `ACCEPTED` sau tích hợp.
