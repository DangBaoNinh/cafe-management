<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveDeliverySettingsRequest;
use App\Models\DeliverySetting;
use App\Services\VietnamAddressCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverySettingsController extends Controller
{
    public function edit(VietnamAddressCatalog $catalog): View
    {
        return view('admin.delivery-settings', [
            'settings' => DeliverySetting::query()->findOrFail(DeliverySetting::SINGLETON_ID),
            'catalog' => $catalog,
        ]);
    }

    public function update(SaveDeliverySettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $districts = $data['eligible_districts'] ?? [];
        sort($districts, SORT_STRING);

        DB::transaction(function () use ($request, $data, $districts): void {
            $settings = DeliverySetting::query()->lockForUpdate()->findOrFail(DeliverySetting::SINGLETON_ID);

            if ($settings->version !== (int) $data['version']) {
                throw ValidationException::withMessages([
                    'version' => 'Cấu hình đã thay đổi. Vui lòng kiểm tra dữ liệu mới trước khi lưu lại.',
                ])->redirectTo(route('admin.delivery-settings.edit'));
            }

            $settings->fill([
                'delivery_enabled' => $data['delivery_enabled'],
                'flat_fee_vnd' => $data['flat_fee_vnd'],
                'eligible_districts' => $districts,
            ]);
            $settings->updated_by = $request->user()->getAuthIdentifier();
            $settings->version++;
            $settings->save();
        }, 3);

        return to_route('admin.delivery-settings.edit')->with('status', 'Đã lưu cấu hình giao hàng.');
    }
}
