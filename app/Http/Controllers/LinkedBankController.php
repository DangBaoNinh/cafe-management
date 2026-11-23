<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkedBankController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'regex:/\A[0-9]{6,30}\z/'],
            'account_holder' => ['required', 'string', 'max:120'],
            'confirm' => ['accepted'],
        ]);
        unset($data['confirm']);
        $request->user()->forceFill(['linked_bank' => $data])->save();

        return to_route('profile.edit')->with('status', 'Đã liên kết tài khoản ngân hàng demo.');
    }
}
