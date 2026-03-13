<?php

namespace App\Http\Controllers;

use App\Models\PaymentSlip;
use App\Models\PettyCashTopup;
use App\Models\ApInvoice;
use App\Models\Claim;
use App\Models\Project;
use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class PaymentSlipController extends Controller
{
    public function index(Request $request)
    {
        $activeBranchId = $this->activeBranchId($request);
        $tab = in_array($request->tab, ['pending', 'processing', 'paid'], true)
            ? $request->tab
            : 'pending';
        $search = trim((string) $request->input('search', ''));
        $projectFilter = $request->input('project_id');
        $voucherFilter = $request->input('voucher');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $processingQuery = PaymentSlip::query()
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
                        Claim::class => [
                            'project',
                            'issuer:id,name',
                        ],
                    ]);
                },
            ])
            ->withCount('attachments');

        if ($activeBranchId !== null) {
            $processingQuery->where(function ($query) use ($activeBranchId) {
                $query->whereHas('companyBankAccount', fn ($bank) => $bank->where('branch_id', $activeBranchId))
                    ->orWhereHasMorph(
                        'source',
                        [Claim::class],
                        fn ($source) => $source->where('branch_id', $activeBranchId)
                    )
                    ->orWhereHasMorph(
                        'source',
                        [ApInvoice::class],
                        fn ($source) => $source->where('branch_id', $activeBranchId)
                    )
                    ->orWhereHasMorph(
                        'source',
                        [PettyCashTopup::class],
                        fn ($source) => $source->whereHas(
                            'wallet.project',
                            fn ($project) => $project->where('branch_id', $activeBranchId)
                        )
                    );
            });
        }

        $processingQuery->whereNull('cancelled_at');

        if ($projectFilter) {
            if ($projectFilter === 'office') {
                $processingQuery->whereHasMorph(
                    'source',
                    [PettyCashTopup::class],
                    fn ($q) => $q->whereHas('wallet', fn ($w) => $w->where('context_type', 'office'))
                );
            } else {
                $projectId = $projectFilter;
                $processingQuery->whereHasMorph(
                    'source',
                    [PettyCashTopup::class, ApInvoice::class, Claim::class],
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

                        if ($type === Claim::class) {
                            $query->where('project_id', $projectId);
                        }
                    }
                );
            }
        }

        if ($voucherFilter === 'yes') {
            $processingQuery->whereHas('attachments');
        }
        if ($voucherFilter === 'no') {
            $processingQuery->whereDoesntHave('attachments');
        }

        if ($dateFrom) {
            $processingQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $processingQuery->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';

            $processingQuery->where(function ($query) use ($like) {
                $query->where('slip_no', 'like', $like)
                    ->orWhereHasMorph('source', [PettyCashTopup::class], function ($q) use ($like) {
                        $q->where('topup_no', 'like', $like)
                            ->orWhereHas('requester', fn ($user) => $user->where('name', 'like', $like))
                            ->orWhereHas('wallet.project', fn ($proj) => $proj->where('name', 'like', $like));
                    })
                    ->orWhereHasMorph('source', [ApInvoice::class], function ($q) use ($like) {
                        $q->where('invoice_number', 'like', $like)
                            ->orWhereHas('supplier', fn ($supp) => $supp->where('company_name', 'like', $like))
                            ->orWhereHas('purchaseOrder.purchaseRequest.project', fn ($proj) => $proj->where('name', 'like', $like));
                    })
                    ->orWhereHasMorph('source', [Claim::class], function ($q) use ($like) {
                        $q->where('claim_no', 'like', $like)
                            ->orWhere('title', 'like', $like)
                            ->orWhereHas('issuer', fn ($user) => $user->where('name', 'like', $like))
                            ->orWhereHas('project', fn ($proj) => $proj->where('name', 'like', $like));
                    });
            });
        }

        $processing = (clone $processingQuery)
            ->where(function ($query) {
                $query->whereHasMorph('source', [Claim::class], fn ($q) => $q->where('status', 'ceo_approved'))
                    ->orWhereHasMorph('source', [PettyCashTopup::class], fn ($q) => $q->where('status', 'approved'))
                    ->orWhereHasMorph('source', [ApInvoice::class], fn ($q) => $q->whereIn('status', ['confirmed', 'partially_paid']));
            })
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'processing_page')
            ->withQueryString();

        $paid = (clone $processingQuery)
            ->where(function ($query) {
                $query->whereHasMorph('source', [Claim::class], fn ($q) => $q->where('status', 'paid'))
                    ->orWhereHasMorph('source', [PettyCashTopup::class], fn ($q) => $q->where('status', 'paid'))
                    ->orWhereHasMorph('source', [ApInvoice::class], fn ($q) => $q->where('status', 'paid'));
            })
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'paid_page')
            ->withQueryString();

        $transformSlip = function ($slip) {
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

            if ($slip->source instanceof Claim) {
                $isPaid = !empty($slip->source->payment_ref_no);
                $paidDate = $isPaid ? $slip->source->paid_at : null;
                if ($slip->source->status === 'paid') {
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
        };

        $processing->setCollection($processing->getCollection()->map($transformSlip));
        $paid->setCollection($paid->getCollection()->map($transformSlip));

        $pendingClaimsQuery = Claim::query()
            ->where('status', 'ceo_approved')
            ->whereDoesntHave('paymentSlip', fn ($q) => $q->whereNull('cancelled_at'))
            ->with(['project:id,name', 'issuer:id,name'])
            ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId));

        $pendingTopupsQuery = PettyCashTopup::query()
            ->where('status', 'approved')
            ->whereDoesntHave('paymentSlip', fn ($q) => $q->whereNull('cancelled_at'))
            ->with(['wallet.project:id,name', 'requester:id,name'])
            ->when($activeBranchId !== null, function ($q) use ($activeBranchId) {
                $q->where(function ($walletScope) use ($activeBranchId) {
                    $walletScope->whereHas('wallet', fn ($wallet) => $wallet->where('context_type', 'office'))
                        ->orWhereHas('wallet.project', fn ($project) => $project->where('branch_id', $activeBranchId));
                });
            });

        $pendingApInvoicesQuery = ApInvoice::query()
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->whereDoesntHave('paymentSlips', fn ($q) => $q->whereNull('cancelled_at'))
            ->with(['purchaseOrder.purchaseRequest.project:id,name', 'supplier:id,company_name'])
            ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId));

        if ($dateFrom) {
            $pendingClaimsQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingTopupsQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingApInvoicesQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $pendingClaimsQuery->whereDate('created_at', '<=', $dateTo);
            $pendingTopupsQuery->whereDate('created_at', '<=', $dateTo);
            $pendingApInvoicesQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($projectFilter) {
            if ($projectFilter === 'office') {
                $pendingClaimsQuery->whereRaw('1 = 0');
                $pendingApInvoicesQuery->whereRaw('1 = 0');
                $pendingTopupsQuery->whereHas('wallet', fn ($w) => $w->where('context_type', 'office'));
            } else {
                $pendingClaimsQuery->where('project_id', $projectFilter);
                $pendingApInvoicesQuery->whereHas('purchaseOrder.purchaseRequest', fn ($pr) => $pr->where('project_id', $projectFilter));
                $pendingTopupsQuery->whereHas('wallet', function ($w) use ($projectFilter) {
                    $w->where('context_type', 'project')->where('context_id', $projectFilter);
                });
            }
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $pendingClaimsQuery->where(function ($q) use ($like) {
                $q->where('claim_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('issuer', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $like));
            });
            $pendingTopupsQuery->where(function ($q) use ($like) {
                $q->where('topup_no', 'like', $like)
                    ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('wallet.project', fn ($p) => $p->where('name', 'like', $like));
            });
            $pendingApInvoicesQuery->where(function ($q) use ($like) {
                $q->where('invoice_number', 'like', $like)
                    ->orWhereHas('supplier', fn ($s) => $s->where('company_name', 'like', $like))
                    ->orWhereHas('purchaseOrder.purchaseRequest.project', fn ($p) => $p->where('name', 'like', $like));
            });
        }

        $pending = collect()
            ->merge($pendingClaimsQuery->get()->map(function (Claim $claim) {
                return [
                    'module' => 'claim',
                    'source_id' => $claim->id,
                    'source_uuid' => $claim->uuid,
                    'reference_no' => $claim->claim_no,
                    'title' => $claim->title,
                    'project' => $claim->project?->name ?? 'Others',
                    'requester' => $claim->issuer?->name ?? '-',
                    'amount' => (float) $claim->total_amount,
                    'date' => optional($claim->approved_at)->toDateString() ?? optional($claim->created_at)->toDateString(),
                    'created_at' => $claim->created_at?->toDateTimeString(),
                ];
            }))
            ->merge($pendingTopupsQuery->get()->map(function (PettyCashTopup $topup) {
                $project = $topup->wallet?->context_type === 'office'
                    ? 'Office'
                    : ($topup->wallet?->project?->name ?? 'Project');

                return [
                    'module' => 'topup',
                    'source_id' => $topup->id,
                    'source_uuid' => $topup->uuid,
                    'reference_no' => $topup->topup_no,
                    'title' => $topup->reason ?? 'Petty Cash Top-up',
                    'project' => $project,
                    'requester' => $topup->requester?->name ?? '-',
                    'amount' => (float) $topup->amount,
                    'date' => optional($topup->approved_at)->toDateString() ?? optional($topup->created_at)->toDateString(),
                    'created_at' => $topup->created_at?->toDateTimeString(),
                ];
            }))
            ->merge($pendingApInvoicesQuery->get()->map(function (ApInvoice $invoice) {
                return [
                    'module' => 'ap_invoice',
                    'source_id' => $invoice->id,
                    'source_uuid' => $invoice->uuid,
                    'reference_no' => $invoice->invoice_number,
                    'title' => 'AP Invoice',
                    'project' => $invoice->purchaseOrder?->purchaseRequest?->project?->name ?? 'Project',
                    'requester' => $invoice->supplier?->company_name ?? '-',
                    'amount' => (float) $invoice->balance_amount,
                    'date' => optional($invoice->invoice_date)->toDateString() ?? optional($invoice->created_at)->toDateString(),
                    'created_at' => $invoice->created_at?->toDateTimeString(),
                ];
            }))
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('PaymentSlips/Index', [
            'pending' => $pending,
            'slips' => [
                'processing' => $processing,
                'paid' => $paid,
            ],
            'counts' => [
                'pending' => $pending->count(),
                'processing' => $processing->total(),
                'paid' => $paid->total(),
            ],
            'activeTab' => $tab,
            'projects' => Project::query()
                ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId))
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'companyBankAccounts' => CompanyBankAccount::query()
                ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId))
                ->where('status', 'active')
                ->select('id', 'bank_name', 'account_name', 'account_no', 'branch_id')
                ->orderBy('bank_name')
                ->get(),
            'filters' => $request->only([
                'tab',
                'search',
                'project_id',
                'voucher',
                'date_from',
                'date_to',
            ]),
        ]);
    }

    public function upload(Request $request, PaymentSlip $paymentSlip)
    {
        $this->ensurePaymentSlipBranchAccess($request, $paymentSlip);

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
        $this->ensurePaymentSlipBranchAccess($request, $paymentSlip);

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

        if ($source instanceof Claim && $source->status === 'paid') {
            throw ValidationException::withMessages([
                'reason' => 'Cannot cancel slip for a paid claim.',
            ]);
        }

        $paymentSlip->update([
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancel_reason' => $request->reason,
        ]);

        return back()->with('success', 'Payment slip cancelled.');
    }

    private function activeBranchId(Request $request): ?int
    {
        if (!$this->shouldScopeToActiveBranch($request)) {
            return null;
        }

        $branchId = (int) ($request->user()?->active_branch_id ?? 0);
        if ($branchId <= 0) {
            abort(422, 'Please select an active branch before proceeding.');
        }

        return $branchId;
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }

    private function ensurePaymentSlipBranchAccess(Request $request, PaymentSlip $paymentSlip): void
    {
        $activeBranchId = $this->activeBranchId($request);
        if ($activeBranchId === null) {
            return;
        }

        $paymentSlip->loadMissing([
            'companyBankAccount:id,branch_id',
            'source' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Claim::class => [],
                    ApInvoice::class => [],
                    PettyCashTopup::class => ['wallet.project:id,branch_id'],
                ]);
            },
        ]);

        $source = $paymentSlip->source;
        $isAllowed = false;

        if ((int) ($paymentSlip->companyBankAccount?->branch_id ?? 0) === $activeBranchId) {
            $isAllowed = true;
        } elseif ($source instanceof Claim && (int) $source->branch_id === $activeBranchId) {
            $isAllowed = true;
        } elseif ($source instanceof ApInvoice && (int) $source->branch_id === $activeBranchId) {
            $isAllowed = true;
        } elseif (
            $source instanceof PettyCashTopup
            && (int) ($source->wallet?->project?->branch_id ?? 0) === $activeBranchId
        ) {
            $isAllowed = true;
        }

        if (!$isAllowed) {
            abort(404);
        }
    }
}
