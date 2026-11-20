<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AccountAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AccountAccessTest extends MySqlTestCase
{
    public function test_crossed_admin_updates_wait_and_recheck_the_actors_role(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            [$first, $second] = User::factory()->count(2)->create(['role' => UserRole::Admin])->all();
            $access = app(AccountAccess::class);

            $access->update($first, $second->id, UserRole::Staff, true, UserRole::Admin, true);

            $this->peer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $peerActor = (clone $second)->setConnection($this->peer->getName());

            try {
                $access->update($peerActor, $first->id, UserRole::Staff, true, UserRole::Admin, true);
                $this->fail('The competing update must wait for the locked users.');
            } catch (QueryException $exception) {
                $this->assertSame(1205, $exception->errorInfo[1]);
                $this->assertStringContainsString('for update', $exception->getSql());
            }

            $this->assertSame(UserRole::Admin, $first->fresh()->role);
            $this->assertSame(UserRole::Staff, $second->fresh()->role);

            $this->expectException(AuthorizationException::class);

            // The caller still holds its original admin model, but the locked row is now staff.
            $access->update($second, $first->id, UserRole::Staff, true, UserRole::Admin, true);
        } finally {
            $this->primary->rollBack();
        }
    }
}
