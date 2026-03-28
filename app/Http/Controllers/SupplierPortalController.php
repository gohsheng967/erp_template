<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierClaim;
use App\Services\RunningNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $supplier = $this->getAuthenticatedSupplier($request);

        $purchaseOrders = $this->accessiblePurchaseOrdersQuery($supplier)
            ->with([
                'supplier:id,company_name',
                'purchaseRequest:id,uuid,code,title,project_id',
                'purchaseRequest.items:id,purchase_request_id,parent_id,total_price',
                'purchaseRequest.project:id,uuid,code,name',
                'items:id,purchase_order_id,purchase_request_item_id,quantity,unit_price',
                'signedPo',
            ])
            ->whereIn('status', ['issued', 'confirmed'])
            ->orderByDesc('id')
            ->get();

        $claims = SupplierClaim::query()
            ->with(['project:id,uuid,code,name,status', 'purchaseOrder:id,uuid,code'])
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Supplier/Portal', [
            'supplier' => [
                'id' => $supplier->id,
                'uuid' => $supplier->uuid,
                'company_name' => $supplier->company_name,
                'login_identity_no' => $supplier->login_identity_no,
            ],
            'purchaseOrders' => $purchaseOrders->map(function (PurchaseOrder $po) {
                $signed = $po->signedPo;

                return [
                    'uuid' => $po->uuid,
                    'code' => $po->code,
                    'status' => $po->status,
                    'order_date' => $po->order_date ? (string) $po->order_date : null,
                    'total_amount' => $this->computeHierarchyAwarePoAmount($po),
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
            'claims' => $claims->map(function (SupplierClaim $claim) {
                return [
                    'uuid' => $claim->uuid,
                    'claim_no' => $claim->claim_no,
                    'title' => $claim->title,
                    'status' => $claim->status,
                    'claimed_amount' => (float) ($claim->claimed_amount ?? 0),
                    'proforma_invoice_name' => $claim->proforma_invoice_name,
                    'proof_attachments' => $this->serializeClaimProofAttachments($claim),
                    'project' => $claim->project ? [
                        'uuid' => $claim->project->uuid,
                        'name' => $claim->project->name,
                        'code' => $claim->project->code,
                    ] : null,
                    'purchase_order' => $claim->purchaseOrder ? [
                        'uuid' => $claim->purchaseOrder->uuid,
                        'code' => $claim->purchaseOrder->code,
                    ] : null,
                    'created_at' => optional($claim->created_at)?->toDateTimeString(),
                ];
            })->values(),
        ]);
    }

    public function confirmPurchaseOrder(Request $request, string $poUuid)
    {
        $supplier = $this->getAuthenticatedSupplier($request);

        $po = $this->accessiblePurchaseOrdersQuery($supplier)
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

    public function storeClaim(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier($request);

        $validated = $request->validate([
            'po_uuid' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'claimed_amount' => ['required', 'numeric', 'min:0'],
            'proforma_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'proof_attachments' => ['required', 'array', 'min:1'],
            'proof_attachments.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $po = $this->accessiblePurchaseOrdersQuery($supplier)
            ->with(['purchaseRequest.project:id,branch_id'])
            ->where('uuid', $validated['po_uuid'])
            ->first();

        if (!$po) {
            return back()->withErrors([
                'po_uuid' => 'Selected PO is not available for your account.',
            ]);
        }

        $project = $po->purchaseRequest?->project;
        if (!$project) {
            return back()->withErrors([
                'po_uuid' => 'Selected PO does not have a valid project.',
            ]);
        }

        $branchSlug = DB::table('branches')
            ->where('id', (int) $project->branch_id)
            ->value('slug');

        if (!$branchSlug) {
            return back()->withErrors([
                'po_uuid' => 'Selected PO project branch is invalid. Please contact admin.',
            ]);
        }

        $file = $request->file('proforma_invoice');
        $path = $file->store('supplier-claims/proforma', 'public');
        $proofFiles = $request->file('proof_attachments', []);
        $proofAttachments = collect($proofFiles)
            ->map(function ($proofFile) {
                $proofPath = $proofFile->store('supplier-claims/proof', 'public');

                return [
                    'path' => $proofPath,
                    'name' => $proofFile->getClientOriginalName(),
                ];
            })
            ->values();
        $primaryProof = $proofAttachments->first();

        $claim = SupplierClaim::create([
            'claim_no' => RunningNumberService::next(
                'supplier_claim',
                null,
                (int) $project->branch_id,
                (string) $branchSlug
            ),
            'project_id' => $project->id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'title' => $validated['title'],
            'status' => 'submitted',
            'claimed_amount' => (float) $validated['claimed_amount'],
            'proforma_invoice_path' => $path,
            'proforma_invoice_name' => $file->getClientOriginalName(),
            'proof_attachment_path' => $primaryProof['path'] ?? null,
            'proof_attachment_name' => $primaryProof['name'] ?? null,
            'proof_attachments' => $proofAttachments->all(),
            'submitted_at' => now(),
            'remark_log' => [[
                'from' => null,
                'to' => 'submitted',
                'action' => 'submit',
                'remark' => $validated['remark'] ?? null,
                'by' => $supplier->company_name,
                'at' => now()->toDateTimeString(),
            ]],
        ]);

        return back()->with('success', 'Claim submitted successfully.');
    }

    public function showPurchaseOrder(Request $request, string $poUuid)
    {
        $supplier = $this->getAuthenticatedSupplier($request);

        $po = $this->accessiblePurchaseOrdersQuery($supplier)
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

        return response()->json([
            'po' => $po,
            'company' => CompanyProfile::first(),
        ]);
    }

    public function downloadClaimProforma(Request $request, string $claimUuid)
    {
        $supplier = $this->getAuthenticatedSupplier($request);

        $claim = SupplierClaim::query()
            ->where('uuid', $claimUuid)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        if (!$claim->proforma_invoice_path || !Storage::disk('public')->exists($claim->proforma_invoice_path)) {
            abort(404, 'Proforma invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->proforma_invoice_path,
            $claim->proforma_invoice_name ?: basename($claim->proforma_invoice_path)
        );
    }

    public function downloadClaimProof(Request $request, string $claimUuid)
    {
        $supplier = $this->getAuthenticatedSupplier($request);

        $claim = SupplierClaim::query()
            ->where('uuid', $claimUuid)
            ->where('supplier_id', $supplier->id)
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

    private function getAuthenticatedSupplier(Request $request): Supplier
    {
        $supplierId = (int) $request->session()->get('supplier_auth_id');

        return Supplier::query()
            ->where('id', $supplierId)
            ->firstOrFail();
    }

    private function accessiblePurchaseOrdersQuery(Supplier $supplier): Builder
    {
        return PurchaseOrder::query()->where('supplier_id', $supplier->id);
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

    private function serializeClaimProofAttachments(SupplierClaim $claim): array
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

