<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SubConClaim;
use App\Services\RunningNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SubConClaimController extends Controller
{
    public function store(Request $request, string $projectUuid)
    {
        $this->ensureInternalUser($request);
        $project = $this->resolveProject($request, $projectUuid);

        $validated = $request->validate([
            'sub_con_id' => ['required', 'integer', Rule::exists('sub_cons', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'claimed_amount' => ['required', 'numeric', 'min:0'],
            'proforma_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $isBound = $project->subCons()
            ->where('sub_cons.id', $validated['sub_con_id'])
            ->exists();

        if (!$isBound) {
            return back()->withErrors([
                'sub_con_id' => 'Selected Sub Con is not bound to this project.',
            ]);
        }

        $file = $request->file('proforma_invoice');
        $path = $file->store('sub-con-claims/proforma', 'public');

        $claim = SubConClaim::create([
            'claim_no' => RunningNumberService::next('sub_con_claim'),
            'project_id' => $project->id,
            'sub_con_id' => $validated['sub_con_id'],
            'title' => $validated['title'],
            'status' => 'submitted',
            'claimed_amount' => (float) $validated['claimed_amount'],
            'proforma_invoice_path' => $path,
            'proforma_invoice_name' => $file->getClientOriginalName(),
            'submitted_at' => now(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->appendLog(
            $claim,
            from: null,
            to: 'submitted',
            action: 'submit',
            remark: $validated['remark'] ?? null,
            request: $request
        );

        return back()->with('success', 'Sub Con claim submitted.');
    }

    public function verifyProject(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if (!in_array($claim->status, ['submitted', 'appealed'], true)) {
            return back()->withErrors([
                'status' => 'Only submitted or appealed claims can be verified by project.',
            ]);
        }

        $validated = $request->validate([
            'verified_amount' => ['nullable', 'numeric', 'min:0'],
            'verified_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasPercent = isset($validated['verified_percent']) && $validated['verified_percent'] !== '';
        $amount = $hasPercent
            ? ((float) $claim->claimed_amount * ((float) $validated['verified_percent'] / 100))
            : (float) ($validated['verified_amount'] ?? $claim->claimed_amount);
        $amount = min($amount, (float) $claim->claimed_amount);
        $amount = round(max($amount, 0), 2);
        $percent = $hasPercent
            ? round((float) $validated['verified_percent'], 2)
            : ($claim->claimed_amount > 0
                ? round(($amount / (float) $claim->claimed_amount) * 100, 2)
                : 0.0);

        $from = $claim->status;
        $claim->fill([
            'status' => 'project_verified',
            'project_verified_amount' => $amount,
            'project_verified_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: $from,
            to: 'project_verified',
            action: 'project_verify',
            remark: $validated['remark'] ?? null,
            request: $request,
            amounts: [
                'project_verified_amount' => $amount,
                'project_verified_percent' => $percent,
            ]
        );

        return back()->with('success', 'Claim verified by Project Department.');
    }

    public function verifyContra(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if ($claim->status !== 'project_verified') {
            return back()->withErrors([
                'status' => 'Only project verified claims can be verified by Contra.',
            ]);
        }

        $validated = $request->validate([
            'verified_amount' => ['nullable', 'numeric', 'min:0'],
            'verified_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseAmount = (float) ($claim->project_verified_amount ?? $claim->claimed_amount);
        $hasPercent = isset($validated['verified_percent']) && $validated['verified_percent'] !== '';
        $amount = $hasPercent
            ? ($baseAmount * ((float) $validated['verified_percent'] / 100))
            : (float) ($validated['verified_amount'] ?? $baseAmount);
        $amount = min($amount, $baseAmount);
        $amount = round(max($amount, 0), 2);
        $percent = $hasPercent
            ? round((float) $validated['verified_percent'], 2)
            : ($baseAmount > 0 ? round(($amount / $baseAmount) * 100, 2) : 0.0);

        $claim->fill([
            'status' => 'contra_verified',
            'contra_verified_amount' => $amount,
            'contra_verified_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: 'project_verified',
            to: 'contra_verified',
            action: 'contra_verify',
            remark: $validated['remark'] ?? null,
            request: $request,
            amounts: [
                'contra_verified_amount' => $amount,
                'contra_verified_percent' => $percent,
            ]
        );

        return back()->with('success', 'Claim verified by Contra.');
    }

    public function approve(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $this->ensureCeoOrGm($request);

        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if ($claim->status !== 'contra_verified') {
            return back()->withErrors([
                'status' => 'Only contra verified claims can be approved.',
            ]);
        }

        $validated = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'approved_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseAmount = (float) ($claim->contra_verified_amount ?? $claim->project_verified_amount ?? $claim->claimed_amount);
        $hasPercent = isset($validated['approved_percent']) && $validated['approved_percent'] !== '';
        $amount = $hasPercent
            ? ($baseAmount * ((float) $validated['approved_percent'] / 100))
            : (float) ($validated['approved_amount'] ?? $baseAmount);
        $amount = min($amount, $baseAmount);
        $amount = round(max($amount, 0), 2);
        $percent = $hasPercent
            ? round((float) $validated['approved_percent'], 2)
            : ($baseAmount > 0 ? round(($amount / $baseAmount) * 100, 2) : 0.0);

        $claim->fill([
            'status' => 'ceo_gm_approved',
            'approved_amount' => $amount,
            'approved_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: 'contra_verified',
            to: 'ceo_gm_approved',
            action: 'ceo_gm_approve',
            remark: $validated['remark'] ?? null,
            request: $request,
            amounts: [
                'approved_amount' => $amount,
                'approved_percent' => $percent,
            ]
        );

        return back()->with('success', 'Claim approved by CEO/GM.');
    }

    public function subConDecision(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if ($claim->status !== 'ceo_gm_approved') {
            return back()->withErrors([
                'status' => 'Only CEO/GM approved claims can be accepted or appealed.',
            ]);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'appeal'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['decision'] === 'appeal' && empty(trim((string) ($validated['remark'] ?? '')))) {
            return back()->withErrors([
                'remark' => 'Appeal remark is required.',
            ]);
        }

        $from = $claim->status;
        $to = $validated['decision'] === 'accept' ? 'accepted_by_subcon' : 'appealed';

        $claim->fill([
            'status' => $to,
            'appeal_round' => $to === 'appealed' ? ((int) $claim->appeal_round + 1) : $claim->appeal_round,
            'subcon_decided_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: $from,
            to: $to,
            action: 'subcon_' . $validated['decision'],
            remark: $validated['remark'] ?? null,
            request: $request
        );

        return back()->with('success', $to === 'appealed'
            ? 'Sub Con appealed this claim. Workflow re-opens for review.'
            : 'Sub Con accepted approved amount.');
    }

    public function uploadRealInvoice(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if ($claim->status !== 'pending_real_invoice_upload') {
            return back()->withErrors([
                'status' => 'Real invoice can only be uploaded after payment slip is prepared.',
            ]);
        }

        $validated = $request->validate([
            'real_invoice_no' => ['required', 'string', 'max:100'],
            'real_invoice_date' => ['required', 'date'],
            'real_invoice_amount' => ['required', 'numeric', 'min:0'],
            'real_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!empty($claim->real_invoice_path)) {
            Storage::disk('public')->delete($claim->real_invoice_path);
        }

        $file = $request->file('real_invoice');
        $path = $file->store('sub-con-claims/real-invoice', 'public');

        $from = $claim->status;
        $claim->fill([
            'status' => 'real_invoice_uploaded',
            'real_invoice_no' => $validated['real_invoice_no'],
            'real_invoice_date' => $validated['real_invoice_date'],
            'real_invoice_amount' => (float) $validated['real_invoice_amount'],
            'real_invoice_path' => $path,
            'real_invoice_name' => $file->getClientOriginalName(),
            'real_invoice_uploaded_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: $from,
            to: 'real_invoice_uploaded',
            action: 'upload_real_invoice',
            remark: $validated['remark'] ?? null,
            request: $request,
            amounts: ['real_invoice_amount' => (float) $validated['real_invoice_amount']]
        );

        return back()->with('success', 'Real invoice uploaded.');
    }

    public function preparePaymentSlip(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if ($claim->status !== 'accepted_by_subcon') {
            return back()->withErrors([
                'status' => 'Payment slip preparation is only allowed after Sub Con accepts.',
            ]);
        }

        $validated = $request->validate([
            'payment_slip_no' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $claim->fill([
            'status' => 'pending_real_invoice_upload',
            'payment_slip_no' => $validated['payment_slip_no'] ?? null,
            'payment_slip_prepared_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: 'accepted_by_subcon',
            to: 'pending_real_invoice_upload',
            action: 'prepare_payment_slip',
            remark: $validated['remark'] ?? null,
            request: $request
        );

        return back()->with('success', 'Payment slip preparation recorded. Waiting Sub Con real invoice upload.');
    }

    public function downloadProforma(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if (!$claim->proforma_invoice_path || !Storage::disk('public')->exists($claim->proforma_invoice_path)) {
            abort(404, 'Proforma invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->proforma_invoice_path,
            $claim->proforma_invoice_name ?: basename($claim->proforma_invoice_path)
        );
    }

    public function downloadRealInvoice(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if (!$claim->real_invoice_path || !Storage::disk('public')->exists($claim->real_invoice_path)) {
            abort(404, 'Real invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->real_invoice_path,
            $claim->real_invoice_name ?: basename($claim->real_invoice_path)
        );
    }

    private function getClaim(Request $request, string $projectUuid, string $claimUuid): SubConClaim
    {
        $project = $this->resolveProject($request, $projectUuid);

        return SubConClaim::query()
            ->where('project_id', $project->id)
            ->where('uuid', $claimUuid)
            ->firstOrFail();
    }

    private function resolveProject(Request $request, string $projectUuid): Project
    {
        $query = Project::where('uuid', $projectUuid);
        $this->scopeToActiveBranch($request, $query, 'branch_id');

        return $query->firstOrFail();
    }

    private function appendLog(
        SubConClaim $claim,
        ?string $from,
        string $to,
        string $action,
        ?string $remark,
        Request $request,
        array $amounts = []
    ): void {
        $log = is_array($claim->remark_log) ? $claim->remark_log : [];
        $log[] = [
            'from' => $from,
            'to' => $to,
            'action' => $action,
            'remark' => $remark,
            'by' => $request->user()?->name,
            'at' => now()->toDateTimeString(),
            'amounts' => $amounts,
        ];

        $claim->forceFill(['remark_log' => $log])->save();
    }

    private function ensureInternalUser(Request $request): void
    {
        if ($request->user()?->sub_con_id) {
            abort(403, 'Sub Con account is not allowed to perform this action.');
        }
    }

    private function ensureCeoOrGm(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->isSuperAdmin() || (bool) $user->is_general_manager) {
            return;
        }

        $activeRoleName = strtolower((string) ($user->activeRole?->name ?? ''));
        if (in_array($activeRoleName, ['ceo', 'gm', 'general manager'], true)) {
            return;
        }

        abort(403, 'Only CEO/GM can approve this claim.');
    }

    private function scopeToActiveBranch(Request $request, Builder $query, string $column = 'branch_id'): void
    {
        $user = $request->user();

        if (!$user || !$this->shouldScopeToActiveBranch($request) || $user->sub_con_id) {
            return;
        }

        $branchId = (int) ($user->active_branch_id ?? 0);
        if ($branchId <= 0) {
            abort(403, 'Active branch is required.');
        }

        $query->where($column, $branchId);
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }
}
