# Phân tích nghiệp vụ Coffee Shop

## 1. Mục tiêu và hiện trạng

Coffee Shop giúp khách chọn đồ uống, chốt giá, đặt giao tận nơi hoặc nhận tại quán và theo dõi đơn. Nhân viên xử lý đơn từ lúc tiếp nhận đến bàn giao. Quản trị viên điều hành danh mục, phân quyền và theo dõi doanh thu của một cửa hàng.

Base Project đã có trang chủ, layout khách và quản trị, cùng Laravel Fortify cho đăng ký, đăng nhập, đăng xuất, đặt lại mật khẩu và xác nhận mật khẩu. Trang hồ sơ Livewire hiện hỗ trợ tên, email và bảo mật tài khoản. Mail đặt lại mật khẩu ở local đi vào log. Gate `access-admin` hiện từ chối truy cập. UC01 và UC02 sẽ rà soát và dùng lại các luồng này. Địa chỉ, phân quyền nghiệp vụ, danh mục bán hàng, giỏ, checkout, đơn và thanh toán là thiết kế của Task 02.

## 2. Tác nhân và trách nhiệm

| Tác nhân | Trách nhiệm và phạm vi |
| --- | --- |
| Khách vãng lai | Xem, tìm và lọc đồ uống công khai. Có thể đăng ký hoặc đăng nhập. |
| Khách hàng | Sửa hồ sơ và địa chỉ của mình, chọn tùy chọn, quản lý giỏ, đặt và theo dõi đơn của mình, hủy khi đủ điều kiện. |
| Nhân viên | Làm việc với hàng đợi của cửa hàng, xác nhận hoặc từ chối, cập nhật pha chế và bàn giao, đối chiếu thanh toán theo quyền. |
| Quản trị viên | Phân quyền, vận hành danh mục, phí, khuyến mãi và đơn, tra cứu khách hàng, xem báo cáo. |
| Cổng thanh toán và đối tác giao hàng | Tác nhân tích hợp ở phạm vi mở rộng. MVP dùng thao tác đối chiếu và giao nhận của cửa hàng. |

## 3. Yêu cầu chức năng và phạm vi

| Nhóm | Phạm vi MVP | Use Case |
| --- | --- | --- |
| Danh tính và khách hàng | Xác thực, hồ sơ, địa chỉ, phân quyền vận hành và tra cứu khách | UC01–UC04, UC22 |
| Danh mục và cấu hình món | Danh mục, sản phẩm, size và giá, topping hợp lệ, lựa chọn đường và đá | UC05–UC10 |
| Giỏ và mua hàng | Giỏ, phí giao hàng, mã giảm giá, giao tận nơi hoặc nhận tại quán, COD hoặc chuyển khoản thủ công | UC11–UC14 |
| Vòng đời đơn | Lịch sử, theo dõi, hủy, tiếp nhận, pha chế, bàn giao và quản trị đơn | UC15–UC19, UC21 |
| Thanh toán và doanh thu | Đối chiếu chuyển khoản, xác nhận COD, ghi nhận hoàn thủ công và tổng hợp doanh thu | UC20, UC23 |

MVP phục vụ **một cửa hàng**, dùng VND và thời gian nghiệp vụ theo `Asia/Ho_Chi_Minh`. Cửa hàng tự điều phối giao nhận. Hai UC mở rộng là cổng thanh toán tự động và tích hợp đối tác vận chuyển (UC24–UC25). Quản lý đa cửa hàng, kho nguyên liệu, POS và bàn là đề xuất ngoài phạm vi thiết kế hiện hành.

## 4. Luồng chính và ngoại lệ

1. Khách xem menu, chọn món, size, đường, đá, topping và số lượng. Sau khi đăng nhập, khách thêm cấu hình vào giỏ.
2. Khách chọn cách nhận và phương thức thanh toán. Hệ thống kiểm tra lại khả năng bán, giá, phí và mã giảm tại thời điểm chốt. Nếu giá hoặc tùy chọn đổi, khách xem lại tổng và xác nhận lại.
3. Checkout tạo một đơn, snapshot các dòng, khoản thanh toán và lịch sử trạng thái trong cùng giao dịch. Giỏ được làm trống sau khi đơn được lưu. Gửi lại cùng khóa không tạo đơn thứ hai.
4. Nhân viên tiếp nhận, kiểm tra đơn rồi xác nhận hoặc từ chối có lý do. Đơn chuyển khoản chỉ được pha chế sau khi đối chiếu đủ tiền. Đơn COD được ghi nhận tiền tại bàn giao.
5. Khi khách hủy đúng trạng thái, cửa hàng ngừng xử lý đơn. Tiền đã nhận chuyển sang chờ hoàn thủ công và được lưu lịch sử. Chi tiết chuyển trạng thái nằm trong [quy trình đơn hàng](ORDER_WORKFLOW.md).

## 5. Yêu cầu phi chức năng

| Yêu cầu | Cách kiểm tra khi triển khai |
| --- | --- |
| Phân quyền phía máy chủ | Thử truy cập với khách, nhân viên, admin và khách khác chủ đơn. Endpoint từ chối yêu cầu ngoài quyền dù gọi trực tiếp. |
| Chính xác tiền và nhất quán | Kiểm thử số tiền VND nguyên, chênh lệch giá, checkout lặp, hai thao tác cùng đơn và lỗi giữa transaction trên MySQL thử nghiệm. |
| Bảo mật và riêng tư | CSRF cho web, validation backend, giới hạn đăng nhập, escape khi hiển thị. Không lộ mật khẩu, bí mật và dữ liệu khách trong log. |
| Khả dụng trên màn hình nhỏ | Kiểm thử menu, giỏ, checkout và lịch sử ở chiều rộng 360 px và màn hình desktop. Form có nhãn, lỗi và thao tác bàn phím. |
| Hiệu năng và truy vết | Phân trang danh sách, eager load quan hệ và dùng chỉ mục theo truy vấn. Kiểm tra EXPLAIN trên dữ liệu đại diện và lịch sử có tác nhân, thời điểm, lý do. |
| Vận hành | Kiểm tra migrate trên database thử nghiệm có dữ liệu mẫu, khôi phục bản sao lưu thử và cấu hình mail/HTTPS theo môi trường trước demo. |

## 6. Quyết định cần xác nhận

| Mã | Cần xác nhận | Giả định thiết kế để review |
| --- | --- | --- |
| Q01 | Khu vực giao, biểu phí và lịch nhận đơn | Danh sách quận/huyện cấu hình, một phí giao cố định, nhận tại quán phí 0 và chưa đặt theo khung giờ. |
| Q02 | Mức đường, đá và giới hạn topping | Mức enum cố định, topping tối đa một phần mỗi loại. Sản phẩm đánh dấu có cho chọn đường/đá. |
| Q03 | Thông tin chuyển khoản, thời hạn chờ và quyền hoàn tiền | Nhân viên đối chiếu sao kê. Admin xác nhận hoàn thực tế. Chưa tự động hết hạn hoặc hoàn tiền. |
| Q04 | Chính sách khuyến mãi và hủy | Một mã trên một đơn, giới hạn tổng/lượt mỗi khách tùy mã. Khách hủy ở trạng thái chờ xác nhận. |
| Q05 | Công thức doanh thu dùng khi nghiệm thu | Doanh thu thanh toán của đơn đã hoàn tất, theo ngày hoàn tất, tách doanh số món, giảm giá và phí giao. |

Các giả định là đầu vào review. Cần xác nhận riêng thời hạn lưu dữ liệu và cách xử lý yêu cầu xóa tài khoản theo BR20. Khi có quyết định mới, cập nhật quy tắc, UC liên quan và thiết kế dữ liệu cùng nhau.

## 7. Nguồn đặc tả

[Danh sách UC và nhiệm vụ kỹ thuật](USE_CASE_TASKS.md) quản lý trạng thái và phụ thuộc. [Quy tắc nghiệp vụ](BUSINESS_RULES.md), [thiết kế dữ liệu](DATABASE_DESIGN.md), [kiến trúc](ARCHITECTURE.md) và [quy trình đơn hàng](ORDER_WORKFLOW.md) là nguồn tham chiếu khi triển khai.
