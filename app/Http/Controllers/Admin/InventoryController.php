<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IngredientUnit;
use App\Enums\InventoryMovementKind;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\InventoryControl;
use App\Services\InventoryStock;
use App\Support\InventoryQuantity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private InventoryControl $control) {}

    public function index(): View
    {
        return view('admin.inventory.index', [
            'ingredients' => Ingredient::query()->orderBy('name')->paginate(20),
            'enabled' => $this->control->enabled(),
        ]);
    }

    public function show(Ingredient $ingredient): View
    {
        return view('admin.inventory.show', [
            'ingredient' => $ingredient,
            'movements' => $ingredient->movements()->with(['actor', 'order'])->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->ingredientFields($request, new Ingredient);
        $data['unit'] = $request->validate([
            'unit' => ['required', Rule::enum(IngredientUnit::class)],
        ], [], ['unit' => 'đơn vị cơ sở'])['unit'];

        $ingredient = DB::transaction(function () use ($data): Ingredient {
            $this->control->lockForOperation();
            $ingredient = new Ingredient;
            $this->saveIngredient($ingredient, $data);

            return $ingredient;
        }, 5);

        return to_route('admin.inventory.show', $ingredient, 303)->with('status', 'Đã khai báo nguyên liệu.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->saveIngredient($ingredient, $this->ingredientFields($request, $ingredient));

        return to_route('admin.inventory.show', $ingredient, 303)->with('status', 'Đã lưu thông tin nguyên liệu.');
    }

    public function record(Request $request, Ingredient $ingredient, InventoryStock $stock): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(InventoryMovementKind::class)->except(InventoryMovementKind::Consumption)],
            'quantity' => ['required', 'string', InventoryQuantity::SIGNED_RULE],
            'reason' => ['required', 'string', 'max:255'],
            'stock_version' => ['required', 'integer', 'min:0'],
            'entry_key' => ['required', 'uuid'],
        ], [], ['kind' => 'loại biến động', 'quantity' => 'số lượng', 'reason' => 'lý do', 'stock_version' => 'phiên bản tồn', 'entry_key' => 'mã phiếu']);
        $kind = InventoryMovementKind::from($data['kind']);
        $quantity = InventoryQuantity::parse($data['quantity']);
        if (($kind === InventoryMovementKind::Receipt && $quantity <= 0)
            || ($kind === InventoryMovementKind::Count && $quantity < 0)
            || ($kind === InventoryMovementKind::Adjustment && $quantity === 0)) {
            throw ValidationException::withMessages(['quantity' => 'Nhập kho cần số dương, kiểm kê cần từ 0 trở lên, điều chỉnh cần khác 0.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        $stock->record($actor, $ingredient, $kind, $quantity, $data['reason'], (int) $data['stock_version'], $data['entry_key']);

        return to_route('admin.inventory.show', $ingredient, 303)->with('status', 'Đã ghi biến động tồn kho.');
    }

    public function enable(): RedirectResponse
    {
        $this->control->enable();

        return to_route('admin.inventory.index', status: 303)->with('status', 'Đã bật kiểm soát tồn khi bắt đầu pha chế.');
    }

    /** @return array{name: string, low_threshold: int} */
    private function ingredientFields(Request $request, Ingredient $ingredient): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique(Ingredient::class)->ignore($ingredient)],
            'low_threshold' => ['required', 'string', InventoryQuantity::UNSIGNED_RULE],
        ], [], ['name' => 'tên nguyên liệu', 'low_threshold' => 'ngưỡng tồn thấp']);

        return ['name' => $data['name'], 'low_threshold' => InventoryQuantity::parse($data['low_threshold'])];
    }

    /** @param array{name: string, low_threshold: int, unit?: string} $data */
    private function saveIngredient(Ingredient $ingredient, array $data): void
    {
        try {
            $ingredient->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Tên nguyên liệu đã được sử dụng.']);
        }
    }
}
