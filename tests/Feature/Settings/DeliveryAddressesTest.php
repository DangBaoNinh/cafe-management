<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Livewire\DeliveryAddresses;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryAddressesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_customer_can_open_the_address_book(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get(route('addresses.index'))->assertForbidden();
        Livewire::test(DeliveryAddresses::class)->assertForbidden();

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('dashboard'))->assertSee(route('addresses.index'));
        $this->get(route('addresses.index'))
            ->assertOk()
            ->assertSee('Địa chỉ giao hàng')
            ->assertSee('Bạn chưa có địa chỉ giao hàng.')
            ->assertSee('Thành phố Hà Nội');
    }

    public function test_a_customer_can_enter_and_edit_an_address(): void
    {
        $customer = User::factory()->create();
        $other = $this->createAddress(User::factory()->create(), ['recipient_name' => 'Người nhận riêng']);
        $this->actingAs($customer);

        $form = Livewire::test(DeliveryAddresses::class)
            ->assertDontSee($other->recipient_name)
            ->set('recipientName', '  Khách thử  ')
            ->set('phone', '  +84901234567  ')
            ->set('provinceCode', '01')
            ->assertSee('Quận Ba Đình')
            ->set('districtCode', '001')
            ->assertSee('Phường Phúc Xá')
            ->set('wardCode', '00001')
            ->set('addressLine', '  12 Phó Đức Chính  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('12 Phó Đức Chính')
            ->assertSee('Phường Phúc Xá');

        $address = $customer->addresses()->sole();
        $this->assertSame('Khách thử', $address->recipient_name);
        $this->assertSame('+84901234567', $address->phone);
        $this->assertFalse($address->is_default);

        $form->call('edit', $address->id)
            ->assertSet('wardCode', '00001')
            ->set('addressLine', '14 Phó Đức Chính')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('14 Phó Đức Chính');

        $this->assertSame('14 Phó Đức Chính', $address->fresh()->address_line);
        $this->assertDatabaseCount('user_addresses', 2);
    }

    public function test_invalid_phone_and_unrelated_administrative_codes_are_rejected(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);

        $form = Livewire::test(DeliveryAddresses::class)
            ->set('recipientName', 'Người nhận')
            ->set('addressLine', '12 Phó Đức Chính')
            ->set('phone', '123')
            ->set('provinceCode', '01')
            ->set('districtCode', '760')
            ->set('wardCode', '00001')
            ->call('save')
            ->assertHasErrors(['phone' => 'regex', 'districtCode' => 'in', 'wardCode' => 'in']);

        $form->set('phone', '0901234567')
            ->set('districtCode', '001')
            ->assertSet('wardCode', '')
            ->call('save')
            ->assertHasErrors(['wardCode' => 'required']);

        $form->set('wardCode', '00001')
            ->set('provinceCode', '79')
            ->assertSet('districtCode', '')
            ->assertSet('wardCode', '');

        $this->assertDatabaseCount('user_addresses', 0);
    }

    public function test_a_district_without_wards_can_be_saved(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);

        Livewire::test(DeliveryAddresses::class)
            ->set('recipientName', 'Người nhận')
            ->set('addressLine', 'Địa chỉ trên đảo')
            ->set('phone', '0901234567')
            ->set('provinceCode', '31')
            ->set('districtCode', '318')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($customer->addresses()->sole()->ward_code);
    }

    public function test_set_default_switches_the_flag_and_deleting_it_leaves_no_default(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $first = $this->createAddress($customer, ['is_default' => true]);
        $second = $this->createAddress($customer);
        $other = $this->createAddress(User::factory()->create(), ['is_default' => true]);

        $form = Livewire::test(DeliveryAddresses::class)
            ->call('setDefault', $second->id)
            ->call('setDefault', $second->id)
            ->assertHasNoErrors();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertTrue($other->fresh()->is_default);

        $form->call('edit', $second->id)
            ->call('delete', $second->id)
            ->assertSet('editingId', null);

        $this->assertModelMissing($second);
        $this->assertFalse($customer->addresses()->where('is_default', true)->exists());
    }

    public static function scopedActions(): array
    {
        return ['edit' => ['edit'], 'set default' => ['setDefault'], 'delete' => ['delete']];
    }

    #[DataProvider('scopedActions')]
    public function test_a_customer_cannot_act_on_another_customers_address(string $action): void
    {
        $foreign = $this->createAddress(User::factory()->create());
        $this->actingAs(User::factory()->create());

        Livewire::test(DeliveryAddresses::class)->call($action, $foreign->id)->assertNotFound();

        $this->assertModelExists($foreign);
        $this->assertFalse($foreign->fresh()->is_default);
    }

    public function test_a_customer_cannot_overwrite_the_editing_id(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $own = $this->createAddress($customer);
        $foreign = $this->createAddress(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(DeliveryAddresses::class)
            ->call('edit', $own->id)
            ->set('editingId', $foreign->id);
    }

    private function createAddress(User $customer, array $attributes = []): UserAddress
    {
        return $customer->addresses()->create(array_replace([
            'recipient_name' => 'Người nhận',
            'phone' => '0901234567',
            'province_code' => '01',
            'district_code' => '001',
            'ward_code' => '00001',
            'address_line' => '12 Phó Đức Chính',
            'is_default' => false,
        ], $attributes));
    }
}
