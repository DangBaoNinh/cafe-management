<?php

namespace Tests\Integration\MySql;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseTransactionsTest extends MySqlTestCase
{
    public function test_failed_transaction_rolls_back_writes_to_both_tables(): void
    {
        $id = (string) Str::uuid();
        $email = "test-{$id}@example.test";
        $failure = new RuntimeException('Simulated failure after both inserts');

        try {
            $this->primary->transaction(function () use ($id, $email, $failure): void {
                $this->primary->table('users')->insert([
                    'name' => 'Transaction fixture',
                    'email' => $email,
                    'password' => 'unused-fixture-value',
                ]);
                $this->primary->table('cache')->insert([
                    'key' => $id,
                    'value' => 'fixture',
                    'expiration' => now()->addMinute()->timestamp,
                ]);

                $this->assertTrue($this->primary->table('users')->where('email', $email)->exists());
                $this->assertTrue($this->primary->table('cache')->where('key', $id)->exists());
                $this->assertFalse($this->peer->table('users')->where('email', $email)->exists());
                $this->assertFalse($this->peer->table('cache')->where('key', $id)->exists());

                throw $failure;
            });
            $this->fail('Expected the transaction to throw.');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }

        $this->assertFalse($this->peer->table('users')->where('email', $email)->exists());
        $this->assertFalse($this->peer->table('cache')->where('key', $id)->exists());
    }

    public function test_row_lock_blocks_another_session_until_the_transaction_ends(): void
    {
        $this->assertNotSame(
            $this->primary->selectOne('SELECT CONNECTION_ID() AS id')->id,
            $this->peer->selectOne('SELECT CONNECTION_ID() AS id')->id,
        );
        // Use a committed migration row so the lock test needs no persistent fixtures.
        $id = $this->primary->table('migrations')->min('id');
        $this->assertNotNull($id);

        $this->primary->transaction(function () use ($id): void {
            $locked = $this->primary->table('migrations')->where('id', $id)->lockForUpdate()->first();
            $this->assertNotNull($locked);

            try {
                $this->peer->transaction(fn () => $this->peer->table('migrations')
                    ->where('id', $id)->lock('for update nowait')->first());
                $this->fail('Expected MySQL to report a row lock conflict.');
            } catch (QueryException $error) {
                $this->assertSame(3572, $error->errorInfo[1]);
            }
        });

        $result = $this->peer->transaction(fn () => $this->peer->table('migrations')
            ->where('id', $id)->lock('for update nowait')->first());
        $this->assertSame($id, $result?->id);
    }
}
