<?php

namespace App\Services;

use App\Enums\CounterOrderStatus;
use App\Models\TableInvoice;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaidCounterOrderStatus
{
    public function change(User $actor, TableInvoice $target, string $status, int $revision, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('access-admin');
        if (! in_array($status, ['completed', 'cancelled'], true) || blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Chọn trạng thái hợp lệ và nhập lý do thay đổi.']);
        }
        DB::transaction(function () use ($actor, $target, $status, $revision, $reason): void {
            // Use the same lock order as other invoice writers; never alter the receipt or stock.
            TableSession::query()->lockForUpdate()->findOrFail($target->table_session_id);
            $invoice = TableInvoice::query()->lockForUpdate()->findOrFail($target->id);
            if ($invoice->status !== 'paid' || $invoice->revision !== $revision || $invoice->order_status->value === $status) {
                throw ValidationException::withMessages(['revision' => 'Hóa đơn đã thay đổi hoặc trạng thái mới trùng trạng thái hiện tại. Vui lòng tải lại.'])->status(409);
            }
            $before = $invoice->order_status->value;
            $invoice->order_status = CounterOrderStatus::from($status);
            $invoice->revision++;
            $invoice->save();
            $invoice->revisions()->create([
                'actor_id' => $actor->id, 'revision' => $invoice->revision,
                'before' => ['order_status' => $before, 'payment_status' => 'paid'],
                'after' => ['order_status' => $status, 'payment_status' => 'paid', 'reason' => trim($reason)],
            ]);
        }, 5);
    }
}
