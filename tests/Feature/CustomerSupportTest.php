<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_start_and_reply_to_own_conversation(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('support.store'), [
            'subject' => 'Cần hỗ trợ đơn hàng', 'message' => 'Xin kiểm tra giúp tôi.',
        ])->assertRedirect();

        $conversation = SupportConversation::query()->sole();
        $this->assertSame($customer->id, $conversation->user_id);
        $this->assertSame('open', $conversation->status);
        $this->assertCount(1, $conversation->messages);

        $this->post(route('support.reply', $conversation), ['message' => 'Tôi xin bổ sung thông tin.'])
            ->assertRedirectToRoute('support.show', $conversation);
        $this->assertCount(2, $conversation->fresh()->messages);
    }

    public function test_customer_cannot_read_or_reply_to_another_customers_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $owner->supportConversations()->create(['subject' => 'Riêng tư', 'status' => 'open', 'last_message_at' => now()]);
        $conversation->messages()->create(['sender_id' => $owner->id, 'body' => 'Nội dung riêng tư']);

        $this->actingAs($other)->get(route('support.show', $conversation))->assertNotFound();
        $this->post(route('support.reply', $conversation), ['message' => 'Không được phép'])->assertNotFound();
    }

    public function test_staff_can_reply_and_close_but_customer_cannot_reply_after_close(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $conversation = $customer->supportConversations()->create(['subject' => 'Thanh toán', 'status' => 'open', 'last_message_at' => now()]);
        $conversation->messages()->create(['sender_id' => $customer->id, 'body' => 'Tôi cần trợ giúp']);

        $this->actingAs($staff)->post(route('operations.support.reply', $conversation), ['message' => 'Cửa hàng đã nhận tin.'])
            ->assertRedirectToRoute('operations.support.show', $conversation);
        $this->post(route('operations.support.close', $conversation))->assertRedirectToRoute('operations.support.show', $conversation);
        $this->assertSame('closed', $conversation->refresh()->status);
        $this->assertSame($staff->id, $conversation->closed_by);

        $this->actingAs($customer)->post(route('support.reply', $conversation), ['message' => 'Tin nhắn muộn'])
            ->assertSessionHasErrors('message');
    }
}
