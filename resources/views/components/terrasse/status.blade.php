@props(['status'])

<span {{ $attributes->class(['terrasse-status terrasse-pill']) }} data-tone="{{ match ($status->value) {
    'pending', 'refund_pending' => 'amber',
    'confirmed', 'preparing' => 'rose',
    'ready', 'completed', 'paid', 'refunded' => 'green',
    'out_for_delivery' => 'blue',
    default => 'neutral',
} }}">{{ $status->label() }}</span>
