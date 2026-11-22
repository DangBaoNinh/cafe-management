@use('App\Enums\TableRoundStatus')

@if ($round->entered_by !== null)
    <p class="text-sm">Nhân viên nhập thay khách</p>
@endif

@if ($round->events->isNotEmpty())
    <details class="text-sm">
        <summary class="cursor-pointer">Lịch sử lượt gọi</summary>
        <ul class="mt-2 space-y-1">
            @foreach ($round->events->sortBy('id') as $event)
                <li>
                    {{ $event->to_status->label() }} · {{ $event->actor->name }} · <x-customer.order-time :date="$event->created_at" />
                    @if ($event->reason) · {{ $event->reason }} @endif
                </li>
            @endforeach
        </ul>
    </details>
@endif

@if ($round->status->next() !== [])
    <div class="flex flex-wrap items-start gap-2">
        @foreach ($round->status->next() as $next)
            @php
                $label = match ($next) {
                    TableRoundStatus::Accepted => 'Tiếp nhận',
                    TableRoundStatus::Preparing => 'Bắt đầu pha chế',
                    TableRoundStatus::Ready => 'Đánh dấu sẵn sàng',
                    TableRoundStatus::Served => 'Xác nhận đã phục vụ',
                    TableRoundStatus::Rejected => 'Từ chối lượt',
                    TableRoundStatus::Cancelled => 'Hủy lượt',
                    default => $next->label(),
                };
            @endphp
            @if ($next->needsReason())
                <details class="rounded-lg border border-terrasse-ink/15 px-2 py-1">
                    <summary class="cursor-pointer text-sm">{{ $label }}</summary>
            @endif
            <form method="POST" action="{{ route('operations.table-sessions.advance', [$tableSession, $round->id]) }}" class="space-y-2">
                @csrf
                <input type="hidden" name="expected" value="{{ $round->status->value }}">
                <input type="hidden" name="target" value="{{ $next->value }}">
                @if ($next->needsReason())
                    <flux:textarea name="reason" label="Lý do" maxlength="500" rows="2" required />
                @endif
                <flux:button type="submit" size="sm" :variant="$next->needsReason() ? 'danger' : 'primary'">{{ $label }}</flux:button>
            </form>
            @if ($next->needsReason())
                </details>
            @endif
        @endforeach
    </div>
@endif
