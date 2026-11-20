# Git Flow của Coffee Shop

## Nhánh cơ sở và nhánh công việc

| Nhánh | Tạo từ | Merge vào | Mục đích |
| --- | --- | --- | --- |
| `main` | — | — | Phiên bản ổn định cho phát hành và demo chính thức. Chỉ nhận release và hotfix đã được chấp nhận. |
| `develop` | `main` ổn định ban đầu | — | Tích hợp thay đổi đã review và kiểm thử. Duy trì các kiểm thử cơ bản đạt. |
| `feature/*` | `develop` | `develop` | Chức năng mới hoặc nhóm UC liên quan, ví dụ `feature/shopping-cart`. |
| `docs/*` | `develop` | `develop` | Phân tích, đặc tả UC, ERD và thiết kế, ví dụ `docs/system-design`. |
| `fix/*` | `develop` | `develop` | Sửa lỗi phát hiện trong quá trình phát triển. |
| `chore/*` | `develop` | `develop` | Cấu hình, công cụ và bảo trì. |
| `refactor/*` | `develop` | `develop` | Tái cấu trúc mã. |
| `release/*` | `develop` | `main` và `develop` | Hoàn thiện bản phát hành và kiểm thử hồi quy, ví dụ `release/v1.0.0`. |
| `hotfix/*` | `main` | `main` và `develop` | Sửa lỗi khẩn cấp của phiên bản đã phát hành. |

Dùng một nhánh cho mỗi nhiệm vụ độc lập. Đặt tên viết thường theo kebab-case, với dấu chấm được dùng trong số phiên bản như `release/v1.0.0`. Thực hiện thay đổi trên nhánh công việc. Giữ `main` và `develop` cho tích hợp.

## Bắt đầu và triển khai Use Case

1. Đọc `AGENTS.md` và đặc tả cần thiết. Xác nhận checkout, nhánh hiện tại và thay đổi đã stage hoặc chưa stage.

   ```powershell
   git rev-parse --show-toplevel
   git branch --show-current
   git status --short --branch
   git worktree list
   ```

2. Phân loại và bảo toàn thay đổi có sẵn. Nếu checkout đang chứa công việc khác, tiếp tục ở checkout phù hợp hoặc dùng worktree riêng khi cần. Khi có thể chuyển nhánh an toàn, tạo nhánh từ cơ sở trong bảng.

   ```powershell
   git switch -c feature/shopping-cart develop
   git status --short --branch
   ```

3. Dùng `$implement-use-case` để triển khai theo đặc tả và `$verify-use-case` để kiểm tra tiêu chí chấp nhận. Chạy kiểm thử phù hợp, chẳng hạn `php artisan test` với thay đổi Laravel hoặc `npm run build` nếu thay đổi frontend.
4. Kiểm tra `git status --short`, `git diff` và `git diff --cached`. Stage file đã rà soát, chạy `git diff --cached --check` và commit theo mốc có thể review độc lập. Giữ `.env`, token, khóa riêng, bản sao lưu và log ngoài commit.
5. Dùng `$report-task` để báo cáo nhánh, commit, working tree, kết quả kiểm thử, nhánh đích và điều kiện còn thiếu trước khi merge.

## Conventional Commits

Dùng dạng `type(scope): mô tả`. Có thể bỏ `scope` khi phạm vi đã rõ.

| Loại | Mục đích |
| --- | --- |
| `feat` | Thêm chức năng |
| `fix` | Sửa lỗi |
| `docs` | Cập nhật tài liệu |
| `refactor` | Tái cấu trúc |
| `test` | Bổ sung hoặc sửa kiểm thử |
| `chore` | Cấu hình hoặc bảo trì |

```text
feat(cart): implement shopping cart
test(cart): add cart feature tests
docs(uc): add checkout use case specification
```

## Review, merge và xung đột

1. So sánh nhánh nguồn với cơ sở phù hợp, ví dụ `git diff develop...feature/shopping-cart`. Review phạm vi, tiêu chí chấp nhận và kết quả kiểm thử bắt buộc.
2. Khi người dùng yêu cầu merge, kiểm tra trạng thái của nhánh nguồn và working tree tại nhánh đích. Ví dụ sau khi nhánh feature được chấp nhận:

   ```powershell
   git switch develop
   git merge feature/shopping-cart
   ```

3. Nếu xung đột, dùng `git status` để xác định file và đọc thay đổi từ cả hai nhánh. Xử lý theo tiêu chí đã chấp nhận, stage file đã xử lý và chạy lại kiểm thử bị ảnh hưởng trước khi dùng `git merge --continue`. Nếu cần quyết định nghiệp vụ, báo rõ file và nội dung xung đột.
4. Sau merge, kiểm tra lịch sử và working tree. Dùng `git merge-base --is-ancestor <commit-nhiệm-vụ> develop` để xác nhận tích hợp vào `develop` hoặc thay bằng nhánh đích tương ứng. Giữ nhánh nguồn và chỉ push hoặc xóa nhánh theo yêu cầu riêng.

## Release và hotfix

- **Release.** Tạo `release/v1.0.0` từ `develop` bằng `git switch -c release/v1.0.0 develop`. Hoàn thiện phiên bản và chạy kiểm thử hồi quy. Khi được chấp nhận và có yêu cầu merge, tích hợp vào `main` và đồng bộ nhánh release về `develop`. Gắn tag Semantic Versioning vào commit phát hành trên `main`, ví dụ `git tag -a v1.0.0 -m "Release v1.0.0"`.
- **Hotfix.** Tạo `hotfix/<mô-tả>` từ `main` cho lỗi khẩn cấp của bản đã phát hành. Chạy kiểm thử cho lỗi và hồi quy liên quan. Khi được chấp nhận và có yêu cầu merge, tích hợp vào `main` rồi đồng bộ nhánh hotfix về `develop`. Gắn tag bản vá nếu phát hành, ví dụ `v1.0.1`. Lỗi thông thường trong quá trình phát triển đi qua `fix/*`.

## Git Worktree và Codex Desktop

Xác định nhánh và thư mục làm việc khi giao nhiệm vụ cho thread. Một thread mới có thể dùng chung checkout đang mở. Khi làm song song, ưu tiên mỗi nhiệm vụ một worktree riêng để tránh thay đổi chồng lên nhau. Tái sử dụng worktree phù hợp nếu đã có. Khi cần tạo worktree trong Codex Desktop, dùng thao tác tạo worktree của ứng dụng. Nếu thao tác trong terminal, ví dụ sau tạo một worktree từ `develop`.

```powershell
git worktree list
git worktree add -b feature/product-catalog ../../coffee-shop-catalog develop
```

Mở thư mục worktree tương ứng trong Codex Desktop và kiểm tra nhánh thực tế trước khi chỉnh file. Mỗi worktree dùng chung lịch sử Git và có thư mục làm việc riêng.

## Thao tác cần tránh

- Giữ thay đổi sẵn có khi đổi nhánh. Tránh `git reset --hard`, `git clean -fd` và lệnh restore có thể làm mất dữ liệu.
- Tránh tự stash công việc hiện có hoặc stage hàng loạt file lẫn nhiều nhiệm vụ.
- Giữ lịch sử commit. Tránh force push, rebase hoặc amend theo cách viết lại lịch sử hiện có.
- Chỉ merge vào `develop` hoặc `main`, push hoặc xóa nhánh khi người dùng yêu cầu.
