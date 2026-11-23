<?php

namespace App\Services;

use App\Enums\CollectionMethod;
use App\Enums\CounterOrderStatus;
use App\Enums\TableRoundStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableInvoice;
use App\Models\TableRound;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type Selection from TableRounds */
class TableInvoices
{
    public function __construct(private TableRounds $rounds, private OrderNumbers $numbers, private BankTransferQr $qr, private InventoryControl $inventory, private InventoryStock $stock) {}

    /** @param list<Selection> $selections */
    public function save(TableSession $target, array $selections, string $key, ?int $roundId, int $revision,
        CollectionMethod $method, int $acceptedTotal, bool $changesConfirmed, bool $pay, ?User $actor, bool $depositRefundConfirmed = false): TableInvoice
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('operate-orders');
        }
        $this->require(! $pay || $actor !== null, 'Chỉ nhân viên được xác nhận tiền thực thu.');
        $selections = TableRounds::canonicalSelections($selections);
        $hash = hash('sha256', json_encode([$selections, $method->value, $acceptedTotal], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($target, $selections, $key, $roundId, $revision, $method, $acceptedTotal, $changesConfirmed, $pay, $actor, $hash, $depositRefundConfirmed): TableInvoice {
            $this->inventory->lockForOperation();
            $session = TableSession::query()->lockForUpdate()->findOrFail($target->id);
            $this->require($session->billing_mode === 'prepaid', 'Phiên cũ tiếp tục dùng hóa đơn đã chốt theo luồng cũ.');
            $round = $roundId === null
                ? $session->rounds()->where('submission_key', $key)->lockForUpdate()->first()
                : $session->rounds()->whereKey($roundId)->lockForUpdate()->firstOrFail();
            $invoice = $round?->invoice()->lockForUpdate()->first();
            $originalItems = $round?->items()->get()->toArray();
            $this->require($invoice !== null || $revision === 0, 'Phiên bản order không hợp lệ. Vui lòng tải lại.');
            if ($invoice !== null && $invoice->status === 'paid') {
                $this->require(TableRounds::canonicalSelections($invoice->selections) === $selections && $invoice->method === $method && $invoice->total_vnd === $acceptedTotal, 'Hóa đơn đã thanh toán được giữ nguyên. Hãy tạo order mới cho phần gọi thêm.');

                return $invoice;
            }
            $this->require($invoice === null || $invoice->status === 'draft', 'Phiếu đã hủy. Hãy tạo order mới.');
            if ($invoice !== null && $invoice->revision !== $revision) {
                $this->require(TableRounds::canonicalSelections($invoice->selections) === $selections && $invoice->method === $method && $invoice->total_vnd === $acceptedTotal && ! $pay, 'Hóa đơn đã thay đổi trên màn hình khác. Vui lòng tải lại.');

                return $invoice;
            }
            $this->require($session->status === TableSessionStatus::Open || ($invoice !== null && $pay), 'Bàn đã đóng phiên. Chỉ có thể thanh toán phiếu đã lưu.');
            if ($round !== null) {
                $this->require($round->status === TableRoundStatus::Pending, 'Order đã vào pha chế hoặc đã hủy, không thể sửa hóa đơn.');
                $this->require($round->submission_key === $key, 'Mã order không khớp. Vui lòng tải lại.');
            }
            $lines = $this->rounds->price($selections);
            $subtotal = 0;
            foreach ($lines as $line) {
                if ($line['line_total_vnd'] < 0 || $subtotal > PHP_INT_MAX - $line['line_total_vnd']) {
                    throw ValidationException::withMessages(['table_invoice' => 'Tổng hóa đơn vượt giới hạn VND.']);
                }
                $subtotal += $line['line_total_vnd'];
            }
            $fees = $invoice?->fees()->where('status', 'pending')->sum('amount_vnd') ?? 0;
            $reservation = $session->reservation()->lockForUpdate()->first();
            $newFees = collect();
            if ($reservation !== null) {
                $this->require(! $reservation->fees()->where('channel', 'app')->where('status', 'pending')->exists(), 'Còn phụ phí chuyển khoản cần đối chiếu trên lượt đặt chỗ.');
                $newFees = $reservation->fees()->where('channel', 'counter')->where('status', 'pending')
                    ->whereNull('table_session_id')->whereNull('table_invoice_id')->lockForUpdate()->get();
                $fees += $newFees->sum('amount_vnd');
            }
            if ($fees < 0 || $subtotal > PHP_INT_MAX - $fees) {
                throw ValidationException::withMessages(['table_invoice' => 'Tổng hóa đơn vượt giới hạn VND.']);
            }
            $total = $subtotal + $fees;
            $this->require($total === $acceptedTotal, 'Tổng tiền đã thay đổi. Vui lòng tải lại và kiểm tra tạm tính.');
            $changed = $invoice !== null && (TableRounds::canonicalSelections($invoice->selections) !== $selections || $invoice->method !== $method || $invoice->total_vnd !== $total);
            $guestOrderChanged = $invoice === null && $round !== null && $this->selectionsFor($round) !== $selections;
            $this->require(! ($changed || $guestOrderChanged) || $changesConfirmed, 'Xác nhận thay đổi trước khi lưu tạm tính hoặc hoàn tất thanh toán.', 'changes_confirmed');
            $bank = $method === CollectionMethod::BankTransfer
                ? ($invoice?->method === CollectionMethod::BankTransfer ? $invoice->bank_details : $this->qr->details()) : null;
            $this->require($method !== CollectionMethod::BankTransfer || $bank !== null, 'Chưa cấu hình đầy đủ ngân hàng và BIN VietQR. Vui lòng cấu hình trước khi chọn chuyển khoản.');
            if ($round === null) {
                $round = $this->rounds->submit($session, $selections, $key, $actor);
            } elseif ($invoice === null || $changed) {
                $round->items()->delete();
                $round->items()->createMany($lines);
                $round->inventory_snapshot = app(InventoryRecipes::class)->snapshotForLines($lines);
                $round->total_vnd = $subtotal;
                $round->save();
            }
            $before = $invoice === null ? null : ['selections' => $invoice->selections, 'method' => $invoice->method->value, 'total_vnd' => $invoice->total_vnd];
            if ($invoice === null) {
                $invoice = new TableInvoice;
                $invoice->fill(['reference' => Str::uuid()->toString(), 'number' => $this->numbers->next(),
                    'table_session_id' => $session->id, 'table_round_id' => $round->id, 'submission_key' => $key,
                    'initial_items' => $originalItems ?? $lines, 'created_by' => $actor?->id, 'revision' => 1,
                    'order_status' => CounterOrderStatus::PendingPayment]);
            } elseif ($changed) {
                $invoice->revision++;
            }
            $invoice->fill(['selections' => $selections, 'method' => $method, 'request_hash' => $hash, 'bank_details' => $bank,
                'subtotal_vnd' => $subtotal, 'fees_vnd' => $fees, 'total_vnd' => $total]);
            if ($changed) {
                $invoice->payment_reported_at = null;
            }
            $invoice->save();
            app(ReservationDeposits::class)->claim($invoice);
            if ($newFees->isNotEmpty()) {
                $reservation->fees()->whereKey($newFees->pluck('id')->all())->update(['table_invoice_id' => $invoice->id]);
            }
            if ($changed) {
                DB::table('table_invoice_revisions')->insert(['table_invoice_id' => $invoice->id, 'actor_id' => $actor?->id,
                    'revision' => $invoice->revision, 'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode(['selections' => $selections, 'method' => $method->value, 'total_vnd' => $total], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            if ($pay && $actor !== null) {
                $this->settle($invoice, $round, $actor, $depositRefundConfirmed);
            }

            return $invoice;
        }, 5);
    }

    /** @return list<Selection> */
    public function selectionsFor(TableRound $round): array
    {
        return TableRounds::canonicalSelections(array_values($round->items()->with('size')->get()->map(fn ($item): array => [
            'product_id' => $item->size->product_id, 'size_id' => $item->product_size_id, 'quantity' => $item->quantity,
            'sugar_level' => $item->sugar_level, 'ice_level' => $item->ice_level,
            'topping_ids' => array_column($item->toppings, 'id'),
            'notes' => $item->notes,
        ])->all()));
    }

    public function completeSaved(User $actor, TableInvoice $target, int $revision, bool $depositRefundConfirmed = false): TableInvoice
    {
        Gate::forUser($actor)->authorize('operate-orders');

        return DB::transaction(function () use ($actor, $target, $revision, $depositRefundConfirmed): TableInvoice {
            $this->inventory->lockForOperation();
            TableSession::query()->lockForUpdate()->findOrFail($target->table_session_id);
            $round = TableRound::query()->lockForUpdate()->findOrFail($target->table_round_id);
            $invoice = TableInvoice::query()->lockForUpdate()->findOrFail($target->id);
            if ($invoice->status === 'paid') {
                return $invoice;
            }
            $this->require($invoice->status === 'draft' && $round->status === TableRoundStatus::Pending, 'Phiếu đã hủy hoặc order đã đổi trạng thái.');
            $this->require($invoice->revision === $revision, 'Phiếu đã được sửa trên màn hình khác. Vui lòng tải lại tạm tính trước khi thanh toán.');
            $this->require((int) $invoice->fees()->where('status', 'pending')->sum('amount_vnd') === $invoice->fees_vnd, 'Phụ phí của phiếu đã thay đổi. Vui lòng kiểm tra lại trước khi thu tiền.');
            $this->settle($invoice, $round, $actor, $depositRefundConfirmed);

            return $invoice;
        }, 5);
    }

    private function settle(TableInvoice $invoice, TableRound $round, User $actor, bool $depositRefundConfirmed): void
    {
        if ($this->inventory->enabled()) {
            $round->load('tableSession');
            try {
                $this->stock->consume($round, $actor);
            } catch (ValidationException $exception) {
                throw $exception->redirectTo($invoice->wasRecentlyCreated
                    ? route('operations.table-sessions.pos', $round->tableSession)
                    : route('operations.invoices.prepaid.show', $invoice));
            }
        }
        app(ReservationDeposits::class)->settle($invoice, $actor, $depositRefundConfirmed);
        $invoice->status = 'paid';
        $invoice->order_status = CounterOrderStatus::Completed;
        $invoice->paid_by = $actor->id;
        $invoice->paid_at = now();
        $invoice->save();
        $invoice->fees()->where('status', 'pending')->update(['status' => 'paid', 'paid_at' => $invoice->paid_at, 'counter_actor_id' => $actor->id]);
        $round->update(['status' => TableRoundStatus::Completed]);
        $round->events()->create(['actor_id' => $actor->id, 'from_status' => TableRoundStatus::Pending,
            'to_status' => TableRoundStatus::Completed, 'reason' => 'Hoàn tất order, đã thu tiền theo hóa đơn '.$invoice->number]);
    }

    /** @return array{total: int, waiting: bool} */
    public function unassignedFees(TableSession $session): array
    {
        $fees = $session->reservation?->fees()->where('status', 'pending')->whereNull('table_session_id')->whereNull('table_invoice_id')->get();

        return ['total' => (int) ($fees?->where('channel', 'counter')->sum('amount_vnd') ?? 0),
            'waiting' => $fees?->where('channel', 'app')->isNotEmpty() ?? false];
    }

    private function require(bool $allowed, string $message, string $field = 'table_invoice'): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages([$field => $message])->status(409);
        }
    }
}
