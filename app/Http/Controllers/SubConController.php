<?php

namespace App\Http\Controllers;

use App\Models\SubCon;
use App\Models\SubConTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubConController extends Controller
{
    public function index(Request $request)
    {
        $banks = config('banks.malaysia', []);

        $subCons = SubCon::query()
            ->with('bankAccounts')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('bank', 'like', "%{$search}%")
                        ->orWhereHas('bankAccounts', function ($bankQuery) use ($search) {
                            $bankQuery->where('bank_name', 'like', "%{$search}%")
                                ->orWhere('account_name', 'like', "%{$search}%")
                                ->orWhere('account_no', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('SubCons/Index', [
            'subCons' => $subCons,
            'filters' => $request->only('search'),
            'bankOptions' => $banks,
        ]);
    }

    public function store(Request $request)
    {
        $banks = config('banks.malaysia', []);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'bank_accounts' => 'nullable|array|max:10',
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255', Rule::in($banks)],
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_no' => 'nullable|string|max:100',
        ]);

        $bankAccounts = $this->normalizeBankAccounts($validated['bank_accounts'] ?? []);
        $legacyBank = $bankAccounts[0]['bank_name'] ?? null;

        DB::transaction(function () use ($validated, $bankAccounts, $legacyBank) {
            $subCon = SubCon::create([
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank' => $legacyBank,
            ]);

            $this->syncBankAccounts($subCon, $bankAccounts);
        });

        return redirect()->back()->with('success', 'Sub Con created successfully.');
    }

    public function show(SubCon $subCon)
    {
        $taskBase = SubConTask::query()
            ->where('sub_con_id', $subCon->id);

        $taskStats = [
            'total'     => (clone $taskBase)->count(),
            'draft'     => (clone $taskBase)->where('status', 'draft')->count(),
            'submitted' => (clone $taskBase)->where('status', 'submitted')->count(),
            'verified'  => (clone $taskBase)->where('status', 'verified')->count(),
            'justified' => (clone $taskBase)->where('status', 'justified')->count(),
            'certified' => (clone $taskBase)->where('status', 'certified')->count(),
            'paid'      => (clone $taskBase)->where('status', 'paid')->count(),
            'total_amount' => (float) (clone $taskBase)->sum('amount'),
        ];

        return Inertia::render('SubCons/Show', [
            'subCon' => $subCon->load('bankAccounts'),
            'taskStats' => $taskStats,
        ]);
    }

    public function update(Request $request, SubCon $subCon)
    {
        $banks = config('banks.malaysia', []);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'bank_accounts' => 'nullable|array|max:10',
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255', Rule::in($banks)],
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_no' => 'nullable|string|max:100',
        ]);

        $bankAccounts = $this->normalizeBankAccounts($validated['bank_accounts'] ?? []);
        $legacyBank = $bankAccounts[0]['bank_name'] ?? null;

        DB::transaction(function () use ($subCon, $validated, $bankAccounts, $legacyBank) {
            $subCon->update([
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank' => $legacyBank,
            ]);

            $this->syncBankAccounts($subCon, $bankAccounts);
        });

        return redirect()->back()->with('success', 'Sub Con updated successfully.');
    }

    public function destroy(SubCon $subCon)
    {
        $subCon->delete();

        return redirect()->back()->with('success', 'Sub Con deleted successfully.');
    }

    private function normalizeBankAccounts(array $accounts): array
    {
        return collect($accounts)
            ->map(function ($account) {
                return [
                    'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                    'account_name' => trim((string) ($account['account_name'] ?? '')),
                    'account_no' => trim((string) ($account['account_no'] ?? '')),
                ];
            })
            ->filter(function ($account) {
                return $account['bank_name'] !== ''
                    || $account['account_name'] !== ''
                    || $account['account_no'] !== '';
            })
            ->values()
            ->all();
    }

    private function syncBankAccounts(SubCon $subCon, array $accounts): void
    {
        $subCon->bankAccounts()->delete();

        if (empty($accounts)) {
            return;
        }

        $subCon->bankAccounts()->createMany($accounts);
    }
}
