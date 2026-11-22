<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LinkedBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_is_owned_validated_encrypted_and_visible_in_profile(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $data = ['bank_name' => 'VCB', 'account_number' => '0123456789', 'account_holder' => 'NGUYEN VAN A', 'confirm' => 1, 'user_id' => $other->id];
        $this->post(route('profile.bank.update'), $data)->assertRedirectToRoute('login');
        $this->actingAs($customer)->postJson(route('profile.bank.update'), [...$data, 'account_number' => '<script>'])->assertUnprocessable();
        $this->post(route('profile.bank.update'), $data)->assertRedirectToRoute('profile.edit');
        $this->assertSame('0123456789', $customer->fresh()->linked_bank['account_number']);
        $this->assertNull($other->fresh()->linked_bank);
        $this->assertStringNotContainsString('0123456789', DB::table('users')->where('id', $customer->id)->value('linked_bank'));
        $this->assertArrayNotHasKey('linked_bank', $customer->fresh()->toArray());
        $this->get(route('profile.edit'))->assertOk()->assertSee('Liên kết tài khoản ngân hàng');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->post(route('profile.bank.update'), $data)->assertForbidden();
    }
}
