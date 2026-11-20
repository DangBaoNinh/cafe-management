<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Voided = 'voided';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
}
