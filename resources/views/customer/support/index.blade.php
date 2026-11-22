<x-layouts::customer title="Liên hệ cửa hàng">
    <section class="mx-auto max-w-4xl space-y-5">
        <header class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Hỗ trợ khách hàng</p><h1 class="text-3xl font-semibold tracking-tight">Liên hệ cửa hàng</h1><p class="terrasse-muted mt-2">Gửi câu hỏi về đơn hàng hoặc dịch vụ. Nhân viên sẽ phản hồi trong cuộc trao đổi này.</p></div><flux:button :href="route('support.create')" variant="primary">Gửi tin nhắn mới</flux:button></header>
        @include('reservations.feedback')
        @forelse ($conversations as $conversation)
            <a href="{{ route('support.show', $conversation) }}" class="block rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 transition hover:border-terrasse-ink/35"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold">{{ $conversation->subject }}</h2><p class="terrasse-muted mt-1 text-sm">Cập nhật {{ $conversation->last_message_at->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</p></div><span class="rounded-full px-3 py-1 text-xs {{ $conversation->status === 'open' ? 'bg-terrasse-sage/20 text-terrasse-ink' : 'bg-terrasse-ink/10 text-terrasse-ink/70' }}">{{ $conversation->status === 'open' ? 'Đang mở' : 'Đã đóng' }}</span></div></a>
        @empty
            <div class="rounded-xl border border-dashed border-terrasse-ink/25 bg-terrasse-paper p-8 text-center"><h2 class="font-semibold">Chưa có cuộc trao đổi</h2><p class="terrasse-muted mt-2 text-sm">Bạn có thể gửi câu hỏi đầu tiên cho cửa hàng.</p></div>
        @endforelse
        {{ $conversations->links() }}
    </section>
</x-layouts::customer>
