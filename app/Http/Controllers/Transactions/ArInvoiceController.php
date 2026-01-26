<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Models\ArInvoice;
use App\Models\ArInvoiceItem;
use App\Models\Project;
use App\Models\Client;
use App\Models\Attachment;
use App\Models\CompanyProfile;

use Inertia\Inertia;
use Carbon\Carbon;

use App\Services\RunningNumberService;
use App\Services\AttachmentService;

class ArInvoiceController extends Controller
{
    /* ============================================================
     * INDEX
     * ============================================================
     */
    public function index(Request $request)
    {
        $tab       = $request->tab ?? 'issued';
        $projectId = $request->project_id;
        $customerId = $request->customer_id;

        $from = $request->from
            ?? Carbon::now()->subMonth()->toDateString();

        $to = $request->to
            ?? Carbon::now()->toDateString();

        $search = $request->search;

        /*
        |--------------------------------------------------------------------------
        | Base Query (shared)
        |--------------------------------------------------------------------------
        */
        $baseQuery = ArInvoice::query()
            ->with([
                'project:id,name',
                'customer:id,name',
                'issuer:id,name',
            ])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->withSum('receipts', 'amount')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($projectId, fn ($q) =>
                $q->where('project_id', $projectId)
            )
            ->when($customerId, fn ($q) =>
                $q->where('customer_id', $customerId)
            )
            ->when($from, fn ($q) =>
                $q->whereDate('ar_invoices.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('ar_invoices.created_at', '<=', $to)
            )
            ->orderByDesc('ar_invoices.created_at');

        /*
        |--------------------------------------------------------------------------
        | Tabs
        |--------------------------------------------------------------------------
        */
        $draft     = (clone $baseQuery)->where('status', 'draft')->paginate(15)->withQueryString();
        $issued    = (clone $baseQuery)->where('status', 'issued')->paginate(15)->withQueryString();
        $approved  = (clone $baseQuery)->where('status', 'approved')->paginate(15)->withQueryString();
        $received  = (clone $baseQuery)->where('status', 'received')->paginate(15)->withQueryString();
        $cancelled = (clone $baseQuery)->where('status', 'cancelled')->paginate(15)->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Badge Counts
        |--------------------------------------------------------------------------
        */
        $countsRaw = (clone $baseQuery)
            ->whereIn('status', ['issued', 'approved'])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'issued'   => (int) ($countsRaw['issued'] ?? 0),
            'approved' => (int) ($countsRaw['approved'] ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | Approved Summary (for Approved tab only)
        |--------------------------------------------------------------------------
        */
        $approvedInvoiceQuery = ArInvoice::query()
            ->where('status', 'approved')
            ->when($projectId, fn ($q) =>
                $q->where('project_id', $projectId)
            )
            ->when($from, fn ($q) =>
                $q->whereDate('created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('created_at', '<=', $to)
            );

        $approvedTotal = (float) $approvedInvoiceQuery->sum('total_amount');

        $receivedTotal = (float) DB::table('ar_invoice_receipts')
            ->join('ar_invoices', 'ar_invoice_receipts.ar_invoice_id', '=', 'ar_invoices.id')
            ->where('ar_invoices.status', 'approved')
            ->when($projectId, fn ($q) =>
                $q->where('ar_invoices.project_id', $projectId)
            )
            ->when($from, fn ($q) =>
                $q->whereDate('ar_invoice_receipts.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('ar_invoice_receipts.created_at', '<=', $to)
            )
            ->sum('ar_invoice_receipts.amount');

        $approvedTotals = [
            'total_amount'    => $approvedTotal,
            'received_amount' => $receivedTotal,
            'outstanding'     => max($approvedTotal - $receivedTotal, 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | Dropdown Data
        |--------------------------------------------------------------------------
        */
        $projects  = Project::select('id', 'name')->orderBy('name')->get();
        $customers = Client::select('id', 'name')->orderBy('name')->get();

        /*
        |--------------------------------------------------------------------------
        | Render
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Transactions/ArInvoices/Index', [
            'invoices' => [
                'draft'     => $draft,
                'issued'    => $issued,
                'approved'  => $approved,
                'received'  => $received,
                'cancelled' => $cancelled,
            ],

            'filters' => [
                'search'     => $search,
                'from'       => $from,
                'to'         => $to,
                'project_id' => $projectId,
                'customer_id' => $customerId,
            ],

            'counts'          => $counts,
            'approvedTotals'  => $approvedTotals,
            'activeTab'       => $tab,

            'projects'  => $projects,
            'customers' => $customers,
            'prefill' => [
                'customer_id' => $customerId,
                'open_create' => $request->boolean('create'),
            ],
        ]);
    }

    /* ============================================================
     * STORE (CREATE DRAFT)
     * ============================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id'   => ['nullable', 'exists:projects,id'],
            'customer_id'  => ['required', 'exists:clients,id'],
            'title'        => ['required', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $invoice = ArInvoice::create([
            'title'        => $validated['title'],
            'project_id'   => $validated['project_id'] ?? null,
            'customer_id'  => $validated['customer_id'],
            'total_amount' => $validated['total_amount'],
            'payment_term_days' => $validated['payment_term_days'] ?? null,
            'status'       => 'draft',
            'issued_by'    => $request->user()->id,
        ]);

        return redirect()->route('ar-invoices.edit', $invoice->uuid);
    }

    /* ============================================================
     * EDIT (DRAFT ONLY)
     * ============================================================
     */
    public function edit(string $uuid)
    {
        $invoice = ArInvoice::where('uuid', $uuid)
            ->with(['items.attachments'])
            ->firstOrFail();

        if ($invoice->status !== 'draft') {
            abort(403, 'Invoice is no longer editable.');
        }

        return inertia('Transactions/ArInvoices/Edit', [
            'invoice' => $invoice,
        ]);
    }

    /* ============================================================
     * UPDATE (SAVE DRAFT / ISSUE)
     * ============================================================
     */
    public function update(Request $request, string $uuid)
    {
        $invoice = ArInvoice::where('uuid', $uuid)->firstOrFail();

        if ($invoice->status !== 'draft') {
            abort(403, 'Invoice is locked.');
        }

        $baseRules = [
            'status' => ['required', Rule::in(['draft', 'issued'])],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
        ];

        $issueRules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
        ];

        $rules = $request->status === 'issued'
            ? array_merge($baseRules, $issueRules)
            : $baseRules;

        $validated = $request->validate($rules);

        if ($validated['status'] === 'issued') {
            $itemsSum = collect($validated['items'])
                ->sum(fn ($item) => (float) ($item['amount'] ?? 0));

            if (bccomp((string) $itemsSum, (string) $validated['total_amount'], 2) !== 0) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Total amount must equal item sum.',
                ]);
            }
        }

        DB::transaction(function () use ($invoice, $validated, $request) {

            if ($validated['status'] === 'issued' && !$invoice->invoice_no) {
                $invoice->invoice_no = RunningNumberService::next('ar_invoice');
                $invoice->issued_at = now();
            }

            $issuedAt = $invoice->issued_at ?? now();
            $dueDate = null;
            if ($validated['status'] === 'issued' && array_key_exists('payment_term_days', $validated)) {
                $dueDate = $validated['payment_term_days'] !== null
                    ? Carbon::parse($issuedAt)->addDays((int) $validated['payment_term_days'])
                    : null;
            }

            $invoice->update([
                'status'       => $validated['status'],
                'total_amount' => $validated['total_amount'] ?? 0,
                'payment_term_days' => $validated['payment_term_days'] ?? null,
                'due_date' => $validated['status'] === 'issued' ? $dueDate : null,
            ]);

            $keptItemIds = [];

            foreach ($validated['items'] ?? [] as $index => $item) {

                if (!empty($item['id'])) {
                    $invoiceItem = $invoice->items()
                        ->where('id', $item['id'])
                        ->firstOrFail();

                    $invoiceItem->update([
                        'title'       => $item['title'],
                        'description' => $item['description'] ?? null,
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['unit_price'],
                        'amount'      => $item['amount'],
                    ]);
                } else {
                    $invoiceItem = $invoice->items()->create([
                        'title'       => $item['title'],
                        'description' => $item['description'] ?? null,
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['unit_price'],
                        'amount'      => $item['amount'],
                    ]);
                }

                $keptItemIds[] = $invoiceItem->id;
            }

            $invoice->items()
                ->whereNotIn('id', $keptItemIds)
                ->each(function ($item) {
                    $item->attachments()->each(function ($attachment) {
                        Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    });
                    $item->delete();
                });
        });

        return redirect()
            ->route('ar-invoices.index')
            ->with(
                'success',
                $validated['status'] === 'issued'
                    ? 'Invoice issued successfully.'
                    : 'Draft saved successfully.'
            );
    }

    /* ============================================================
     * APPROVAL
     * ============================================================
     */
    public function approval(Request $request, string $uuid)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'remark' => 'nullable|string|max:1000',
        ]);

        $invoice = ArInvoice::where('uuid', $uuid)
            ->lockForUpdate()
            ->firstOrFail();

        if ($invoice->status !== 'issued') {
            abort(403, 'Invoice cannot be approved.');
        }

        DB::transaction(function () use ($invoice, $request) {
            $invoice->update([
                'status'       => $request->status,
                'remark'       => $request->remark,
                'approved_by'  => auth()->id(),
                'approved_at'  => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'status'  => $invoice->status,
        ]);
    }

    /* ============================================================
     * MARK RECEIVED
     * ============================================================
     */
    public function markReceived(Request $request, string $uuid)
    {
        $validated = $request->validate([
            'receipts'               => ['required', 'array', 'min:1'],
            'receipts.*.amount'      => ['required', 'numeric', 'min:0.01'],
            'receipts.*.reference'   => ['nullable', 'string', 'max:255'],
            'receipts.*.file'        => ['nullable', 'file', 'max:10240'],
        ]);

        return DB::transaction(function () use ($validated, $uuid, $request) {
            $invoice = ArInvoice::where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status !== 'approved') {
                abort(422, 'Only approved invoices can receive payments.');
            }

            foreach ($validated['receipts'] as $index => $receipt) {

                $attachmentPath = null;
                $attachmentName = null;

                if ($request->hasFile("receipts.$index.file")) {
                    $file = $request->file("receipts.$index.file");

                    $attachmentPath = $file->store(
                        'ar-invoices/receipts',
                        'public'
                    );

                    $attachmentName = $file->getClientOriginalName();
                }

                $invoice->receipts()->create([
                    'amount'          => $receipt['amount'],
                    'reference'       => $receipt['reference'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachmentName,
                    'received_at'     => now(),
                    'received_by'     => auth()->id(),
                ]);
            }

            $totalReceived = $invoice->receipts()->sum('amount');

            if ($totalReceived >= $invoice->total_amount) {
                $invoice->update([
                    'status'       => 'received',
                    'received_at'  => now(),
                    'received_by'  => auth()->id(),
                ]);
            }

            return response()->json([
                'success'         => true,
                'total_received' => $totalReceived,
                'outstanding'    => max(
                    $invoice->total_amount - $totalReceived,
                    0
                ),
            ]);
        });
    }

    /* ============================================================
     * SHOW (PDF / VIEW)
     * ============================================================
     */
    public function show(string $uuid)
    {
        $invoice = ArInvoice::where('uuid', $uuid)
            ->with([
                'items.attachments',
                'issuer',
                'approver',
                'receiver',
                'project',
                'customer',
                'receipts',
                'receipts.receiver'
            ])
            ->firstOrFail();

        $company = CompanyProfile::first();

        return response()->json([
            'invoice' => $invoice,
            'company' => $company,
        ]);
    }

    public function cancel(string $uuid)
    {
        return DB::transaction(function () use ($uuid) {

            $invoice = ArInvoice::where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($invoice->status, ['draft', 'issued'])) {
                abort(422, 'This invoice cannot be cancelled.');
            }

            $invoice->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancelled_by'  => auth()->id(),
            ]);
            return back()->with('success','Invoice cancelled successfully.');
        });
    }

}
