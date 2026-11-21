# Quy tắc nghiệp vụ Coffee Shop

Mỗi quy tắc có mã ổn định để đặc tả UC tham chiếu. Các quyết định nghiệp vụ đã nghiệm thu và thông số cấu hình còn cần điền được ghi tại [Q01–Q05](BUSINESS_ANALYSIS.md#6-quyết-định-nghiệp-vụ-đã-xác-nhận).

| Mã | Quy tắc |
| --- | --- |
| BR01 | Khách vãng lai chỉ đọc menu công khai và thực hiện xác thực. Khách đã đăng nhập chỉ thao tác hồ sơ, địa chỉ, giỏ và đơn thuộc tài khoản mình. Kiểm tra ownership ở backend. |
| BR02 | Mỗi tài khoản có đúng một vai trò `customer`, `staff` hoặc `admin` và trạng thái hoạt động. Tự đăng ký mặc định là customer. Admin đang hoạt động phân quyền, không tự hạ vai trò hoặc khóa mình. Bảo vệ admin hoạt động cuối cùng. |
| BR03 | Địa chỉ thuộc một khách. Khách có thể đặt một địa chỉ mặc định. Địa chỉ được sao chép vào đơn giao tại checkout để thay đổi hồ sơ sau này không sửa đơn. |
| BR04 | Menu chỉ hiện danh mục và sản phẩm hoạt động với ít nhất một size hoạt động. Sản phẩm hoặc tùy chọn đã có lịch sử được ngừng bán thay cho xóa dữ liệu liên quan. |
| BR05 | Mỗi dòng món chọn một size hợp lệ. Mức đường và đá là `none`, `less`, `standard`, `extra` khi món cho phép. Nếu không cho chọn, lưu null và dùng cách pha của món. Topping phải hoạt động và được gắn cho món. Mỗi loại topping tối đa một phần trên một ly theo Q02. |
| BR06 | Tiền dùng số nguyên VND. Giá dòng là `số lượng × (giá size + tổng giá topping)`. Size có giá bán đầy đủ. Đường và đá không cộng giá. Tổng đơn là `tạm tính - giảm giá + phí giao` và không âm. |
| BR07 | Mỗi khách có một giỏ. Dòng cùng món, size, đường, đá và tập topping chuẩn hóa được cộng số lượng. Giỏ có phiên bản, giá xem trước và được kiểm tra lại trước khi chốt. |
| BR08 | Đơn giao yêu cầu tên, điện thoại, địa chỉ hợp lệ và vùng hỗ trợ. Phí dùng mức cấu hình tại lúc đặt. Đơn nhận tại quán dùng tên và điện thoại, không gửi địa chỉ giao và có phí 0. |
| BR09 | Mỗi đơn áp tối đa một mã. Mã hoạt động trong khoảng `starts_at ≤ hiện tại < ends_at`, đạt ngưỡng tạm tính, giới hạn lượt và điều kiện loại giảm. Giảm theo phần trăm số nguyên làm tròn xuống, có thể có mức trần, và không vượt tạm tính. |
| BR10 | Tính lượt dùng từ đơn gắn mã, bỏ đơn đã hủy hoặc từ chối. Khóa bản ghi mã trong transaction checkout khi kiểm tra tổng lượt và lượt mỗi khách. Hủy hoặc từ chối giải phóng lượt sử dụng. |
| BR11 | Khóa giỏ và kiểm tra phiên bản, cấu hình, tổng tiền đã xác nhận trước khi tạo đơn. Lưu đơn, dòng snapshot, payment, lịch sử ban đầu và làm trống giỏ trong cùng transaction. Dùng khóa idempotency theo khách và hash yêu cầu. Cùng khóa/cùng nội dung trả đơn cũ. Cùng khóa/khác nội dung báo xung đột. |
| BR12 | Đơn đổi trạng thái theo ma trận trong [quy trình đơn](ORDER_WORKFLOW.md#2-chuyển-trạng-thái-đơn). API kiểm tra trạng thái kỳ vọng, quyền, điều kiện thanh toán và ghi lịch sử trong cùng transaction. |
| BR13 | Khách chỉ hủy đơn của mình khi `pending`. Nhân viên từ chối đơn `pending` có lý do. Admin được hủy có lý do ở `pending`, `confirmed`, `preparing`, `ready`. Đơn đã bàn giao đi giao hoặc đã hoàn tất qua quy trình hỗ trợ ngoài MVP khi có sự cố. |
| BR14 | COD được ghi nhận khi bàn giao cho khách. Hoàn tất đơn COD cập nhật tiền và đơn trong một transaction. Chuyển khoản phải đối chiếu dựa trên số tiền và tham chiếu thực tế, chỉ được bắt đầu pha chế sau khi payment là `paid`. |
| BR15 | Hủy hoặc từ chối đơn chưa thu tiền chuyển payment sang `voided`. Đã thu tiền chuyển sang `refund_pending`. Admin chỉ ghi `refunded` khi đã thực hiện hoàn ngoài hệ thống và có tham chiếu hoàn. |
| BR16 | Nhân viên có quyền xử lý đơn cửa hàng và đối chiếu thanh toán. Admin có thêm quyền cấu hình, tra cứu khách, hủy ngoại lệ, xác nhận hoàn và báo cáo. Các vai trò vận hành không được bỏ qua quy tắc trạng thái. |
| BR17 | Doanh thu MVP tính tổng đơn `completed` có payment `paid`, theo `completed_at` trong khoảng ngày địa phương nửa mở. Tách tạm tính, giảm giá và phí giao. Không cộng đơn đang xử lý, hủy hoặc từ chối. |
| BR18 | Giá, tên món, size, topping, mã giảm, phí, dữ liệu người nhận và địa chỉ trên đơn dùng snapshot. Lịch sử trạng thái lưu actor, thời điểm, trạng thái trước/sau và lý do khi bắt buộc. Không hiện ghi chú nội bộ hoặc tham chiếu riêng cho khách. |
| BR19 | Tất cả nhiệm vụ trên đơn thực hiện trên bản ghi đúng quyền, giữ khóa hàng khi có cập nhật đồng thời và xử lý xung đột bằng yêu cầu tải lại. Thanh toán và vận chuyển tự động là mở rộng, dùng xác thực nguồn sự kiện và chống xử lý lặp. |
| BR20 | MVP tạm chặn tự xóa tài khoản ở backend để bảo toàn dữ liệu liên kết và lịch sử. Admin có thể khóa tài khoản theo BR02. Chính sách giữ và xóa dữ liệu được chốt trước khi mở luồng tự xóa. |

## Ma trận quyền tóm tắt

| Năng lực | Khách | Nhân viên | Admin |
| --- | --- | --- | --- |
| Địa chỉ, giỏ, đặt và theo dõi | Dữ liệu của mình | — | — |
| Tiếp nhận, pha chế và bàn giao | — | Đơn của cửa hàng | Đơn của cửa hàng |
| Xác nhận chuyển khoản và COD | — | Theo quy trình | Theo quy trình |
| Vai trò, danh mục, phí, ưu đãi, tra cứu khách và doanh thu | — | — | Được cấp |
| Hủy ngoại lệ và xác nhận hoàn | — | — | Theo BR13 và BR15 |

## Quy tắc mở rộng UC26 và UC27

Các quy tắc dưới đây là thiết kế đề xuất cho hai UC mới.

| Mã | Quy tắc |
| --- | --- |
| BR21 | Hẹn giờ chỉ áp dụng khi nhận tại quán, phí giao bằng 0 theo BR08. Kiểm tra khung, thời hạn đặt và sức chứa trên backend. Cấp chỗ trong transaction checkout. Hủy hoặc từ chối trả chỗ và làm phiếu mất hiệu lực. |
| BR22 | Phiếu điện tử có mã tra cứu duy nhất, khó đoán. Chỉ chủ đơn xem phiếu, chỉ staff/admin đang hoạt động tra cứu và bàn giao. Kiểm tra đơn sẵn sàng, thanh toán và hiệu lực mã. Ghi bàn giao và dùng phiếu trong cùng transaction, áp dụng BR14 và UC19. |
| BR23 | Một đặt chỗ giữ một bàn hoặc một phòng đủ sức chứa, đang hoạt động, trong giờ mở cửa. Hai lịch giữ chỗ trên cùng tài nguyên không được giao nhau theo khoảng `[bắt đầu, kết thúc)`. Khóa tài nguyên và kiểm tra lại khi ghi. |
| BR24 | Khách chỉ xem hoặc hủy đặt chỗ của mình. Staff/admin tra lịch, hủy có lý do, check-in, ghi không đến hoặc hoàn tất theo UC27. Chỉ admin cấu hình danh mục chỗ. Hủy giải phóng khoảng đã giữ. Ghi lịch sử trạng thái và tác nhân trong transaction. |
