<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ReservationSeat;
use App\Models\TableInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): array
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        $seat = ReservationSeat::query()->where('code', 'T1-B01')->sole();
        $this->post(route('operations.table-sessions.table.order', $seat->code))->assertStatus(303);
        $session = $seat->activeTableSession()->sole();
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'coffee']);
        $product = $category->products()->create(['name' => 'Latte', 'slug' => 'latte']);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);
        $this->post(route('operations.table-sessions.pos.store', $session), [
            'submission_key' => (string) Str::uuid(), 'revision' => 0, 'method' => 'cash', 'intent' => 'draft', 'accepted_total_vnd' => 40000,
            'lines' => [['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 1]],
        ])->assertStatus(303);

        return [$staff, $seat, $session, TableInvoice::query()->sole()];
    }

    public function test_reopening_a_table_resumes_its_draft_even_after_the_table_is_freed(): void
    {
        [, $seat, $session, $invoice] = $this->draft();
        $order = route('operations.table-sessions.table.order', $seat->code);
        $this->post($order)->assertRedirectToRoute('operations.invoices.prepaid.show', $invoice);
        $this->get(route('operations.table-sessions.index'))->assertOk()->assertSee($invoice->number)
            ->assertSee('Trạng thái bàn')->assertDontSee('Mã phiên cho khách')->assertDontSee('Đối chiếu thanh toán');
        $this->get(route('operations.table-sessions.show', $session))->assertRedirectToRoute('operations.table-sessions.index');
        $this->post(route('operations.table-sessions.table.status', $seat->code), ['expected' => 'occupied', 'status' => 'free'])->assertStatus(303);
        $this->assertSame('free', $seat->fresh()->availability());
        $this->assertSame('draft', $invoice->refresh()->status);
        $this->post($order)->assertRedirectToRoute('operations.invoices.prepaid.show', $invoice);
        $this->assertDatabaseCount('table_sessions', 1);
        $this->post(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 1])->assertStatus(303);
        $this->post($order)->assertStatus(303);
        $this->assertDatabaseCount('table_sessions', 2);
        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_status_changes_are_authorized_and_reject_stale_requests(): void
    {
        $seat = ReservationSeat::query()->where('code', 'T1-B01')->sole();
        $path = route('operations.table-sessions.table.status', $seat->code);
        $payload = ['expected' => 'free', 'status' => 'occupied'];
        $this->post($path, $payload)->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create())->postJson($path, $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->post($path, $payload)->assertStatus(303);
        $this->postJson($path, ['expected' => 'free', 'status' => 'free'])->assertConflict();
        $this->postJson($path, ['expected' => 'occupied', 'status' => 'inactive'])->assertForbidden();
        $this->post($path, ['expected' => 'occupied', 'status' => 'free'])->assertStatus(303);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->post($path, ['expected' => 'free', 'status' => 'inactive'])->assertStatus(303);
        $this->assertSame('inactive', $seat->fresh()->availability());
        $this->post($path, ['expected' => 'inactive', 'status' => 'free'])->assertStatus(303);
        $this->assertSame('free', $seat->fresh()->availability());
    }

    public function test_customer_qr_requires_a_valid_signed_link_and_is_revoked_on_returning_the_table(): void
    {
        [$staff, $seat, $session] = $this->draft();
        $link = URL::temporarySignedRoute('table-orders.access', now()->addHour(), ['reservationSeat' => $seat->code, 'tableSession' => $session]);
        Auth::forgetGuards();
        $this->get(route('table-orders.show', $seat->code))->assertOk()->assertDontSee('Mã phiên do nhân viên cung cấp');
        $this->get(str_replace('signature=', 'signature=bad', $link))->assertForbidden();
        $this->get($link)->assertStatus(303)->assertSessionHas('table_order_session', $session->reference);
        $this->get(route('table-orders.pos', $seat->code))->assertOk();
        $cross = URL::temporarySignedRoute('table-orders.access', now()->addHour(), ['reservationSeat' => 'T2-B01', 'tableSession' => $session]);
        $this->get($cross)->assertForbidden();
        $this->actingAs($staff)->post(route('operations.table-sessions.table.status', $seat->code), ['expected' => 'occupied', 'status' => 'free'])->assertStatus(303);
        Auth::forgetGuards();
        $this->get($link)->assertForbidden();
        $this->get(route('table-orders.pos', $seat->code))->assertForbidden();
    }
}
