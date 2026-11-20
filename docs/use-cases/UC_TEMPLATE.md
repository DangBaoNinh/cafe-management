# [Mã Use Case] — [Tên Use Case]

## Thông tin chung

- **Mã Use Case** — `UC-<số>`
- **Tên Use Case** — *Tên thể hiện mục tiêu của tác nhân*
- **Mục tiêu** — *Kết quả tác nhân cần đạt*
- **Tác nhân** — *Tác nhân chính và tác nhân phụ nếu có*

## Điều kiện

- **Tiền điều kiện** — *Trạng thái, dữ liệu và quyền cần có trước khi bắt đầu*
- **Hậu điều kiện** — *Trạng thái và dữ liệu sau khi thành công*

## Luồng chính

1. *Tác nhân thực hiện hành động khởi đầu*
2. *Hệ thống xử lý và trả kết quả cho tác nhân*

## Luồng thay thế

- **A1** — *Bước rẽ nhánh, điều kiện, các bước thay thế và điểm quay lại hoặc kết thúc*

## Luồng ngoại lệ

- **E1** — *Bước gặp lỗi, điều kiện, phản hồi cho tác nhân và trạng thái dữ liệu sau lỗi*

## Quy tắc nghiệp vụ

- *Mã hoặc nội dung quy tắc áp dụng, kèm tham chiếu nếu có*

## Dữ liệu đầu vào

- *Trường, nguồn, định dạng, bắt buộc hay tùy chọn và ràng buộc*

## Kết quả đầu ra

- *Phản hồi, dữ liệu được tạo hoặc cập nhật và trạng thái cuối*

## Tiêu chí chấp nhận

- **AC-01** — *Với điều kiện ban đầu cụ thể, khi tác nhân thực hiện hành động thì hệ thống cho kết quả có thể kiểm tra*
- **AC-02** — *Với dữ liệu sai hoặc thiếu quyền, hệ thống xử lý theo luồng ngoại lệ và bảo toàn dữ liệu*
