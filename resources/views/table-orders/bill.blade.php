<section class="space-y-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
    <h2 class="text-lg font-semibold">Hóa đơn tại quầy</h2>
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between gap-2"><dt>Món đã phục vụ</dt><dd>{{ number_format($tableSession->subtotal_vnd, 0, ',', '.') }} ₫</dd></div>
        <div class="flex justify-between gap-2"><dt>Phụ phí tại quầy</dt><dd>{{ number_format($tableSession->fees_vnd, 0, ',', '.') }} ₫</dd></div>
        @foreach ($tableSession->fees as $fee)
            <div class="flex justify-between gap-2 ps-4"><dt>{{ $fee->label() }}</dt><dd>{{ number_format($fee->amount_vnd, 0, ',', '.') }} ₫</dd></div>
        @endforeach
        <div class="flex justify-between gap-2 border-t border-terrasse-ink/15 pt-2 font-semibold">
            <dt>{{ $tableSession->paid_at === null ? 'Tổng phải thu' : 'Tổng đã thanh toán' }}</dt>
            <dd>{{ number_format($tableSession->total_vnd, 0, ',', '.') }} ₫</dd>
        </div>
    </dl>
</section>
