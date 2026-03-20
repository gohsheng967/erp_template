<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PettyCashClaimController extends Controller
{
    private const ALLOWED_TABS = [
        'my_in_progress',
        'my_rejected',
        'my_completed',
        'all_non_draft',
        Claim::STATUS_SUBMITTED,
        Claim::STATUS_CHECKED,
        Claim::STATUS_VERIFIED,
        Claim::STATUS_REJECTED,
    ];

    public function index(Request $request)
    {
        $tab = in_array($request->tab, self::ALLOWED_TABS, true)
            ? $request->tab
            : Claim::STATUS_SUBMITTED;

        $from = $request->from ?? Carbon::now()->subMonth()->toDateString();
        $to = $request->to ?? Carbon::now()->toDateString();

        $search = $request->search;
        $projectId = $request->filled('project_id') ? (int) $request->project_id : null;
        $issuerId = $request->filled('issuer_id') ? (int) $request->issuer_id : null;
        $amountMin = $request->filled('amount_min') ? (float) $request->amount_min : null;
        $amountMax = $request->filled('amount_max') ? (float) $request->amount_max : null;

        $filterQuery = Claim::query()
            ->whereRaw(
                'LOWER(TRIM(COALESCE(claims.remark, ""))) = ?',
                [strtolower(trim(Claim::REMARK_PETTY_CASH_ORIGIN))]
            )
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('claims.branch_id', $this->activeBranchId($request))
            )
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('claim_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('issuer', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($projectId, fn ($q) => $q->where('claims.project_id', $projectId))
            ->when($issuerId, fn ($q) => $q->where('claims.user_id', $issuerId))
            ->when($from, fn ($q) => $q->whereDate('claims.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('claims.created_at', '<=', $to))
            ->when($amountMin !== null, fn ($q) => $q->where('claims.total_amount', '>=', $amountMin))
            ->when($amountMax !== null, fn ($q) => $q->where('claims.total_amount', '<=', $amountMax));

        $listQuery = (clone $filterQuery)
            ->withTrashed()
            ->with([
                'project:id,name',
                'issuer:id,name',
            ])
            ->withCount('items')
            ->withSum('items', 'amount')
            ->orderByDesc('claims.created_at');

        $myBaseQuery = (clone $listQuery)
            ->where('claims.user_id', (int) auth()->id());

        $allNonDraft = (clone $listQuery)
            ->where('status', '!=', Claim::STATUS_DRAFT)
            ->paginate(15)
            ->withQueryString();

        $myInProgress = (clone $myBaseQuery)
            ->whereNull('claims.deleted_at')
            ->whereNotIn('status', [Claim::STATUS_REJECTED, Claim::STATUS_PAID])
            ->paginate(15)
            ->withQueryString();

        $myRejected = (clone $myBaseQuery)
            ->where(function ($q) {
                $q->where('status', Claim::STATUS_REJECTED)
                    ->orWhereNotNull('claims.deleted_at');
            })
            ->paginate(15)
            ->withQueryString();

        $myCompleted = (clone $myBaseQuery)
            ->whereNull('claims.deleted_at')
            ->where('status', Claim::STATUS_PAID)
            ->paginate(15)
            ->withQueryString();

        $submitted = (clone $listQuery)
            ->whereNull('claims.deleted_at')
            ->where('status', Claim::STATUS_SUBMITTED)
            ->paginate(15)
            ->withQueryString();

        $checked = (clone $listQuery)
            ->whereNull('claims.deleted_at')
            ->where('status', Claim::STATUS_CHECKED)
            ->paginate(15)
            ->withQueryString();

        $verified = (clone $listQuery)
            ->whereNull('claims.deleted_at')
            ->where('status', Claim::STATUS_VERIFIED)
            ->paginate(15)
            ->withQueryString();

        $rejected = (clone $listQuery)
            ->where(function ($q) {
                $q->where('status', Claim::STATUS_REJECTED)
                    ->orWhereNotNull('claims.deleted_at');
            })
            ->paginate(15)
            ->withQueryString();

        $countsRaw = (clone $filterQuery)
            ->whereIn('status', [
                Claim::STATUS_SUBMITTED,
                Claim::STATUS_CHECKED,
                Claim::STATUS_VERIFIED,
            ])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'submitted' => (int) ($countsRaw[Claim::STATUS_SUBMITTED] ?? 0),
            'checked' => (int) ($countsRaw[Claim::STATUS_CHECKED] ?? 0),
            'verified' => (int) ($countsRaw[Claim::STATUS_VERIFIED] ?? 0),
            'approved' => 0,
        ];

        $projects = Project::query()
            ->when($this->shouldScopeToActiveBranch($request), fn ($q) =>
                $q->where('branch_id', $this->activeBranchId($request))
            )
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $issuerIds = (clone $filterQuery)
            ->whereNotNull('claims.user_id')
            ->distinct()
            ->pluck('claims.user_id');

        $issuers = User::query()
            ->whereIn('id', $issuerIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('PettyCash/Claims/Index', [
            'claims' => [
                'all_non_draft' => $allNonDraft,
                'my_in_progress' => $myInProgress,
                'my_rejected' => $myRejected,
                'my_completed' => $myCompleted,
                'submitted' => $submitted,
                'checked' => $checked,
                'verified' => $verified,
                'rejected' => $rejected,
            ],
            'filters' => [
                'search' => $search,
                'from' => $from,
                'to' => $to,
                'project_id' => $projectId,
                'issuer_id' => $issuerId,
                'amount_min' => $amountMin,
                'amount_max' => $amountMax,
            ],
            'counts' => $counts,
            'activeTab' => $tab,
            'projects' => $projects,
            'issuers' => $issuers,
            'canBrowseAllClaims' => $this->canBrowseAllClaims($request),
        ]);
    }

    private function activeBranchId(Request $request): int
    {
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

    private function canBrowseAllClaims(Request $request): bool
    {
        return (bool) ($request->user()?->is_superadmin || $request->user()?->is_general_manager);
    }
}
