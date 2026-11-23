<div class="terrasse-chat" data-storefront-chat
    data-cart-url="@can('manage-cart'){{ route('cart.add-from-chat', absolute: false) }}@endcan">
    <section id="storefront-chat-panel" class="terrasse-chat-panel terrasse-panel shadow-xl" aria-labelledby="storefront-chat-title" hidden>
        <header class="terrasse-chat-header flex items-center justify-between gap-4 px-4 py-3">
            <h2 id="storefront-chat-title" class="terrasse-display text-2xl font-semibold">Trợ lý Toàn</h2>
            <button type="button" data-chat-close aria-label="Đóng trò chuyện" class="grid size-9 cursor-pointer place-items-center rounded-full hover:bg-white/15">
                <flux:icon.x-mark class="size-5" aria-hidden="true" />
            </button>
        </header>

        <div class="terrasse-chat-log flex flex-col gap-3 overflow-y-auto p-4" role="log" aria-live="polite" aria-label="Cuộc trò chuyện">
            <p class="terrasse-chat-message">Chào bạn! Bạn muốn uống gì hôm nay? Mình có thể gợi ý món và giúp bạn thêm món vào giỏ.</p>
        </div>

        <div class="space-y-2 border-t border-terrasse-ink/15 p-3 sm:p-4">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <a class="terrasse-store-link underline underline-offset-4" href="{{ route('menu.index') }}">Xem thực đơn</a>
                @can('manage-cart')
                    <a class="terrasse-store-link underline underline-offset-4" href="{{ route('cart.index') }}">Giỏ hàng</a>
                    <a class="terrasse-store-link underline underline-offset-4" href="{{ route('checkout.create') }}">Thanh toán</a>
                @endcan
            </div>
            <p data-chat-status role="status" class="terrasse-muted text-sm" hidden></p>
            <form action="{{ route('storefront-chat.reply', absolute: false) }}" method="POST" class="flex items-center gap-2">
                @csrf
                <label for="storefront-chat-message" class="sr-only">Tin nhắn</label>
                <input id="storefront-chat-message" name="message" type="text" maxlength="500" required
                    placeholder="Ví dụ: Món nào ít ngọt?" autocomplete="off"
                    class="min-w-0 flex-1 rounded-lg border border-terrasse-ink/25 bg-terrasse-paper px-3 py-2">
                <button class="terrasse-chat-action" type="submit">Gửi</button>
            </form>
            <p class="terrasse-muted text-xs">Tin nhắn được gửi đến Gemini. Vui lòng tránh nhập thông tin cá nhân hoặc thanh toán.</p>
        </div>
    </section>

    <button class="terrasse-chat-launch grid size-14 cursor-pointer place-items-center rounded-full shadow-lg" type="button"
        data-chat-toggle aria-label="Mở trò chuyện" aria-controls="storefront-chat-panel" aria-expanded="false">
        <span class="french-maid-sprite" aria-hidden="true"></span>
        <span class="french-maid-label" aria-hidden="true">Toàn ở đây!</span>
    </button>

    <template data-chat-product>
        <article class="rounded-lg border border-terrasse-ink/15 p-3">
            <a data-product-link class="terrasse-store-link text-sm font-semibold underline underline-offset-4"></a>
            @can('manage-cart')
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">Size</span>
                        <select class="w-full rounded-lg border border-terrasse-ink/25 bg-terrasse-paper px-2 py-2 text-sm"></select>
                    </label>
                    <button type="button" class="terrasse-chat-action text-sm">Thêm 1 món</button>
                </div>
                <p class="terrasse-muted mt-2 text-xs">Thêm với tùy chọn tiêu chuẩn. Mở tên món để tùy chỉnh.</p>
            @endcan
        </article>
    </template>
</div>
