<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CustomerSupport
{
    public function start(User $customer, string $subject, string $body): SupportConversation
    {
        Gate::forUser($customer)->authorize('access-customer-orders');

        return DB::transaction(function () use ($customer, $subject, $body): SupportConversation {
            $conversation = $customer->supportConversations()->create([
                'subject' => $subject,
                'status' => 'open',
                'last_message_at' => now(),
            ]);
            $conversation->messages()->create(['sender_id' => $customer->id, 'body' => $body]);

            return $conversation;
        }, 3);
    }

    public function reply(User $actor, SupportConversation $conversation, string $body): void
    {
        DB::transaction(function () use ($actor, $conversation, $body): void {
            $locked = SupportConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $isCustomer = $locked->user_id === $actor->id;
            if (! $isCustomer) {
                Gate::forUser($actor)->authorize('operate-orders');
            }
            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['message' => 'Cuộc trao đổi này đã được đóng.']);
            }
            $locked->messages()->create(['sender_id' => $actor->id, 'body' => $body]);
            $locked->last_message_at = now();
            $locked->save();
        }, 3);
    }

    public function close(User $actor, SupportConversation $conversation): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $conversation): void {
            $locked = SupportConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($locked->status === 'closed') {
                return;
            }
            $locked->status = 'closed';
            $locked->closed_at = now();
            $locked->closed_by = $actor->id;
            $locked->save();
        }, 3);
    }
}
