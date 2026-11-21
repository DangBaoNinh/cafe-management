@props(['entries', 'labelForStatus'])

@if ($entries->isEmpty())
    <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">Chưa có lịch sử.</p>
@else
    <ol class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
        @foreach ($entries as $entry)
            <li class="space-y-1 p-4 wrap-anywhere">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-semibold">
                        @if ($entry->from_status !== null)
                            {{ $labelForStatus($entry->from_status) }} <span aria-label="sang">→</span>
                        @endif
                        {{ $labelForStatus($entry->to_status) }}
                    </p>
                    <p class="text-sm"><x-customer.order-time :date="$entry->created_at" /></p>
                </div>
                <p class="text-sm">Thực hiện bởi {{ $entry->actor?->name ?? 'Hệ thống' }}</p>
                @if ($entry->reason !== null)
                    <p class="whitespace-pre-line text-sm">{{ $entry->reason }}</p>
                @endif
            </li>
        @endforeach
    </ol>
@endif
