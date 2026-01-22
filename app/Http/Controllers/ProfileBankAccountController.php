<?php

namespace App\Http\Controllers;

use App\Models\UserBankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileBankAccountController extends Controller
{
    public function store(Request $request)
    {
        $banks = config('banks.malaysia', []);

        $data = $request->validate([
            'bank_name' => ['required', 'string', Rule::in($banks)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $request->user()->bankAccounts()->create([
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'],
            'account_no' => $data['account_no'],
            'status' => $data['status'] ?? 'active',
        ]);

        return back()->with('status', 'bank-account-added');
    }

    public function update(Request $request, UserBankAccount $bankAccount)
    {
        if ($bankAccount->user_id !== $request->user()->id) {
            abort(403);
        }

        $banks = config('banks.malaysia', []);

        $data = $request->validate([
            'bank_name' => ['sometimes', 'required', 'string', Rule::in($banks)],
            'account_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_no' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $bankAccount->update($data);

        return back()->with('status', 'bank-account-updated');
    }

    public function destroy(Request $request, UserBankAccount $bankAccount)
    {
        if ($bankAccount->user_id !== $request->user()->id) {
            abort(403);
        }

        $bankAccount->delete();

        return back()->with('status', 'bank-account-deleted');
    }
}
