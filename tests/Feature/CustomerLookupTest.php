<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_searches_customer_names_and_email_with_a_status_filter(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'email' => 'team-admin@example.test']);
        User::factory()->create(['role' => UserRole::Staff, 'email' => 'team-staff@example.test', 'is_active' => false]);
        $active = User::factory()->create(['name' => 'Khách Linh', 'email' => 'team-active@example.test']);
        $locked = User::factory()->create(['email' => 'team-locked@example.test', 'is_active' => false]);
        User::factory()->create(['email' => 'other@example.test', 'is_active' => false]);

        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => 'TEAM-', 'status' => 'locked', 'role' => 'staff']))
            ->assertOk()->assertSee($locked->email)->assertDontSee($active->email)
            ->assertDontSee('other@example.test')->assertDontSee('team-staff@example.test')->assertDontSee($admin->email);
        $this->get(route('admin.customers.index', ['search' => 'Linh', 'status' => 'active']))
            ->assertOk()->assertSee($active->email)->assertDontSee($locked->email);
        $this->get(route('admin.customers.index'))->assertOk()->assertSee($active->email)->assertSee($locked->email)
            ->assertDontSee('team-staff@example.test')->assertDontSee($admin->email);
        $this->get(route('admin.customers.index', ['search' => 'nobody']))
            ->assertOk()->assertSee('Không tìm thấy khách hàng.');
        $this->getJson(route('admin.customers.index', ['search' => ['nobody'], 'status' => 'unknown']))
            ->assertUnprocessable()->assertJsonValidationErrors(['search', 'status']);
        $this->get(route('admin.customers.index', ['status' => 'unknown']))
            ->assertRedirectToRoute('admin.customers.index')->assertSessionHasErrors('status');
    }

    public function test_search_treats_wildcard_characters_as_literal_and_paginates_with_the_filters(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        for ($index = 1; $index <= 21; $index++) {
            User::factory()->create([
                'name' => 'Đồng tên',
                'email' => sprintf('support_!%02d@example.test', $index),
                'is_active' => false,
            ]);
        }
        User::factory()->create(['email' => 'supportX!00@example.test', 'is_active' => false]);
        User::factory()->create(['name' => 'Giảm 20%', 'email' => 'percent@example.test']);
        User::factory()->create(['name' => 'Giảm 200', 'email' => 'plain@example.test']);
        $filters = ['search' => 'support_!', 'status' => 'locked'];

        $this->actingAs($admin)->get(route('admin.customers.index', $filters))
            ->assertOk()->assertSeeInOrder(['support_!01@example.test', 'support_!20@example.test'])
            ->assertDontSee('support_!21@example.test')->assertDontSee('supportX!00@example.test')
            ->assertSee(route('admin.customers.index', $filters + ['page' => 2]));
        $this->get(route('admin.customers.index', $filters + ['page' => 2]))
            ->assertOk()->assertSee('support_!21@example.test')->assertDontSee('support_!20@example.test');
        $this->get(route('admin.customers.index', $filters + ['page' => 9]))
            ->assertOk()->assertSee('Trang này không có khách hàng.');
        $this->get(route('admin.customers.index', ['search' => '20%']))
            ->assertOk()->assertSee('percent@example.test')->assertDontSee('plain@example.test');
    }

    public function test_profile_exposes_only_basic_fields_and_links_to_the_customers_own_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create([
            'name' => '<b>Khách bị khóa</b>',
            'remember_token' => 'private-remember-token',
            'is_active' => false,
        ]);
        $other = User::factory()->create();
        $other->orders()->create($this->orderData('CF-OTHER-CUSTOMER'));
        for ($index = 1; $index <= 16; $index++) {
            $customer->orders()->create($this->orderData(sprintf('CF-OWN-%02d', $index)));
        }
        $profile = route('admin.customers.show', $customer);
        $order = route('admin.orders.show', 'CF-OWN-16');
        $account = route('admin.accounts.edit', $customer);

        $index = $this->actingAs($admin)->get(route('admin.customers.index'))
            ->assertOk()->assertSee($profile)->assertSee($customer->name)->assertDontSee($customer->name, false)
            ->assertDontSee($customer->password)->assertDontSee($customer->remember_token);
        $this->assertSame(['id', 'name', 'email', 'is_active'], array_keys($index->viewData('customers')->first()->getAttributes()));
        $response = $this->get($profile.'?order=CF-OTHER-CUSTOMER')
            ->assertOk()->assertSee($customer->email)->assertSee('Đã khóa')->assertSee('Đơn hàng (16)')
            ->assertSee($order)->assertSee($account)->assertSeeInOrder(['CF-OWN-16', 'CF-OWN-02'])
            ->assertDontSee('CF-OWN-01')->assertDontSee('CF-OTHER-CUSTOMER')
            ->assertDontSee('0901234567')->assertDontSee('Tên người nhận riêng')
            ->assertDontSee($customer->password)->assertDontSee($customer->remember_token);
        $this->assertSame(['id', 'name', 'email', 'is_active'], array_keys($response->viewData('customer')->getAttributes()));
        $this->get($profile.'?page=2')->assertOk()->assertSee('CF-OWN-01')->assertDontSee('CF-OTHER-CUSTOMER');
        $this->get($order)->assertOk()->assertSee('Đơn CF-OWN-16')->assertSee('Tên người nhận riêng');
        $this->get($account)->assertRedirectToRoute('password.confirm');
        $this->withSession(['auth.password_confirmed_at' => time()])->get($account)
            ->assertOk()->assertSee($customer->email);
    }

    public function test_lookup_requires_an_active_admin_and_customer_role_for_the_profile(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $lockedAdmin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $index = route('admin.customers.index');
        $profile = route('admin.customers.show', $customer);

        $this->get($index)->assertRedirectToRoute('login');
        $this->get($profile)->assertRedirectToRoute('login');
        foreach ([$customer, $staff, $lockedAdmin] as $actor) {
            $this->actingAs($actor)->get($index)->assertForbidden();
            $this->get($profile)->assertForbidden();
        }

        $this->actingAs($admin)->get($profile)->assertOk()->assertSee('Khách hàng chưa có đơn hàng.');
        foreach ([$admin->id, $staff->id, $customer->id + 100, 'abc'] as $id) {
            $this->get(route('admin.customers.show', $id))->assertNotFound();
        }
        $this->post($profile, ['is_active' => false])->assertMethodNotAllowed();
        $this->assertTrue($customer->refresh()->is_active);
    }

    /** @return array<string, mixed> */
    private function orderData(string $number): array
    {
        return [
            'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', $number), 'recipient_name' => 'Tên người nhận riêng',
            'phone' => '0901234567', 'fulfillment_type' => 'pickup', 'status' => OrderStatus::Ready,
            'subtotal_vnd' => 40000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ];
    }
}
