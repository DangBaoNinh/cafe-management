---
name: verify-use-case
description: Kiểm thử hoặc rà soát một Use Case Coffee Shop đã triển khai và báo cáo kết quả theo tiêu chí chấp nhận.
---

# Kiểm thử và rà soát Use Case

1. Đọc đặc tả UC và tiêu chí chấp nhận. Đối chiếu các quy tắc liên quan trong `docs/BUSINESS_RULES.md` nếu có. Xác định route hoặc action thực tế, tác nhân và dữ liệu thử cần thiết.
2. Đối chiếu từng tiêu chí với mã nguồn và bằng chứng kiểm thử. Kiểm tra luồng chính, luồng thay thế và ngoại lệ. Thử dữ liệu sai, tác nhân thiếu quyền và truy cập tài nguyên của người dùng khác nếu có liên quan. Rà soát các biện pháp bảo mật áp dụng cho luồng.
3. Kiểm tra dữ liệu được ghi, điều kiện chuyển trạng thái và tính nhất quán sau thành công hoặc thất bại. Khi UC có transaction, kiểm tra việc hoàn tác khi ghi dở dang. Dùng dữ liệu kiểm thử cô lập.
4. Chạy các bài PHPUnit, Livewire hoặc kiểm tra HTTP phù hợp với điểm vào và rủi ro của UC. Ghi lệnh hoặc kịch bản đã chạy cùng kết quả.
5. Ghi lỗi theo mức độ ảnh hưởng, vị trí `file:dòng` hoặc route/action, điều kiện và các bước tái hiện, kết quả mong đợi và kết quả thực tế. Với mỗi tiêu chí, ghi **Đạt**, **Không đạt** hoặc **Chưa kiểm chứng** và nêu bằng chứng hoặc lý do.
6. After completing the workflow, apply the `report-task` skill to generate the standardized completion report.

Khi yêu cầu chỉ là đánh giá, giới hạn công việc ở kiểm tra và báo cáo. Chỉ sửa mã khi người dùng yêu cầu. Mọi thay đổi kiến trúc hoặc phạm vi chức năng cần có yêu cầu tương ứng.
