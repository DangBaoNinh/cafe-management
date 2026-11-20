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

Xem UC24–UC25 khi có yêu cầu và nhà cung cấp phù hợp. Mỗi UC có nhánh riêng từ `develop`, kiểm thử, commit và báo cáo. Chờ nghiệm thu và yêu cầu merge trước khi cập nhật `ACCEPTED` sau tích hợp.
