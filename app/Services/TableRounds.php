<?php

namespace App\Services;

use App\Enums\TableRoundStatus;
use App\Enums\TableSessionStatus;
use App\Models\Product;
use App\Models\TableRound;
use App\Models\TableSession;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Selection array{product_id: int, size_id: int, quantity: int, sugar_level: ?string, ice_level: ?string, topping_ids: list<int>}
 * @phpstan-type PricedLine array{product_size_id: int, product_name: string, size_label: string, quantity: int, sugar_level: ?string, ice_level: ?string, toppings: list<array{id: int, name: string, price_vnd: int}>, unit_price_vnd: int, line_total_vnd: int}
 */
class TableRounds
{
    public function __construct(
        private DrinkPricing $pricing,
        private InventoryControl $inventory,
        private InventoryRecipes $recipes,
        private InventoryStock $stock,
    ) {}

    /** @param list<Selection> $selections */
    public function submit(TableSession $target, array $selections, string $key, ?User $actor = null): TableRound
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('operate-orders');
        }
        $hash = hash('sha256', json_encode($selections, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($target, $actor, $selections, $key, $hash): TableRound {
            $this->inventory->lockForOperation();
            $session = TableSession::query()->lockForUpdate()->findOrFail($target->id);
            $this->require($session->status !== TableSessionStatus::Closed, 'Phiên này đã đóng.');
            $existing = $session->rounds()->where('submission_key', $key)->first();
            if ($existing !== null) {
                $this->require(hash_equals($existing->request_hash, $hash), 'Mã gửi này đã dùng cho nội dung khác. Hãy tải lại trang.');

                return $existing;
            }
            $this->require($session->status === TableSessionStatus::Open, 'Phiên đã khóa gọi thêm.');
            $lines = $this->price($selections);
            $round = $session->rounds()->create([
                'submission_key' => $key, 'request_hash' => $hash, 'entered_by' => $actor?->id,
                'status' => TableRoundStatus::Pending, 'total_vnd' => array_sum(array_column($lines, 'line_total_vnd')),
                'inventory_snapshot' => $this->recipes->snapshotForLines($lines),
            ]);
            $round->items()->createMany($lines);

            return $round;
        }, 5);
    }

    public function advance(User $actor, TableSession $target, string $roundId, TableRoundStatus $expected, TableRoundStatus $next, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $target, $roundId, $expected, $next, $reason): void {
            $trackStock = $next === TableRoundStatus::Preparing && $this->inventory->lockForOperation();
            $session = TableSession::query()->lockForUpdate()->findOrFail($target->id);
            $round = $session->rounds()->findOrFail($roundId);
            $this->require(in_array($session->status, [TableSessionStatus::Open, TableSessionStatus::Closing], true) && $session->billed_at === null,
                'Hóa đơn đã chốt hoặc phiên đã kết thúc.');
            $this->require($round->status === $expected && in_array($next, $round->status->next(), true), 'Lượt đã đổi trạng thái hoặc thao tác sai thứ tự.');
            $this->require(! $next->needsReason() || filled($reason), 'Nhập lý do từ chối hoặc hủy lượt.');
            if ($trackStock) {
                $round->setRelation('tableSession', $session);
                $this->stock->consume($round, $actor);
            }
            $round->update(['status' => $next]);
            $round->events()->create([
                'actor_id' => $actor->id, 'from_status' => $expected, 'to_status' => $next, 'reason' => $next->needsReason() ? $reason : null,
            ]);
        }, 5);
    }

    /**
     * @param  list<Selection>  $selections
     * @return list<PricedLine>
     */
    private function price(array $selections): array
    {
        $products = Product::query()->visibleInCatalog()->whereKey(array_values(array_unique(array_column($selections, 'product_id'))))
            ->with(['activeSizes', 'activeToppings'])->get()->keyBy('id');

        $lines = [];
        foreach ($selections as $index => $selection) {
            $product = $products->get($selection['product_id']);
            $number = $index + 1;
            if ($product === null) {
                throw ValidationException::withMessages(['lines' => "Món ở dòng {$number} hiện không còn bán."]);
            }

            try {
                $quote = $this->pricing->quote($product, $selection);
            } catch (ValidationException $exception) {
                $message = $exception->validator->errors()->first();

                throw ValidationException::withMessages(['lines' => "Dòng {$number}: {$message}"]);
            }

            $size = $product->activeSizes->findOrFail($quote['size_id']);
            $toppings = $product->activeToppings->whereIn('id', $quote['topping_ids'])->sortBy('id')->values();
            $lines[] = [
                'product_size_id' => $quote['size_id'], 'product_name' => $product->name, 'size_label' => $size->label,
                'quantity' => $selection['quantity'], 'sugar_level' => $quote['sugar_level'], 'ice_level' => $quote['ice_level'],
                'toppings' => array_values($toppings->map(fn (Topping $topping): array => [
                    'id' => $topping->id, 'name' => $topping->name, 'price_vnd' => $topping->price_vnd,
                ])->all()),
                'unit_price_vnd' => $quote['unit_price_vnd'], 'line_total_vnd' => $quote['unit_price_vnd'] * $selection['quantity'],
            ];
        }

        return $lines;
    }

    private function require(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['table_round' => $message])->status(409);
        }
    }
}
