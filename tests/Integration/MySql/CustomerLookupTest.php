<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerLookupTest extends MySqlTestCase
{
    public function test_literal_email_search_combines_with_role_and_status_on_mysql(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $prefix = Str::lower(Str::random(12));
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $match = User::factory()->create(['email' => $prefix.'_!%locked@example.test', 'is_active' => false]);
            $wildcard = User::factory()->create(['email' => $prefix.'X!Ylocked@example.test', 'is_active' => false]);
            $active = User::factory()->create(['email' => $prefix.'_!%active@example.test']);
            $staff = User::factory()->create([
                'email' => $prefix.'_!%staff@example.test', 'role' => UserRole::Staff, 'is_active' => false,
            ]);

            $this->actingAs($admin)->get(route('admin.customers.index', [
                'search' => strtoupper($prefix).'_!%', 'status' => 'locked',
            ]))->assertOk()->assertSee($match->email)
                ->assertDontSee($wildcard->email)->assertDontSee($active->email)->assertDontSee($staff->email);
            $this->get(route('admin.customers.show', $match))->assertOk()->assertSee($match->email);
            $this->get(route('admin.customers.show', $staff))->assertNotFound();
        } finally {
            $this->primary->rollBack();
        }
    }
}
