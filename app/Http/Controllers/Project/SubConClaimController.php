<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\PaymentSlip;
use App\Models\Project;
use App\Models\SubConClaim;
use App\Services\RunningNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'verified_percent' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasPercent = isset($validated['verified_percent']) && $validated['verified_percent'] !== '';
        $amount = $hasPercent
            ? ((float) $claim->claimed_amount * ((float) $validated['verified_percent'] / 100))
            : (float) ($validated['verified_amount'] ?? $claim->claimed_amount);
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
            'verified_percent' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseAmount = (float) ($claim->project_verified_amount ?? $claim->claimed_amount);
        $hasPercent = isset($validated['verified_percent']) && $validated['verified_percent'] !== '';
        $amount = $hasPercent
            ? ($baseAmount * ((float) $validated['verified_percent'] / 100))
            : (float) ($validated['verified_amount'] ?? $baseAmount);
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
            'approved_percent' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $baseAmount = (float) ($claim->contra_verified_amount ?? $claim->project_verified_amount ?? $claim->claimed_amount);
        $hasPercent = isset($validated['approved_percent']) && $validated['approved_percent'] !== '';
        $amount = $hasPercent
            ? ($baseAmount * ((float) $validated['approved_percent'] / 100))
            : (float) ($validated['approved_amount'] ?? $baseAmount);
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
            'status' => 'payment_in_progress',
            'payment_slip_no' => $validated['payment_slip_no'] ?? null,
            'payment_slip_prepared_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->appendLog(
            $claim,
            from: 'accepted_by_subcon',
            to: 'payment_in_progress',
            action: 'prepare_payment_slip',
            remark: $validated['remark'] ?? null,
            request: $request
        );

        return back()->with('success', 'Payment slip generated. Continue payment process in accounting.');
    }

    public function paymentSlip(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);
        $projectBranchId = (int) (Project::query()->whereKey($claim->project_id)->value('branch_id') ?? 0);

        if (!in_array($claim->status, ['payment_in_progress', 'accepted_by_subcon'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Payment slip can only be generated after Sub Con acceptance.',
            ]);
        }

        $validated = $request->validate([
            'company_bank_account_id' => [
                'required',
                Rule::exists('company_bank_accounts', 'id')->where(function ($query) use ($request, $projectBranchId) {
                    $query->where('status', 'active');

                    $activeBranchId = (int) ($request->user()?->active_branch_id ?? 0);
                    $resolvedBranchId = $activeBranchId > 0 ? $activeBranchId : $projectBranchId;
                    if ($resolvedBranchId > 0) {
                        $query->where('branch_id', $resolvedBranchId);
                    }
                }),
            ],
            'less_retention' => ['nullable', 'numeric', 'min:0'],
            'less_retention_label' => ['nullable', 'string', 'max:255'],
            'less_recoupment' => ['nullable', 'numeric', 'min:0'],
            'less_recoupment_label' => ['nullable', 'string', 'max:255'],
            'less_material_ob' => ['nullable', 'numeric', 'min:0'],
            'less_material_ob_label' => ['nullable', 'string', 'max:255'],
            'less_paid_previously' => ['nullable', 'numeric', 'min:0'],
            'less_paid_previously_label' => ['nullable', 'string', 'max:255'],
            'payment_slip_remark' => ['nullable', 'string', 'max:255'],
            'remark_label' => ['nullable', 'string', 'max:255'],
        ]);

        $slip = $claim->paymentSlip ?? new PaymentSlip();
        if (!$slip->exists) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->source()->associate($claim);
        } elseif ($slip->cancelled_at) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->cancelled_at = null;
            $slip->cancelled_by = null;
            $slip->cancel_reason = null;
        }

        $slip->company_bank_account_id = $validated['company_bank_account_id'];
        $slip->amount = (float) ($claim->approved_amount ?? $claim->claimed_amount ?? 0);
        $slip->payment_date = now()->toDateString();
        $slip->less_retention = $request->input('less_retention');
        $slip->less_retention_label = $request->input('less_retention_label');
        $slip->less_recoupment = $request->input('less_recoupment');
        $slip->less_recoupment_label = $request->input('less_recoupment_label');
        $slip->less_material_ob = $request->input('less_material_ob');
        $slip->less_material_ob_label = $request->input('less_material_ob_label');
        $slip->less_paid_previously = $request->input('less_paid_previously');
        $slip->less_paid_previously_label = $request->input('less_paid_previously_label');
        $slip->payment_slip_remark = $request->input('payment_slip_remark');
        $slip->remark_label = $request->input('remark_label');
        $slip->workflow_status = 'processing';
        $slip->approved_at = null;
        $slip->approved_by = null;
        $slip->rejected_at = null;
        $slip->rejected_by = null;
        $slip->rejected_reason = null;
        $slip->created_by = $request->user()?->id;
        $slip->save();

        $claim->fill([
            'payment_slip_no' => $slip->slip_no,
            'status' => 'payment_in_progress',
            'payment_slip_prepared_at' => $claim->payment_slip_prepared_at ?? now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        $slip->load([
            'companyBankAccount',
            'source.project',
            'source.subCon',
        ]);

        return response()->json([
            'slip' => $slip,
        ]);
    }

    public function markPaymentCompleted(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        if (!in_array($claim->status, ['payment_in_progress', 'accepted_by_subcon'], true)) {
            return back()->withErrors([
                'status' => 'Payment can only be completed after Sub Con accepts and payment flow is in progress.',
            ]);
        }

        $validated = $request->validate([
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = (string) $claim->status;

        $claim->fill([
            'status' => 'pending_real_invoice_upload',
            'updated_by' => $request->user()?->id,
        ])->save();

        if ($claim->paymentSlip) {
            $claim->paymentSlip->update([
                'workflow_status' => 'paid',
                'payment_date' => now()->toDateString(),
            ]);
        }

        $this->appendLog(
            $claim,
            from: $from,
            to: 'pending_real_invoice_upload',
            action: 'payment_completed',
            remark: $validated['remark'] ?? null,
            request: $request
        );

        return back()->with('success', 'Payment completed. Sub Con can now upload real invoice.');
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

    public function downloadProof(Request $request, string $projectUuid, string $claimUuid)
    {
        $this->ensureInternalUser($request);
        $claim = $this->getClaim($request, $projectUuid, $claimUuid);

        $attachments = $this->serializeClaimProofAttachments($claim);
        $idx = max((int) $request->integer('idx', 0), 0);
        $selected = $attachments[$idx] ?? null;

        if (!$selected || !Storage::disk('public')->exists((string) ($selected['path'] ?? ''))) {
            abort(404, 'Proof attachment not found.');
        }

        return Storage::disk('public')->download(
            (string) $selected['path'],
            (string) ($selected['name'] ?? basename((string) $selected['path']))
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

    private function serializeClaimProofAttachments(SubConClaim $claim): array
    {
        $rows = collect(is_array($claim->proof_attachments) ? $claim->proof_attachments : [])
            ->filter(fn ($row) => is_array($row) && !empty($row['path']))
            ->map(fn ($row) => [
                'path' => (string) $row['path'],
                'name' => !empty($row['name']) ? (string) $row['name'] : basename((string) $row['path']),
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            return $rows;
        }

        if (!empty($claim->proof_attachment_path)) {
            return [[
                'path' => (string) $claim->proof_attachment_path,
                'name' => !empty($claim->proof_attachment_name)
                    ? (string) $claim->proof_attachment_name
                    : basename((string) $claim->proof_attachment_path),
            ]];
        }

        return [];
    }
}
