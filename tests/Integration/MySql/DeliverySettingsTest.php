<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\DeliverySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeliverySettingsTest extends MySqlTestCase
{
    public function test_mysql_preserves_district_codes_and_rejects_overwriting_a_newer_version(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $customer = User::factory()->create();
            $address = $customer->addresses()->create([
                'recipient_name' => 'Khách thử',
                'phone' => '0901234567',
                'province_code' => '01',
                'district_code' => '001',
                'address_line' => '10 Đường thử',
            ]);
            $settings = DeliverySetting::query()->sole();
            $version = $settings->version;
            $update = route('admin.delivery-settings.update');
            $payload = [
                'delivery_enabled' => true,
                'flat_fee_vnd' => DeliverySetting::MAX_FEE_VND,
                'eligible_districts' => ['760', '001'],
                'version' => $version,
            ];

            $this->actingAs($admin)->patchJson($update, $payload)->assertRedirectToRoute('admin.delivery-settings.edit');
            $this->patchJson($update, [...$payload, 'flat_fee_vnd' => 0])
                ->assertUnprocessable()->assertJsonValidationErrors('version');
            $settings->refresh();
            $this->assertSame($version + 1, $settings->version);
            $this->assertSame(['001', '760'], $settings->eligible_districts);
            $this->assertSame($admin->id, $settings->updated_by);
            $this->actingAs($customer)->get(route('cart.delivery', ['address' => $address->id]))
                ->assertOk()->assertViewHas('deliveryFee', DeliverySetting::MAX_FEE_VND)->assertViewHas('pickupFee', 0);
        } finally {
            $this->primary->rollBack();
        }
    }
}
