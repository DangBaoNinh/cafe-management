<?php

namespace Tests\Integration\MySql;

use App\Models\User;
use App\Services\DeliveryAddressBook;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DeliveryAddressesTest extends MySqlTestCase
{
    private const ADDRESS = [
        'recipient_name' => 'Người nhận',
        'phone' => '0901234567',
        'province_code' => '01',
        'district_code' => '001',
        'ward_code' => '00001',
        'address_line' => '12 Phó Đức Chính',
    ];

    public function test_the_generated_unique_key_enforces_one_default_per_customer(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $addressBook = app(DeliveryAddressBook::class);
            $first = $addressBook->save($customer, self::ADDRESS + ['is_default' => true]);
            $second = $addressBook->save($customer, self::ADDRESS + ['is_default' => true]);
            $third = $addressBook->save($customer, self::ADDRESS + ['is_default' => false]);

            $addressBook->save($customer, ['is_default' => true], $first->id);
            $this->assertSame($first->id, $customer->addresses()->where('is_default', true)->sole()->id);
            $this->assertFalse($second->fresh()->is_default);
            $this->assertFalse($third->fresh()->is_default);

            $this->expectException(UniqueConstraintViolationException::class);

            $second->refresh()->update(['is_default' => true]);
        } finally {
            $this->primary->rollBack();
        }
    }

    public function test_another_mysql_connection_must_wait_for_the_customer_row_before_saving(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $addressBook = app(DeliveryAddressBook::class);
            $address = $addressBook->save($customer, self::ADDRESS + ['is_default' => true]);

            $this->peer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $peerCustomer = (clone $customer)->setConnection($this->peer->getName());

            try {
                $addressBook->save($peerCustomer, ['is_default' => true], $address->id);
                $this->fail('The peer should wait for the locked customer row.');
            } catch (QueryException $exception) {
                $this->assertSame(1205, $exception->errorInfo[1]);
                $this->assertStringContainsString('for update', $exception->getSql());
            }

            $this->assertSame($address->id, $customer->addresses()->where('is_default', true)->sole()->id);
        } finally {
            $this->primary->rollBack();
        }
    }
}
