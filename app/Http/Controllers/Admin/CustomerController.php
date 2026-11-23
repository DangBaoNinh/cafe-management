<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCustomersRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class CustomerController extends Controller
{
    public function index(FilterCustomersRequest $request): View
    {
        $filters = array_filter($request->validated(), fn ($value) => $value !== null);
        $customers = User::query()
            ->select(['id', 'name', 'email', 'is_active'])
            ->where('role', UserRole::Customer)
            ->when(isset($filters['search']), function (Builder $customers) use ($filters) {
                // Treat SQL wildcard characters as part of the name or email.
                $pattern = '%'.strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

                $customers->where(fn (Builder $matches) => $matches
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when(isset($filters['status']), fn (Builder $customers) => $customers
                ->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')->orderBy('id')
            ->paginate(20)->appends($filters);

        return view('admin.customers.index', ['customers' => $customers, 'filters' => $filters]);
    }

    public function show(string $customer): View
    {
        $profile = User::query()
            ->select(['id', 'name', 'email', 'is_active'])
            ->where('role', UserRole::Customer)
            ->findOrFail($customer);

        $orders = $profile->orders()
            ->select(['order_number', 'status', 'total_vnd', 'created_at'])
            ->latest()->latest('id')->paginate(15);

        return view('admin.customers.show', ['customer' => $profile, 'orders' => $orders]);
    }
}
