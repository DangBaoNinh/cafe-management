<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeliverySetting;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_the_single_configuration_and_old_forms_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.delivery-settings.edit'))
            ->assertOk()->assertSee('Quận 1')->assertSeeHtml('name="version" value="0"');

        $this->patch(route('admin.delivery-settings.update'), $this->settings())
            ->assertRedirectToRoute('admin.delivery-settings.edit')->assertSessionHas('status');
        $settings = DeliverySetting::query()->sole();
        $this->assertTrue($settings->delivery_enabled);
        $this->assertSame(23000, $settings->flat_fee_vnd);
        $this->assertSame(['001', '760'], $settings->eligible_districts);
        $this->assertSame($admin->id, $settings->updated_by);
        $this->assertSame(1, $settings->version);

        $this->followingRedirects()->from(route('admin.delivery-settings.edit'))
            ->patch(route('admin.delivery-settings.update'), $this->settings(['flat_fee_vnd' => 90000]))
            ->assertOk()->assertSee('Cấu hình đã thay đổi')->assertSeeHtml('name="version" value="1"')
            ->assertSeeHtml('value="23000"')->assertDontSeeHtml('value="90000"');
        $this->assertSame(23000, $settings->refresh()->flat_fee_vnd);
        $this->assertSame(1, $settings->version);
    }

    public function test_invalid_data_is_rejected_without_changing_the_settings(): void
    {
        $this->actingAs($this->admin());

        foreach ([-1, true, 2.5, '1e3', [], DeliverySetting::MAX_FEE_VND + 1] as $fee) {
            $this->patchJson(route('admin.delivery-settings.update'), $this->settings(['flat_fee_vnd' => $fee]))
                ->assertUnprocessable()->assertJsonValidationErrors('flat_fee_vnd');
        }

        foreach ([
            [[], 'eligible_districts'],
            [['760', '760'], 'eligible_districts.0'],
            [['000'], 'eligible_districts.0'],
            [['0760'], 'eligible_districts.0'],
            [[760], 'eligible_districts.0'],
            [['district' => '760'], 'eligible_districts'],
        ] as [$districts, $error]) {
            $this->patchJson(route('admin.delivery-settings.update'), $this->settings(['eligible_districts' => $districts]))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $initial = DeliverySetting::query()->sole();
        $this->assertFalse($initial->delivery_enabled);
        $this->assertSame(0, $initial->version);
        $this->assertSame([], $initial->eligible_districts);
    }

    public function test_only_active_admins_can_edit_and_only_customers_can_preview(): void
    {
        $edit = route('admin.delivery-settings.edit');
        $update = route('admin.delivery-settings.update');
        $preview = route('cart.delivery');
        $this->get($edit)->assertRedirectToRoute('login');
        $this->patch($update, $this->settings())->assertRedirectToRoute('login');
        $this->get($preview)->assertRedirectToRoute('login');

        foreach ([UserRole::Staff, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($edit)->assertForbidden();
            $this->patch($update, $this->settings())->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get($preview)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]))
            ->patch($update, $this->settings())->assertForbidden();
        $this->assertSame(0, DeliverySetting::query()->sole()->version);
    }

    public function test_customer_sees_current_fee_by_owned_address_and_pickup_stays_free(): void
    {
        $customer = User::factory()->create();
        $eligible = $this->address($customer, '760');
        $outside = $this->address($customer, '761');
        $foreign = $this->address(User::factory()->create(), '760');
        $preview = route('cart.delivery', ['address' => $eligible->id]);
        $admin = $this->admin();

        $this->actingAs($customer)->get(route('cart.index'))->assertSee('Xem phí giao hàng và nhận tại quán');
        $this->get($preview)->assertOk()->assertViewHas('deliveryFee', null)->assertViewHas('pickupFee', 0)
            ->assertSee('Không hỗ trợ giao đến địa chỉ này.');

        $this->actingAs($admin)->patch(route('admin.delivery-settings.update'), $this->settings())
            ->assertRedirectToRoute('admin.delivery-settings.edit');
        $this->actingAs($customer)->get($preview)->assertOk()
            ->assertViewHas('deliveryFee', 23000)->assertViewHas('pickupFee', 0)
            ->assertSee('23.000 ₫')->assertSee('Nhận tại quán');
        $this->get(route('cart.delivery', ['address' => $outside->id]))->assertOk()
            ->assertSee('Không hỗ trợ giao đến địa chỉ này.')->assertViewHas('pickupFee', 0);
        $this->get(route('cart.delivery', ['address' => $foreign->id]))->assertNotFound();

        $this->actingAs($admin)->patch(route('admin.delivery-settings.update'), $this->settings([
            'flat_fee_vnd' => '0', 'version' => '1',
        ]))->assertRedirectToRoute('admin.delivery-settings.edit');
        $this->actingAs($customer)->get($preview)->assertOk()->assertViewHas('deliveryFee', 0)
            ->assertDontSee('Không hỗ trợ giao đến địa chỉ này.');

        $this->actingAs($admin)->patch(route('admin.delivery-settings.update'), [
            'delivery_enabled' => '0', 'flat_fee_vnd' => '35000', 'version' => '2',
        ])->assertRedirectToRoute('admin.delivery-settings.edit');
        $this->actingAs($customer)->get($preview)->assertOk()->assertSee('Không hỗ trợ giao đến địa chỉ này.')
            ->assertViewHas('pickupFee', 0);
        $this->assertSame([], DeliverySetting::query()->sole()->eligible_districts);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function settings(array $overrides = []): array
    {
        return [
            'delivery_enabled' => '1',
            'flat_fee_vnd' => '23000',
            'eligible_districts' => ['760', '001'],
            'version' => '0',
            ...$overrides,
        ];
    }

    private function address(User $user, string $district): UserAddress
    {
        return $user->addresses()->create([
            'recipient_name' => 'Khách thử',
            'phone' => '0901234567',
            'province_code' => '79',
            'district_code' => $district,
            'address_line' => '10 Nguyễn Huệ',
        ]);
    }
}
