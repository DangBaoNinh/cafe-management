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

## Kết quả kiểm tra nền tảng ban đầu

- Laravel 13.43.0 chạy trên PHP 8.3.37 của Laragon. Composer xác nhận các yêu cầu nền tảng và file cấu hình hợp lệ.
- `php artisan migrate:status` xác nhận cả ba migration đã chạy. Đã kiểm tra các route xác thực và route quản trị.
- `npm run build` thành công. Apache phục vụ trang chủ, CSS, JavaScript, script Livewire và file font Be Vietnam Pro có dấu tiếng Việt.
- PHPUnit chạy 29 bài kiểm thử với 69 assertion trên SQLite trong bộ nhớ. Pint và PHPStan đều đạt.
- Kiểm tra HTTP trên [cafe_management.test](http://cafe_management.test) thành công cho trang chủ, trang xác thực, đăng ký, đăng nhập, từ chối mật khẩu sai, đăng xuất, session MySQL, hồ sơ Livewire và bảo vệ route quản trị.
- Kiểm tra HTTP đặt lại mật khẩu thành công từ liên kết trong log. Đã đăng nhập lại bằng mật khẩu mới và xóa tài khoản kiểm thử. Các yêu cầu trực tiếp tới `.env` và `.git/config` bị Apache chặn.
- Log Laravel ghi email đặt lại mật khẩu local. Không thấy mục lỗi trong lượt kiểm tra cuối.

## Việc còn lại

- Nhánh `chore/gitflow-setup` chờ review.
- Điền thông số giao hàng và tài khoản nhận chuyển khoản khi cấu hình cửa hàng.
- Việc gửi email thật cần thông tin SMTP. Môi trường local mặc định dùng mailer `log` và đã thử gửi SMTP qua Mailpit.
- Xử lý đơn, đối chiếu thanh toán, quản lý khách hàng và doanh thu thuộc các giai đoạn tiếp theo.
