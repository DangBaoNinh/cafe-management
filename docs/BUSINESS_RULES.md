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

Ngày 23/11/2026, người dùng xác nhận ngừng tạo lịch hẹn nhận đồ uống UC26 từ checkout. Các quy tắc giờ hẹn, đổi lịch và không đến của UC26 bên dưới chỉ còn áp dụng cho lịch cũ; UC27 tiếp tục hoạt động. Checkout mới không tạo phiếu hẹn và không chịu hạn chế đặt lịch theo BR28.

Các quy tắc dưới đây áp dụng cho hai UC mới. Thông số vận hành đã được người dùng xác nhận.

| Mã | Quy tắc |
| --- | --- |
| BR21 | Hẹn giờ chỉ áp dụng khi nhận tại quán, phí giao bằng 0 theo BR08. Backend kiểm tra giờ nhận và thời hạn đặt. Số đơn theo giờ nhận không giới hạn. Hủy hoặc từ chối làm phiếu mất hiệu lực. |
| BR22 | Phiếu điện tử có mã tra cứu duy nhất, khó đoán. Chỉ chủ đơn xem phiếu, chỉ staff/admin đang hoạt động tra cứu và bàn giao. Kiểm tra đơn sẵn sàng, thanh toán và hiệu lực mã. Ghi bàn giao và dùng phiếu trong cùng transaction, áp dụng BR14 và UC19. |
| BR23 | Một đặt chỗ giữ một bàn hoặc một phòng đủ sức chứa, đang hoạt động, trong giờ mở cửa. Sơ đồ từng tầng hiển thị chỗ trống, đã giữ và đang sử dụng. Chỗ đã giữ hoặc đang sử dụng không thể đặt. Mỗi chỗ có tối đa một lượt giữ đang hiệu lực. Backend khóa tài nguyên và kiểm tra lại khi ghi. |
| BR24 | Khách chỉ xem hoặc hủy đặt chỗ của mình. Staff/admin tra lịch, hủy có lý do, check-in, ghi không đến hoặc hoàn tất theo UC27. Chỉ admin cấu hình danh mục chỗ. Hủy hoặc không đến giải phóng chỗ. Sau check-in, chỗ tiếp tục ở trạng thái đang sử dụng đến khi khách rời đi và nhân viên xác nhận trả chỗ. Khách chỉ chọn giờ đến và không có giới hạn thời lượng sử dụng theo lượt đặt. Ghi lịch sử trạng thái và tác nhân trong transaction. |
| BR25 | Quán mở 07:00–23:00 theo giờ Việt Nam. Đặt lịch trước ít nhất 60 phút. Giờ hẹn hợp lệ từ 07:00 đến 22:30, tính cả hai mốc, theo bước 5 phút. Áp dụng cho tạo lịch và đổi lịch của UC26 và UC27. Mỗi tầng có 5 bàn tối đa 4 người, 5 bàn tối đa 2 người và 1 phòng họp tối đa 10 người. |
| BR26 | Chỉ được gửi yêu cầu đổi trước giờ hẹn hiện tại. Đổi lịch hợp lệ thu cố định 10.000 ₫ mỗi lần. Giờ mới chênh tối đa 60 phút so với giờ hẹn đang có và phải thỏa điều kiện đặt lịch, kể cả lịch trống với UC27. Vượt 60 phút thì đặt lại theo luồng tạo lịch mới. Hiển thị phụ phí trước khi xác nhận. Nếu đổi thất bại, giữ lịch cũ. |
| BR27 | Đến trễ 5 phút đầu miễn phí. Từ phút thứ 6, thu 2.000 ₫ cho mỗi phút vượt 5 phút và chốt tiền tại lần đầu nhân viên ghi nhận khách đến. Ví dụ trễ 6 phút thu 2.000 ₫, trễ 7 phút thu 4.000 ₫. Từ mốc giờ hẹn cộng 30 phút, lịch chưa ghi nhận khách đến bị hủy vì không đến. Với lịch 22:30, mốc này là 23:00. Khách đã check-in sử dụng bàn/phòng trong giờ mở cửa. |
| BR28 | Ghi nhận không đến theo tài khoản, dùng chung cho UC26 và UC27. Hai lần liên tiếp khóa quyền đặt hai loại lịch trong 7 ngày kể từ lần thứ hai. Một lượt đến hợp lệ đặt lại chuỗi về 0. Khách vẫn đăng nhập, mua hàng bình thường và xem lịch đã đặt. Hết 7 ngày, quyền đặt lịch tự mở lại. Chỉ hủy do không đến mới tính vào chuỗi. |
| BR29 | Màn hình tra mã hiển thị từng phụ phí đổi lịch hoặc đến trễ, số tiền, kênh thu và trạng thái Chờ thanh toán, Đã thanh toán hoặc Thất bại. Kênh ứng dụng chỉ chuyển Đã thanh toán khi hệ thống xác nhận giao dịch khớp khoản phí, số tiền và tham chiếu. Phí đã thanh toán hiển thị là đã thu và không cộng vào số phải trả tại quầy. Với kênh tại quầy, hóa đơn tự cộng khoản phí đúng một lần. Chỉ chuyển khoản phí từ ứng dụng sang quầy sau khi lần thanh toán trực tuyến đã thất bại, hết hạn hoặc được hủy. Ghi nhận kết quả và chuyển kênh theo cùng một khoản phí để tránh thu trùng. |

## Quy tắc mở rộng UC28 đến UC30

| Mã | Quy tắc |
| --- | --- |
| BR30 | Trang chủ hiển thị tối đa 5 món còn bán có số ly cao nhất trong ngày hiện tại và 29 ngày trước theo giờ Việt Nam. Chỉ tính món từ giao dịch đã hoàn tất và thanh toán. Gộp size và topping theo món. Đồng hạng xếp theo ID món tăng dần. |
| BR31 | Dùng định lượng đã lưu lúc tạo đơn hoặc lượt gọi. Trừ tồn đúng một lần khi bắt đầu pha chế, cùng giao dịch với cập nhật trạng thái. Thiếu định lượng hoặc tồn thì giữ nguyên cả hai. Hủy sau pha chế không tự hoàn kho. Điều chỉnh theo lượng thực tế có lý do và người ghi. |
| BR32 | Một bàn/phòng có tối đa một phiên gọi món chưa đóng. Nhân viên mở phiên cho khách đã check-in hoặc khách ghé trực tiếp. Khách gọi bằng QR gắn với chỗ và mã hiệu lực theo phiên, hoặc nhờ nhân viên nhập hộ. Gửi lại cùng yêu cầu chỉ ghi một lượt. |
| BR33 | Khi chốt, khóa gọi thêm và xử lý hết lượt còn dang dở. Hóa đơn chỉ cộng các món đã phục vụ và khoản phụ phí phải thu tại quầy theo BR29. Ghi nhận thanh toán tại quầy một lần. Chỉ đóng phiên, thu hồi mã và trả chỗ sau khi đã thanh toán và nhân viên ghi nhận khách rời đi. |
| BR34 | Khách đã đăng nhập chỉ xem, tạo và phản hồi cuộc trao đổi hỗ trợ của chính mình. Staff hoặc admin có thể xem, phản hồi và đóng cuộc trao đổi. Tin nhắn được lưu lịch sử; khách không gửi thêm vào cuộc trao đổi đã đóng mà tạo cuộc trao đổi mới. |
