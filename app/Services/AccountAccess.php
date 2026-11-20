<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountAccess
{
    public function update(
        User $actor,
        int $targetId,
        UserRole $role,
        bool $isActive,
        UserRole $previousRole,
        bool $wasActive,
    ): void {
        $actor->getConnection()->transaction(function () use ($actor, $targetId, $role, $isActive, $previousRole, $wasActive): void {
            // Lock both users in primary-key order, including when two admins edit each other.
            $users = $actor->newQuery()
                ->whereKey([$actor->id, $targetId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $currentActor = $users->get($actor->id);
            abort_if($currentActor === null, 403);
            Gate::forUser($currentActor)->authorize('access-admin');

            $target = $users->get($targetId);
            abort_if($target === null, 404);

            // The locked actor remains an active admin after every permitted update.
            if ($currentActor->is($target) && ($role !== UserRole::Admin || ! $isActive)) {
                throw ValidationException::withMessages([
                    'account' => 'Bạn không thể tự hạ vai trò hoặc khóa tài khoản của mình.',
                ]);
            }

            if ($target->role !== $previousRole || $target->is_active !== $wasActive) {
                throw ValidationException::withMessages([
                    'account' => 'Tài khoản đã thay đổi. Hãy tải lại trang trước khi lưu.',
                ]);
            }

            if ($target->is_active && ! $isActive) {
                $target->setRememberToken(Str::random(60));
            }

            $target->role = $role;
            $target->is_active = $isActive;
            $target->saveOrFail();
        }, attempts: 3);
    }
}
