<x-layouts::admin title="Quản lý tiền cọc">
    @php
        $statuses = [
            'pending' => 'Chờ chuyển cọc', 'paid' => 'Đã nhận cọc',
            'settled' => 'Đã hoàn — trừ hóa đơn', 'refunded' => 'Đã hoàn — trả ngân hàng',
            'refund_pending' => 'Chờ hoàn tiền', 'forfeited' => 'Giữ cọc', 'voided' => 'Đã hủy',
        ];
    @endphp
    <section class="deposit-management space-y-6" data-deposit-management>
        <header class="deposit-heading">
            <div>
                <p class="deposit-eyebrow">Quản trị / Tiền cọc</p>
                <h1>Quản lý tiền cọc đặt bàn</h1>
                <p class="deposit-muted">Theo dõi khoản cọc, khấu trừ hóa đơn và hoàn tiền của từng phiếu đặt bàn.</p>
            </div>
            <flux:button :href="route('admin.reservations.index')">Xem phiếu đặt bàn</flux:button>
        </header>

        @include('reservations.feedback')

        <form method="GET" action="{{ route('admin.deposits.index') }}" class="terrasse-panel deposit-filters">
            <div class="deposit-filter-field">
                <flux:select name="status" label="Trạng thái cọc">
                    <flux:select.option value="">Tất cả trạng thái</flux:select.option>
                    @foreach($statuses as $value => $label)
                        <flux:select.option :value="$value" :selected="($filters['status'] ?? '') === $value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="deposit-filter-actions">
                <flux:button type="submit" variant="primary">Lọc tiền cọc</flux:button>
                @if(! empty($filters['status']))
                    <flux:link :href="route('admin.deposits.index')">Xóa bộ lọc</flux:link>
                @endif
            </div>
        </form>

        <div class="terrasse-panel deposit-list">
            <div class="deposit-list-heading">
                <h2>Danh sách chứng từ</h2>
                <p><strong>{{ number_format($deposits->total(), 0, ',', '.') }}</strong> chứng từ{{ ! empty($filters['status']) ? ' phù hợp' : '' }}</p>
            </div>
            <p class="deposit-scroll-hint">Vuốt ngang để xem đầy đủ thông tin và thao tác.</p>
            <div class="deposit-table-scroll" role="region" aria-label="Danh sách tiền cọc đặt bàn" tabindex="0">
                <table class="deposit-table">
                    <caption class="sr-only">Chứng từ tiền cọc, khách hàng, bàn, giờ hẹn, số tiền và trạng thái xử lý.</caption>
                    <thead>
                        <tr>
                            @foreach(['Chứng từ', 'Khách hàng', 'Bàn / giờ hẹn', 'Tiền cọc', 'Trạng thái', 'Thao tác'] as $label)
                                <th scope="col" @class(['deposit-money' => $label === 'Tiền cọc'])>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $deposit)
                            <tr>
                                <th scope="row">
                                    <a class="deposit-number" href="{{ route('operations.deposits.show', $deposit) }}">{{ $deposit->number }}</a>
                                    <span class="deposit-subtext">Lập {{ $deposit->created_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</span>
                                </th>
                                <td>
                                    <strong class="deposit-customer">{{ $deposit->reservation->contact_name }}</strong>
                                    <span class="deposit-subtext">{{ $deposit->reservation->phone }}</span>
                                </td>
                                <td>
                                    <strong>{{ $deposit->reservation->seat->code }}</strong>
                                    <span class="deposit-subtext">{{ $deposit->reservation->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</span>
                                </td>
                                <td class="deposit-money"><strong><x-customer.vnd :amount="$deposit->amount_vnd" /></strong></td>
                                <td>
                                    <span class="deposit-status deposit-status--{{ $deposit->status }}">{{ $statuses[$deposit->status] ?? $deposit->statusLabel() }}</span>
                                    @if($deposit->forfeit_reason)
                                        <span class="deposit-status-note">Lý do: {{ $deposit->forfeit_reason }}</span>
                                    @endif
                                    @if($deposit->status === 'settled' && $deposit->invoice)
                                        <a class="deposit-subtext underline" href="{{ route('admin.invoices.show', $deposit->invoice->number) }}">Hóa đơn {{ $deposit->invoice->number }}</a>
                                    @endif
                                </td>
                                <td>
                                    <div class="deposit-row-actions">
                                        <flux:button :href="route('operations.deposits.show', $deposit)" size="sm" :aria-label="'Xem chứng từ '.$deposit->number">Xem chứng từ</flux:button>
                                        @if($deposit->status === 'paid' && $deposit->table_invoice_id === null && $deposit->reservation->status === \App\Enums\ReservationStatus::Confirmed)
                                            <button type="button" class="deposit-forfeit-button" data-deposit-open
                                                data-deposit-number="{{ $deposit->number }}"
                                                data-deposit-customer="{{ $deposit->reservation->contact_name }}"
                                                data-deposit-seat="{{ $deposit->reservation->seat->code }}"
                                                data-deposit-url="{{ route('admin.deposits.forfeit', $deposit) }}"
                                                aria-haspopup="dialog" aria-controls="deposit-forfeit-dialog"
                                                aria-label="Giữ cọc {{ $deposit->number }}">Giữ cọc / hủy phiếu</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="deposit-empty">
                                    <strong>Không có chứng từ phù hợp</strong>
                                    <p>Thử chọn trạng thái khác hoặc xóa bộ lọc để xem lại danh sách.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($deposits->hasPages())
                <div class="deposit-pagination">{{ $deposits->links() }}</div>
            @endif
        </div>

        <p class="deposit-footnote">Chứng từ cọc được quản lý riêng với hóa đơn bán hàng. “Đã hoàn” gồm cọc đã trừ vào hóa đơn hoặc đã trả về ngân hàng.</p>

        <dialog id="deposit-forfeit-dialog" class="deposit-dialog" data-deposit-dialog aria-labelledby="deposit-forfeit-title" aria-describedby="deposit-forfeit-description">
            <div class="deposit-dialog-heading">
                <h2 id="deposit-forfeit-title">Xác nhận giữ cọc</h2>
                <button type="button" class="deposit-dialog-close" data-deposit-close aria-label="Đóng xác nhận giữ cọc">&times;</button>
            </div>
            <p class="deposit-dialog-summary" data-deposit-summary></p>
            <p id="deposit-forfeit-description" class="deposit-dialog-warning">Thao tác này sẽ hủy phiếu đặt bàn và giữ lại tiền cọc. Vui lòng nhập lý do và kiểm tra thông tin trước khi xác nhận.</p>
            <form method="POST" data-deposit-form class="space-y-4">
                @csrf
                <flux:textarea name="reason" label="Lý do giữ cọc" placeholder="Nhập lý do hủy phiếu và giữ tiền cọc…" rows="3" maxlength="500" required />
                <label class="deposit-dialog-confirm"><input type="checkbox" name="confirm" value="1" required> <span>Tôi xác nhận hủy phiếu đặt bàn và giữ khoản cọc này.</span></label>
                <div class="deposit-dialog-actions">
                    <flux:button type="button" data-deposit-close>Quay lại</flux:button>
                    <flux:button type="submit" variant="danger">Xác nhận giữ cọc</flux:button>
                </div>
            </form>
        </dialog>
        <noscript><p>Vui lòng bật JavaScript để mở hộp xác nhận giữ cọc. Bạn vẫn có thể xem chứng từ và quản lý phiếu đặt bàn.</p></noscript>
    </section>
</x-layouts::admin>
