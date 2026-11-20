<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAddress;
use Closure;
use Illuminate\Support\Facades\Gate;

class DeliveryAddressBook
{
    /** @param array<string, string|bool|null> $attributes */
    public function save(User $customer, array $attributes, ?int $id = null): UserAddress
    {
        return $this->write($customer, function (User $owner) use ($attributes, $id): UserAddress {
            $address = $id === null
                ? $owner->addresses()->make()
                : $owner->addresses()->findOrFail($id);

            if (($attributes['is_default'] ?? false) && (! $address->exists || ! $address->is_default)) {
                $owner->addresses()->where('is_default', true)->update(['is_default' => false]);
            }

            $address->fill($attributes)->save();

            return $address;
        });
    }

    public function delete(User $customer, int $id): void
    {
        $this->write($customer, function (User $owner) use ($id): void {
            $owner->addresses()->findOrFail($id)->delete();
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(User): TResult  $operation
     * @return TResult
     */
    private function write(User $customer, Closure $operation): mixed
    {
        return $customer->getConnection()->transaction(function () use ($customer, $operation) {
            $owner = $customer->newQuery()->lockForUpdate()->findOrFail($customer->id);
            Gate::forUser($owner)->authorize('manage-delivery-addresses');

            return $operation($owner);
        }, attempts: 3);
    }
}
