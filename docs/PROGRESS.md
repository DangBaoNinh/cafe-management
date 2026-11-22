# Tiến độ Coffee Shop

## Giai đoạn 01 — Đã triển khai

- Khởi tạo bộ Livewire chính thức của Laravel trong thư mục dự án. Đã cài dependency và lưu `composer.lock`, `package-lock.json`.
- Cấu hình tên Coffee Shop, application key, locale `vi`, múi giờ `Asia/Ho_Chi_Minh` và phiên đăng nhập lưu trong MySQL.
- Tạo schema `coffee_shop` trên MySQL 8.4.3 với `utf8mb4`. Đã chạy ba migration nền cho người dùng, cache và hàng đợi, gồm bảng session và token đặt lại mật khẩu.
- Có trang chủ, layout khách hàng, khu vực tài khoản với hồ sơ Livewire và layout riêng tại `/admin`. Route quản trị hiện yêu cầu đăng nhập và từ chối quyền truy cập của tài khoản khách hàng.
- Dùng Fortify cho xác thực bằng email và mật khẩu. Email đặt lại mật khẩu được ghi vào log ở môi trường local.
- Commit nền tảng Laravel trên `main` và `develop` tại `571f166`. Git bỏ qua `.env`, biến thể cục bộ của `.env`, `vendor`, `node_modules`, file build và log.

## Giai đoạn 02 — Thiết kế đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu toàn bộ thiết kế Task 02, xác nhận các phương án nghiệp vụ MVP Q01–Q05 cùng cách xử lý tài khoản theo BR20 và cho phép tích hợp `docs/system-design` vào `develop`. Bản Word là bản trình bày của bộ thiết kế tại thời điểm bàn giao. Trạng thái nghiệm thu được ghi trong Markdown.

- Đã soạn [phân tích nghiệp vụ](BUSINESS_ANALYSIS.md), [quy tắc](BUSINESS_RULES.md), [thiết kế dữ liệu](DATABASE_DESIGN.md), [kiến trúc](ARCHITECTURE.md) và [luồng đơn hàng](ORDER_WORKFLOW.md).
- [Sổ công việc](USE_CASE_TASKS.md) có 25 UC, trong đó 23 UC MVP và 2 UC mở rộng. Có 3 nhiệm vụ kỹ thuật. UC01 và UC02 dùng lại Starter Kit và cần rà soát theo tiêu chí mới.
- ERD có 18 bảng nghiệp vụ. Tính 7 bảng hạ tầng, thiết kế có 25 bảng, ngoài bảng theo dõi migration của Laravel.
- Đã thiết lập lệnh ngắn trong `AGENTS.md` và bản trình bày [Word](reports/PHAN_TICH_THIET_KE_HE_THONG.docx). Các UC và schema nghiệp vụ đang ở giai đoạn thiết kế.
- Đã kiểm tra tính duy nhất của mã, thứ tự phụ thuộc, liên kết Markdown, tên bảng ERD và nội dung đặc tả trong Word. Microsoft Word mở được báo cáo và cập nhật mục lục. Lượt kiểm thử hồi quy nền tảng chạy 29 bài với 69 assertion.

## Giai đoạn 03 — Nền tảng

### TECH01 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu TECH01. Commit `3745c63` từ `chore/database-foundation` đã được tích hợp vào `develop`. Laravel và phiên MySQL dùng UTC, MySQL tạo bảng mới bằng InnoDB. [Thiết kế dữ liệu](DATABASE_DESIGN.md) ghi đối chiếu 8 bảng hiện có với ERD và quy ước VND, thời gian, FK, chỉ mục, constraint và migration theo UC.
- MySQL 8.4.3 chạy đủ 3 migration trên schema thử trống. Kiểm tra đạt cho InnoDB, `utf8mb4`, khóa và chỉ mục nền, từ chối email trùng và ghi thời điểm UTC.
- Trên schema thử có dữ liệu mô phỏng theo cấu hình cũ UTC+7, chuyển sang UTC giữ nguyên bản ghi của cả 8 bảng và thời điểm lưu qua mốc 00:00 giờ Việt Nam. Token đặt lại mật khẩu cũ vẫn hợp lệ. Schema `coffee_shop` hiện có chỉ được đọc để đối chiếu metadata.
- Kiểm thử hồi quy đạt 29 bài với 69 assertion trên SQLite. Pint cho hai file cấu hình và PHPStan đều đạt.

### TECH02 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu TECH02 tại commit `fdf23fc`. Nhánh `feature/authorization-foundation` đã được tích hợp vào `develop` tại merge commit `a81b91c`. Đã thêm vai trò và trạng thái tài khoản, gate quản trị và vận hành đơn, kiểm tra khóa tài khoản ở tầng xác thực và lệnh khởi tạo admin đầu tiên. [Kiến trúc ứng dụng](ARCHITECTURE.md#nền-tảng-phân-quyền-tech02)
- SQLite đạt 39 bài kiểm thử và 114 assertion, gồm quyền của guest, customer, staff, admin, từ chối phiên và yêu cầu Livewire của tài khoản khóa, kiểm soát dữ liệu đăng ký và khởi tạo admin. Pint và PHPStan đạt.
- MySQL 8.4.3 thử nghiệm xác nhận migration giữ nguyên các trường của tài khoản mô phỏng và gán mặc định customer đang hoạt động. Kiểm tra mật khẩu cũ, token ghi nhớ, từ chối vai trò sai và tranh chấp khóa khởi tạo đều đạt.
- Đã chạy migration bổ sung trên Laragon local. Bảng `users` trống trước migration. Việc khởi tạo admin trên schema `coffee_shop` đang chờ thông tin tài khoản của người vận hành.

### TECH03 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu TECH03 tại commit `412c7a9`. Nhánh `chore/integration-test-foundation` đã được tích hợp vào `develop` tại merge commit `2664f00`. Bộ mặc định chạy SQLite trong bộ nhớ. Bộ tích hợp MySQL dùng hai kết nối riêng đến `coffee_shop_test`, chạy migration tiến và kiểm tra rollback nhiều bảng cùng tranh chấp khóa hàng. Tài khoản MySQL thử bị từ chối khi đọc `coffee_shop`. Cấu hình thử trỏ đến `coffee_shop` bị từ chối trước khi kết nối.
- `composer ci:check` đạt Pint, PHPStan và 29 bài SQLite với 69 assertion. MySQL 8.4.3 đạt 2 bài với 26 assertion. Mailpit trên loopback nhận thư mẫu từ Laravel. Thông tin đăng nhập MySQL thử nằm trong file local được Git bỏ qua.
- Workflow GitHub Actions đã thêm service MySQL 8.4 và bước chạy bộ tích hợp. `actionlint` đã xác nhận cú pháp workflow.

### Kiểm tra sau tích hợp TECH02 và TECH03

- Trên `develop`, `composer ci:check` đạt Pint, PHPStan và 39 bài SQLite với 114 assertion. `php artisan test --testsuite=MySQL` đạt 2 bài với 26 assertion. `actionlint` xác nhận workflow và `php artisan migrate:status` xác nhận đủ 4 migration trên Laragon.
- Đã cấu hình `origin` trên GitHub. Workflow `tests` trên `develop` tại `f805aef` chạy thành công. [Kết quả GitHub Actions](https://github.com/DangBaoNinh/cafe-management/actions/runs/44754477616)
- Đã tích hợp `chore/git-workflow` và cập nhật tài liệu theo quy ước phát triển từ `develop`. [Quy trình Git](GIT_WORKFLOW.md)

### UC01 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC01 tại commit `c5d8d0d`. Nhánh `feature/authentication` đã được tích hợp vào `develop` tại merge commit `2038772`. Dùng các route Fortify hiện có để đăng ký, đăng nhập, đăng xuất và đặt lại mật khẩu. Đăng ký tạo đúng một tài khoản customer đang hoạt động với email chuẩn hóa. Form hiển thị lỗi tiếng Việt khi vượt giới hạn đăng nhập hoặc gửi dữ liệu không hợp lệ.
- Khi gửi hoặc dùng token đặt lại mật khẩu, giao dịch khóa hàng tài khoản trên MySQL. Đặt lại thành công cập nhật mật khẩu, xoay token ghi nhớ và vô hiệu hóa liên kết đã dùng. Kiểm thử giả lập lỗi giữa chừng xác nhận mật khẩu cũ và token đặt lại vẫn còn hiệu lực.
- `composer ci:check` đạt Pint, PHPStan và 44 bài SQLite với 196 assertion. `php artisan test --testsuite=MySQL` đạt 3 bài với 49 assertion, gồm đặt lại mật khẩu, từ chối dùng lại token và đăng xuất với phiên MySQL. `npm run build` thành công. Mailpit nhận thư tiếng Việt có liên kết đặt lại hạn 60 phút từ route quên mật khẩu trên dữ liệu thử cô lập.

### UC02 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC02 tại commit `a594a0f`. Nhánh `feature/customer-profile` đã được tích hợp vào `develop` tại merge commit `8dbc557`. Livewire chỉ lưu họ tên và email của chủ phiên. Email được chuẩn hóa, email trùng bị từ chối và dữ liệu cũ được giữ nguyên. Khi đổi email, giao dịch xóa token đặt lại mật khẩu gắn với email cũ.
- Mục Bảo mật dùng xác nhận Fortify và kiểm tra lại thời hạn ở action Livewire. Đổi mật khẩu yêu cầu mật khẩu hiện tại còn đúng tại thời điểm ghi. Tự xóa tài khoản đã được gỡ khỏi giao diện và action cũ trả HTTP 403.
- `composer ci:check` đạt Pint, PHPStan và 46 bài SQLite với 251 assertion, gồm kiểm tra thông báo tiếng Việt khi xác nhận sai mật khẩu. `php artisan test --testsuite=MySQL` đạt 4 bài với 67 assertion. `npm run build` thành công.

### UC03 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC03 tại commit `dd93caf`. Nhánh `feature/delivery-addresses` đã được tích hợp vào `develop` tại merge commit `731bccc`.
- Khách hàng quản lý địa chỉ tại `/settings/addresses`. Backend kiểm tra quyền sở hữu và đối chiếu mã tỉnh, huyện, xã theo danh mục ba cấp trước 01/07/2025. Khả năng giao hàng được kiểm tra tại checkout theo BR08. [Nguồn và giấy phép danh mục](../resources/data/vietnam-legacy-addresses.NOTICE.txt)
- Migration tạo `user_addresses` đã chạy trên Laragon local. Giao dịch khóa hàng khách khi ghi và khóa unique sinh từ `is_default` giới hạn tối đa một địa chỉ mặc định. Xóa địa chỉ mặc định để trống mặc định.
- `composer ci:check` đạt Pint, PHPStan và 55 bài SQLite với 307 assertion. `php artisan test --testsuite=MySQL` đạt 6 bài với 88 assertion, gồm kiểm tra cạnh tranh khóa trên hai kết nối và ràng buộc unique. `npm run build` thành công. Edge chạy đạt luồng tạo, sửa, đặt mặc định và xác nhận xóa trên dữ liệu cô lập.
- Tiêu chí giữ nguyên snapshot đơn được kiểm chứng sau khi tích hợp UC14.

### UC04 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC04 tại commit `7e0ba0a`. Nhánh `feature/account-roles` đã được tích hợp vào `develop` tại merge commit `2244573`.
- Admin đang hoạt động có thể tìm và lọc tại `/admin/accounts`, xác nhận mật khẩu qua Fortify rồi đổi vai trò hoặc khóa, mở khóa tài khoản. Yêu cầu dùng dữ liệu quyền đã cũ được hướng dẫn tải lại trang.
- Giao dịch khóa bản ghi người thao tác và tài khoản đích theo thứ tự ID rồi kiểm tra lại quyền. Admin không thể tự hạ quyền hay tự khóa. Tài khoản bị khóa mất quyền trên yêu cầu bảo vệ tiếp theo, kể cả khi đang có phiên.
- `composer ci:check` đạt Pint, PHPStan và 63 bài SQLite với 367 assertion. `php artisan test --testsuite=MySQL` đạt 7 bài với 100 assertion, gồm tranh chấp khóa giữa hai kết nối và kiểm tra lại quyền của admin đã bị hạ vai trò. `npm run build` thành công.
- Schema Laragon local chưa có admin hoạt động. Luồng quản trị đã được kiểm thử qua HTTP với tài khoản thử cô lập. Brave xác nhận chỉ bảng cuộn ngang ở chiều rộng 390 px sau bản sửa bố cục.

### UC05 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC05 tại commit `a8b0d12`. Nhánh `feature/category-management` đã được tích hợp vào `develop` tại merge commit `823836c`.
- Admin quản lý danh mục tại `/admin/categories`. Danh sách phân trang theo thứ tự hiển thị. Backend từ chối slug trùng, slug sai định dạng và số thứ tự âm.
- Migration `categories` và `products` đã chạy trên Laragon local. Khi ẩn danh mục, truy vấn `Product::inActiveCategory()` loại món thuộc danh mục đó và dữ liệu vẫn được giữ. Khóa ngoại ngăn xóa danh mục đã có món. Menu công khai và điều kiện size hoạt động thuộc UC08.
- `composer ci:check` đạt Pint, PHPStan và 67 bài SQLite với 411 assertion. Bộ MySQL đạt 8 bài với 113 assertion, gồm ràng buộc slug, khóa ngoại và truy vấn theo trạng thái danh mục. `npm run build` thành công. Brave ở 390 px chạy đạt luồng tạo và ẩn danh mục với dữ liệu thử cô lập.

### UC06 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC06 tại commit `93b753c`. Nhánh `feature/product-management` được tích hợp vào `develop` tại merge commit `29f3f86`. Pint, PHPStan, 72 bài SQLite, 9 bài MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- Admin thêm, sửa và đổi trạng thái sản phẩm tại `/admin/products`. Mô tả hiển thị dưới dạng văn bản. Ảnh là tùy chọn, nhận JPEG, PNG hoặc WebP tối đa 2 MiB. Laragon local đã có liên kết `public/storage` cho đĩa `public`.
- Lưu ảnh mới và cập nhật đường dẫn trước khi dọn ảnh cũ. Khi lỗi ghi cơ sở dữ liệu, hệ thống dọn ảnh mới và giữ ảnh cũ. Ngừng bán giữ bản ghi và loại món khỏi truy vấn `Product::active()->inActiveCategory()`.
- `composer ci:check` đạt Pint, PHPStan và 72 bài SQLite với 483 assertion. Bộ MySQL cô lập đạt 9 bài với 127 assertion, gồm thay ảnh, dọn ảnh khi trùng slug và ngừng bán. `npm run build` thành công. Brave ở 390 px xác minh tải và hiển thị ảnh, sửa trạng thái, và trang danh sách lẫn trang sửa không cuộn ngang toàn trang.
- Điều kiện size hoạt động, hiển thị menu, mua hàng và lịch sử đơn sẽ được kiểm tra qua các UC tương ứng khi có luồng hoàn chỉnh.

### UC07 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC07 tại commit `de5f22e`. Nhánh `feature/product-options` được tích hợp vào `develop` tại merge commit `81b294a`. Pint, PHPStan, 77 bài SQLite, 10 bài MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- Admin quản lý giá và trạng thái size theo món tại `/admin/products/{product}/options` và quản lý topping tại `/admin/toppings`. Mã được lưu chữ thường. Giá VND là số nguyên từ 0 đến 4.294.967.295 theo kiểu cột MySQL.
- Ba migration mới đã chạy trên Laragon local. Cơ sở dữ liệu ràng buộc mã size duy nhất trong từng món, mã topping duy nhất và cặp gắn duy nhất. Ngừng bán giữ size hoặc topping và loại khỏi `activeSizes()` hoặc `activeToppings()`. Bỏ gắn chỉ xóa liên kết với món tương ứng.
- `composer ci:check` đạt Pint, PHPStan và 77 bài SQLite với 595 assertion. Bộ MySQL cô lập đạt 10 bài với 143 assertion. `npm run build` thành công. Brave ở 390 px xác nhận tạo size, tạo và gắn topping, ngừng bán rồi bỏ gắn. Các trang danh sách và cấu hình đã kiểm tra không cuộn ngang toàn trang.
- Kiểm tra lựa chọn công khai, giỏ cũ và snapshot đơn chờ các UC tương ứng.

### UC08 đã nghiệm thu

- Ngày 20/11/2026, người dùng nghiệm thu UC08 tại commit `43a4809`. Nhánh `feature/product-catalog` được tích hợp vào `develop` tại merge commit `a89ab2c`. Pint, PHPStan, 81 bài SQLite, 11 bài MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- Khách chưa đăng nhập xem được `/menu` và `/menu/{slug}`. Thực đơn lọc theo slug danh mục, phân trang 12 món và giữ danh mục cùng số trang khi quay về từ chi tiết. Danh mục đang hoạt động nhưng trống có thông báo riêng.
- Danh sách và chi tiết chỉ công khai món đang hoạt động, thuộc danh mục đang hoạt động và có ít nhất một size đang bán. Chi tiết ngoài điều kiện trả 404. Giá từ và bảng giá chỉ xét size đang bán. Ảnh dùng đĩa công khai đã cấu hình. UC08 không cần migration.
- `composer ci:check` đạt Pint, PHPStan và 81 bài SQLite với 656 assertion. Bộ MySQL cô lập đạt 11 bài với 159 assertion. `npm run build` thành công. Brave ở 360 px và 1280 px xác nhận ảnh tải được, giá đúng, phân trang và quay lại giữ bộ lọc, trạng thái rỗng, và không cuộn ngang toàn trang.

### UC09 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC09 tại commit `9c50d89`. Nhánh `feature/catalog-search` được tích hợp vào `develop` tại merge commit `2936430`. Pint, PHPStan, 86 test SQLite, 12 test MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- `/menu` hỗ trợ tìm theo tên tối đa 100 ký tự, kết hợp danh mục, khoảng giá VND và sắp xếp theo tên hoặc giá. Khoảng giá gồm cả hai mốc và so với giá rẻ nhất trong các size đang bán. Chỉ món công khai được trả về.
- Phân trang và liên kết quay về từ trang món giữ bộ lọc hợp lệ. Khách có thể bỏ từng bộ lọc hoặc xóa toàn bộ. Khoảng giá sai, giá âm, kiểu dữ liệu sai và sắp xếp ngoài danh sách trả 422. Trang HTML giữ giá trị đã nhập và hiển thị lỗi tiếng Việt.
- `composer ci:check` đạt Pint, PHPStan và 86 test SQLite với 710 assertion. Bộ MySQL cô lập đạt 12 test với 172 assertion. Bộ lọc chỉ phát sinh SELECT trong kiểm thử với tài khoản khách hàng. `npm run build` thành công. Brave ở 360 px và 1280 px xác nhận lọc kết hợp, phân trang, giữ query khi quay lại, bỏ bộ lọc và sửa khoảng giá sai. Các trang thử không cuộn ngang toàn trang.

### UC10 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC10 tại commit `0844e5a`. Nhánh `feature/drink-customization` được tích hợp vào `develop` tại merge commit `323f06b`. Pint, PHPStan, 92 test SQLite, 13 test MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- Trang chi tiết món cho phép khách vãng lai và khách hàng chọn size, mức đường/đá cho món hỗ trợ, và topping đang bán được gắn với món. Mặc định là size đang bán rẻ nhất, đường/đá tiêu chuẩn nếu có và chưa chọn topping.
- Máy chủ tính giá một ly bằng giá size cộng giá các topping. Các mức đường/đá của món pha theo công thức được chuẩn hóa thành `null` và ID topping được sắp xếp. Mỗi cập nhật đọc lại tùy chọn hiện hành, từ chối lựa chọn sai hoặc đã ngừng bán và cho phép làm mới. UC10 chưa ghi giỏ và không cần migration.
- `composer ci:check` đạt Pint, PHPStan và 92 test SQLite với 758 assertion. Bộ MySQL cô lập đạt 13 test với 183 assertion. `npm run build` thành công. Brave ở 360 px và 1280 px xác nhận giá cập nhật khi đổi size, thêm/bỏ hai topping, đổi đường/đá và làm mới sau khi topping ngừng bán. Trang chi tiết không cuộn ngang toàn trang.

### UC11 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC11 tại commit `81c2d37`. Nhánh `feature/shopping-cart` được tích hợp vào `develop` tại merge commit `037dbd7`. Pint, PHPStan, 97 bài SQLite, 14 bài MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- Khách thêm món từ trang chi tiết và quản lý số lượng hoặc xóa dòng tại `/cart`. Mỗi khách có tối đa một giỏ, được tạo ở lần thêm thành công đầu tiên. Cấu hình trùng size, đường, đá và tập topping được gộp dù thứ tự topping khác nhau. Số lượng mỗi dòng từ 1 đến 65.535 theo giới hạn cột lưu trữ.
- Máy chủ định giá theo size và topping còn bán. Giỏ đánh dấu dòng có cấu hình hết hiệu lực, loại dòng đó khỏi tạm tính và vẫn cho phép xóa. Cập nhật và xóa kiểm tra phiên bản trong giao dịch khóa giỏ, từ chối yêu cầu cũ và kiểm tra dòng thuộc giỏ của khách.
- Migration ba bảng giỏ đã chạy trên Laragon local. `composer ci:check` đạt Pint, PHPStan và 97 bài SQLite với 889 assertion. Bộ MySQL cô lập đạt 14 bài với 200 assertion, gồm ràng buộc một giỏ mỗi khách, số lượng dương và hoàn tác cả dòng lẫn topping khi lỗi. `npm run build` thành công.
- Trình duyệt với dữ liệu SQLite cô lập xác nhận khách được dẫn tới đăng nhập và quay lại món. Thêm hai ly có topping cho tạm tính 86.000 ₫, cập nhật thành ba ly lên 129.000 ₫, xóa dòng cuối về 0 ₫.

### UC12 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC12 tại commit `4bce245`. Nhánh `feature/delivery-settings` được tích hợp vào `develop` tại merge commit `56f1a88`. Pint, PHPStan, 101 bài SQLite, 15 bài MySQL và bản build đều đạt khi kiểm tra lại trước merge.

- Admin cập nhật cấu hình duy nhất tại `/admin/delivery-settings`, chọn quận/huyện theo danh mục địa chỉ UC03 và nhập phí cố định từ 0 đến 4.294.967.295 VND. Mặc định tắt giao hàng. Backend từ chối mã sai, mã trùng, danh sách trống khi bật giao và biểu mẫu cũ.
- Khách xem phí tại `/cart/delivery` theo địa chỉ đã lưu của mình. Khi giao bị tắt hoặc địa chỉ ngoài vùng, khách được hướng dẫn nhận tại quán với phí 0 ₫.
- Migration đã chạy trên Laragon local. `composer ci:check` đạt Pint, PHPStan và 101 bài SQLite với 986 assertion. Bộ MySQL cô lập đạt 15 bài với 218 assertion, gồm kiểm tra giữ số 0 đầu mã quận, phí biên và từ chối phiên bản cũ. `npm run build` thành công.
- Kiểm thử UC14 xác nhận phí được lưu vào đơn khi chốt. Thay đổi cấu hình giao hàng sau đó giữ nguyên phí trên đơn đã tạo.

### UC13 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC13 tại commit `b1cffdb` gồm bản sửa khung cuộn danh sách mã. Tích hợp vào `develop` tại merge commit `628883e`. Pint, PHPStan, 106 bài SQLite, 16 bài MySQL và bản build đạt trước merge.

- Admin tạo, sửa và tắt mã tại `/admin/coupons`. Code chuẩn hóa chữ hoa và duy nhất. Mốc hiệu lực nhập theo giờ Việt Nam, lưu UTC. Mã có hiệu lực từ thời điểm bắt đầu đến trước thời điểm kết thúc.
- Hỗ trợ giảm số tiền cố định hoặc phần trăm nguyên từ 1 đến 100, ngưỡng tạm tính, trần giảm cho phần trăm và giới hạn tổng lượt hoặc lượt mỗi khách. Mức giảm phần trăm làm tròn xuống và tiền giảm không vượt tạm tính.
- Migration đã chạy trên Laragon local. `composer ci:check` đạt Pint, PHPStan và 106 bài SQLite với 1.098 assertion. Bộ MySQL cô lập đạt 16 bài với 233 assertion, gồm lưu UTC và khóa duy nhất cho code khác hoa/thường. `npm run build` thành công.
- UC14 đếm lượt từ đơn còn hiệu lực theo BR10, khóa bản ghi mã trong giao dịch checkout và lưu mã trên đơn. Kiểm tra qua hai phiên MySQL thật xác nhận chỉ một checkout dùng được lượt cuối.

### UC14 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC14 tại commit `f59109c`, gồm bản sửa thông báo địa chỉ ngoài vùng chỉ hiện một lần. Tích hợp vào `develop` tại merge commit `b511337`. Pint, PHPStan, 117 bài SQLite, 17 bài MySQL và bản build đạt trước merge.

- Khách từ `/cart` vào `/checkout`, chọn cách nhận và thanh toán rồi xem tổng VND trước khi xác nhận. Đơn giao chỉ dùng địa chỉ đã lưu thuộc khách và còn trong vùng. Đơn nhận tại quán có phí 0 ₫.
- Khi xác nhận, máy chủ khóa khách, giỏ và mã giảm nếu có, kiểm tra phiên bản giỏ và tính lại giá, phí, giảm giá. Lưu đơn, dòng món, topping, payment và hai lịch sử ban đầu rồi làm trống giỏ trong cùng giao dịch. Retry cùng khóa và nội dung trả đơn cũ. Cùng khóa với nội dung khác báo xung đột.
- Trang xác nhận hiển thị mã đơn, phương thức nhận và tổng. Chuyển khoản chỉ hiện khi cấu hình đủ `CHECKOUT_BANK_NAME`, `CHECKOUT_BANK_ACCOUNT_NUMBER` và `CHECKOUT_BANK_ACCOUNT_HOLDER`. Đã kiểm thử bằng dữ liệu mẫu. Laragon local chưa cấu hình tài khoản nhận tiền thực tế.
- Migration đã chạy trên Laragon local. `composer ci:check` đạt Pint, PHPStan và 115 bài SQLite với 1.404 assertion. `php artisan test --testsuite=MySQL` đạt 17 bài với 253 assertion. `npm run build` thành công. Kiểm tra riêng trên hai phiên MySQL có tranh khóa xác nhận một đơn được tạo và giỏ của khách còn lại được giữ.

### UC15 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC15 tại commit `44dd4ff`. Tích hợp vào `develop` tại merge commit `081c471` sau khi chạy lại Pint, PHPStan, SQLite, MySQL và bản build.

- Khách mở **Đơn của tôi** tại `/orders`, từ trang tài khoản hoặc theo liên kết **Theo dõi đơn** sau checkout. Danh sách mới nhất trước, phân trang 10 đơn và giữ bộ lọc **Đang xử lý** hoặc **Đã kết thúc** qua các trang.
- Chi tiết theo mã tại `/orders/{number}` hiển thị món, size, topping, địa chỉ và tiền đã lưu trên đơn, trạng thái thanh toán và mốc trạng thái theo giờ Việt Nam. Cùng trả 404 khi mã không tồn tại hoặc thuộc khách khác. Ghi chú nội bộ và tham chiếu thanh toán riêng không xuất hiện.
- Không có migration. `composer ci:check` đạt Pint, PHPStan và 120 bài SQLite với 1.493 assertion. Bộ MySQL đạt 17 bài với 260 assertion. `npm run build` thành công. Các bài HTTP kiểm tra phân quyền, bộ lọc, phân trang, snapshot sau khi đổi dữ liệu gốc và truy vấn chỉ đọc.

### UC16 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC16 tại commit `afd5e5d`. Tích hợp vào `develop` tại merge commit `d5cf372` sau khi chạy lại Pint, PHPStan, 125 bài SQLite, 17 bài MySQL, bản build và hai kịch bản tranh khóa MySQL.

- Chủ đơn có thể mở **Hủy đơn hàng** và xác nhận trên trang chi tiết khi đơn còn **Chờ xác nhận**. Lý do tùy chọn, tối đa 500 ký tự, được lưu trong lịch sử nghiệp vụ. Yêu cầu cũ hoặc gửi lặp báo trạng thái đã đổi.
- Máy chủ khóa đơn rồi khóa payment, kiểm tra trạng thái hiện tại và lưu trạng thái cùng hai dòng lịch sử trong một giao dịch. Đơn hủy có payment `voided` nếu chưa thu hoặc `refund_pending` nếu đã thu. Trang chi tiết hướng dẫn liên hệ cửa hàng để hoàn tiền thủ công. Đơn đã hủy được loại khỏi số lượt sử dụng mã giảm giá theo BR10.
- `composer ci:check` đạt Pint, PHPStan và 125 bài SQLite với 1.584 assertion, gồm kiểm tra rollback khi ghi lịch sử thanh toán thất bại. Bộ MySQL đạt 17 bài với 270 assertion, gồm kiểm tra dùng lại mã đã hết lượt sau hủy. `npm run build` thành công.
- Hai phiên MySQL cô lập kiểm tra cả hai thứ tự giữa khách hủy và giao dịch mô phỏng nhân viên xác nhận. Trong mỗi lượt, phiên đến sau chờ khóa rồi bị từ chối. Brave ở 360 px kiểm tra xác nhận hủy, phản hồi 419 khi thiếu CSRF, hai kết quả thanh toán và bộ lọc **Đã kết thúc**.

### UC17 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC17 tại commit `fdbf1a4`. Tích hợp vào `develop` tại merge commit `b199726` sau khi chạy lại Pint, PHPStan, 130 bài SQLite, 17 bài MySQL, bản build và bốn kịch bản tranh khóa MySQL.

- Nhân viên và quản trị viên đang hoạt động vào **Đơn chờ xác nhận** tại `/operations/orders`. Hàng đợi hiển thị đơn cũ nhất trước, 15 đơn mỗi trang. Chi tiết dùng thông tin đã lưu lúc đặt và hiển thị trạng thái thanh toán.
- Chỉ quyết định được đơn `pending`. Xác nhận ghi lịch sử và giữ nguyên thanh toán, kể cả chuyển khoản chưa đối chiếu. Từ chối bắt buộc lý do tối đa 500 ký tự, giải phóng lượt mã và chuyển thanh toán sang `voided` hoặc `refund_pending`. Trạng thái và lịch sử ghi trong cùng giao dịch.
- `composer ci:check` đạt Pint, PHPStan và 130 bài SQLite với 1.704 assertion. Bộ MySQL đạt 17 bài với 277 assertion. `npm run build` thành công. Không cần migration.
- Hai phiên MySQL kiểm tra hai nhân viên cùng xác nhận, từ chối đồng thời với xác nhận và cả hai thứ tự giữa xác nhận và khách hủy. Mỗi lượt chỉ một thao tác thành công. Brave ở 360 px kiểm tra nhập lý do, CSRF, xác nhận, từ chối và cập nhật hàng đợi.

### UC20 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC20 tại commit `f018aa4`. Tích hợp vào `develop` tại merge commit `6dc6cc7` sau khi chạy lại Pint, PHPStan, 136 bài SQLite, 18 bài MySQL, bản build và các kịch bản đối chiếu đồng thời.

- Tại `/operations/payments`, nhân viên và quản trị viên đối chiếu khoản chuyển khoản của đơn chờ xác nhận hoặc đã xác nhận. Số tiền thực thu phải khớp tổng đơn và mã giao dịch chỉ được dùng một lần. Lưu trạng thái, tham chiếu, thời điểm, tác nhân và ghi chú trong cùng giao dịch. Khách chỉ thấy trạng thái thanh toán.
- Quản trị viên ghi nhận giao dịch hoàn đã thực hiện ngoài hệ thống cho khoản `refund_pending` và nhập tham chiếu hoàn. Thu COD được ghi cùng bàn giao ở UC19, dùng cùng quy tắc đối chiếu số tiền với tổng đơn.
- Đơn chuyển khoản 0 ₫ có thể được xác nhận thanh toán mà không cần mã giao dịch. Khi hủy hoặc từ chối đơn 0 ₫, thanh toán chuyển sang `voided` vì chưa phát sinh khoản thu.
- Không cần migration. `composer ci:check` đạt Pint, PHPStan và 136 bài SQLite với 1.856 assertion. Bộ MySQL đạt 18 bài với 298 assertion. `npm run build` thành công. Brave ở 360 px kiểm tra đối chiếu sai và đúng số tiền, bảo vệ CSRF, quyền ghi nhận hoàn và mã tham chiếu dài.
- Kiểm tra hai phiên MySQL cùng đối chiếu một đơn, nhập trùng mã cho hai đơn và cả hai thứ tự giữa đối chiếu và hủy. Đối chiếu một lần duy nhất cho mỗi khoản thu. Nếu đối chiếu thành công trước khi hủy, khoản tiền chuyển sang chờ hoàn.

### UC18 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC18 tại commit `49eff9b`. Tích hợp vào `develop` tại merge commit `0a65789` sau khi chạy lại Pint, PHPStan, 141 bài SQLite, 18 bài MySQL, bản build và bốn kịch bản tranh khóa MySQL.

- Nhân viên và quản trị viên đang hoạt động vào **Pha chế** tại `/operations/preparation`. Danh sách gồm đơn đã xác nhận và đang pha chế, sắp theo thời gian đặt, 15 đơn mỗi trang. Đơn rời danh sách sau khi được đánh dấu sẵn sàng.
- Backend chỉ cho chuyển `confirmed → preparing → ready`. COD chưa thu được bắt đầu pha chế. Chuyển khoản phải được đối chiếu ở UC20 và có trạng thái `paid`. Mỗi mốc ghi tác nhân, thời điểm, trạng thái trước và sau trong cùng giao dịch, rồi hiển thị trong tiến trình của chủ đơn.
- Không cần migration. `composer ci:check` đạt Pint, PHPStan và 141 bài SQLite với 1.946 assertion. Bộ MySQL đạt 18 bài với 308 assertion. `npm run build` thành công. Brave ở 360 px kiểm tra hai mốc pha chế, chặn yêu cầu thiếu mã CSRF, chuyển khoản trước và sau đối chiếu, và tiến trình phía khách.
- Hai phiên MySQL kiểm chứng yêu cầu lặp ở cả hai mốc bị từ chối sau khi chờ khóa. Nếu giao dịch mô phỏng hủy bởi quản trị viên ghi trước, yêu cầu pha chế bị từ chối. Nếu đối chiếu chuyển khoản ghi trước, yêu cầu pha chế dùng trạng thái thanh toán đã cập nhật.

### UC19 đã nghiệm thu

- Ngày 21/11/2026, người dùng nghiệm thu UC19 tại commit `75842a3`. Tích hợp vào `develop` tại merge commit `acbce46` sau khi chạy lại Pint, PHPStan, 147 bài SQLite, 19 bài MySQL, bản build và ba kịch bản tranh khóa MySQL.

- Nhân viên và quản trị viên vào **Bàn giao** tại `/operations/handoff`. Danh sách gồm đơn sẵn sàng và đang giao, sắp theo thời gian đặt, 15 đơn mỗi trang. Đơn nhận tại quán hoàn tất trực tiếp sau khi khách nhận. Đơn giao tận nơi phải qua **Đang giao** và giữ trạng thái đó nếu chưa giao thành công.
- Bàn giao yêu cầu chuyển khoản đã `paid`. Với COD, khi hoàn tất đơn, số tiền thực thu phải khớp tổng đơn, kể cả phí giao. Cùng một giao dịch ghi `completed_at`, COD `paid` và hai lịch sử. Chủ đơn xem được các mốc bàn giao trong tiến trình.
- Không cần migration. `composer ci:check` đạt Pint, PHPStan và 147 bài SQLite với 2.057 assertion, gồm hoàn tác tiền COD khi ghi lịch sử đơn thất bại. Bộ MySQL đạt 19 bài với 329 assertion. `npm run build` thành công.
- Hai phiên MySQL kiểm chứng ba cặp yêu cầu đồng thời cho nhận tại quán, bàn giao cho người giao và hoàn tất giao hàng. Mỗi cặp chỉ ghi một lần và phiên đọc giữ trạng thái cũ cho đến khi giao dịch commit. Brave ở 360 px kiểm tra hai cách nhận, từ chối sai tiền COD, chặn yêu cầu thiếu mã CSRF và tiến trình phía khách.

### UC21 đã nghiệm thu

- Người dùng nghiệm thu UC21 tại commit `dc0d96a`. Nhánh `feature/admin-order-management` đã tích hợp vào `develop` tại merge commit `d5367f0` sau khi chạy lại Pint, PHPStan, SQLite, MySQL và bản build.

- Quản trị viên hoạt động vào **Quản lý đơn hàng** tại `/admin/orders`. Lọc theo mã hoặc đầu mã, ngày đặt theo giờ Việt Nam, trạng thái và phương thức thanh toán. Ngày kết thúc được tính trọn ngày và các bộ lọc được giữ khi phân trang.
- Chi tiết hiển thị dữ liệu đã lưu khi đặt và lịch sử đơn, thanh toán với tác nhân, thời điểm và lý do nội bộ. Hủy ngoại lệ yêu cầu lý do tối đa 500 ký tự và chỉ áp dụng ở `pending`, `confirmed`, `preparing`, `ready`. Ghi trạng thái đơn, thanh toán và lịch sử cùng giao dịch. Đơn đã hủy giải phóng lượt mã. Khoản đã thu chuyển sang `refund_pending` để ghi nhận hoàn ở UC20.
- Không cần migration. `composer ci:check` đạt Pint, PHPStan và 153 bài SQLite với 2.201 assertion. Bộ MySQL đạt 20 bài với 350 assertion. `npm run build` thành công.
- Hai phiên MySQL kiểm chứng hủy và bàn giao ở cả hai thứ tự, cùng với hai yêu cầu hủy một đơn đã thu. Chỉ thao tác giữ khóa trước thành công. Brave ở 360 px kiểm tra lọc chéo, CSRF, lý do dài, phân quyền và giữ kín lý do hủy với khách.

### UC22 đã nghiệm thu

- Người dùng nghiệm thu UC22 tại commit `86c6960`. Nhánh `feature/customer-management` đã tích hợp vào `develop` tại merge commit `2a8f7ed` sau khi chạy lại Pint, PHPStan, SQLite, MySQL và bản build.

- Quản trị viên hoạt động vào **Tra cứu khách hàng** tại `/admin/customers`. Tìm một phần tên hoặc email, lọc tài khoản hoạt động hoặc đã khóa, giữ bộ lọc khi phân trang. Danh sách và hồ sơ chỉ lấy tài khoản vai trò khách hàng.
- Hồ sơ hiển thị tên, email, trạng thái và tóm tắt đơn thuộc khách, gồm mã, giờ đặt, trạng thái và tổng tiền. Mã đơn mở chi tiết UC21. Liên kết chỉnh sửa tài khoản đi qua UC04 và yêu cầu xác nhận mật khẩu khi cần. UC22 chỉ đọc và không cần migration.
- `composer ci:check` đạt Pint, PHPStan và 157 bài SQLite với 2.287 assertion. Bộ MySQL đạt 21 bài với 365 assertion. `npm run build` thành công. Brave ở 360 px kiểm tra lọc, dữ liệu dài không tràn ngang, liên kết đến đúng đơn, lỗi bộ lọc, xác nhận mật khẩu và từ chối nhân viên.

### UC23 đã nghiệm thu

- Người dùng nghiệm thu UC23 tại commit `f7ea9f3`. Nhánh `feature/revenue-dashboard` đã tích hợp vào `develop` tại merge commit `63375bd` sau khi chạy lại Pint, PHPStan, SQLite, MySQL và bản build.

- Trang tổng quan `/admin` dành cho quản trị viên hoạt động. Bộ lọc mặc định gồm 7 ngày gần nhất kể cả hôm nay, giới hạn 366 ngày tính cả hai đầu và dùng ngày Việt Nam. Backend lấy từ 00:00 ngày đầu đến trước 00:00 ngày sau ngày cuối, quy đổi sang UTC.
- Doanh thu tổng và theo ngày hoàn tất chỉ tính đơn `completed` có thanh toán `paid`. Tách tạm tính, giảm giá, phí giao, tổng thu và số đơn đủ điều kiện. Phần trạng thái đếm theo ngày đặt. Ngày trống hiển thị 0 và báo cáo chỉ trả số liệu tổng hợp. Không cần migration.
- `composer ci:check` đạt Pint, PHPStan và 161 bài SQLite với 2.339 assertion. Bộ MySQL đạt 22 bài với 378 assertion. `npm run build` thành công. Brave ở 360 và 1.280 px kiểm tra bộ lọc, số liệu, lỗi ngày, quyền và cuộn ngang trong bảng.

### UC24 mô phỏng đã nghiệm thu

- Người dùng nghiệm thu UC24 tại commit `a9cb264`. Nhánh `feature/payment-gateway` đã tích hợp vào `develop` tại merge commit `f700d4c` sau khi chạy lại Pint, PHPStan, SQLite, MySQL và bản build.

- Dùng cổng mô phỏng cho đồ án theo phạm vi người dùng xác nhận, khả dụng ở `local` và `testing`. Chủ đơn chọn **Cổng thanh toán mô phỏng** khi checkout, mở phiên, mô phỏng thành công hoặc thất bại và có thể mở lại phiên bỏ dở.
- Sự kiện ký HMAC phải khớp kết quả, tham chiếu và số tiền VND của phiên. Callback và đối chiếu dùng cùng giao dịch để đánh dấu sự kiện và ghi lịch sử một lần. Khi thiếu callback, quản trị viên đối chiếu trong **Đối chiếu thanh toán**. Nếu giao dịch dương được báo sau khi hủy hoặc từ chối đơn, thanh toán chuyển sang chờ hoàn mô phỏng.
- Migration mới chỉ tạo bảng `demo_payment_sessions` và đã chạy trên MySQL local. `composer ci:check` đạt Pint, PHPStan và 167 bài SQLite với 2.444 assertion. Bộ MySQL đạt 23 bài với 395 assertion. `npm run build` thành công. Brave 360 px kiểm tra checkout, thử lại, thiếu callback, quyền đối chiếu qua giao diện, CSRF, callback thiếu chữ ký và độ rộng trang. Bản Word đã cập nhật phần UC24.

### UC25 mô phỏng đã nghiệm thu

- Người dùng nghiệm thu UC25 tại commit `78addff`. Nhánh `feature/delivery-provider` đã tích hợp vào `develop` tại merge commit `b41742f` sau khi chạy lại Pint, PHPStan, SQLite, MySQL và bản build.

- Nhân viên và quản trị viên mở đơn giao sẵn sàng từ **Bàn giao** để tạo vận đơn mô phỏng ở `local` và `testing`. Gửi lại trả cùng mã. Kịch bản mất kết nối trước khi tạo cho phép thử lại hoặc bàn giao thủ công. Đơn nhận tại quán tiếp tục theo UC19.
- Sự kiện có chữ ký HMAC cập nhật lần lượt mốc nhận từ quán và giao khách. Từ chối chữ ký sai, sự kiện sai thứ tự hoặc sai số tiền COD. Đánh dấu mốc vận đơn, cập nhật đơn, ghi nhận COD và lịch sử cùng giao dịch. Sự kiện đã áp dụng gửi lại không ghi thêm lịch sử.
- Migration mới chỉ tạo bảng `demo_shipments` và đã chạy trên MySQL local. `composer ci:check` đạt Pint, PHPStan, 173 bài SQLite với 2.544 assertion, gồm hoàn tác đơn và COD khi cập nhật vận đơn lỗi. Bộ MySQL đạt 24 bài với 417 assertion. `npm run build` thành công. Brave 360 px kiểm tra mất kết nối, vận đơn, hai mốc, sai COD, CSRF, chữ ký, tiến trình khách và đơn nhận tại quán. Đã đồng bộ phần UC25 trong bản Word.

### Hoàn thành phạm vi chức năng đồ án

- Đã nghiệm thu và tích hợp vào `develop` toàn bộ 23 UC MVP, hai UC mở rộng mô phỏng và ba nhiệm vụ kỹ thuật trong sổ công việc.

## Giao diện Terrasse

- Đã chốt phong cách Terrasse và triển khai trên nhánh `feature/storefront-templates` cho trang chủ, thực đơn và layout đặt món của khách hàng, cùng layout quản trị và vận hành. Tổng quan quản trị dùng số liệu báo cáo thật. Hàng đợi vận hành hiển thị thẻ đơn từ dữ liệu hiện có.
- `npm run build` thành công. SQLite đạt 173 bài kiểm thử với 2.554 assertion. Báo cáo doanh thu trên MySQL thử nghiệm đạt 1 bài với 13 assertion. Đã xác nhận trên Edge tại 320, 390 và 1440 px, gồm chuyển một đơn thử từ chờ xác nhận sang hàng đợi pha chế.

## Tích hợp bản trình diễn

- Đã nghiệm thu giao diện Terrasse, seeder thực đơn mẫu và bản sửa chuyển hướng đăng nhập. Quản trị viên đăng nhập trực tiếp vào trang tổng quan quản trị. Nếu đã mở một trang quản trị trước khi đăng nhập, ứng dụng quay lại trang đó. Nhân viên và khách tiếp tục vào trang tài khoản theo mặc định.
- `DemoMenuSeeder` thêm 24 món mẫu thuộc 6 danh mục, với 48 size và 8 topping trên cơ sở dữ liệu trống. Đã kiểm tra chạy hai lần trên SQLite mà số bản ghi không tăng. Brave ở 360 px xác nhận chuyển hướng đúng cho ba vai trò, CSS Terrasse và truy cập thực đơn mẫu.
- Bản tích hợp đạt Pint, PHPStan, 176 bài SQLite với 2.569 assertion, 24 bài MySQL với 417 assertion và `npm run build`.
- Đã xử lý xung đột với nhánh tài liệu Git Flow cũ và giữ hướng dẫn dự án hiện hành.

## Đồng bộ giao diện Terrasse

- Đã nghiệm thu các thay đổi từ `feature/terrasse-account` tại `2f73b27`. Trang tài khoản, cài đặt và chân trang cửa hàng dùng giao diện Terrasse. Chế độ sáng, tối và theo hệ thống được dùng chung giữa cửa hàng, khu quản trị và khu vận hành.
- Đã kiểm tra trên Edge tại 360 và 1440 px với dữ liệu thử. Chế độ hiển thị đồng bộ giữa hai tab, quyền truy cập và chuyển hướng đăng nhập đúng theo vai trò, các trang đã kiểm tra không cuộn ngang toàn trang. Build, Pint, PHPStan, 177 bài SQLite với 2.605 assertion và 24 bài MySQL với 417 assertion đều đạt.

## Trợ lý cửa hàng

- Đã nghiệm thu nhánh `feature/storefront-chat` tại `7d46d88` và tích hợp vào `develop` tại `fc56ffc`. Khách có thể hỏi về thực đơn, nhận gợi ý, chọn size và thêm món vào giỏ từ khung chat.
- Laravel trên Laragon đã trả lời hai lượt liên tiếp với Gemini thật và hiển thị thẻ món. SQLite đạt 180 bài với 2.633 assertion, MySQL đạt 24 bài với 417 assertion. Build, Pint và PHPStan đều đạt.

## Ảnh đồ uống demo

- Đã nghiệm thu 24 ảnh WebP và tích hợp nhánh `chore/demo-drink-photos` vào `develop` tại `c945c80`. Seeder bổ sung ảnh thiếu và giữ ảnh đã có.
- SQLite thử nghiệm xác nhận chạy lại seeder giữ nguyên số lượng dữ liệu và giữ ảnh tùy chỉnh. Laragon trả HTTP 200 cho cả 24 ảnh. 180 bài SQLite, Pint và PHPStan đều đạt.

## Kết quả kiểm tra nền tảng ban đầu

- Laravel 13.43.0 chạy trên PHP 8.3.37 của Laragon. Composer xác nhận các yêu cầu nền tảng và file cấu hình hợp lệ.
- `php artisan migrate:status` xác nhận cả ba migration đã chạy. Đã kiểm tra các route xác thực và route quản trị.
- `npm run build` thành công. Apache phục vụ trang chủ, CSS, JavaScript, script Livewire và file font Be Vietnam Pro có dấu tiếng Việt.
- PHPUnit chạy 29 bài kiểm thử với 69 assertion trên SQLite trong bộ nhớ. Pint và PHPStan đều đạt.
- Kiểm tra HTTP trên [cafe_management.test](http://cafe_management.test) thành công cho trang chủ, trang xác thực, đăng ký, đăng nhập, từ chối mật khẩu sai, đăng xuất, session MySQL, hồ sơ Livewire và bảo vệ route quản trị.
- Kiểm tra HTTP đặt lại mật khẩu thành công từ liên kết trong log. Đã đăng nhập lại bằng mật khẩu mới và xóa tài khoản kiểm thử. Các yêu cầu trực tiếp tới `.env` và `.git/config` bị Apache chặn.
- Log Laravel ghi email đặt lại mật khẩu local. Không thấy mục lỗi trong lượt kiểm tra cuối.

## Mở rộng phục vụ tại quán

- Đã bổ sung đặc tả [UC26 — Đặt đồ uống hẹn giờ nhận tại quán](use-cases/UC26.md) và [UC27 — Đặt bàn hoặc phòng họp tại quán](use-cases/UC27.md). Tổng danh mục hiện có 27 UC. Đã ghi các thông số vận hành được xác nhận về giờ quán, sức chứa, phụ phí và xử lý không đến. Đã cập nhật bước giờ 5 phút, hạn đổi trước giờ hẹn, phụ phí trên hóa đơn tại quầy và sơ đồ từng tầng với trạng thái sử dụng thực tế. Hai UC ở trạng thái `TODO` vì đang chờ triển khai.

- Đã chốt giờ hẹn cuối 22:30 và cách đối chiếu phụ phí trên màn hình tra mã cho UC26–UC27. Đặc tả đã sẵn sàng để triển khai.

### UC26 đã nghiệm thu

- Người dùng nghiệm thu UC26 đến commit `3edc935`. Đã tích hợp vào `develop` tại `29cff2a`. Kiểm tra lại UC26 đạt 12 bài với 225 assertion, gồm ca scheduler xử lý trễ một lượt vắng xảy ra trước lần đến gần nhất.

- Checkout nhận giờ 07:00–22:30 theo bước 5 phút và kiểm tra hạn đặt trước 60 phút khi chốt đơn. Khách xem phiếu trong đơn, đổi giờ và xem phụ phí. Nhân viên lọc lịch theo ngày hoặc giờ, nhập mã, ghi nhận khách đến và bàn giao qua quy trình UC19.
- Phí tại quầy được chốt trong giao dịch bàn giao. Kênh ứng dụng dùng nội dung chuyển khoản riêng và nhân viên đối chiếu sao kê. Lịch chưa ghi nhận đến hết hiệu lực sau 30 phút. Tác vụ `pickups:expire` hủy đơn và cập nhật chuỗi không đến, dùng quy tắc chung cấp tài khoản cho UC26–UC27.
- Đã chạy migration bổ sung trên MySQL local. Đã thử checkout và phiếu ở Brave 360 px. MySQL 8.4 riêng xác minh hoàn tác khi ghi phiếu lỗi và ba tình huống tranh chấp giữa ghi nhận đến/hủy quá hạn, bàn giao/hủy đơn, xác nhận thanh toán/chuyển kênh. Luồng liên UC27 được kiểm tra qua quy tắc chung với dữ liệu mô phỏng, vì màn hình UC27 đang chờ triển khai.
- `composer ci:check` đạt Pint, PHPStan và 191 bài SQLite với 2.847 assertion. Bộ MySQL đạt 25 bài với 439 assertion. `npm run build` thành công.
- Hướng dẫn vận hành scheduler và đối chiếu chuyển khoản nằm trong [README](../README.md#lịch-nhận-tại-quán).

### UC27 đã nghiệm thu

- Người dùng nghiệm thu UC27 tại commit `bb64e42`. Đã tích hợp vào `develop` tại merge commit `96e1b7b` sau khi chạy lại Pint, PHPStan, 200 bài SQLite, 26 bài MySQL và bản build.

- Đã triển khai chọn bàn hoặc phòng họp trên sơ đồ hai tầng, đặt chỗ theo sức chứa và giờ, tra lịch, check-in bằng mã, ghi khách đến trực tiếp và xác nhận trả chỗ. Admin quản lý trạng thái phục vụ và khung nhận lịch mới trong giới hạn BR25.
- UC27 dùng chung quy tắc giờ, phụ phí và chuỗi vắng mặt với UC26. Scheduler `bookings:expire` xử lý hai loại lịch theo thứ tự thời điểm vắng mặt. Chỗ đã check-in vẫn được giữ cho đến khi nhân viên xác nhận khách rời đi.
- `composer ci:check` đạt Pint, PHPStan và 200 bài SQLite với 3.083 assertion. Bộ MySQL đạt 26 bài với 453 assertion. MySQL 8.4 riêng xác minh bốn tranh chấp giữa hai yêu cầu giữ cùng chỗ, đổi giờ và yêu cầu từ sơ đồ cũ, check-in và hủy quá hạn, trả chỗ và đặt lại.
- `npm run build` thành công. Brave ở khổ 360 px kiểm chứng sơ đồ hai tầng, lọc chỗ theo sức chứa, đặt chỗ, phiếu xác nhận và trạng thái đã giữ. Hai migration UC27 đã chạy trên MySQL local.

### UC28 đã nghiệm thu

- Người dùng nghiệm thu UC28 tại commit `2c55d69`. Đã tích hợp vào `develop` tại merge commit `8c37fcb`.

- Trang chủ hiển thị tối đa 5 món còn bán theo số ly từ các đơn hoàn tất và đã thanh toán trong ngày hiện tại và 29 ngày trước theo giờ Việt Nam. Hệ thống gộp các size theo món và xếp ID tăng dần khi đồng hạng. Khu vực tự ẩn nếu không có dữ liệu đủ điều kiện.
- `composer ci:check` đạt Pint, PHPStan và 202 bài SQLite với 3.101 assertion. Bộ MySQL đạt 27 bài với 463 assertion. `npm run build` thành công. Đã kiểm tra HTTP 200 trên trang chủ với MySQL local.
- AC-04 đã được kiểm chứng và tích hợp cùng UC30 vào `develop`. Bảng xếp hạng tính lượt đã phục vụ thuộc phiên đã thanh toán, kể cả sau khi trả chỗ. Lượt hủy và phiên chưa thanh toán bị loại.

### UC29 đã nghiệm thu

- Đã tích hợp UC29 và bản sửa thông báo 403 `d5012df` vào `develop` tại `858f2ac`. Bộ kiểm thử UC29 sau bản sửa đạt 6 bài với 152 assertion.

- Quản trị viên quản lý danh mục nguyên liệu, ngưỡng tồn thấp và công thức theo size hoặc phần topping. Mỗi lần nhập kho, kiểm kê và điều chỉnh có người ghi, lý do và số dư sau biến động. Lượng theo đơn vị cơ sở dùng độ chính xác ba chữ số thập phân.
- Checkout chốt định lượng theo số ly, size và topping. Khi kiểm soát tồn đã bật, thao tác bắt đầu pha chế khóa đơn và nguyên liệu theo thứ tự ID, trừ tồn, ghi sổ và cập nhật đơn trong cùng giao dịch. Đơn thiếu định lượng hoặc thiếu tồn giữ nguyên trạng thái. Hủy trước pha không tiêu hao, còn hủy sau pha giữ lịch sử tiêu hao.
- `composer ci:check` đạt Pint, PHPStan và 207 bài trên SQLite với 3.250 assertion. Bộ MySQL đạt 28 bài với 494 assertion, gồm kiểm tra khóa giữa hai kết nối, gửi lặp và đơn tranh phần tồn cuối. `npm run build` thành công.
- Migration đã chạy trên MySQL local. Kiểm soát tồn đang tắt và chỉ bật sau khi xử lý đơn cũ, hoàn thiện công thức và chốt tồn đầu kỳ theo [hướng dẫn vận hành](../README.md#kho-nguyên-liệu-theo-công-thức). UC30 dùng cùng sổ tiêu hao khi bắt đầu pha chế từng lượt.

### UC30 đã nghiệm thu

- Người dùng nghiệm thu UC30 tại `e957d07`. Đã tích hợp vào `develop` tại `a1b5107` sau khi chạy lại Pint, PHPStan, 212 bài SQLite, 29 bài MySQL và bản build.

- Nhân viên mở phiên cho chỗ đã check-in hoặc khách ghé trực tiếp. Khách vào từ QR cố định bằng mã 8 số theo phiên và gọi nhiều lượt trên trình duyệt chưa đăng nhập. Nhân viên có thể nhập hộ, xem số lượt chờ theo bàn, xử lý từng lượt và tra phiên đã đóng.
- Hệ thống chống gửi lặp, lưu giá và định lượng theo lượt, khóa cả hai kênh gọi thêm khi chốt. Hóa đơn cộng món đã phục vụ và khoản phụ phí chưa thu tại quầy. Thu đúng số tiền một lần và giữ chỗ đến khi nhân viên xác nhận khách rời đi.
- Bốn bài HTTP cho UC30 đạt 159 assertion. `composer ci:check` đạt Pint, PHPStan và 212 bài kiểm thử với 3.412 assertion. Bộ MySQL đạt 29 bài với 526 assertion, gồm kiểm tra khóa chỗ và phiên trên hai kết nối độc lập. `npm run build` thành công.
- Trình duyệt với dữ liệu thử đã vào phiên bằng mã, gửi hai dòng món có size và topping khác nhau, xem đúng tạm tính và yêu cầu tính tiền. Migration đã chạy trên MySQL local. [Hướng dẫn vận hành](../README.md#gọi-món-tại-bàn-hoặc-phòng).

## Theme Toàn Café & Jardin — chờ nghiệm thu

- Đã giải nén và đối chiếu gói `Toan_French_Theme_Complete.zip` trên nhánh `feature/ui-redesign`. Chuyển trang chủ Jardin mới nhất sang Blade, thay nhận diện public/auth/admin/vận hành, thẻ món, giỏ và trang đặt chỗ; các form và dữ liệu nghiệp vụ tiếp tục dùng backend hiện có.
- Bổ sung trang Câu chuyện và Không gian. Không gian 3D đọc snapshot công khai từ 20 bàn và 2 phòng thực tế, không trả thông tin khách hoặc giao dịch. Khi mất kết nối, bỏ thông báo còn chỗ và hiển thị chưa xác minh. Trợ lý dùng sprite của gói và endpoint chat hiện có.
- `php artisan test --compact` đạt 215 bài với 3.465 assertion. Build, biên dịch Blade, Pint cho các file PHP thay đổi, PHPStan và kiểm tra cú pháp các module JS tích hợp đều đạt.
- Chromium headless với SQLite riêng kiểm tra trang chủ/thực đơn tại 320, 390 và 1.440 px; đăng nhập, tùy chỉnh món và thêm giỏ bằng Livewire, checkout, đặt chỗ, quản trị/sơ đồ vận hành, sidebar và Escape, chuyển trang cài đặt bằng Livewire và chế độ tối. Các màn hình đã đo không tràn ngang toàn trang; không có lỗi JavaScript trong các kịch bản đã chạy. Mô hình quán có một canvas, đổi góc nhìn thực sự thay đổi hình; snapshot lỗi không giữ số chỗ khả dụng cũ.
- Chưa kiểm chứng WebGL trên GPU/thiết bị thật; trình duyệt kiểm tra dùng renderer tương thích. Chưa chạy bộ tích hợp MySQL do chưa có cấu hình database thử riêng trong checkout hiện tại. Gói chưa chứa MP4 13 giây nên banner dùng poster đã gửi. Không chạy migration hoặc ghi dữ liệu thử vào MySQL cửa hàng. [Đối chiếu và hướng dẫn tích hợp](ui-preview/FRENCH_THEME_INTEGRATION.md).

### Bố cục banner và menu mobile — đã nghiệm thu

- Đã nghiệm thu bản sửa `1fe2ea4` và tích hợp vào `develop` tại `46fd7f6`. Hai nhóm nút trên banner nằm ở hai hàng và tự giãn theo nội dung. Menu mobile hiện một mục đăng ký hoặc đăng xuất đúng theo trạng thái đăng nhập.
- Đã kiểm tra trên trình duyệt ở 9 kích thước từ 320 đến 1.440 px. Sau tích hợp, build, Pint, PHPStan, 216 bài SQLite với 3.471 assertion và 29 bài MySQL với 526 assertion đều đạt.

### Video banner trang chủ — đã tích hợp, chờ nghiệm thu

- Đã tích hợp video PixVerse người dùng gửi vào banner Toàn. Giữ nguyên file, dài khoảng 8,04 giây và kích thước 1024 × 688; tự phát tắt tiếng, lặp, có nút phát/tạm dừng và poster khi tải lỗi. Tôn trọng reduced motion/tiết kiệm dữ liệu, tạm dừng khi banner ra ngoài viewport hoặc tab bị ẩn, giải phóng media theo lifecycle Livewire.
- Edge headless xác minh giải mã và phát video thật, điều khiển, phát lặp, offscreen pause, reduced motion với phát chủ động và fallback khi media trả 404. Mobile 390 px không tràn ngang, không phát sinh lỗi JavaScript. Build, cú pháp JS và 6 bài hồi quy catalog/trang chủ với 89 assertion đạt. Không thay database hoặc logic nghiệp vụ.

### Đặt chỗ trên trang Không gian — đã nghiệm thu

- Đã nghiệm thu bản sửa `0a28324` và tích hợp vào `develop` tại `8beac6d`.
- Sửa xác nhận hủy lịch bằng checkbox HTML. Giữ chỗ đã chọn qua đăng nhập và chọn sẵn chỗ còn phù hợp sau khi khách tìm giờ đến. Hướng dẫn trên sơ đồ giải thích hệ thống giữ chỗ và cấp mã ngay khi đặt thành công.
- Chi tiết chỗ đã giữ công khai ngày giờ hẹn sắp tới theo giờ Việt Nam. Sơ đồ cập nhật theo lần lấy trạng thái kế tiếp sau khi đổi hoặc hủy. Chỗ đang sử dụng chỉ hiển thị trạng thái, không có giờ trống dự kiến.
- Sau tích hợp, 218 bài SQLite với 3.511 assertion, 29 bài MySQL với 526 assertion, Pint, PHPStan, build và kiểm tra cú pháp JS đều đạt. Trình duyệt với SQLite riêng giữ đúng phòng T2-PH sau đăng nhập, chọn sẵn phòng, thấy giờ mới sau khi đổi và trả chỗ khi hủy qua form.

## Việc còn lại

### Trải nghiệm khách hàng và liên hệ cửa hàng — đang chờ nghiệm thu

- Nhánh `feature/customer-experience-support` bổ sung số điện thoại vào hồ sơ khách để làm giá trị gợi ý cho checkout và giữ chỗ, nhưng vẫn giữ snapshot người nhận trên đơn/lịch theo BR03 và BR18.
- Customer đăng nhập trực tiếp được đưa về trang chủ; admin và staff giữ luồng chuyển hướng workspace hiện có. Màn đặt chỗ hiển thị lại sơ đồ 3D/2D tương tác ngay trong hành trình tạo lịch; lõi UC26/UC27 không đổi.
- Bổ sung UC31: khách tạo/xem/phản hồi cuộc trao đổi của mình, staff/admin phản hồi hoặc đóng; có kiểm tra ownership, quyền vận hành, giới hạn nội dung và lịch sử tin nhắn. Đã chạy 50 bài kiểm thử trọng tâm với 769 assertion và `npm run build`. Ngày 22/11/2026, migration UC31 đã chạy trên MySQL local; chưa được nghiệm thu/tích hợp vào `develop`.
- Không triển khai tích điểm vì chưa có chính sách tích/đổi điểm, hết hạn và hoàn điểm khi hủy đơn được chốt.

- Điền thông số giao hàng và tài khoản nhận chuyển khoản khi cấu hình cửa hàng.
- Việc gửi email thật cần thông tin SMTP. Môi trường local mặc định dùng mailer `log` và đã thử gửi SMTP qua Mailpit.
