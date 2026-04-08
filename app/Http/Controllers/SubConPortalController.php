<?php

namespace App\Http\Controllers;

use App\Models\SubCon;
use App\Models\SubConClaim;
use App\Models\PurchaseOrder;
use App\Models\SubConTask;
use App\Models\SubConTaskUpdate;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\RunningNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubConPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $tasks = SubConTask::query()
            ->with([
                'project:id,uuid,code,name,status',
                'updates',
                'children:id,uuid,parent_id,title,status,progress_percent',
            ])
            ->where('sub_con_id', $subCon->id)
            ->orderByDesc('id')
            ->get();

        $purchaseOrders = $this->accessiblePurchaseOrdersQuery($subCon)
            ->with([
                'supplier:id,company_name',
                'purchaseRequest:id,uuid,code,title,project_id,is_subcon_purchase_request',
                'purchaseRequest.items:id,purchase_request_id,parent_id,total_price',
                'purchaseRequest.project:id,uuid,code,name',
                'items:id,purchase_order_id,purchase_request_item_id,quantity,unit_price',
                'signedPo',
            ])
            ->whereIn('status', ['issued', 'confirmed'])
            ->orderByDesc('id')
            ->get();

        $claims = SubConClaim::query()
            ->with(['project:id,uuid,code,name,status', 'purchaseOrder:id,uuid,code'])
            ->where('sub_con_id', $subCon->id)
            ->orderByDesc('id')
            ->get();
        $claimPurchaseOrders = $this->mapClaimPurchaseOrders($claims);

        $mainTasks = $tasks->whereNull('parent_id');

        $stats = [
            'total' => $mainTasks->count(),
            'draft' => $mainTasks->where('status', 'draft')->count(),
            'submitted' => $mainTasks->where('status', 'submitted')->count(),
            'verified' => $mainTasks->whereIn('status', ['verified', 'contra_verified', 'invoiced', 'approved', 'justified', 'certified', 'paid'])->count(),
        ];
        $poStats = [
            'total' => $purchaseOrders->count(),
            'pending_confirmation' => $purchaseOrders->where('status', 'issued')->count(),
            'confirmed' => $purchaseOrders->where('status', 'confirmed')->count(),
        ];
        $claimStats = [
            'total' => $claims->count(),
            'pending_decision' => $claims->where('status', 'ceo_gm_approved')->count(),
            'appealed' => $claims->where('status', 'appealed')->count(),
            'payment_in_progress' => $claims->where('status', 'payment_in_progress')->count(),
            'pending_real_invoice_upload' => $claims->where('status', 'pending_real_invoice_upload')->count(),
            'completed' => $claims->where('status', 'real_invoice_uploaded')->count(),
        ];

        return Inertia::render('SubCon/Portal', [
            'subCon' => [
                'id' => $subCon->id,
                'uuid' => $subCon->uuid,
                'name' => $subCon->name,
                'company_name' => $subCon->company_name,
                'login_identity_no' => $subCon->login_identity_no,
            ],
            'stats' => $stats,
            'poStats' => $poStats,
            'tasks' => $tasks,
            'purchaseOrders' => $purchaseOrders->map(function (PurchaseOrder $po) {
                $signed = $po->signedPo;

                return [
                    'uuid' => $po->uuid,
                    'code' => $po->code,
                    'status' => $po->status,
                    'order_date' => $po->order_date ? (string) $po->order_date : null,
                    'total_amount' => $this->computeHierarchyAwarePoAmount($po),
                    'supplier' => $po->supplier ? [
                        'company_name' => $po->supplier->company_name,
                    ] : null,
                    'purchase_request' => $po->purchaseRequest ? [
                        'uuid' => $po->purchaseRequest->uuid,
                        'code' => $po->purchaseRequest->code,
                        'title' => $po->purchaseRequest->title,
                        'project' => $po->purchaseRequest->project ? [
                            'uuid' => $po->purchaseRequest->project->uuid,
                            'code' => $po->purchaseRequest->project->code,
                            'name' => $po->purchaseRequest->project->name,
                        ] : null,
                    ] : null,
                    'signed_po' => $signed ? [
                        'name' => $signed->original_name ?: basename((string) $signed->file_path),
                        'url' => Storage::disk('public')->url($signed->file_path),
                    ] : null,
                ];
            })->values(),
            'claimStats' => $claimStats,
            'claims' => $claims->map(function (SubConClaim $claim) use ($claimPurchaseOrders) {
                $purchaseOrders = $claimPurchaseOrders[$claim->id] ?? [];
                $primaryPurchaseOrder = $purchaseOrders[0] ?? null;

                return [
                    'uuid' => $claim->uuid,
                    'claim_no' => $claim->claim_no,
                    'title' => $claim->title,
                    'status' => $claim->status,
                    'claimed_amount' => (float) ($claim->claimed_amount ?? 0),
                    'approved_amount' => $claim->approved_amount !== null ? (float) $claim->approved_amount : null,
                    'payment_slip_no' => $claim->payment_slip_no,
                    'appeal_round' => (int) ($claim->appeal_round ?? 0),
                    'proforma_invoice_name' => $claim->proforma_invoice_name,
                    'proof_attachment_name' => $claim->proof_attachment_name,
                    'proof_attachments' => $this->serializeClaimProofAttachments($claim),
                    'real_invoice_name' => $claim->real_invoice_name,
                    'project' => $claim->project ? [
                        'uuid' => $claim->project->uuid,
                        'name' => $claim->project->name,
                        'code' => $claim->project->code,
                    ] : null,
                    'purchase_order' => $primaryPurchaseOrder,
                    'purchase_orders' => $purchaseOrders,
                    'created_at' => optional($claim->created_at)?->toDateTimeString(),
                    'updated_at' => optional($claim->updated_at)?->toDateTimeString(),
                    'remark_log' => is_array($claim->remark_log) ? $claim->remark_log : [],
                ];
            })->values(),
        ]);
    }

    public function storeClaim(Request $request)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $validated = $request->validate([
            'po_uuids' => ['required', 'array', 'min:1'],
            'po_uuids.*' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'claimed_amount' => ['required', 'numeric', 'min:0'],
            'proforma_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'proof_attachments' => ['required', 'array', 'min:1'],
            'proof_attachments.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $selectedPoUuids = collect($validated['po_uuids'])
            ->filter(fn ($uuid) => is_string($uuid) && trim($uuid) !== '')
            ->map(fn ($uuid) => trim($uuid))
            ->unique()
            ->values();

        $purchaseOrders = $this->accessiblePurchaseOrdersQuery($subCon)
            ->with(['purchaseRequest.project:id,branch_id'])
            ->whereIn('uuid', $selectedPoUuids)
            ->get();

        if ($purchaseOrders->count() !== $selectedPoUuids->count()) {
            return back()->withErrors([
                'po_uuids' => 'Some selected POs are not available for your account.',
            ]);
        }

        $purchaseOrdersByUuid = $purchaseOrders->keyBy('uuid');
        $orderedPurchaseOrders = $selectedPoUuids
            ->map(fn ($uuid) => $purchaseOrdersByUuid->get($uuid))
            ->filter()
            ->values();

        $projectCandidates = $orderedPurchaseOrders
            ->map(fn ($item) => $item->purchaseRequest?->project)
            ->filter();

        if ($projectCandidates->count() !== $orderedPurchaseOrders->count()) {
            return back()->withErrors([
                'po_uuids' => 'Some selected POs do not have a valid project.',
            ]);
        }

        $projectIds = $projectCandidates->pluck('id')->unique()->values();
        if ($projectIds->count() !== 1) {
            return back()->withErrors([
                'po_uuids' => 'All selected POs must belong to the same project.',
            ]);
        }

        $project = $projectCandidates->first();
        $primaryPo = $orderedPurchaseOrders->first();

        $branchSlug = DB::table('branches')
            ->where('id', (int) $project->branch_id)
            ->value('slug');

        if (!$branchSlug) {
            return back()->withErrors([
                'po_uuids' => 'Selected PO project branch is invalid. Please contact admin.',
            ]);
        }

        $file = $request->file('proforma_invoice');
        $path = $file->store('sub-con-claims/proforma', 'public');
        $proofFiles = $request->file('proof_attachments', []);
        $proofAttachments = collect($proofFiles)
            ->map(function ($proofFile) {
                $proofPath = $proofFile->store('sub-con-claims/proof', 'public');

                return [
                    'path' => $proofPath,
                    'name' => $proofFile->getClientOriginalName(),
                ];
            })
            ->values();
        $primaryProof = $proofAttachments->first();

        $claim = SubConClaim::create([
            'claim_no' => RunningNumberService::next(
                'sub_con_claim',
                null,
                (int) $project->branch_id,
                (string) $branchSlug
            ),
            'project_id' => $project->id,
            'sub_con_id' => $subCon->id,
            'purchase_order_id' => $primaryPo?->id,
            'purchase_order_ids' => $orderedPurchaseOrders->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'title' => $validated['title'],
            'status' => 'submitted',
            'claimed_amount' => (float) $validated['claimed_amount'],
            'proforma_invoice_path' => $path,
            'proforma_invoice_name' => $file->getClientOriginalName(),
            'proof_attachment_path' => $primaryProof['path'] ?? null,
            'proof_attachment_name' => $primaryProof['name'] ?? null,
            'proof_attachments' => $proofAttachments->all(),
            'submitted_at' => now(),
        ]);

        $this->appendClaimLog(
            $claim,
            from: null,
            to: 'submitted',
            action: 'submit',
            by: $subCon->name,
            remark: $validated['remark'] ?? null
        );

        return back()->with('success', 'Claim submitted successfully.');
    }

    public function confirmPurchaseOrder(Request $request, string $poUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $po = $this->accessiblePurchaseOrdersQuery($subCon)
            ->with('signedPo')
            ->where('uuid', $poUuid)
            ->firstOrFail();

        if ($po->status !== 'issued') {
            return back()->withErrors([
                'status' => 'Only issued purchase orders can be confirmed.',
            ]);
        }

        $validated = $request->validate([
            'order_date' => ['required', 'date'],
            'signed_po' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        DB::transaction(function () use ($po, $validated, $request) {
            $hasSignedPO = $po->attachments()->exists();

            if (!$hasSignedPO && !$request->hasFile('signed_po')) {
                throw ValidationException::withMessages([
                    'signed_po' => 'Signed PO is required before confirmation.',
                ]);
            }

            if ($request->hasFile('signed_po')) {
                if ($hasSignedPO) {
                    throw ValidationException::withMessages([
                        'signed_po' => 'Signed PO already uploaded.',
                    ]);
                }

                $file = $request->file('signed_po');
                $path = $file->store('purchase-orders/signed', 'public');

                $po->attachments()->create([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'created_by' => null,
                ]);
            }

            $po->update([
                'order_date' => $validated['order_date'],
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => null,
            ]);
        });

        return back()->with('success', 'PO confirmed successfully.');
    }

    public function showPurchaseOrder(Request $request, string $poUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $po = $this->accessiblePurchaseOrdersQuery($subCon)
            ->with([
                'supplier',
                'items',
                'purchaseRequest.items',
                'purchaseRequest.approver',
                'purchaseRequest.requester',
                'siteContact:id,name',
            ])
            ->where('uuid', $poUuid)
            ->firstOrFail();

        if ($po->purchaseRequest) {
            $logUserIds = collect($po->purchaseRequest->remark_log ?? [])
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->values();

            $remarkSigners = User::query()
                ->whereIn('id', $logUserIds)
                ->get(['id', 'name', 'signature_path'])
                ->mapWithKeys(function ($user) {
                    return [
                        (string) $user->id => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'signature_url' => $user->signature_path
                                ? Storage::disk('public')->url($user->signature_path)
                                : null,
                        ],
                    ];
                });

            $po->purchaseRequest->setAttribute('remark_signers', $remarkSigners);
        }

        return response()->json([
            'po' => $po,
            'company' => CompanyProfile::first(),
        ]);
    }

    public function storeUpdate(Request $request, string $taskUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->with('project:id,uuid')
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if ($task->status !== 'draft') {
            return back()->withErrors([
                'status' => 'Progress can only be submitted while task is In Progress.',
            ]);
        }

        if ($task->parent_id) {
            return back()->withErrors([
                'status' => 'Please submit progress from Main Task only.',
            ]);
        }

        $validated = $request->validate([
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'note' => 'nullable|string',
            'progress_report' => 'required|file|max:204800',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'sub_task_ids' => 'nullable|array',
            'sub_task_ids.*' => 'integer',
        ]);

        $children = $task->children()
            ->get(['id', 'status']);
        $childIds = $children->pluck('id')->values();
        $hasChildren = $children->isNotEmpty();

        if (!$hasChildren && !isset($validated['progress_percent'])) {
            return back()->withErrors([
                'progress_percent' => 'Progress (%) is required.',
            ]);
        }

        $selectedSubTaskIds = collect($validated['sub_task_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedSubTaskIds->isNotEmpty()) {
            $invalidIds = $selectedSubTaskIds->diff($childIds);
            if ($invalidIds->isNotEmpty()) {
                return back()->withErrors([
                    'sub_task_ids' => 'Some selected sub tasks are invalid for this main task.',
                ]);
            }
        }

        $calculatedProgress = (int) ($validated['progress_percent'] ?? 0);
        $nextStatus = 'draft';
        if ($hasChildren) {
            $doneChildIds = $children
                ->filter(fn ($child) => strtolower((string) $child->status) !== 'draft')
                ->pluck('id');

            $doneAfterSubmit = $doneChildIds
                ->merge($selectedSubTaskIds)
                ->unique()
                ->count();

            $calculatedProgress = (int) round(($doneAfterSubmit / max($childIds->count(), 1)) * 100);
            $nextStatus = $doneAfterSubmit >= $childIds->count() ? 'submitted' : 'draft';
        } else {
            $nextStatus = $calculatedProgress >= 100 ? 'submitted' : 'draft';
        }

        $progressReportFile = $request->file('progress_report');
        $progressReportPath = $progressReportFile->store('sub-con-task-progress-reports', 'public');
        $progressReportName = $progressReportFile->getClientOriginalName();
        $nextProgressReportVersion = ((int) SubConTaskUpdate::query()
            ->where('sub_con_task_id', $task->id)
            ->whereNotNull('progress_report_path')
            ->max('progress_report_version')) + 1;
        $files = $request->file('attachments', []);
        if (empty($files)) {
            SubConTaskUpdate::create([
                'sub_con_task_id' => $task->id,
                'progress_percent' => $calculatedProgress,
                'note' => $validated['note'] ?? null,
                'progress_report_path' => $progressReportPath,
                'progress_report_name' => $progressReportName,
                'progress_report_version' => $nextProgressReportVersion,
                'attachment_path' => null,
                'attachment_name' => null,
            ]);
        } else {
            foreach ($files as $file) {
                $path = $file->store('sub-con-task-updates', 'public');

                SubConTaskUpdate::create([
                    'sub_con_task_id' => $task->id,
                    'progress_percent' => $calculatedProgress,
                    'note' => $validated['note'] ?? null,
                    'progress_report_path' => $progressReportPath,
                    'progress_report_name' => $progressReportName,
                    'progress_report_version' => $nextProgressReportVersion,
                    'attachment_path' => $path,
                    'attachment_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        $task->update([
            'progress_percent' => $calculatedProgress,
            'status' => $nextStatus,
        ]);

        if ($selectedSubTaskIds->isNotEmpty()) {
            $children = SubConTask::query()
                ->whereIn('id', $selectedSubTaskIds)
                ->get();

            foreach ($children as $child) {
                $child->update([
                    'progress_percent' => 100,
                    'status' => 'submitted',
                ]);

                SubConTaskUpdate::create([
                    'sub_con_task_id' => $child->id,
                    'progress_percent' => 100,
                    'note' => 'Marked complete from main task submission.',
                    'attachment_path' => null,
                    'attachment_name' => null,
                ]);
            }
        }

        $task->refreshProgressFromChildren();

        return back()->with(
            'success',
            $nextStatus === 'submitted'
                ? 'Progress submitted successfully.'
                : 'Progress saved. Task remains In Progress until all sub tasks are checked.'
        );
    }

    public function storeInvoice(Request $request, string $taskUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!in_array($task->status, ['contra_verified', 'verified'], true)) {
            return back()->withErrors([
                'status' => 'Invoice can only be submitted after Contra Verified.',
            ]);
        }

        $validated = $request->validate([
            'invoice_no' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'invoice_amount' => 'required|numeric|min:0',
            'invoice_remark' => 'nullable|string|max:1000',
            'invoice_attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('invoice_attachment');
        $path = $file->store('sub-con-task-invoices', 'public');

        $task->update([
            'status' => 'invoiced',
            'invoice_no' => $validated['invoice_no'],
            'invoice_date' => $validated['invoice_date'],
            'invoice_amount' => $validated['invoice_amount'],
            'invoice_remark' => $validated['invoice_remark'] ?? null,
            'invoice_attachment_path' => $path,
            'invoice_attachment_name' => $file->getClientOriginalName(),
            'invoice_submitted_at' => now(),
        ]);

        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Invoice submitted successfully.');
    }

    public function downloadInvoice(Request $request, string $taskUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!$task->invoice_attachment_path || !Storage::disk('public')->exists($task->invoice_attachment_path)) {
            abort(404, 'Invoice attachment not found.');
        }

        $name = $task->invoice_attachment_name ?? basename($task->invoice_attachment_path);

        return Storage::disk('public')->download($task->invoice_attachment_path, $name);
    }

    public function downloadUpdate(Request $request, string $taskUuid, string $updateUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        $update = SubConTaskUpdate::query()
            ->where('uuid', $updateUuid)
            ->where('sub_con_task_id', $task->id)
            ->firstOrFail();

        $kind = strtolower((string) $request->query('kind', 'attachment'));
        if ($kind === 'progress_report') {
            if (!$update->progress_report_path || !Storage::disk('public')->exists($update->progress_report_path)) {
                abort(404, 'Progress report not found.');
            }

            $name = $update->progress_report_name ?? basename($update->progress_report_path);

            return Storage::disk('public')->download($update->progress_report_path, $name);
        }

        if (!$update->attachment_path || !Storage::disk('public')->exists($update->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        $name = $update->attachment_name ?? basename($update->attachment_path);

        return Storage::disk('public')->download($update->attachment_path, $name);
    }

    public function decideClaim(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if ($claim->status !== 'ceo_gm_approved') {
            return back()->withErrors([
                'status' => 'This claim is not waiting for Sub Con decision.',
            ]);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:accept,appeal'],
            'remark' => ['nullable', 'string', 'max:1000'],
            'accept_proforma_invoice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'appeal_proof_attachments' => ['nullable', 'array'],
            'appeal_proof_attachments.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($validated['decision'] === 'accept' && !$request->hasFile('accept_proforma_invoice')) {
            return back()->withErrors([
                'accept_proforma_invoice' => 'New proforma invoice is required when accepting.',
            ]);
        }

        if ($validated['decision'] === 'appeal' && empty(trim((string) ($validated['remark'] ?? '')))) {
            return back()->withErrors([
                'remark' => 'Appeal remark is required.',
            ]);
        }

        $proofAttachments = $this->serializeClaimProofAttachments($claim);
        if ($validated['decision'] === 'appeal') {
            $appealFiles = $request->file('appeal_proof_attachments', []);
            if (empty($appealFiles)) {
                return back()->withErrors([
                    'appeal_proof_attachments' => 'Appeal proof attachment is required.',
                ]);
            }

            $newProofAttachments = collect($appealFiles)
                ->map(function ($file) {
                    $path = $file->store('sub-con-claims/proof', 'public');

                    return [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                    ];
                })
                ->values()
                ->all();

            $proofAttachments = array_values(array_merge($proofAttachments, $newProofAttachments));
        }

        $to = $validated['decision'] === 'accept' ? 'accepted_by_subcon' : 'appealed';
        $updatePayload = [
            'status' => $to,
            'appeal_round' => $to === 'appealed' ? ((int) $claim->appeal_round + 1) : $claim->appeal_round,
            'subcon_decided_at' => now(),
        ];
        if ($validated['decision'] === 'accept' && $request->hasFile('accept_proforma_invoice')) {
            if (!empty($claim->proforma_invoice_path)) {
                Storage::disk('public')->delete($claim->proforma_invoice_path);
            }

            $newProforma = $request->file('accept_proforma_invoice');
            $newProformaPath = $newProforma->store('sub-con-claims/proforma', 'public');
            $updatePayload['proforma_invoice_path'] = $newProformaPath;
            $updatePayload['proforma_invoice_name'] = $newProforma->getClientOriginalName();
        }
        if (!empty($proofAttachments)) {
            $updatePayload['proof_attachments'] = $proofAttachments;
            $updatePayload['proof_attachment_path'] = (string) ($proofAttachments[0]['path'] ?? null);
            $updatePayload['proof_attachment_name'] = (string) ($proofAttachments[0]['name'] ?? null);
        }

        $claim->update($updatePayload);

        $this->appendClaimLog(
            $claim,
            from: 'ceo_gm_approved',
            to: $to,
            action: 'subcon_' . $validated['decision'],
            by: $subCon->name,
            remark: $validated['remark'] ?? null
        );

        return back()->with('success', $to === 'accepted_by_subcon'
            ? 'You accepted the approved amount.'
            : 'Appeal submitted. Our team will review again.');
    }

    public function uploadClaimRealInvoice(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if ($claim->status !== 'pending_real_invoice_upload') {
            return back()->withErrors([
                'status' => 'Real invoice upload is not open yet for this claim.',
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

        $claim->update([
            'status' => 'real_invoice_uploaded',
            'real_invoice_no' => $validated['real_invoice_no'],
            'real_invoice_date' => $validated['real_invoice_date'],
            'real_invoice_amount' => (float) $validated['real_invoice_amount'],
            'real_invoice_path' => $path,
            'real_invoice_name' => $file->getClientOriginalName(),
            'real_invoice_uploaded_at' => now(),
        ]);

        $this->appendClaimLog(
            $claim,
            from: 'pending_real_invoice_upload',
            to: 'real_invoice_uploaded',
            action: 'upload_real_invoice',
            by: $subCon->name,
            remark: $validated['remark'] ?? null,
            amounts: ['real_invoice_amount' => (float) $validated['real_invoice_amount']]
        );

        return back()->with('success', 'Real invoice uploaded successfully.');
    }

    public function downloadClaimProforma(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!$claim->proforma_invoice_path || !Storage::disk('public')->exists($claim->proforma_invoice_path)) {
            abort(404, 'Proforma invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->proforma_invoice_path,
            $claim->proforma_invoice_name ?: basename($claim->proforma_invoice_path)
        );
    }

    public function downloadClaimRealInvoice(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!$claim->real_invoice_path || !Storage::disk('public')->exists($claim->real_invoice_path)) {
            abort(404, 'Real invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->real_invoice_path,
            $claim->real_invoice_name ?: basename($claim->real_invoice_path)
        );
    }

    public function downloadClaimProof(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

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

    private function getAuthenticatedSubCon(Request $request): SubCon
    {
        $subConId = (int) $request->session()->get('sub_con_auth_id');

        return SubCon::query()
            ->where('id', $subConId)
            ->firstOrFail();
    }

    private function accessiblePurchaseOrdersQuery(SubCon $subCon): Builder
    {
        $supplierNames = collect([
            trim((string) $subCon->company_name),
            trim((string) $subCon->name),
        ])
            ->filter()
            ->map(fn ($name) => strtolower($name))
            ->unique()
            ->values();

        return PurchaseOrder::query()
            ->whereHas('purchaseRequest', fn ($pr) => $pr->where('is_subcon_purchase_request', true))
            ->whereHas('supplier', function ($supplierQuery) use ($supplierNames) {
                if ($supplierNames->isEmpty()) {
                    $supplierQuery->whereRaw('1 = 0');
                    return;
                }

                $supplierQuery->where(function ($q) use ($supplierNames) {
                    foreach ($supplierNames as $name) {
                        $q->orWhereRaw('LOWER(company_name) = ?', [$name]);
                    }
                });
            });
    }

    private function computeHierarchyAwarePoAmount(PurchaseOrder $po): float
    {
        $items = $po->items ?? collect();
        $prItems = $po->purchaseRequest?->items ?? collect();
        if ($items->isEmpty()) {
            return $this->computeFromPurchaseRequestTopLevel($prItems, $po);
        }

        $prParentIds = $prItems
            ->pluck('parent_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $hasMappedPrItems = $items->contains(fn ($item) => (int) ($item->purchase_request_item_id ?? 0) > 0);
        if (!$hasMappedPrItems && $prParentIds->isNotEmpty()) {
            return $this->computeFromPurchaseRequestTopLevel($prItems, $po);
        }

        return (float) $items->reduce(function ($sum, $item) use ($prParentIds, $prItems) {
            $lineAmount = (float) (($item->quantity ?? 0) * ($item->unit_price ?? 0));
            $prItemId = (int) ($item->purchase_request_item_id ?? 0);

            if ($prItemId <= 0) {
                return $sum + $lineAmount;
            }

            $prItemExists = $prItems->contains(fn ($row) => (int) $row->id === $prItemId);
            $isParentWithChildren = $prItemExists && $prParentIds->contains($prItemId);

            return $isParentWithChildren ? $sum : ($sum + $lineAmount);
        }, 0.0);
    }

    private function computeFromPurchaseRequestTopLevel($prItems, PurchaseOrder $po): float
    {
        if ($prItems->isEmpty()) {
            return (float) ($po->total_amount ?? 0);
        }

        return (float) $prItems
            ->filter(fn ($item) => empty($item->parent_id))
            ->sum(fn ($item) => (float) ($item->total_price ?? 0));
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

    private function appendClaimLog(
        SubConClaim $claim,
        ?string $from,
        string $to,
        string $action,
        string $by,
        ?string $remark = null,
        array $amounts = []
    ): void {
        $log = is_array($claim->remark_log) ? $claim->remark_log : [];
        $log[] = [
            'from' => $from,
            'to' => $to,
            'action' => $action,
            'remark' => $remark,
            'by' => $by,
            'at' => now()->toDateTimeString(),
            'amounts' => $amounts,
        ];

        $claim->forceFill(['remark_log' => $log])->save();
    }

    private function mapClaimPurchaseOrders($claims): array
    {
        $claims = collect($claims);
        $allPoIds = $claims
            ->flatMap(function (SubConClaim $claim) {
                $ids = collect(is_array($claim->purchase_order_ids) ? $claim->purchase_order_ids : [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                if ($ids->isEmpty() && (int) ($claim->purchase_order_id ?? 0) > 0) {
                    $ids = collect([(int) $claim->purchase_order_id]);
                }

                return $ids;
            })
            ->unique()
            ->values();

        $poMap = PurchaseOrder::query()
            ->whereIn('id', $allPoIds)
            ->get(['id', 'uuid', 'code'])
            ->keyBy('id');

        return $claims
            ->mapWithKeys(function (SubConClaim $claim) use ($poMap) {
                $ids = collect(is_array($claim->purchase_order_ids) ? $claim->purchase_order_ids : [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                if ($ids->isEmpty() && (int) ($claim->purchase_order_id ?? 0) > 0) {
                    $ids = collect([(int) $claim->purchase_order_id]);
                }

                $rows = $ids
                    ->map(fn ($id) => $poMap->get($id))
                    ->filter()
                    ->map(fn ($po) => [
                        'uuid' => $po->uuid,
                        'code' => $po->code,
                    ])
                    ->values()
                    ->all();

                return [$claim->id => $rows];
            })
            ->all();
    }
}

