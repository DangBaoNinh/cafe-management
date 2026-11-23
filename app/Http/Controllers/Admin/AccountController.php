<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'locked'])],
        ], attributes: [
            'search' => 'từ khóa',
            'role' => 'vai trò',
            'status' => 'trạng thái',
        ]);

        $search = $filters['search'] ?? '';
        $role = $filters['role'] ?? '';
        $status = $filters['status'] ?? '';

        $accounts = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_active'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%'),
            ))
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.accounts.index', compact('accounts', 'search', 'role', 'status'));
    }

    public function edit(User $account): View
    {
        return view('admin.accounts.edit', ['account' => $account]);
    }

    public function update(Request $request, User $account, AccountAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'previous_role' => ['required', Rule::enum(UserRole::class)],
            'previous_is_active' => ['required', 'boolean'],
        ], attributes: [
            'role' => 'vai trò',
            'is_active' => 'trạng thái',
            'previous_role' => 'vai trò trước khi sửa',
            'previous_is_active' => 'trạng thái trước khi sửa',
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $access->update(
            $actor,
            $account->id,
            UserRole::from($validated['role']),
            $request->boolean('is_active'),
            UserRole::from($validated['previous_role']),
            $request->boolean('previous_is_active'),
        );

        return to_route('admin.accounts.edit', $account)->with('status', 'Đã lưu quyền truy cập của tài khoản.');
    }
}
