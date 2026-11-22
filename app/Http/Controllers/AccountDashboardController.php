<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountDashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()?->role === UserRole::Staff) {
            return to_route('operations.table-sessions.index');
        }

        return view('customer.account');
    }
}
