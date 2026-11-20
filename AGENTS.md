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
- `docs/BUSINESS_RULES.md` là nơi ghi quy tắc nghiệp vụ dùng chung khi đã được xác nhận.
- [Đặc tả Use Case](docs/use-cases/) mô tả luồng và tiêu chí chấp nhận của từng UC. Dùng [mẫu đặc tả](docs/use-cases/UC_TEMPLATE.md) khi soạn UC mới.

Ưu tiên `$implement-use-case` khi được giao triển khai UC và `$verify-use-case` khi được giao kiểm thử hoặc rà soát UC đã triển khai.

## Git Flow

- Trước khi bắt đầu bất kỳ nhiệm vụ nào, kiểm tra nhánh thực tế, working tree và checkout hoặc worktree đang dùng. Xác định nhánh cho mỗi thread Codex mới. Dùng worktree riêng khi các nhiệm vụ song song có thể ghi đè nhau.
- Dùng nhánh riêng cho mỗi nhiệm vụ độc lập. Tạo `feature/*`, `docs/*`, `fix/*`, `chore/*`, `refactor/*` và `release/*` từ `develop`. Tạo `hotfix/*` từ `main`. Dùng tên viết thường theo kebab-case.
- Phát triển trên nhánh công việc. `develop` nhận thay đổi đã review và kiểm thử. `main` dùng cho phát hành và demo chính thức, chỉ nhận release hoặc hotfix đã được chấp nhận.
- Phân loại và bảo toàn thay đổi chưa commit trước khi đổi nhánh. Trước khi commit, kiểm tra diff và kết quả kiểm thử, stage đúng file thuộc nhiệm vụ và loại trừ thông tin nhạy cảm. Khi hoàn thành, commit theo Conventional Commits.
- Chỉ merge vào `develop` hoặc `main`, push, hoặc xóa nhánh khi người dùng yêu cầu. Khi được giao merge, kiểm tra tiêu chí chấp nhận, kết quả test và xung đột. Giữ lịch sử Git và tránh force push hoặc rewrite history.

Xem [quy trình Git Flow](docs/GIT_WORKFLOW.md) để biết cách review, phát hành, xử lý hotfix và làm việc với Git Worktree.

## Báo cáo cuối nhiệm vụ

Sau mỗi nhiệm vụ phát triển, sửa lỗi, kiểm thử, cấu hình, refactor hoặc rà soát, đọc và áp dụng [`report-task`](.agents/skills/report-task/SKILL.md) để kết thúc bằng báo cáo đúng cấu trúc trong hội thoại. Quy tắc áp dụng cả khi nhiệm vụ hoàn thành một phần hoặc bị chặn. Với nhiệm vụ nhỏ, dùng báo cáo trong hội thoại. Chỉ cập nhật `docs/PROGRESS.md` khi tiến độ dự án thực sự thay đổi.
