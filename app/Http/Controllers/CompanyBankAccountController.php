<?php

namespace App\Http\Controllers;

use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyBankAccountController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $activeBranchId = $user?->active_branch_id;

        $banks = config('banks.malaysia', []);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'bank_name' => ['required', 'string', Rule::in($banks)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $branchId = (int) ($data['branch_id'] ?? $activeBranchId);
        if (!$branchId) {
            abort(422, 'Please select a branch before managing company bank accounts.');
        }

        $allowed = $user->isSuperAdmin()
            ? \App\Models\Branch::where('id', $branchId)->where('is_active', true)->exists()
            : $user->branches()->where('branches.id', $branchId)->exists();

        if (!$allowed) {
            abort(403, 'You cannot assign bank account to this branch.');
        }

        CompanyBankAccount::create([
            'branch_id' => $branchId,
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'],
            'account_no' => $data['account_no'],
            'status' => $data['status'] ?? 'active',
        ]);

        return back()->with('status', 'company-bank-account-added');
    }

    public function update(Request $request, CompanyBankAccount $bankAccount)
    {
        $user = $request->user();
        $activeBranchId = $user?->active_branch_id;
        if (!$user->isSuperAdmin()) {
            if (!$activeBranchId) {
                abort(422, 'Please select an active branch before managing company bank accounts.');
            }
            if ((int) $bankAccount->branch_id !== (int) $activeBranchId) {
                abort(403, 'You cannot modify bank account from another branch.');
            }
        }

        $banks = config('banks.malaysia', []);

        $data = $request->validate([
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:branches,id'],
            'bank_name' => ['sometimes', 'required', 'string', Rule::in($banks)],
            'account_name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_no' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        if (array_key_exists('branch_id', $data)) {
            $targetBranchId = (int) $data['branch_id'];
            $allowed = $user->isSuperAdmin()
                ? \App\Models\Branch::where('id', $targetBranchId)->where('is_active', true)->exists()
                : $user->branches()->where('branches.id', $targetBranchId)->exists();

            if (!$allowed) {
                abort(403, 'You cannot move bank account to this branch.');
            }
        }

        $bankAccount->update($data);

        return back()->with('status', 'company-bank-account-updated');
    }

    public function destroy(CompanyBankAccount $bankAccount)
    {
        $user = request()->user();
        $branchId = $user?->active_branch_id;
        if (!$user->isSuperAdmin()) {
            if (!$branchId) {
                abort(422, 'Please select an active branch before managing company bank accounts.');
            }
            if ((int) $bankAccount->branch_id !== (int) $branchId) {
                abort(403, 'You cannot delete bank account from another branch.');
            }
        }

        $bankAccount->delete();

        return back()->with('status', 'company-bank-account-deleted');
    }
}
