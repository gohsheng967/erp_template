<?php

namespace App\Http\Controllers;

use App\Models\SubCon;
use App\Models\SubConTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->through(function (SubCon $subCon) {
                return [
                    'id' => $subCon->id,
                    'uuid' => $subCon->uuid,
                    'name' => $subCon->name,
                    'company_name' => $subCon->company_name,
                    'email' => $subCon->email,
                    'phone' => $subCon->phone,
                    'address' => $subCon->address,
                    'bank' => $subCon->bank,
                    'bank_accounts' => $subCon->bankAccounts->map(function ($account) {
                        return [
                            'bank_name' => $account->bank_name,
                            'account_name' => $account->account_name,
                            'account_no' => $account->account_no,
                        ];
                    })->values(),
                    'portal_user' => $subCon->login_identity_no ? [
                        'identity_no' => $subCon->login_identity_no,
                        'email' => $subCon->login_email,
                        'status' => (int) $subCon->login_status,
                        'must_change_password' => (bool) $subCon->login_must_change_password,
                    ] : null,
                ];
            })
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
            'create_login_account' => 'nullable|boolean',
            'login_identity_no' => [
                'nullable',
                'required_if:create_login_account,1,true',
                'string',
                'max:50',
                Rule::unique('sub_cons', 'login_identity_no'),
            ],
            'login_email' => [
                'nullable',
                'required_if:create_login_account,1,true',
                'email',
                'max:255',
                Rule::unique('sub_cons', 'login_email'),
            ],
            'login_password' => 'nullable|string|min:6|max:100',
            'bank_accounts' => 'nullable|array|max:10',
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255', Rule::in($banks)],
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_no' => 'nullable|string|max:100',
        ]);

        $bankAccounts = $this->normalizeBankAccounts($validated['bank_accounts'] ?? []);
        $legacyBank = $bankAccounts[0]['bank_name'] ?? null;
        $portalPassword = null;

        DB::transaction(function () use ($validated, $bankAccounts, $legacyBank, &$portalPassword) {
            $subCon = SubCon::create([
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank' => $legacyBank,
            ]);

            $this->syncBankAccounts($subCon, $bankAccounts);

            $portalPassword = $this->syncPortalLogin(
                $subCon,
                $validated,
                'create_login_account'
            );
        });

        $message = 'Sub Con created successfully.';
        if ($portalPassword) {
            $message .= " Portal login created. Temporary password: {$portalPassword}";
        }

        return redirect()->back()->with('success', $message);
    }

    public function show(SubCon $subCon)
    {
        $taskBase = SubConTask::query()
            ->where('sub_con_id', $subCon->id);

        $amountExpression = 'COALESCE(invoice_amount, amount)';

        $taskStats = [
            'total'     => (clone $taskBase)->count(),
            'draft'     => (clone $taskBase)->where('status', 'draft')->count(),
            'submitted' => (clone $taskBase)->where('status', 'submitted')->count(),
            'contra_verified' => (clone $taskBase)->whereIn('status', ['contra_verified', 'verified'])->count(),
            'invoiced' => (clone $taskBase)->where('status', 'invoiced')->count(),
            'approved' => (clone $taskBase)->where('status', 'approved')->count(),
            'verified'  => (clone $taskBase)->where('status', 'verified')->count(),
            'justified' => (clone $taskBase)->where('status', 'justified')->count(),
            'certified' => (clone $taskBase)->where('status', 'certified')->count(),
            'paid'      => (clone $taskBase)->where('status', 'paid')->count(),
            'total_amount' => (float) (clone $taskBase)->sum('amount'),
            'invoiced_amount' => (float) (clone $taskBase)
                ->whereIn('status', ['invoiced', 'approved', 'justified', 'certified', 'paid'])
                ->sum(DB::raw($amountExpression)),
            'paid_amount' => (float) (clone $taskBase)
                ->where('status', 'paid')
                ->sum(DB::raw($amountExpression)),
            'project_count' => (clone $taskBase)->distinct('project_id')->count('project_id'),
            'latest_task_update_at' => (clone $taskBase)->max('updated_at'),
        ];

        $taskStats['outstanding_amount'] = max(
            (float) $taskStats['invoiced_amount'] - (float) $taskStats['paid_amount'],
            0
        );

        $projectSummaries = SubConTask::query()
            ->where('sub_con_id', $subCon->id)
            ->select('project_id')
            ->selectRaw('COUNT(*) as total_tasks')
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN {$amountExpression} ELSE 0 END) as paid_amount")
            ->selectRaw("SUM(CASE WHEN status IN ('invoiced', 'approved', 'justified', 'certified', 'paid') THEN {$amountExpression} ELSE 0 END) as invoiced_amount")
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_tasks")
            ->selectRaw("SUM(CASE WHEN status IN ('submitted', 'contra_verified', 'verified', 'invoiced') THEN 1 ELSE 0 END) as progress_tasks")
            ->selectRaw("MAX(updated_at) as latest_task_update_at")
            ->groupBy('project_id')
            ->with('project:id,uuid,name,code,status')
            ->orderByDesc('latest_task_update_at')
            ->get()
            ->map(function (SubConTask $row) {
                $invoicedAmount = (float) ($row->invoiced_amount ?? 0);
                $paidAmount = (float) ($row->paid_amount ?? 0);

                return [
                    'project' => $row->project ? [
                        'uuid' => $row->project->uuid,
                        'name' => $row->project->name,
                        'code' => $row->project->code,
                        'status' => $row->project->status,
                    ] : null,
                    'total_tasks' => (int) ($row->total_tasks ?? 0),
                    'draft_tasks' => (int) ($row->draft_tasks ?? 0),
                    'progress_tasks' => (int) ($row->progress_tasks ?? 0),
                    'invoiced_amount' => $invoicedAmount,
                    'paid_amount' => $paidAmount,
                    'outstanding_amount' => max($invoicedAmount - $paidAmount, 0),
                    'latest_task_update_at' => $row->latest_task_update_at,
                ];
            })
            ->filter(fn ($row) => !empty($row['project']))
            ->values();

        $recentTasks = SubConTask::query()
            ->where('sub_con_id', $subCon->id)
            ->with([
                'project:id,uuid,name,code',
                'parent:id,title',
            ])
            ->latest()
            ->limit(8)
            ->get([
                'id',
                'uuid',
                'task_no',
                'project_id',
                'parent_id',
                'title',
                'status',
                'progress_percent',
                'amount',
                'invoice_amount',
                'updated_at',
            ])
            ->map(function (SubConTask $task) {
                return [
                    'uuid' => $task->uuid,
                    'task_no' => $task->task_no,
                    'title' => $task->title,
                    'status' => $task->status,
                    'progress_percent' => (int) ($task->progress_percent ?? 0),
                    'amount' => (float) ($task->amount ?? 0),
                    'invoice_amount' => $task->invoice_amount !== null ? (float) $task->invoice_amount : null,
                    'updated_at' => optional($task->updated_at)?->toDateTimeString(),
                    'project' => $task->project ? [
                        'uuid' => $task->project->uuid,
                        'name' => $task->project->name,
                        'code' => $task->project->code,
                    ] : null,
                    'parent' => $task->parent ? [
                        'title' => $task->parent->title,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('SubCons/Show', [
            'subCon' => $subCon->load('bankAccounts'),
            'taskStats' => $taskStats,
            'portalUser' => $subCon->login_identity_no ? [
                'identity_no' => $subCon->login_identity_no,
                'email' => $subCon->login_email,
                'status' => (int) $subCon->login_status,
                'must_change_password' => (bool) $subCon->login_must_change_password,
            ] : null,
            'projectSummaries' => $projectSummaries,
            'recentTasks' => $recentTasks,
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
            'manage_login_account' => 'nullable|boolean',
            'login_identity_no' => [
                'nullable',
                'required_if:manage_login_account,1,true',
                'string',
                'max:50',
                Rule::unique('sub_cons', 'login_identity_no')->ignore($subCon->id),
            ],
            'login_email' => [
                'nullable',
                'required_if:manage_login_account,1,true',
                'email',
                'max:255',
                Rule::unique('sub_cons', 'login_email')->ignore($subCon->id),
            ],
            'login_status' => 'nullable|in:0,1',
            'login_password' => 'nullable|string|min:6|max:100',
            'bank_accounts' => 'nullable|array|max:10',
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255', Rule::in($banks)],
            'bank_accounts.*.account_name' => 'nullable|string|max:255',
            'bank_accounts.*.account_no' => 'nullable|string|max:100',
        ]);

        $bankAccounts = $this->normalizeBankAccounts($validated['bank_accounts'] ?? []);
        $legacyBank = $bankAccounts[0]['bank_name'] ?? null;
        $portalPassword = null;

        DB::transaction(function () use ($subCon, $validated, $bankAccounts, $legacyBank, &$portalPassword) {
            $subCon->update([
                'name' => $validated['name'],
                'company_name' => $validated['company_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'bank' => $legacyBank,
            ]);

            $this->syncBankAccounts($subCon, $bankAccounts);

            $portalPassword = $this->syncPortalLogin(
                $subCon,
                $validated,
                'manage_login_account'
            );
        });

        $message = 'Sub Con updated successfully.';
        if ($portalPassword) {
            $message .= " Portal login password reset to: {$portalPassword}";
        }

        return redirect()->back()->with('success', $message);
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

    private function syncPortalLogin(SubCon $subCon, array $validated, string $toggleField): ?string
    {
        if (empty($validated[$toggleField])) {
            return null;
        }

        $loginIdentityNo = trim((string) ($validated['login_identity_no'] ?? ''));
        $loginEmail = trim((string) ($validated['login_email'] ?? ''));

        if ($loginIdentityNo === '' || $loginEmail === '') {
            return null;
        }

        $plainPassword = trim((string) ($validated['login_password'] ?? ''));
        if (!$subCon->login_password && $plainPassword === '') {
            $plainPassword = '123456';
        }

        $subCon->login_identity_no = $loginIdentityNo;
        $subCon->login_email = $loginEmail;
        $subCon->login_status = (int) ($validated['login_status'] ?? 1);

        if ($plainPassword !== '') {
            $subCon->login_password = Hash::make($plainPassword);
            $subCon->login_must_change_password = true;
        }

        $subCon->save();

        return $plainPassword !== '' ? $plainPassword : null;
    }
}
