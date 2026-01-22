<?php

namespace App\Http\Controllers;

use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyBankAccountController extends Controller
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

        CompanyBankAccount::create([
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'],
            'account_no' => $data['account_no'],
            'status' => $data['status'] ?? 'active',
        ]);

        return back()->with('status', 'company-bank-account-added');
    }

    public function update(Request $request, CompanyBankAccount $bankAccount)
    {
        $banks = config('banks.malaysia', []);

        $data = $request->validate([
            'bank_name' => ['sometimes', 'required', 'string', Rule::in($banks)],
            'account_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_no' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $bankAccount->update($data);

        return back()->with('status', 'company-bank-account-updated');
    }

    public function destroy(CompanyBankAccount $bankAccount)
    {
        $bankAccount->delete();

        return back()->with('status', 'company-bank-account-deleted');
    }
}
