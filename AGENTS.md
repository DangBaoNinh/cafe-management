# Hướng dẫn làm việc với Coffee Shop

Coffee Shop là website đặt cà phê trực tuyến. Dự án dùng Laravel, Blade, Livewire, Tailwind CSS và MySQL. Môi trường phát triển là Laragon trên Windows. Viết nội dung giao diện và thông báo cho người dùng bằng tiếng Việt, đồng thời tái sử dụng layout và component hiện có.

## Quy ước triển khai

- Theo cấu trúc MVC và quy ước Laravel. Giữ controller và Livewire component tập trung vào xử lý yêu cầu và giao diện. Tách nghiệp vụ phức tạp hoặc dùng chung sang service khi có nhu cầu.
- Quản lý schema bằng migration và dùng Eloquent cho mô hình, quan hệ và truy vấn nghiệp vụ. Bảo toàn dữ liệu hiện có. Mọi thao tác xóa dữ liệu hoặc đặt lại database cần được người dùng cho phép rõ ràng.
- Validation và authorization thực hiện ở backend, kể cả với action Livewire. Dùng cơ chế Laravel phù hợp như Form Request, middleware, policy và gate. Giữ bảo vệ CSRF, escape dữ liệu người dùng khi hiển thị và giữ thông tin nhạy cảm trong cấu hình môi trường.
- Ưu tiên dependency và mã đã có. Giữ các chức năng ngoài phạm vi hoạt động như trước.
- Dùng PHPUnit qua `php artisan test`. Bộ kiểm thử hiện dùng SQLite trong bộ nhớ. Khi hành vi phụ thuộc vào MySQL, kiểm chứng trên database kiểm thử riêng nếu có và báo cáo phần chưa kiểm chứng.

## Tài liệu và skill

Chỉ đọc phần tài liệu liên quan đến yêu cầu hiện tại.

- [Kế hoạch triển khai](docs/IMPLEMENTATION_PLAN.md) ghi phạm vi và thứ tự các giai đoạn.
- [Tiến độ](docs/PROGRESS.md) ghi phần đã triển khai, kết quả kiểm tra thực tế và việc còn lại.
- [Sổ công việc](docs/USE_CASE_TASKS.md) chứa mã, trạng thái, phụ thuộc, đặc tả và nhánh của từng UC hoặc nhiệm vụ kỹ thuật.
- [Quy tắc nghiệp vụ](docs/BUSINESS_RULES.md) là nguồn quy tắc dùng chung. [Phân tích nghiệp vụ](docs/BUSINESS_ANALYSIS.md) ghi phạm vi và các quyết định đã nghiệm thu.
- [Đặc tả Use Case](docs/use-cases/) mô tả luồng và tiêu chí chấp nhận của từng UC. Dùng [mẫu đặc tả](docs/use-cases/UC_TEMPLATE.md) khi soạn UC mới.

Ưu tiên `$implement-use-case` khi được giao triển khai UC và `$verify-use-case` khi được giao kiểm thử hoặc rà soát UC đã triển khai.

## Quy trình Git

- Áp dụng [quy trình Git](docs/GIT_WORKFLOW.md) cho phát triển, kiểm thử, sửa lỗi, refactor, cấu hình và cập nhật tài liệu. Tạo nhánh công việc từ `develop` theo tiền tố phù hợp. Dùng tên viết thường theo kebab-case. Có thể gom các UC phụ thuộc chặt chẽ vào cùng một nhánh.
- Trước khi chỉnh file, kiểm tra `git status --short --branch`, `git branch --show-current` và thư mục làm việc thực tế của thread. Codex Desktop có thể dùng chung checkout giữa các thread. Dùng worktree riêng khi cần làm song song và tránh để hai thread sửa cùng một checkout.
- Bảo toàn thay đổi có sẵn khi chuyển nhánh. Chỉ sửa và stage file thuộc nhiệm vụ. Kiểm tra diff và chạy kiểm thử phù hợp trước khi commit theo mốc có ý nghĩa. Giữ thông tin nhạy cảm ngoài Git.
- Khi được giao merge, kiểm tra working tree, tiêu chí chấp nhận, kết quả kiểm thử và xung đột. Giữ lịch sử Git và giữ nhánh sau merge cho đến khi người dùng yêu cầu xóa.

## Báo cáo cuối nhiệm vụ

Sau mỗi nhiệm vụ phát triển, sửa lỗi, kiểm thử, cấu hình, refactor hoặc rà soát, đọc và áp dụng [`report-task`](.agents/skills/report-task/SKILL.md) để kết thúc bằng báo cáo đúng cấu trúc trong hội thoại. Quy tắc áp dụng cả khi nhiệm vụ hoàn thành một phần hoặc bị chặn. Với nhiệm vụ nhỏ, dùng báo cáo trong hội thoại. Chỉ cập nhật `docs/PROGRESS.md` khi tiến độ dự án thực sự thay đổi.

## Lệnh ngắn cho Use Case

- **Làm UCxx** (ví dụ **Làm UC01** hoặc **Làm UC02**). Tra mã trong sổ công việc. Đọc đặc tả và quy tắc liên quan. Đối chiếu mã, trạng thái và phụ thuộc trên `develop`. Nếu thiếu điều kiện, báo mã việc chặn và dừng trước khi sửa mã. Kiểm tra nhánh, working tree và worktree. Dùng lại nhánh `feature/*` đã chỉ định hoặc tạo từ `develop` nếu chưa có. Bảo toàn thay đổi có sẵn khi đổi checkout. Dùng `implement-use-case` đúng phạm vi. Viết và chạy kiểm thử, dùng `verify-use-case` nếu phù hợp. Kiểm tra diff, cập nhật trạng thái và tiến độ theo kết quả, commit file liên quan, báo cáo theo `report-task` và dừng chờ nghiệm thu.
- **Kiểm tra UCxx**. Tra tiêu chí nghiệm thu, đối chiếu nhánh và mã, dùng `verify-use-case`, chạy test có thể thực hiện và báo lỗi hoặc phần chưa kiểm chứng. Giữ phạm vi ở rà soát khi người dùng chỉ yêu cầu kiểm tra.
- **Tiếp tục UCxx**. Đọc trạng thái, tiến độ và báo cáo trước đó. Xác định phần còn thiếu và tiếp tục trên nhánh/worktree hiện có của UC, sau khi kiểm tra thay đổi chưa commit.

Phát triển trên nhánh công việc từ `develop`. Tích hợp vào `develop` theo Git Flow, rồi release về `main`. Đặt `ACCEPTED` sau khi người dùng nghiệm thu và commit UC có trong `develop`. Chỉ merge hoặc push khi người dùng yêu cầu. Đồng bộ bản Word với Markdown khi thay đổi thiết kế quan trọng hoặc có yêu cầu.
