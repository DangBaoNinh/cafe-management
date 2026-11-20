# Danh sách Use Case và nhiệm vụ kỹ thuật

Danh sách dưới đây quản lý nhánh, phụ thuộc và trạng thái. Có **25 UC**, gồm **23 UC MVP** và **2 UC mở rộng**, cùng **3 nhiệm vụ kỹ thuật**. Mỗi mã giữ nguyên khi thêm việc mới. Các UC được sắp theo thứ tự phụ thuộc, nên UC20 xuất hiện trước UC18 và UC19.

## Trạng thái và cách dùng

| Trạng thái | Ý nghĩa |
| --- | --- |
| TODO | Chưa triển khai theo đặc tả. |
| IN_PROGRESS | Đang triển khai trên nhánh công việc. |
| IMPLEMENTED | Đã có mã nhưng chưa xác minh đủ yêu cầu. UC01 và UC02 có code Starter Kit để rà soát và tận dụng. |
| VERIFIED | Đã kiểm thử đạt tiêu chí của UC và có bằng chứng. Chờ nghiệm thu hoặc tích hợp. |
| ACCEPTED | Người dùng đã nghiệm thu và commit đã được tích hợp vào develop. |
| BLOCKED | Đang bị chặn. Ghi lý do, dependency và cách tháo gỡ trong PROGRESS. |

P0 là luồng lõi hoặc điều kiện bắt buộc. P1 là nghiệp vụ MVP hoàn thiện sau nền tảng. P2 là mở rộng triển khai khi được giao. Phụ thuộc phải có đầu ra đủ và đã được xác minh trên cơ sở dùng để triển khai. Nếu chưa dùng được từ develop, báo mã việc chặn và chờ người dùng. Xem [các quyết định Q01–Q05 đã nghiệm thu](BUSINESS_ANALYSIS.md#6-quyết-định-nghiệp-vụ-đã-xác-nhận). Trạng thái từng UC chỉ thay đổi theo kết quả triển khai, kiểm thử và tích hợp UC đó.

## Nhiệm vụ kỹ thuật

| Mã | Công việc | Tác nhân | Mô tả | Ưu tiên | Phụ thuộc | Nhánh dự kiến | Trạng thái | Tiêu chí nghiệm thu chính |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TECH01 | Nền tảng dữ liệu | Nhóm phát triển | Rà soát 8 bảng hiện có, quy ước migration, đơn vị VND, UTC, InnoDB, constraint và tổ chức migration theo UC. | P0 | — | `chore/database-foundation` | ACCEPTED | MySQL thử nghiệm chạy migration nền, quy ước FK và chỉ mục được đối chiếu với ERD, có kiểm chứng dữ liệu cũ. |
| TECH02 | Nền tảng phân quyền | Nhóm phát triển | Bổ sung vai trò và cờ hoạt động vào users, policy/gate chung và lệnh/luồng khởi tạo admin có kiểm soát. | P0 | TECH01 | `feature/authorization-foundation` | ACCEPTED | Guest, customer, staff, admin và tài khoản khóa được kiểm thử đúng quyền, dữ liệu cũ mặc định customer. |
| TECH03 | Môi trường và kiểm thử tích hợp | Nhóm phát triển | Chuẩn hóa môi trường Laragon, mail local, MySQL thử riêng và kiểm thử CI cho transaction/khóa hàng. | P0 | TECH01 | `chore/integration-test-foundation` | ACCEPTED | Lệnh test và cấu hình chạy được với SQLite và MySQL thử, dữ liệu thật được bảo toàn và bí mật giữ ngoài Git. |

## Use Case theo thứ tự phụ thuộc

| Mã | Tên | Tác nhân | Phạm vi | Mô tả | Ưu tiên | Phụ thuộc | Đặc tả | Nhánh dự kiến | Trạng thái | Tiêu chí nghiệm thu chính |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UC01 | Xác thực tài khoản | Khách vãng lai, khách hàng | MVP | Dùng lại Fortify để đăng ký, đăng nhập, đăng xuất và đặt lại mật khẩu | P0 | TECH02, TECH03 | [UC01.md](use-cases/UC01.md) | `feature/authentication` | ACCEPTED | Đăng ký tạo đúng một customer; user bị khóa không truy cập route bảo vệ; reset hợp lệ dùng được một lần. |
| UC02 | Quản lý hồ sơ cá nhân | Khách hàng | MVP | Rà soát trang hồ sơ Livewire và đổi mật khẩu có sẵn | P0 | UC01 | [UC02.md](use-cases/UC02.md) | `feature/customer-profile` | ACCEPTED | Email hợp lệ được chuẩn hóa; email trùng không ghi; đổi mật khẩu đúng cho phép đăng nhập lại; action tự xóa không xóa tài khoản. |
| UC03 | Quản lý địa chỉ giao hàng | Khách hàng | MVP | Lưu và chọn địa chỉ thuộc tài khoản | P0 | UC02 | [UC03.md](use-cases/UC03.md) | `feature/delivery-addresses` | ACCEPTED | Tạo/sửa/xóa đúng chủ và địa chỉ mặc định duy nhất. Sau khi tích hợp UC14, xóa/sửa địa chỉ không thay đổi snapshot đơn cũ. |
| UC04 | Quản lý tài khoản và vai trò | Quản trị viên | MVP | Cấp quyền customer, staff và admin, đồng thời khóa hoặc mở tài khoản | P0 | TECH02, UC01 | [UC04.md](use-cases/UC04.md) | `feature/account-roles` | ACCEPTED | Staff/guest nhận 403 hoặc chuyển đăng nhập; giữ ít nhất một admin hoạt động; user bị khóa bị từ chối ở lần truy cập kế. |
| UC05 | Quản lý danh mục | Quản trị viên | MVP | Tạo, sắp xếp và bật/tắt danh mục | P0 | UC04 | [UC05.md](use-cases/UC05.md) | `feature/category-management` | ACCEPTED | Slug trùng bị từ chối; ẩn danh mục làm ẩn sản phẩm thuộc danh mục; lịch sử không mất. |
| UC06 | Quản lý sản phẩm | Quản trị viên | MVP | Quản lý nội dung món, ảnh và trạng thái | P0 | UC05 | [UC06.md](use-cases/UC06.md) | `feature/product-management` | ACCEPTED | Lưu ảnh hợp lệ, từ chối file sai, mô tả được escape, món tắt không thể mua. |
| UC07 | Quản lý size và topping | Quản trị viên | MVP | Thiết lập mức giá theo size và topping hợp lệ theo món | P0 | UC06 | [UC07.md](use-cases/UC07.md) | `feature/product-options` | ACCEPTED | Unique theo món/mã size và cặp gắn; giá nguyên không âm; tùy chọn ngừng bán không xuất hiện trong thêm mới. |
| UC08 | Xem danh mục và sản phẩm | Khách vãng lai, khách hàng | MVP | Xem menu và trang chi tiết món công khai | P0 | UC07 | [UC08.md](use-cases/UC08.md) | `feature/product-catalog` | ACCEPTED | Ẩn món/danh mục tắt và món không size, giá từ chỉ xét size hoạt động, khách chưa login vẫn xem được. |
| UC09 | Tìm kiếm và lọc sản phẩm | Khách vãng lai, khách hàng | MVP | Thu hẹp menu theo từ khóa, danh mục và khoảng giá | P1 | UC08 | [UC09.md](use-cases/UC09.md) | `feature/catalog-search` | ACCEPTED | Lọc kết hợp trả đúng, phân trang giữ query, giỏ/đơn không bị ghi. |
| UC10 | Tùy chỉnh đồ uống | Khách vãng lai, khách hàng | MVP | Chọn size, đường, đá, topping và xem giá mỗi ly | P0 | UC08 | [UC10.md](use-cases/UC10.md) | `feature/drink-customization` | TODO | Giá đúng BR06; không cộng phụ thu đường/đá; topping khác món bị từ chối. |
| UC11 | Quản lý giỏ hàng | Khách hàng | MVP | Thêm, sửa số lượng và xóa dòng cấu hình món | P0 | UC01, UC10 | [UC11.md](use-cases/UC11.md) | `feature/shopping-cart` | TODO | Hai tập topping cùng phần tử tạo một dòng; giá do server tính; thao tác chéo trả lỗi. |
| UC12 | Cấu hình phí và vùng giao hàng | Quản trị viên | MVP | Cấu hình hỗ trợ giao, phạm vi và phí cố định | P0 | UC04 | [UC12.md](use-cases/UC12.md) | `feature/delivery-settings` | TODO | Giao hợp lệ tính đúng phí; nhận tại quán luôn phí 0; thay phí không sửa đơn đã tạo. |
| UC13 | Quản lý mã giảm giá | Quản trị viên | MVP | Tạo mã theo giá cố định hoặc phần trăm với giới hạn | P1 | UC04 | [UC13.md](use-cases/UC13.md) | `feature/coupon-management` | TODO | Mã trùng bị từ chối; hết hạn đúng biên; giảm tính theo BR09 và checkout khóa mã theo BR10. |
| UC14 | Đặt hàng và chọn thanh toán | Khách hàng | MVP | Chốt một đơn giao hoặc nhận tại quán bằng COD hoặc chuyển khoản thủ công | P0 | UC03, UC11, UC12, UC13 | [UC14.md](use-cases/UC14.md) | `feature/checkout` | TODO | Tổng đúng trên hai cách nhận và hai phương thức; retry cho một đơn; lỗi giữa giao dịch giữ giỏ và không để bản ghi dở dang. |
| UC15 | Theo dõi và xem lịch sử đơn | Khách hàng | MVP | Xem danh sách, chi tiết, tiến trình và trạng thái thanh toán đơn của mình | P0 | UC14 | [UC15.md](use-cases/UC15.md) | `feature/customer-order-tracking` | TODO | Hai khách không thấy đơn nhau; tên, giá và địa chỉ giữ nguyên sau chỉnh catalog; không lộ lý do nội bộ. |
| UC16 | Hủy đơn của khách | Khách hàng | MVP | Hủy đơn còn chờ xác nhận và thể hiện kết quả tài chính | P0 | UC15 | [UC16.md](use-cases/UC16.md) | `feature/customer-order-cancellation` | TODO | Chỉ pending được hủy; order và payment đổi cùng nhau; lượt mã được tính lại; hai thao tác tranh chấp chỉ một thành công. |
| UC17 | Tiếp nhận và quyết định đơn | Nhân viên, quản trị viên | MVP | Xem hàng đợi và xác nhận hoặc từ chối đơn mới | P0 | UC14 | [UC17.md](use-cases/UC17.md) | `feature/order-intake` | TODO | Chỉ trạng thái pending có thể quyết định; từ chối bắt buộc lý do; hai người nhận cùng đơn chỉ một thành công. |
| UC20 | Đối chiếu thanh toán và hoàn tiền | Nhân viên, quản trị viên | MVP | Đối chiếu chuyển khoản, hỗ trợ xác nhận COD khi bàn giao và ghi hoàn thủ công | P0 | UC14 | [UC20.md](use-cases/UC20.md) | `feature/manual-payment` | TODO | Chuyển khoản đúng số tiền chuyển paid một lần; thanh toán không khớp giữ nguyên; hoàn có tham chiếu và đúng quyền. |
| UC18 | Cập nhật pha chế | Nhân viên, quản trị viên | MVP | Đưa đơn hợp lệ vào pha chế rồi đánh dấu sẵn sàng | P0 | UC17, UC20 | [UC18.md](use-cases/UC18.md) | `feature/order-preparation` | TODO | Đúng thứ tự, COD pending vẫn bắt đầu được, chuyển khoản pending bị chặn, mốc hiển thị cho chủ đơn. |
| UC19 | Bàn giao và hoàn tất đơn | Nhân viên, quản trị viên | MVP | Bàn giao tại quán hoặc giao hàng và hoàn tất đơn đúng điều kiện | P0 | UC18, UC20 | [UC19.md](use-cases/UC19.md) | `feature/order-handoff` | TODO | Giao hàng không bỏ bước đang giao; pickup không qua đang giao; COD và đơn ghi nguyên tử, retry không ghi lặp. |
| UC21 | Quản trị và tra cứu đơn | Quản trị viên | MVP | Tra cứu tất cả đơn và thực hiện hủy ngoại lệ có lý do | P1 | UC16, UC17, UC19, UC20 | [UC21.md](use-cases/UC21.md) | `feature/admin-order-management` | TODO | Bộ lọc trả đúng, phải có lý do hủy và lưu actor, đơn đã giao không đổi. |
| UC22 | Tra cứu khách hàng | Quản trị viên | MVP | Tra cứu hồ sơ khách và tóm tắt đơn phục vụ hỗ trợ | P1 | UC04, UC15, UC21 | [UC22.md](use-cases/UC22.md) | `feature/customer-management` | TODO | Chỉ hiện khách đúng lọc, không lộ mật khẩu/token, điều hướng đơn đúng chủ. |
| UC23 | Dashboard và thống kê doanh thu | Quản trị viên | MVP | Tổng hợp doanh thu, số đơn và trạng thái theo khoảng ngày | P1 | UC19, UC20 | [UC23.md](use-cases/UC23.md) | `feature/revenue-dashboard` | TODO | Số liệu khớp bộ dữ liệu đối chiếu; biên 00:00 chính xác; tổng thu bằng tạm tính trừ giảm cộng phí. |
| UC24 | Thanh toán qua cổng trực tuyến | Khách hàng, cổng thanh toán | Mở rộng | Mở rộng quy trình với phiên thanh toán và sự kiện đã xác thực | P2 | UC20, UC19 | [UC24.md](use-cases/UC24.md) | `feature/payment-gateway` | TODO | Mỗi sự kiện hợp lệ chỉ áp một lần; mất callback có quy trình đối chiếu; giao dịch sai giá trị bị từ chối. |
| UC25 | Điều phối qua đối tác vận chuyển | Nhân viên, quản trị viên, đối tác vận chuyển | Mở rộng | Mở rộng đơn giao với mã vận đơn và cập nhật đối tác | P2 | UC19 | [UC25.md](use-cases/UC25.md) | `feature/delivery-provider` | TODO | Không tạo trùng vận đơn khi thử lại; cập nhật đúng thứ tự; pickup giữ nguyên; quy tắc thu COD vẫn áp dụng. |
