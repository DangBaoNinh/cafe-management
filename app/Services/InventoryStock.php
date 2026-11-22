<?php

namespace App\Services;

use App\Enums\InventoryMovementKind;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\TableRound;
use App\Models\User;
use App\Support\InventoryQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventoryStock
{
    public function __construct(private InventoryControl $control) {}

    public function record(User $actor, Ingredient $ingredient, InventoryMovementKind $kind, int $quantity, string $reason, int $version, string $entryKey): void
    {
        Gate::forUser($actor)->authorize('access-admin');

        DB::transaction(function () use ($actor, $ingredient, $kind, $quantity, $reason, $version, $entryKey): void {
            $this->control->lockForOperation();
            $current = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $existing = $current->movements()->where('entry_key', $entryKey)->first();
            if ($existing !== null) {
                $recordedQuantity = $existing->kind === InventoryMovementKind::Count ? $existing->balance_after : $existing->quantity_change;
                if ($existing->kind !== $kind || $recordedQuantity !== $quantity || $existing->reason !== $reason || $existing->actor_id !== $actor->id) {
                    throw ValidationException::withMessages(['quantity' => 'Phiếu này đã được dùng. Vui lòng tải lại trang để tạo phiếu mới.']);
                }

                return;
            }
            if ($kind === InventoryMovementKind::Count && $current->stock_version !== $version) {
                throw ValidationException::withMessages(['quantity' => 'Số tồn đã thay đổi. Hãy kiểm kê lại trước khi lưu.']);
            }

            $change = match ($kind) {
                InventoryMovementKind::Receipt, InventoryMovementKind::Adjustment => $quantity,
                InventoryMovementKind::Count => $quantity - $current->stock,
                InventoryMovementKind::Consumption => throw new \InvalidArgumentException('Consumption is recorded when preparation starts.'),
            };
            $balance = $current->stock + $change;
            if ($balance < 0 || $balance > InventoryQuantity::MAX) {
                throw ValidationException::withMessages(['quantity' => 'Số tồn sau thao tác phải từ 0 đến 999999999,999.']);
            }

            $this->move($current, $actor, $kind, $change, reason: $reason, entryKey: $entryKey);
        }, 5);
    }

    /** Call in the preparation transaction with the order or table session already locked. */
    public function consume(Order|TableRound $source, User $actor): void
    {
        $snapshot = $source->inventory_snapshot;
        if ($snapshot === null || $snapshot['missing'] !== [] || $snapshot['quantities'] === []) {
            $missing = $snapshot['missing'] ?? [];
            $detail = $missing === [] ? 'Đơn chưa có ảnh chụp công thức.' : 'Đơn thiếu công thức đã chốt cho: '.implode(', ', $missing).'.';

            throw $this->conflict($source, $detail);
        }

        $required = $snapshot['quantities'];
        ksort($required, SORT_NUMERIC);
        $ingredients = [];
        $shortages = [];
        foreach ($required as $id => $quantity) {
            $ingredient = Ingredient::query()->lockForUpdate()->find($id);
            if ($ingredient === null) {
                $shortages[] = "Không tìm thấy nguyên liệu #{$id} trong ảnh chụp công thức";

                continue;
            }
            $ingredients[$id] = $ingredient;
            if ($quantity > $ingredient->stock) {
                $shortage = InventoryQuantity::format($quantity - $ingredient->stock);
                $shortages[] = "{$ingredient->name} thiếu {$shortage} {$ingredient->unit->label()}";
            }
        }
        if ($shortages !== []) {
            throw $this->conflict($source, 'Không đủ nguyên liệu: '.implode(', ', $shortages).'.');
        }

        foreach ($required as $id => $quantity) {
            $this->move($ingredients[$id], $actor, InventoryMovementKind::Consumption, -$quantity, $source);
        }
    }

    private function move(Ingredient $ingredient, User $actor, InventoryMovementKind $kind, int $change, Order|TableRound|null $source = null, ?string $reason = null, ?string $entryKey = null): void
    {
        $ingredient->stock += $change;
        $ingredient->stock_version++;
        $ingredient->save();
        $ingredient->movements()->create([
            'actor_id' => $actor->id,
            'order_id' => $source instanceof Order ? $source->id : null,
            'table_round_id' => $source instanceof TableRound ? $source->id : null,
            'kind' => $kind,
            'quantity_change' => $change,
            'balance_after' => $ingredient->stock,
            'reason' => $reason,
            'entry_key' => $entryKey,
        ]);
    }

    private function conflict(Order|TableRound $source, string $message): ValidationException
    {
        return ValidationException::withMessages(['inventory' => $message])->status(409)
            ->redirectTo($source instanceof Order
                ? route('operations.orders.show', $source->order_number)
                : route('operations.table-sessions.show', $source->tableSession));
    }
}
