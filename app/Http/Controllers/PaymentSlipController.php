<?php

namespace App\Http\Controllers;

use App\Models\PaymentSlip;
use App\Models\PettyCashTopup;
use App\Models\ApInvoice;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class PaymentSlipController extends Controller
{
    public function index(Request $request)
    {
        $slipsQuery = PaymentSlip::query()
            ->with([
                'companyBankAccount',
                'source' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        PettyCashTopup::class => [
                            'wallet.project',
                            'requester:id,name',
                        ],
                        ApInvoice::class => [
                            'purchaseOrder.purchaseRequest.project',
                            'supplier',
                            'payments',
                        ],
                    ]);
                },
            ])
            ->withCount('attachments');

        if ($request->filled('status')) {
            if ($request->status === 'cancelled') {
                $slipsQuery->whereNotNull('cancelled_at');
            }
            if ($request->status === 'approved') {
                $slipsQuery->whereNull('cancelled_at');
            }
        }

        if ($request->filled('project_id')) {
            if ($request->project_id === 'office') {
                $slipsQuery->whereHasMorph(
                    'source',
                    [PettyCashTopup::class],
                    fn ($q) => $q->whereHas('wallet', fn ($w) => $w->where('context_type', 'office'))
                );
            } else {
                $projectId = $request->project_id;
                $slipsQuery->whereHasMorph(
                    'source',
                    [PettyCashTopup::class, ApInvoice::class],
                    function ($query, $type) use ($projectId) {
                        if ($type === PettyCashTopup::class) {
                            $query->whereHas('wallet', function ($wallet) use ($projectId) {
                                $wallet->where('context_type', 'project')
                                    ->where('context_id', $projectId);
                            });
                        }

                        if ($type === ApInvoice::class) {
                            $query->whereHas('purchaseOrder.purchaseRequest', function ($pr) use ($projectId) {
                                $pr->where('project_id', $projectId);
                            });
                        }
                    }
                );
            }
        }

        if ($request->filled('voucher')) {
            if ($request->voucher === 'yes') {
                $slipsQuery->whereHas('attachments');
            }
            if ($request->voucher === 'no') {
                $slipsQuery->whereDoesntHave('attachments');
            }
        }

        if ($request->filled('date_from')) {
            $slipsQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $slipsQuery->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';

            $slipsQuery->where(function ($query) use ($search) {
                $query->where('slip_no', 'like', $search)
                    ->orWhereHasMorph('source', [PettyCashTopup::class], function ($q) use ($search) {
                        $q->where('topup_no', 'like', $search)
                            ->orWhereHas('requester', fn ($user) => $user->where('name', 'like', $search))
                            ->orWhereHas('wallet.project', fn ($proj) => $proj->where('name', 'like', $search));
                    })
                    ->orWhereHasMorph('source', [ApInvoice::class], function ($q) use ($search) {
                        $q->where('invoice_number', 'like', $search)
                            ->orWhereHas('supplier', fn ($supp) => $supp->where('company_name', 'like', $search))
                            ->orWhereHas('purchaseOrder.purchaseRequest.project', fn ($proj) => $proj->where('name', 'like', $search));
                    });
            });
        }

        $slips = $slipsQuery
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $slips->getCollection()->transform(function ($slip) {
            $isPaid = false;
            $paidDate = null;
            $canCancel = true;

            if ($slip->source instanceof PettyCashTopup) {
                $isPaid = !empty($slip->source->payment_ref_no);
                $paidDate = $isPaid ? $slip->source->paid_at : null;
                if ($slip->source->status === 'paid') {
                    $canCancel = false;
                }
            }

            if ($slip->source instanceof ApInvoice) {
                $payment = $slip->source->payments
                    ->firstWhere('payment_slip_no', $slip->slip_no);
                $isPaid = $payment && !empty($payment->reference);
                $paidDate = $isPaid ? $payment->payment_date : null;
                if ($payment && $payment->cancelled_at) {
                    $canCancel = false;
                }
            }

            if ($slip->cancelled_at) {
                $isPaid = false;
                $paidDate = null;
                $canCancel = false;
            }

            $slip->setAttribute('is_paid', $isPaid);
            $slip->setAttribute('paid_date', $paidDate);
            $slip->setAttribute('can_cancel', $canCancel);

            return $slip;
        });

        return Inertia::render('PaymentSlips/Index', [
            'slips' => $slips,
            'projects' => Project::select('id', 'name')->orderBy('name')->get(),
            'filters' => $request->only([
                'search',
                'status',
                'project_id',
                'voucher',
                'date_from',
                'date_to',
            ]),
        ]);
    }

    public function upload(Request $request, PaymentSlip $paymentSlip)
    {
        $request->validate([
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        foreach ($request->file('attachments', []) as $file) {
            AttachmentService::store($file, $paymentSlip);
        }

        return back()->with('success', 'Payment slip uploaded.');
    }

    public function cancel(Request $request, PaymentSlip $paymentSlip)
    {
        if ($paymentSlip->cancelled_at) {
            abort(422, 'Payment slip already cancelled.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $source = $paymentSlip->source;

        if ($source instanceof ApInvoice) {
            $existingPayment = $source->payments()
                ->where('payment_slip_no', $paymentSlip->slip_no)
                ->first();

            if ($existingPayment && $existingPayment->cancelled_at) {
                throw ValidationException::withMessages([
                    'reason' => 'Payment already cancelled. Slip cancellation is not allowed.',
                ]);
            }

            $payment = $source->payments()
                ->where('payment_slip_no', $paymentSlip->slip_no)
                ->whereNull('cancelled_at')
                ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'reason' => 'Payment not found or already cancelled.',
                ]);
            }

            $source->paid_amount -= $payment->amount;
            $source->balance_amount += $payment->amount;

            if ($source->paid_amount <= 0) {
                $source->status = 'confirmed';
            } else {
                $source->status = 'partially_paid';
            }

            $source->save();

            $payment->update([
                'cancelled_at'  => now(),
                'cancelled_by'  => auth()->id(),
                'cancel_reason' => $request->reason,
            ]);
        }

        if ($source instanceof PettyCashTopup && $source->status === 'paid') {
            throw ValidationException::withMessages([
                'reason' => 'Cannot cancel slip for a paid top-up.',
            ]);
        }

        $paymentSlip->update([
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancel_reason' => $request->reason,
        ]);

        return back()->with('success', 'Payment slip cancelled.');
    }
}
