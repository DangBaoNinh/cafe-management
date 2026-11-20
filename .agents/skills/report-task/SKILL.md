---
name: report-task
description: Generate a standardized completion report after implementing, testing, fixing, refactoring, configuring, or reviewing tasks in the Coffee Shop project. Use at the end of any development task to summarize actual changes, verification results, remaining issues, and next steps.
---

# Báo cáo nhiệm vụ Coffee Shop

Kết thúc nhiệm vụ bằng báo cáo tiếng Việt trong hội thoại, kể cả khi chỉ hoàn thành một phần hoặc gặp trở ngại. Giữ đúng bảy mục và thứ tự dưới đây. Viết ngắn gọn, dựa trên thay đổi và kết quả quan sát được. Tôn trọng tiêu chí chấp nhận của UC. Dùng báo cáo trong hội thoại cho nhiệm vụ nhỏ và chỉ cập nhật `docs/PROGRESS.md` khi tiến độ dự án thực sự thay đổi.

## 1. THÔNG TIN NHIỆM VỤ

Ghi mã nhiệm vụ hoặc UC nếu có, tên, mục tiêu và một trạng thái **Hoàn thành**, **Hoàn thành một phần**, **Bị chặn** hoặc **Chưa thực hiện**. Chỉ ghi UC hoàn thành khi đáp ứng tiêu chí chấp nhận.

## 2. CÔNG VIỆC ĐÃ THỰC HIỆN

Liệt kê công việc thực tế. Nêu rõ chức năng đã hoàn chỉnh và chức năng mới triển khai một phần. Để công việc dự kiến ở mục 7.

## 3. DANH SÁCH FILE THAY ĐỔI

Chỉ liệt kê các file thực sự được tạo, chỉnh sửa hoặc xóa trong nhiệm vụ. Ưu tiên file quan trọng và nhóm các thay đổi tương tự khi danh sách dài. Nếu nhiệm vụ chỉ đọc, ghi rõ kết quả đó.

| File | Hành động | Mô tả |
| --- | --- | --- |
| Đường dẫn | Tạo mới / Chỉnh sửa / Xóa | Nội dung thay đổi |

## 4. KẾT QUẢ KIỂM THỬ

Ghi lệnh đã chạy, số test thành công và thất bại nếu công cụ cung cấp số liệu, và chức năng đã xác minh. Nêu kiểm tra còn thiếu và lý do. Khi rà soát UC, giữ kết quả theo từng tiêu chí chấp nhận.

- **PASS** — Đã chạy và thành công.
- **FAIL** — Đã chạy và thất bại.
- **NOT RUN** — Chưa chạy. Nêu lý do.
- **BLOCKED** — Không thể chạy. Nêu điều kiện môi trường hoặc phụ thuộc còn thiếu.

Chỉ ghi **PASS** khi có kết quả kiểm tra thực tế.

## 5. LỖI VÀ VẤN ĐỀ CÒN TỒN TẠI

Ghi lỗi chưa xử lý, chức năng còn thiếu, giới hạn kỹ thuật và rủi ro thực tế với module liên quan. Khi rà soát UC, kèm mức độ, vị trí và bước tái hiện cho từng lỗi. Nếu chưa phát hiện vấn đề, giới hạn kết luận trong phạm vi đã kiểm tra.

## 6. HƯỚNG DẪN KIỂM TRA THỦ CÔNG

Khi có nội dung cần người dùng kiểm tra, ghi cách khởi chạy, URL hoặc màn hình, thao tác và kết quả mong đợi. Nếu không cần kiểm tra thủ công, ghi “Không áp dụng”.

## 7. CÔNG VIỆC TIẾP THEO

Ghi bước hợp lý tiếp theo theo kế hoạch hiện tại và các phụ thuộc hoặc điều kiện cần có. Thực hiện bước đó khi người dùng yêu cầu.
