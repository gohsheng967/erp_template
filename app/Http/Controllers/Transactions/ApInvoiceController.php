<?php

namespace App\Http\Controllers\Transactions;
use App\Http\Controllers\Controller;

use App\Models\ApInvoice;
use App\Models\PurchaseOrder;
use App\Models\ApInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\AttachmentService;

class ApInvoiceController extends Controller
{
    /**
     * Store AP Invoice (from modal)
     */
    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'invoice_number'    => 'required|string|max:100',
            'invoice_date'      => 'required|date',
            'due_date'          => 'required|date|after_or_equal:invoice_date',
            'invoice_amount'    => 'required|numeric|min:0.01',
            'remarks'           => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {

            $po = PurchaseOrder::lockForUpdate()
                ->findOrFail($request->purchase_order_id);

            if (!$po->confirmed_at) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'PO must be confirmed before invoicing.',
                ]);
            }

            $exists = ApInvoice::where('supplier_id', $po->supplier_id)
                ->where('invoice_number', $request->invoice_number)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'invoice_number' => 'This supplier invoice already exists.',
                ]);
            }

            $alreadyInvoiced = ApInvoice::where('purchase_order_id', $po->id)
                ->whereNotIn('status', ['cancelled'])
                ->sum('invoice_amount');

            if ($request->invoice_amount > ($po->total_amount - $alreadyInvoiced)) {
                throw ValidationException::withMessages([
                    'invoice_amount' => 'Invoice amount exceeds PO remaining balance.',
                ]);
            }

            $invoice = ApInvoice::create([
                'uuid'              => Str::uuid(),
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'invoice_number'    => $request->invoice_number,
                'invoice_date'      => $request->invoice_date,
                'due_date'          => $request->due_date,
                'invoice_amount'    => $request->invoice_amount,
                'paid_amount'       => 0,
                'balance_amount'    => $request->invoice_amount,
                'status'            => 'confirmed',
                'remarks'           => $request->remarks,
                'created_by'        => auth()->id(),
            ]);

            AttachmentService::store(
                $request->file('document'),
                $invoice
            );
        });

        return back()->with('success', 'AP Invoice created successfully.');
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'outstanding');

        $baseQuery = ApInvoice::query()
            ->with([
                'purchaseOrder:id,code',
                'supplier:id,company_name',
            ]);

        if ($tab === 'outstanding') {
            $baseQuery->whereIn('status', ['confirmed', 'partially_paid']);
        } elseif ($tab === 'paid') {
            $baseQuery->where('status', 'paid');
        }

        // 🔢 Subtotals (clone query)
        $subtotal = (clone $baseQuery)->selectRaw('
            SUM(invoice_amount) as total_invoice,
            SUM(balance_amount) as total_balance
        ')->first();

        // 📄 Paginated list
        $invoices = $baseQuery
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return inertia('Transactions/ApInvoices/Index', [
            'invoices' => $invoices,
            'tab'      => $tab,
            'subtotal' => [
                'invoice' => $subtotal->total_invoice ?? 0,
                'balance' => $subtotal->total_balance ?? 0,
            ],
        ]);
    }

    public function show(string $uuid)
    {
        $invoice = ApInvoice::with([
            'supplier:id,company_name',
            'purchaseOrder:id,code',
            'attachments',
            'payments.attachments',       // 👈 REQUIRED
            'payments.createdBy:id,name',
        ])->where('uuid', $uuid)->firstOrFail();

        return inertia('Transactions/ApInvoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($request, $uuid) {

            $invoice = ApInvoice::with('payments')
                ->lockForUpdate()
                ->where('uuid', $uuid)
                ->firstOrFail();

            if ($invoice->cancelled_at) {
                abort(422, 'Invoice already cancelled.');
            }

            // Safety rule (current business rule)
            if ($invoice->paid_amount > 0) {
                abort(422, 'Cannot cancel invoice with payments. Use credit note.');
            }

            /* =========================
            CANCEL PAYMENTS (DEFENSIVE)
            ========================= */
            foreach ($invoice->payments as $payment) {
                if (!$payment->cancelled_at) {
                    $payment->update([
                        'cancelled_at'  => now(),
                        'cancelled_by'  => auth()->id(),
                        'cancel_reason' => 'Invoice cancelled: ' . $request->reason,
                    ]);
                }
            }

            /* =========================
            CANCEL INVOICE
            ========================= */
            $invoice->update([
                'status'         => 'cancelled',
                'cancelled_at'   => now(),
                'cancelled_by'   => auth()->id(),
                'cancel_reason'  => $request->reason,
                'balance_amount' => 0,
                'paid_amount'    => 0,
            ]);
        });

        return back()->with('success', 'Invoice cancelled successfully.');
    }



    public function storePayment(Request $request, string $uuid)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'reference'    => 'nullable|string|max:100',
            'remarks'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $uuid) {

            /* =========================
            LOCK INVOICE
            ========================= */
            $invoice = ApInvoice::where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->amount > $invoice->balance_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds outstanding balance.',
                ]);
            }

            /* =========================
            CREATE PAYMENT
            ========================= */
            $payment = ApInvoicePayment::create([
                'uuid'          => Str::uuid(),
                'ap_invoice_id' => $invoice->id,
                'amount'        => $request->amount,
                'payment_date'  => $request->payment_date,
                'reference'     => $request->reference,
                'remarks'       => $request->remarks,
                'created_by'    => auth()->id(),
            ]);

            /* =========================
            STORE PAYMENT PROOFS
            ========================= */
            if ($request->hasFile('proofs')) {
                foreach ($request->file('proofs') as $file) {
                    AttachmentService::store(
                        $file,
                        $payment                    
                    );
                }
            }

            /* =========================
            UPDATE INVOICE BALANCE
            ========================= */
            $invoice->paid_amount += $request->amount;
            $invoice->balance_amount -= $request->amount;

            if ($invoice->balance_amount <= 0) {
                $invoice->status = 'paid';
                $invoice->balance_amount = 0;
            } else {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();
        });

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function updatePayment(Request $request, string $uuid)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'reference'    => 'nullable|string|max:100',
            'remarks'      => 'nullable|string',

            // attachment handling
            'proof_mode'   => 'nullable|in:add,replace',
            'proofs'       => 'nullable|array',
            'proofs.*'     => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        DB::transaction(function () use ($request, $uuid) {

            /* =========================
            LOCK PAYMENT + INVOICE
            ========================= */
            $payment = ApInvoicePayment::with([
                    'invoice',
                    'attachments',
                ])
                ->lockForUpdate()
                ->where('uuid', $uuid)
                ->firstOrFail();

            $invoice = $payment->invoice;

            /* =========================
            REVERSE OLD PAYMENT
            ========================= */
            $invoice->paid_amount -= $payment->amount;
            $invoice->balance_amount += $payment->amount;

            if ($invoice->paid_amount < 0 || $invoice->balance_amount > $invoice->invoice_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Invoice balance mismatch detected.',
                ]);
            }

            /* =========================
            APPLY NEW PAYMENT
            ========================= */
            if ($request->amount > $invoice->balance_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds outstanding balance.',
                ]);
            }

            $invoice->paid_amount += $request->amount;
            $invoice->balance_amount -= $request->amount;

            /* =========================
            RECALCULATE STATUS
            ========================= */
            if ($invoice->balance_amount <= 0) {
                $invoice->status = 'paid';
                $invoice->balance_amount = 0;
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partially_paid';
            } else {
                $invoice->status = 'confirmed';
            }

            $invoice->save();

            /* =========================
            UPDATE PAYMENT
            ========================= */
            $payment->update([
                'amount'       => $request->amount,
                'payment_date' => $request->payment_date,
                'reference'    => $request->reference,
                'remarks'      => $request->remarks,
            ]);

            /* =========================
            ATTACHMENT HANDLING
            ========================= */
            $mode = $request->input('proof_mode', 'add');

            // 🔴 REPLACE MODE → remove old proofs first
            if ($mode === 'replace' && $payment->attachments->count()) {
                foreach ($payment->attachments as $attachment) {
                    // if you have soft delete, use ->delete()
                    // otherwise delete file + db record
                    $attachment->delete();
                }
            }

            // ➕ ADD (or replace after delete)
            if ($request->hasFile('proofs')) {
                foreach ($request->file('proofs') as $file) {
                    AttachmentService::store(
                        $file,
                        $payment,
                        'payment_voucher'
                    );
                }
            }
        });

        return back()->with('success', 'Payment updated successfully.');
    }

    public function cancelPayment(Request $request, string $uuid)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($request, $uuid) {

            $payment = ApInvoicePayment::with('invoice')
                ->lockForUpdate()
                ->where('uuid', $uuid)
                ->firstOrFail();

            if ($payment->cancelled_at) {
                abort(422, 'Payment already cancelled.');
            }

            $invoice = $payment->invoice;

            /* -------------------------
            REVERSE PAYMENT
            ------------------------- */
            $invoice->paid_amount -= $payment->amount;
            $invoice->balance_amount += $payment->amount;

            if ($invoice->paid_amount <= 0) {
                $invoice->status = 'confirmed';
            } else {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();

            /* -------------------------
            MARK PAYMENT CANCELLED
            ------------------------- */
            $payment->update([
                'cancelled_at'  => now(),
                'cancelled_by'  => auth()->id(),
                'cancel_reason' => $request->reason,
            ]);
        });

        return back()->with('success', 'Payment cancelled successfully.');
    }
}
