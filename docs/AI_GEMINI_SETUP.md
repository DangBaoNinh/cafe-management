# Cấu hình chatbox AI Gemini

## 1. Cấu hình API key

Không đưa API key thật vào Git hoặc file nén. Tạo/copy `.env.ai.example` vào cấu hình local:

```env
GEMINI_API_KEY=your-real-gemini-api-key
GEMINI_MODEL=gemini-3.5-flash-lite
```

Đặt hai dòng này trong file `.env` ở thư mục gốc project.

## 2. Xóa cache cấu hình

Từ thư mục project chạy:

```bash
php artisan config:clear
php artisan cache:clear
```

Nếu Laravel đang chạy bằng `php artisan serve`, hãy dừng và chạy lại:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

## 3. Kiểm tra chatbox

Mở website khách hàng, bấm biểu tượng trợ lý và gửi:

```text
Tôi muốn một món cà phê ít ngọt, giá dưới 40.000đ. Hãy gợi ý món phù hợp.
```

Kết quả mong đợi: AI trả lời bằng tiếng Việt và hiển thị các món đang bán từ database.

## 4. Luồng chức năng

```text
Khách gửi câu hỏi
→ Laravel lấy menu đang bán
→ Gemini tư vấn
→ hệ thống xác minh product ID
→ hiển thị món và giá server
→ khách chọn size
→ thêm vào giỏ
```

Nếu Gemini lỗi hoặc hết quota, chatbox dùng danh sách món fallback và không làm hỏng website.

## 5. Bảo mật

- Không commit `.env`.
- Không đưa API key vào Blade, JavaScript hoặc ảnh chụp màn hình.
- Không gửi mật khẩu, thông tin thẻ hoặc dữ liệu thanh toán vào chat.
- Nếu key đã bị lộ, thu hồi key cũ và tạo key mới trên Google AI Studio.
