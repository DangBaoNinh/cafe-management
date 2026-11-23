<x-layouts::admin title="Quản lý hoàn tiền">
    <section class="refund-management space-y-5">
        <header class="refund-heading">
            <p class="refund-eyebrow">Quản trị / Hoàn tiền</p>
            <h1>Quản lý hoàn tiền</h1>
            <p>Lịch sử hoàn tiền đơn hàng, hoàn cọc, khấu trừ cọc và hoàn phụ phí.</p>
        </header>
        @include('reservations.feedback')

        <div class="terrasse-panel refund-help">
            <strong>Phân biệt tiền hoàn và tiền khấu trừ</strong>
            <p>“Đã khấu trừ” là cọc đã dùng thanh toán hóa đơn. Chỉ số tiền “Hoàn lại khách” là khoản chuyển trả hoặc tiền mặt đã hoàn.</p>
        </div>
        <div class="refund-list-heading">
            <h2>Lịch sử xử lý</h2>
            <p><strong>{{ number_format($refunds->total(), 0, ',', '.') }}</strong> lần ghi nhận</p>
        </div>

        @forelse($refunds as $refund)
            <article class="terrasse-panel refund-card" data-refund-key="{{ $refund->source }}-{{ $refund->id }}">
                <header class="refund-card-heading">
                    <div>
                        <p class="refund-type">{{ $refund->label }}</p>
                        <h2>
                            @if($refund->document_url)<a href="{{ $refund->document_url }}">{{ $refund->number }}</a>@else{{ $refund->number }}@endif
                        </h2>
                        <p class="refund-customer">{{ $refund->customer }}</p>
                        @if($refund->context)<p class="refund-context">{{ $refund->context }}</p>@endif
                    </div>
                    <span class="refund-status refund-status--{{ $refund->status }}">{{ match($refund->status) { 'completed' => 'Đã hoàn tiền', 'credited' => 'Đã khấu trừ', default => 'Chờ hoàn tiền' } }}</span>
                </header>

                <dl class="refund-details">
                    <div><dt>Hoàn lại khách</dt><dd class="refund-amount"><x-customer.vnd :amount="$refund->amount_vnd" /></dd></div>
                    @if($refund->credited_vnd > 0)<div><dt>Đã trừ vào hóa đơn</dt><dd><x-customer.vnd :amount="$refund->credited_vnd" /></dd></div>@endif
                    <div><dt>Hình thức</dt><dd>{{ $refund->method }}</dd></div>
                    <div><dt>{{ $refund->source === 'deposit' ? 'Hóa đơn khấu trừ' : 'Mã giao dịch hoàn' }}</dt><dd>{{ $refund->reference ?: 'Chưa ghi nhận' }}</dd></div>
                    <div><dt>Người xử lý</dt><dd>{{ $refund->actor ?: ($refund->status === 'pending' ? 'Chờ xử lý' : 'Chưa lưu người xử lý') }}</dd></div>
                    <div><dt>Cập nhật lúc</dt><dd><x-customer.order-time :date="Carbon\CarbonImmutable::parse($refund->event_at, 'UTC')" /></dd></div>
                </dl>
                @if(is_array($refund->bank) && ! empty($refund->bank['account_number']))
                    <div class="refund-bank">
                        <strong>Tài khoản nhận hoàn</strong>
                        <p>{{ $refund->bank['bank_name'] ?? 'Chưa lưu ngân hàng' }} · {{ $refund->bank['account_number'] }}</p>
                        <p>{{ $refund->bank['account_holder'] ?? 'Chưa lưu chủ tài khoản' }}</p>
                    </div>
                @elseif(in_array($refund->source, ['payment', 'fee'], true))
                    <p class="refund-note">Giao dịch này chưa lưu thông tin tài khoản nhận hoàn.</p>
                @endif
                <p class="refund-note">{{ $refund->note }}</p>

                @if($refund->complete_url)
                    <details class="refund-manual-action">
                        <summary>Ghi nhận hoàn tiền thủ công</summary>
                        <p>Chỉ xác nhận sau khi đã chuyển trả đủ tiền vào tài khoản ở trên.</p>
                        <form method="POST" action="{{ $refund->complete_url }}" class="space-y-3">
                            @csrf
                            <flux:input name="refund_reference" label="Mã giao dịch hoàn tiền" required maxlength="128" />
                            <label class="refund-confirm"><input type="checkbox" name="confirm" value="1" required> <span>Tôi xác nhận đã chuyển trả đủ tiền đúng tài khoản.</span></label>
                            <flux:button type="submit" variant="primary">Xác nhận đã hoàn tiền</flux:button>
                        </form>
                    </details>
                @elseif($refund->action_url)
                    <div class="refund-card-actions"><flux:button :href="$refund->action_url" size="sm">{{ $refund->source === 'deposit' ? 'Xem hóa đơn' : ($refund->status === 'pending' ? 'Xử lý hoàn tiền' : 'Xem chi tiết') }}</flux:button></div>
                @endif
            </article>
        @empty
            <div class="terrasse-panel refund-empty">
                <strong>Chưa có lịch sử hoàn tiền hoặc khấu trừ cọc.</strong>
                <p>Các khoản sẽ xuất hiện sau khi có yêu cầu hoàn, giao dịch hoàn tiền hoặc cọc đã được trừ vào hóa đơn.</p>
            </div>
        @endforelse
        {{ $refunds->links() }}
    </section>
</x-layouts::admin>
