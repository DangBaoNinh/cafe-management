@use('Illuminate\Support\Str')
<section data-table-pos class="table-pos space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3" data-no-print>
        <div><p class="terrasse-muted text-sm">Tầng {{ $tableSession->seat->floor }} · {{ $tableSession->seat->code }}</p><h1>{{ $invoice?->number ?? 'Order mới' }}</h1></div>
        <flux:link :href="$customer ? route('table-orders.show', $tableSession->seat->code) : route('operations.table-sessions.index')">{{ $customer ? 'Về bàn' : 'Quay về nhận order' }}</flux:link>
    </header>
    @include('table-orders.feedback')
    @if(!$bankAvailable)<p class="terrasse-panel p-3 text-sm">Chuyển khoản cần cấu hình ngân hàng, số tài khoản, chủ tài khoản và BIN VietQR. Hiện có thể thu tiền mặt.</p>@endif
    @if($fees['waiting'])<p role="alert">Còn phụ phí chuyển khoản cần nhân viên đối chiếu trên lượt đặt chỗ.</p>@endif
    <script type="application/json" data-pos-config>{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <div class="pos-workspace" data-no-print>
        <aside class="pos-categories terrasse-panel p-3" aria-label="Danh mục món">
            <p class="mb-3 text-sm font-semibold">Danh mục</p>
            <button type="button" class="pos-category active" data-pos-category="all">Tất cả</button>
            @foreach($products->pluck('category')->unique('id') as $category)
                <button type="button" class="pos-category" data-pos-category="{{ $category->id }}">{{ $category->name }}</button>
            @endforeach
        </aside>
        <section class="min-w-0 space-y-3" aria-label="Menu chọn món">
            <label class="block text-sm font-medium" for="pos-search">Tìm món theo tên</label>
            <input id="pos-search" type="search" data-pos-search placeholder="Nhập tên món…" class="pos-input w-full" autocomplete="off">
            <div class="pos-menu">
                @foreach($products as $product)
                    <button type="button" data-pos-product="{{ $product->id }}" data-category="{{ $product->category_id }}" data-name="{{ $product->name }}" class="pos-product terrasse-panel text-left">
                        <x-customer.product-image :$product />
                        <span class="block p-3"><strong class="block">{{ $product->name }}</strong><span class="terrasse-muted text-sm">Từ {{ number_format($product->activeSizes->min('price_vnd'), 0, ',', '.') }} ₫</span></span>
                    </button>
                @endforeach
            </div>
            <p data-pos-empty hidden>Không tìm thấy món phù hợp.</p>
        </section>
        <form method="POST" action="{{ $action }}" data-pos-form class="pos-cart terrasse-panel space-y-4 p-4">
            @csrf
            <input type="hidden" name="session_reference" value="{{ $tableSession->reference }}">
            <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
            <input type="hidden" name="revision" value="{{ $invoice?->revision ?? 0 }}">
            <input type="hidden" name="round_id" value="{{ $round?->id }}">
            <input type="hidden" name="accepted_total_vnd" value="0">
            <input type="hidden" name="changes_confirmed" value="0">
            <input type="hidden" name="intent" value="draft">
            <div data-pos-fields></div>
            <h2 class="text-xl font-semibold">Chi tiết order</h2>
            @if($invoice?->payment_reported_at)<p role="status" class="font-semibold">Khách đã báo chuyển khoản. Kiểm tra tiền vào trước khi xác nhận.</p>@endif
            <div data-pos-lines class="space-y-3"></div>
            <p data-pos-cart-empty class="terrasse-muted text-sm">Chọn món từ menu để bắt đầu.</p>
            <section data-pos-original hidden class="space-y-2 border-t border-terrasse-ink/20 pt-3"><h3 class="font-semibold">Món ban đầu trên phiếu</h3><ul data-pos-original-list class="space-y-2 text-sm"></ul></section>
            <p data-pos-changed hidden class="rounded-lg border border-amber-500/40 p-3 text-sm">Order có thay đổi so với phiếu đã lưu. Cần xác nhận thay đổi trước khi lưu hoặc thanh toán.</p>
            <label class="block space-y-1 text-sm">Phương thức thanh toán
                <select name="method" data-pos-method class="pos-input w-full" required>
                    <option value="cash" @selected(($invoice?->method->value ?? 'cash') === 'cash')>Tiền mặt</option>
                    <option value="bank_transfer" @selected($invoice?->method->value === 'bank_transfer') @disabled(!$bankAvailable)>Chuyển khoản · VietQR</option>
                </select>
            </label>
            <dl class="space-y-2 border-t border-terrasse-ink/20 pt-3 text-sm">
                <div class="flex justify-between"><dt>Tiền món</dt><dd data-pos-subtotal>0 ₫</dd></div>
                <div class="flex justify-between"><dt>Phụ phí tại quầy</dt><dd>{{ number_format($config['fees'], 0, ',', '.') }} ₫</dd></div>
                <div class="flex justify-between text-lg font-semibold"><dt>Tổng phải thu</dt><dd data-pos-total>0 ₫</dd></div>
            </dl>
            @if(!$customer)
                <input type="hidden" name="payment_confirmed" value="1">
                <p class="terrasse-muted text-sm">Bấm Hoàn tất thanh toán để xác nhận đã nhận đủ tiền theo phương thức đã chọn.</p>
            @endif
            <div class="pos-buttons">
                <flux:button type="submit" data-pos-intent="draft">{{ $customer ? 'Xem tạm tính / QR thanh toán' : 'Lưu tạm tính' }}</flux:button>
                @if(!$customer)<flux:button type="submit" variant="primary" data-pos-intent="pay">Hoàn tất thanh toán</flux:button>@endif
            </div>
            <p data-pos-message role="alert" class="text-sm text-terrasse-danger"></p>
            <noscript>Cần bật JavaScript để chọn món.</noscript>
        </form>
    </div>
    <dialog data-pos-confirm class="pos-confirm terrasse-panel p-6">
        <h2 class="text-xl font-semibold">Xác nhận thay đổi</h2>
        <p class="my-4">Món, số lượng hoặc phương thức thanh toán đã thay đổi. Xác nhận dùng nội dung hiện tại trên phiếu?</p>
        <div class="flex flex-wrap gap-3"><flux:button type="button" data-pos-cancel>Quay lại kiểm tra</flux:button><flux:button type="button" variant="primary" data-pos-approve>Xác nhận thay đổi</flux:button></div>
    </dialog>
</section>
