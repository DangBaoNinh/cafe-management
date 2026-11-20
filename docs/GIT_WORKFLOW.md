# Quy trình Git của Coffee Shop

## Chiến lược phân nhánh

| Nhánh | Mục đích | Ví dụ |
| --- | --- | --- |
| `main` | Phiên bản ổn định đã được phát hành | `main` |
| `develop` | Tích hợp thay đổi đã nghiệm thu | `develop` |
| `docs/*` | Phân tích, thiết kế và tài liệu | `docs/system-design` |
| `feature/*` | Chức năng mới hoặc nhóm UC phụ thuộc chặt chẽ | `feature/product-catalog` |
| `fix/*` | Sửa lỗi | `fix/cart-quantity` |
| `refactor/*` | Tái cấu trúc mã | `refactor/order-pricing` |
| `test/*` | Bổ sung kiểm thử độc lập | `test/order-permissions` |
| `chore/*` | Cấu hình, công cụ và bảo trì | `chore/git-workflow` |

Tạo một nhánh cho mỗi nhiệm vụ độc lập từ `develop`. Phần tên sau tiền tố dùng chữ thường và dấu gạch ngang giữa các từ. Tích hợp thay đổi vào `develop`, rồi phát hành từ `develop` về `main` theo Git Flow.

## Bắt đầu nhiệm vụ

1. Đọc `AGENTS.md` và tài liệu liên quan. Xác nhận thư mục gốc, nhánh thực tế, thay đổi đã stage hoặc chưa stage và danh sách worktree.

   ```powershell
   git rev-parse --show-toplevel
   git branch --show-current
   git status --short --branch
   git worktree list
   ```

2. Nếu checkout hiện tại có thay đổi, xác định chúng thuộc nhiệm vụ nào. Tiếp tục trên nhánh tương ứng nếu đã đúng phạm vi. Nếu thay đổi liên quan đến nhiệm vụ khác, giữ nguyên checkout đó và dùng checkout hoặc worktree riêng khi phù hợp.
3. Khi có thể chuyển nhánh an toàn, tạo nhánh từ `develop` cho nhiệm vụ. Ví dụ:

   ```powershell
   git switch -c docs/system-design develop
   git status --short --branch
   ```

   Nếu nhánh đã tồn tại, dùng `git switch docs/system-design` sau khi xác nhận checkout an toàn. Nếu nhánh đã được mở trong worktree khác, dùng worktree đó.

## Phát triển, kiểm thử và commit

1. Làm việc trên nhánh nhiệm vụ, giới hạn thay đổi trong phạm vi đã giao. Chạy kiểm thử phù hợp, chẳng hạn `php artisan test` đối với thay đổi Laravel hoặc `npm run build` nếu task thay đổi frontend.
2. Kiểm tra cả thay đổi đã stage và chưa stage. Stage từng file hoặc nhóm file đã rà soát. Giữ `.env`, thông tin đăng nhập, token, khóa riêng, dữ liệu sao lưu, log và dependency sinh tại máy khỏi commit.

   ```powershell
   git status --short
   git diff
   git add -- AGENTS.md docs/GIT_WORKFLOW.md
   git diff --cached
   git diff --cached --check
   ```

3. Commit theo mốc có thể review độc lập. Dùng tiêu đề ngắn nêu hành động, ví dụ `docs: document Git workflow` hoặc `fix: validate cart quantity`. Sau commit, kiểm tra `git log -1 --oneline` và `git status --short --branch`. Báo cáo nhánh hiện tại, nhánh cơ sở, hash commit, working tree, kết quả kiểm thử và trạng thái merge.

## Review và merge

1. Review phạm vi, tiêu chí chấp nhận và kiểm thử bắt buộc trước khi chấp nhận nhánh. Có thể so sánh thay đổi bằng `git diff develop...docs/system-design`.
2. Khi người dùng yêu cầu merge, xác nhận cả nhánh nguồn và checkout của `develop` ở trạng thái phù hợp. Từ checkout của `develop`, chạy `git merge docs/system-design` với nhánh đã được chấp nhận.
3. Nếu có xung đột, xem `git status`, đọc cả hai phiên bản và giải quyết theo hành vi đã được chấp nhận. Stage các file đã xử lý, chạy lại kiểm thử bị ảnh hưởng rồi dùng `git merge --continue`. Nếu chưa thể giải quyết an toàn, báo file xung đột và quyết định còn thiếu trước khi tiếp tục.
4. Sau merge, xác nhận lịch sử và working tree. Có thể dùng `git merge-base --is-ancestor <commit-nhiệm-vụ> develop` để kiểm tra commit đã được tích hợp. Giữ nhánh nguồn. Thực hiện push hoặc xóa nhánh khi có yêu cầu riêng.

## Codex Desktop và Git Worktree

Một thread có thể chia sẻ checkout với thread khác. Trước khi sửa mã, kiểm tra nhánh và đường dẫn checkout thực tế của thread. Khi làm song song, giao mỗi nhiệm vụ một nhánh và một working directory riêng. Tái sử dụng worktree phù hợp đã có.

Ví dụ tạo một worktree dành cho nhánh mới từ thư mục gốc dự án:

```powershell
git worktree add -b feature/product-catalog ../../coffee-shop-catalog develop
git worktree list
```

Mở thư mục worktree đó trong Codex Desktop và kiểm tra lại nhánh trước khi thao tác. Worktree dùng chung lịch sử Git nhưng có working tree riêng. Nếu cần chạy ứng dụng, cấu hình môi trường và dependency phù hợp cho worktree đó.

## Thao tác cần tránh

- Tránh `git reset --hard`, `git clean -fd` và các lệnh restore có thể làm mất thay đổi. Giữ nguyên thay đổi đang làm khi cần đổi nhánh.
- Tránh tự stash công việc hiện có hoặc stage hàng loạt khi có thay đổi thuộc nhiều nhiệm vụ.
- Giữ lịch sử đã chia sẻ. Tránh force push, rebase hoặc amend các commit đã chia sẻ và xóa nhánh khi chưa được yêu cầu.
