<?php

namespace App\Http\Controllers;

use App\Models\PaymentSlip;
use App\Models\PettyCashTopup;
use App\Models\ApInvoice;
use App\Models\Claim;
use App\Models\SubConClaim;
use App\Models\Project;
use App\Models\CompanyBankAccount;
use App\Models\SubConTask;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PaymentSlipController extends Controller
{
    public function index(Request $request)
    {
        $activeBranchId = $this->activeBranchId($request);
        $tab = in_array($request->tab, ['pending', 'processing', 'payment_arrangement', 'paid'], true)
            ? $request->tab
            : 'pending';
        $search = trim((string) $request->input('search', ''));
        $projectFilter = $request->input('project_id');
        $moduleFilter = $request->input('module');
        $requesterFilter = trim((string) $request->input('requester', ''));
        $voucherFilter = $request->input('voucher');
        $amountMin = $request->input('amount_min');
        $amountMax = $request->input('amount_max');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $processingQuery = PaymentSlip::query()
            ->with([
                'companyBankAccount',
                'approvedBy:id,name,signature_path',
                'creator:id,name,signature_path',
                'source' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        PettyCashTopup::class => [
                            'wallet.project',
                            'requester:id,name,signature_path',
                            'verifier:id,name,signature_path',
                            'approver:id,name,signature_path',
                            'payer:id,name,signature_path',
                        ],
                        ApInvoice::class => [
                            'purchaseOrder.purchaseRequest.project',
                            'supplier',
                            'creator:id,name,signature_path',
                            'payments.createdBy:id,name,signature_path',
                        ],
                        Claim::class => [
                            'project',
                            'issuer:id,name,signature_path',
                            'checker:id,name,signature_path',
                            'approver:id,name,signature_path',
                            'payer:id,name,signature_path',
                        ],
                        SubConClaim::class => [
                            'project:id,uuid,name,branch_id',
                            'subCon:id,name,company_name',
                        ],
                        SubConTask::class => [
                            'project:id,uuid,name,branch_id',
                            'subCon:id,name,company_name',
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
                    )
                    ->orWhereHasMorph(
                        'source',
                        [SubConClaim::class],
                        fn ($source) => $source->whereHas(
                            'project',
                            fn ($project) => $project->where('branch_id', $activeBranchId)
                        )
                    )
                    ->orWhereHasMorph(
                        'source',
                        [SubConTask::class],
                        fn ($source) => $source->whereHas(
                            'project',
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
                    [PettyCashTopup::class, ApInvoice::class, Claim::class, SubConClaim::class, SubConTask::class],
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

                        if ($type === SubConClaim::class) {
                            $query->where('project_id', $projectId);
                        }

                        if ($type === SubConTask::class) {
                            $query->where('project_id', $projectId);
                        }
                    }
                );
            }
        }

        if (in_array($moduleFilter, ['claim', 'topup', 'ap_invoice', 'sub_con_claim', 'sub_con_task'], true)) {
            $moduleMorphMap = [
                'claim' => Claim::class,
                'topup' => PettyCashTopup::class,
                'ap_invoice' => ApInvoice::class,
                'sub_con_claim' => SubConClaim::class,
                'sub_con_task' => SubConTask::class,
            ];

            $processingQuery->whereHasMorph('source', [$moduleMorphMap[$moduleFilter]]);
        }

        if ($voucherFilter === 'yes') {
            $processingQuery->whereHas('attachments');
        }
        if ($voucherFilter === 'no') {
            $processingQuery->whereDoesntHave('attachments');
        }

        if ($requesterFilter !== '') {
            $likeRequester = '%' . $requesterFilter . '%';
            $processingQuery->where(function ($query) use ($likeRequester) {
                $query->whereHasMorph(
                    'source',
                    [PettyCashTopup::class],
                    fn ($q) => $q->whereHas('requester', fn ($u) => $u->where('name', 'like', $likeRequester))
                )->orWhereHasMorph(
                    'source',
                    [Claim::class],
                    fn ($q) => $q->whereHas('issuer', fn ($u) => $u->where('name', 'like', $likeRequester))
                )->orWhereHasMorph(
                    'source',
                    [ApInvoice::class],
                    fn ($q) => $q->whereHas('supplier', fn ($s) => $s->where('company_name', 'like', $likeRequester))
                )->orWhereHasMorph(
                    'source',
                    [SubConClaim::class],
                    fn ($q) => $q->whereHas('subCon', fn ($s) => $s->where('name', 'like', $likeRequester)
                        ->orWhere('company_name', 'like', $likeRequester))
                )->orWhereHasMorph(
                    'source',
                    [SubConTask::class],
                    fn ($q) => $q->whereHas('subCon', fn ($s) => $s->where('name', 'like', $likeRequester)
                        ->orWhere('company_name', 'like', $likeRequester))
                );
            });
        }

        if ($amountMin !== null && $amountMin !== '') {
            $processingQuery->where('amount', '>=', (float) $amountMin);
        }
        if ($amountMax !== null && $amountMax !== '') {
            $processingQuery->where('amount', '<=', (float) $amountMax);
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
                            ->orWhereHas('purchaseOrder', fn ($po) => $po->where('code', 'like', $like))
                            ->orWhereHas('supplier', fn ($supp) => $supp->where('company_name', 'like', $like))
                            ->orWhereHas('purchaseOrder.purchaseRequest.project', fn ($proj) => $proj->where('name', 'like', $like));
                    })
                    ->orWhereHasMorph('source', [Claim::class], function ($q) use ($like) {
                        $q->where('claim_no', 'like', $like)
                            ->orWhere('title', 'like', $like)
                            ->orWhereHas('issuer', fn ($user) => $user->where('name', 'like', $like))
                            ->orWhereHas('project', fn ($proj) => $proj->where('name', 'like', $like));
                    })
                    ->orWhereHasMorph('source', [SubConClaim::class], function ($q) use ($like) {
                        $q->where('claim_no', 'like', $like)
                            ->orWhere('title', 'like', $like)
                            ->orWhere('payment_slip_no', 'like', $like)
                            ->orWhereHas('subCon', fn ($subCon) => $subCon->where('name', 'like', $like)
                                ->orWhere('company_name', 'like', $like))
                            ->orWhereHas('project', fn ($proj) => $proj->where('name', 'like', $like));
                    })
                    ->orWhereHasMorph('source', [SubConTask::class], function ($q) use ($like) {
                        $q->where('task_no', 'like', $like)
                            ->orWhere('title', 'like', $like)
                            ->orWhere('invoice_no', 'like', $like)
                            ->orWhere('payment_slip_no', 'like', $like)
                            ->orWhereHas('subCon', fn ($subCon) => $subCon->where('name', 'like', $like)
                                ->orWhere('company_name', 'like', $like))
                            ->orWhereHas('project', fn ($proj) => $proj->where('name', 'like', $like));
                    });
            });
        }

        $processing = (clone $processingQuery)
            ->where('workflow_status', 'processing')
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'processing_page')
            ->withQueryString();

        $paymentArrangement = (clone $processingQuery)
            ->where(function ($query) {
                $query->where('workflow_status', 'payment_arrangement')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('workflow_status')
                            ->where(function ($sourceQuery) {
                                $sourceQuery->whereHasMorph('source', [Claim::class], function ($q) {
                                    $q->where('status', 'ceo_approved')
                                        ->where(function ($claimQuery) {
                                            $claimQuery->whereNull('remark')
                                                ->orWhereRaw(
                                                    'LOWER(TRIM(remark)) <> ?',
                                                    [Str::lower(trim(Claim::REMARK_PETTY_CASH_ORIGIN))]
                                                );
                                        });
                                })
                                    ->orWhereHasMorph('source', [PettyCashTopup::class], fn ($q) => $q->where('status', 'approved'))
                                    ->orWhereHasMorph('source', [ApInvoice::class], fn ($q) => $q->whereIn('status', ['confirmed', 'partially_paid']))
                                    ->orWhereHasMorph('source', [SubConTask::class], fn ($q) => $q->whereIn('status', ['approved', 'justified', 'certified']));
                            });
                    });
            })
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'arrangement_page')
            ->withQueryString();

        $paid = (clone $processingQuery)
            ->where(function ($query) {
                $query->where('workflow_status', 'paid')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('workflow_status')
                            ->where(function ($sourceQuery) {
                                $sourceQuery->whereHasMorph('source', [Claim::class], fn ($q) => $q->where('status', 'paid'))
                                    ->orWhereHasMorph('source', [PettyCashTopup::class], fn ($q) => $q->where('status', 'paid'))
                                    ->orWhereHasMorph('source', [ApInvoice::class], fn ($q) => $q->where('status', 'paid'))
                                    ->orWhereHasMorph('source', [SubConTask::class], fn ($q) => $q->where('status', 'paid'));
                            });
                    });
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

            if ($slip->source instanceof SubConTask) {
                $isPaid = $slip->source->status === 'paid' || !empty($slip->source->payment_ref_no);
                $paidDate = $isPaid ? $slip->source->paid_at : null;
                if ($slip->source->status === 'paid') {
                    $canCancel = false;
                }
            }

            if ($slip->source instanceof SubConClaim) {
                $isPaid = in_array($slip->source->status, ['pending_real_invoice_upload', 'real_invoice_uploaded'], true);
                $paidDate = $isPaid ? ($slip->source->payment_slip_prepared_at ?? $slip->updated_at) : null;
                if ($isPaid) {
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
        $paymentArrangement->setCollection($paymentArrangement->getCollection()->map($transformSlip));
        $paid->setCollection($paid->getCollection()->map($transformSlip));

        $pendingClaimsQuery = Claim::query()
            ->where('status', 'ceo_approved')
            ->where(function ($q) {
                $q->whereNull('remark')
                    ->orWhereRaw(
                        'LOWER(TRIM(remark)) <> ?',
                        [Str::lower(trim(Claim::REMARK_PETTY_CASH_ORIGIN))]
                    );
            })
            ->whereDoesntHave('paymentSlip', function ($q) {
                $q->whereNull('cancelled_at')
                    ->where(function ($workflow) {
                        $workflow->whereIn('workflow_status', ['processing', 'payment_arrangement', 'paid'])
                            ->orWhereNull('workflow_status');
                    });
            })
            ->with(['project:id,name', 'issuer:id,name'])
            ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId));

        $pendingTopupsQuery = PettyCashTopup::query()
            ->where('status', 'approved')
            ->whereDoesntHave('paymentSlip', function ($q) {
                $q->whereNull('cancelled_at')
                    ->where(function ($workflow) {
                        $workflow->whereIn('workflow_status', ['processing', 'payment_arrangement', 'paid'])
                            ->orWhereNull('workflow_status');
                    });
            })
            ->with(['wallet.project:id,name', 'requester:id,name'])
            ->when($activeBranchId !== null, function ($q) use ($activeBranchId) {
                $q->where(function ($walletScope) use ($activeBranchId) {
                    $walletScope->whereHas('wallet', fn ($wallet) => $wallet->where('context_type', 'office'))
                        ->orWhereHas('wallet.project', fn ($project) => $project->where('branch_id', $activeBranchId));
                });
            });

        $pendingApInvoicesQuery = ApInvoice::query()
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->whereDoesntHave('paymentSlips', function ($q) {
                $q->whereNull('cancelled_at')
                    ->where(function ($workflow) {
                        $workflow->whereIn('workflow_status', ['processing', 'payment_arrangement', 'paid'])
                            ->orWhereNull('workflow_status');
                    });
            })
            ->with(['purchaseOrder.purchaseRequest.project:id,name', 'supplier:id,company_name'])
            ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId));

        $pendingSubConTasksQuery = SubConTask::query()
            ->whereNull('parent_id')
            ->whereIn('status', ['approved', 'justified', 'certified'])
            ->whereDoesntHave('paymentSlip', function ($q) {
                $q->whereNull('cancelled_at')
                    ->where(function ($workflow) {
                        $workflow->whereIn('workflow_status', ['processing', 'payment_arrangement', 'paid'])
                            ->orWhereNull('workflow_status');
                    });
            })
            ->with(['project:id,uuid,name', 'subCon:id,name,company_name'])
            ->when($activeBranchId !== null, fn ($q) => $q->whereHas('project', fn ($project) => $project->where('branch_id', $activeBranchId)));

        $pendingSubConClaimsQuery = SubConClaim::query()
            ->whereIn('status', ['payment_in_progress', 'accepted_by_subcon'])
            ->whereDoesntHave('paymentSlip', function ($q) {
                $q->whereNull('cancelled_at')
                    ->where(function ($workflow) {
                        $workflow->whereIn('workflow_status', ['processing', 'payment_arrangement', 'paid'])
                            ->orWhereNull('workflow_status');
                    });
            })
            ->with(['project:id,uuid,name', 'subCon:id,name,company_name'])
            ->when($activeBranchId !== null, fn ($q) => $q->whereHas('project', fn ($project) => $project->where('branch_id', $activeBranchId)));

        if (in_array($moduleFilter, ['claim', 'topup', 'ap_invoice', 'sub_con_claim', 'sub_con_task'], true)) {
            if ($moduleFilter !== 'claim') {
                $pendingClaimsQuery->whereRaw('1 = 0');
            }
            if ($moduleFilter !== 'topup') {
                $pendingTopupsQuery->whereRaw('1 = 0');
            }
            if ($moduleFilter !== 'ap_invoice') {
                $pendingApInvoicesQuery->whereRaw('1 = 0');
            }
            if ($moduleFilter !== 'sub_con_claim') {
                $pendingSubConClaimsQuery->whereRaw('1 = 0');
            }
            if ($moduleFilter !== 'sub_con_task') {
                $pendingSubConTasksQuery->whereRaw('1 = 0');
            }
        }

        if ($requesterFilter !== '') {
            $likeRequester = '%' . $requesterFilter . '%';
            $pendingClaimsQuery->whereHas('issuer', fn ($u) => $u->where('name', 'like', $likeRequester));
            $pendingTopupsQuery->whereHas('requester', fn ($u) => $u->where('name', 'like', $likeRequester));
            $pendingApInvoicesQuery->whereHas('supplier', fn ($s) => $s->where('company_name', 'like', $likeRequester));
            $pendingSubConClaimsQuery->whereHas('subCon', fn ($subCon) => $subCon->where('name', 'like', $likeRequester)
                ->orWhere('company_name', 'like', $likeRequester));
            $pendingSubConTasksQuery->whereHas('subCon', fn ($subCon) => $subCon->where('name', 'like', $likeRequester)
                ->orWhere('company_name', 'like', $likeRequester));
        }

        if ($amountMin !== null && $amountMin !== '') {
            $pendingClaimsQuery->where('total_amount', '>=', (float) $amountMin);
            $pendingTopupsQuery->where('amount', '>=', (float) $amountMin);
            $pendingApInvoicesQuery->where('balance_amount', '>=', (float) $amountMin);
            $pendingSubConClaimsQuery->whereRaw('COALESCE(approved_amount, claimed_amount) >= ?', [(float) $amountMin]);
            $pendingSubConTasksQuery->whereRaw('COALESCE(invoice_amount, amount) >= ?', [(float) $amountMin]);
        }
        if ($amountMax !== null && $amountMax !== '') {
            $pendingClaimsQuery->where('total_amount', '<=', (float) $amountMax);
            $pendingTopupsQuery->where('amount', '<=', (float) $amountMax);
            $pendingApInvoicesQuery->where('balance_amount', '<=', (float) $amountMax);
            $pendingSubConClaimsQuery->whereRaw('COALESCE(approved_amount, claimed_amount) <= ?', [(float) $amountMax]);
            $pendingSubConTasksQuery->whereRaw('COALESCE(invoice_amount, amount) <= ?', [(float) $amountMax]);
        }

        if ($dateFrom) {
            $pendingClaimsQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingTopupsQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingApInvoicesQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingSubConClaimsQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingSubConTasksQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $pendingClaimsQuery->whereDate('created_at', '<=', $dateTo);
            $pendingTopupsQuery->whereDate('created_at', '<=', $dateTo);
            $pendingApInvoicesQuery->whereDate('created_at', '<=', $dateTo);
            $pendingSubConClaimsQuery->whereDate('created_at', '<=', $dateTo);
            $pendingSubConTasksQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($projectFilter) {
            if ($projectFilter === 'office') {
                $pendingClaimsQuery->whereRaw('1 = 0');
                $pendingApInvoicesQuery->whereRaw('1 = 0');
                $pendingSubConClaimsQuery->whereRaw('1 = 0');
                $pendingSubConTasksQuery->whereRaw('1 = 0');
                $pendingTopupsQuery->whereHas('wallet', fn ($w) => $w->where('context_type', 'office'));
            } else {
                $pendingClaimsQuery->where('project_id', $projectFilter);
                $pendingApInvoicesQuery->whereHas('purchaseOrder.purchaseRequest', fn ($pr) => $pr->where('project_id', $projectFilter));
                $pendingTopupsQuery->whereHas('wallet', function ($w) use ($projectFilter) {
                    $w->where('context_type', 'project')->where('context_id', $projectFilter);
                });
                $pendingSubConClaimsQuery->where('project_id', $projectFilter);
                $pendingSubConTasksQuery->where('project_id', $projectFilter);
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
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('code', 'like', $like))
                    ->orWhereHas('supplier', fn ($s) => $s->where('company_name', 'like', $like))
                    ->orWhereHas('purchaseOrder.purchaseRequest.project', fn ($p) => $p->where('name', 'like', $like));
            });
            $pendingSubConClaimsQuery->where(function ($q) use ($like) {
                $q->where('claim_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('payment_slip_no', 'like', $like)
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('subCon', fn ($subCon) => $subCon->where('name', 'like', $like)
                        ->orWhere('company_name', 'like', $like));
            });
            $pendingSubConTasksQuery->where(function ($q) use ($like) {
                $q->where('task_no', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('invoice_no', 'like', $like)
                    ->orWhere('payment_slip_no', 'like', $like)
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('subCon', fn ($subCon) => $subCon->where('name', 'like', $like)
                        ->orWhere('company_name', 'like', $like));
            });
        }

        $pending = collect()
            ->merge($pendingClaimsQuery->get()->map(function (Claim $claim) {
                return [
                    'module' => 'claim',
                    'source_id' => $claim->id,
                    'source_uuid' => $claim->uuid,
                    'reference_no' => $claim->claim_no,
                    'external_doc_ref_no' => null,
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
                    'external_doc_ref_no' => null,
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
                    'reference_no' => $invoice->purchaseOrder?->code ?? '-',
                    'external_doc_ref_no' => $invoice->invoice_number,
                    'title' => 'AP Invoice',
                    'project' => $invoice->purchaseOrder?->purchaseRequest?->project?->name ?? 'Project',
                    'requester' => $invoice->supplier?->company_name ?? '-',
                    'amount' => (float) $invoice->balance_amount,
                    'date' => optional($invoice->invoice_date)->toDateString() ?? optional($invoice->created_at)->toDateString(),
                    'created_at' => $invoice->created_at?->toDateTimeString(),
                ];
            }))
            ->merge($pendingSubConClaimsQuery->get()->map(function (SubConClaim $claim) {
                return [
                    'module' => 'sub_con_claim',
                    'source_id' => $claim->id,
                    'source_uuid' => $claim->uuid,
                    'project_uuid' => $claim->project?->uuid,
                    'reference_no' => $claim->claim_no ?? $claim->title,
                    'external_doc_ref_no' => $claim->payment_slip_no,
                    'title' => $claim->title,
                    'project' => $claim->project?->name ?? 'Project',
                    'requester' => $claim->subCon?->name ?? $claim->subCon?->company_name ?? '-',
                    'amount' => (float) ($claim->approved_amount ?? $claim->claimed_amount ?? 0),
                    'date' => optional($claim->payment_slip_prepared_at)->toDateString()
                        ?? optional($claim->updated_at)->toDateString()
                        ?? optional($claim->created_at)->toDateString(),
                    'created_at' => $claim->created_at?->toDateTimeString(),
                ];
            }))
            ->merge($pendingSubConTasksQuery->get()->map(function (SubConTask $task) {
                return [
                    'module' => 'sub_con_task',
                    'source_id' => $task->id,
                    'source_uuid' => $task->uuid,
                    'project_uuid' => $task->project?->uuid,
                    'reference_no' => $task->task_no ?? $task->title,
                    'external_doc_ref_no' => $task->invoice_no,
                    'title' => $task->title,
                    'project' => $task->project?->name ?? 'Project',
                    'requester' => $task->subCon?->name ?? $task->subCon?->company_name ?? '-',
                    'amount' => (float) ($task->invoice_amount ?? $task->amount),
                    'date' => optional($task->justified_at)->toDateString()
                        ?? optional($task->updated_at)->toDateString()
                        ?? optional($task->created_at)->toDateString(),
                    'created_at' => $task->created_at?->toDateTimeString(),
                ];
            }))
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('PaymentSlips/Index', [
            'pending' => $pending,
            'slips' => [
                'processing' => $processing,
                'payment_arrangement' => $paymentArrangement,
                'paid' => $paid,
            ],
            'counts' => [
                'pending' => $pending->count(),
                'processing' => $processing->total(),
                'payment_arrangement' => $paymentArrangement->total(),
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
                'module',
                'requester',
                'voucher',
                'amount_min',
                'amount_max',
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

        if ($source instanceof SubConTask && $source->status === 'paid') {
            throw ValidationException::withMessages([
                'reason' => 'Cannot cancel slip for a paid Sub Con task.',
            ]);
        }

        $paymentSlip->update([
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancel_reason' => $request->reason,
        ]);

        return back()->with('success', 'Payment slip cancelled.');
    }

    public function approve(Request $request, PaymentSlip $paymentSlip)
    {
        $this->ensurePaymentSlipBranchAccess($request, $paymentSlip);

        if (!$this->canApproveSlip($request)) {
            abort(403, 'Only CEO / GM can approve payment slips.');
        }

        if ($paymentSlip->workflow_status !== 'processing') {
            abort(422, 'Only processing slips can be approved.');
        }

        $paymentSlip->update([
            'workflow_status' => 'payment_arrangement',
            'approved_at' => now(),
            'approved_by' => $request->user()?->id,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejected_reason' => null,
        ]);

        return back()->with('success', 'Payment slip approved and moved to payment arrangement.');
    }

    public function reject(Request $request, PaymentSlip $paymentSlip)
    {
        $this->ensurePaymentSlipBranchAccess($request, $paymentSlip);

        if (!$this->canApproveSlip($request)) {
            abort(403, 'Only CEO / GM can reject payment slips.');
        }

        if ($paymentSlip->workflow_status !== 'processing') {
            abort(422, 'Only processing slips can be rejected.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $paymentSlip->update([
            'workflow_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $request->user()?->id,
            'rejected_reason' => $validated['reason'],
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return back()->with('success', 'Payment slip rejected and returned to pending.');
    }

    public function revertToArrangement(Request $request, PaymentSlip $paymentSlip)
    {
        $this->ensurePaymentSlipBranchAccess($request, $paymentSlip);

        if ($paymentSlip->workflow_status !== 'paid') {
            abort(422, 'Only paid slips can be reverted.');
        }

        $source = $paymentSlip->source;

        if ($source instanceof Claim) {
            if ($source->status === 'paid') {
                $source->update([
                    'status' => 'ceo_approved',
                    'payment_ref_no' => null,
                    'paid_at' => null,
                    'paid_by' => null,
                ]);
            }
        }

        if ($source instanceof PettyCashTopup) {
            if ($source->status === 'paid') {
                $source->update([
                    'status' => 'approved',
                    'payment_ref_no' => null,
                    'paid_at' => null,
                    'paid_by' => null,
                ]);
            }
        }

        if ($source instanceof ApInvoice) {
            $payment = $source->payments()
                ->where('payment_slip_no', $paymentSlip->slip_no)
                ->whereNull('cancelled_at')
                ->first();

            if ($payment) {
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
                    'cancel_reason' => 'Reverted from paid to payment arrangement.',
                ]);
            }
        }

        if ($source instanceof SubConTask && $source->status === 'paid') {
            $source->update([
                'status' => 'approved',
                'payment_ref_no' => null,
                'paid_at' => null,
            ]);
        }

        if ($source instanceof SubConClaim && in_array($source->status, ['pending_real_invoice_upload', 'real_invoice_uploaded'], true)) {
            $source->update([
                'status' => 'payment_in_progress',
            ]);
        }

        $paymentSlip->update([
            'workflow_status' => 'payment_arrangement',
        ]);

        return back()->with('success', 'Slip reverted to payment arrangement.');
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

    private function canApproveSlip(Request $request): bool
    {
        return (bool) ($request->user()?->is_superadmin || $request->user()?->is_general_manager);
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
                    SubConClaim::class => ['project:id,branch_id'],
                    SubConTask::class => ['project:id,branch_id'],
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
        } elseif (
            $source instanceof SubConClaim
            && (int) ($source->project?->branch_id ?? 0) === $activeBranchId
        ) {
            $isAllowed = true;
        } elseif (
            $source instanceof SubConTask
            && (int) ($source->project?->branch_id ?? 0) === $activeBranchId
        ) {
            $isAllowed = true;
        }

        if (!$isAllowed) {
            abort(404);
        }
    }
}
