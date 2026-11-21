---
name: implement-use-case
description: Triển khai hoặc sửa luồng nghiệp vụ của một Use Case Coffee Shop theo đặc tả đã thống nhất.
---

# Triển khai Use Case

Trước khi viết mã, kiểm tra nhánh thực tế, working tree và worktree của thread. Thực hiện trên nhánh công việc phù hợp theo `docs/GIT_WORKFLOW.md`, thường là `feature/*` hoặc `fix/*` từ `develop`. Bảo toàn thay đổi sẵn có khi chuyển nhánh. Giữ `main` và `develop` cho tích hợp theo Git Flow.

1. Đọc đặc tả được chỉ định trong `docs/use-cases/` hoặc trong yêu cầu. Xác định tác nhân, tiền điều kiện, hậu điều kiện, luồng chính, luồng thay thế, luồng ngoại lệ và tiêu chí chấp nhận. Đối chiếu các quy tắc liên quan trong `docs/BUSINESS_RULES.md` nếu có. Làm rõ điểm còn thiếu nếu nó ảnh hưởng đến quyền, dữ liệu hoặc kết quả nghiệp vụ.
2. Kiểm tra route, controller, Livewire component, model và test hiện có liên quan đến luồng để tái sử dụng hành vi đã triển khai. Xác định thay đổi schema, quan hệ và ràng buộc dữ liệu trước khi viết migration.
3. Triển khai qua điểm vào thực tế của UC. Chọn Model, Migration, Controller, Service, Form Request hoặc Policy theo nhu cầu. Tái sử dụng layout và component Blade/Livewire. Kiểm tra validation, quyền truy cập, xử lý lỗi và dùng transaction khi nhiều thao tác ghi phải thành công cùng nhau.
4. Thêm hoặc cập nhật test phù hợp với phạm vi và rủi ro. Bao phủ luồng chính cùng các nhánh quan trọng về ngoại lệ, quyền và dữ liệu. Chạy các kiểm tra khả thi, sửa lỗi do thay đổi mới gây ra và chạy lại kiểm tra bị ảnh hưởng.
5. Khi tiến độ dự án thực sự thay đổi, cập nhật `docs/PROGRESS.md` với phạm vi đã triển khai, kết quả thực tế và phần còn thiếu. Commit thay đổi đã rà soát trên nhánh công việc. Chờ yêu cầu của người dùng trước khi merge.
6. After completing the workflow, apply the `report-task` skill to generate the standardized completion report.

## Tiêu chí hoàn thành

UC hoàn thành khi tác nhân thực hiện được các luồng theo tiêu chí chấp nhận qua điểm vào thật, các ràng buộc về quyền, dữ liệu và ngoại lệ đã được kiểm chứng, các bài kiểm tra liên quan đạt và thay đổi tiến độ đã được ghi nhận khi có. Nếu tiêu chí bắt buộc còn lỗi hoặc chưa kiểm chứng, ghi rõ tiêu chí đó và lý do trong báo cáo cuối nhiệm vụ.
