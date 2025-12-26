<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class ClaimsController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Defaults
        |--------------------------------------------------------------------------
        */
        $tab = $request->tab ?? 'submitted';

        $from = $request->from
            ?? Carbon::now()->subMonth()->toDateString();

        $to = $request->to
            ?? Carbon::now()->toDateString();

        $search = $request->search;

        /*
        |--------------------------------------------------------------------------
        | 2. Base Query (shared filters for CLAIMS)
        |--------------------------------------------------------------------------
        */
        $baseQuery = Claim::query()
            ->with([
                'project:id,name',
                'user:id,name',
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('claim_no', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($u) use ($search) {
                          $u->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->orderByDesc('claims.created_at');

        /*
        |--------------------------------------------------------------------------
        | 3. Paginated Tabs
        |--------------------------------------------------------------------------
        */
        $draft = (clone $baseQuery)->where('status', 'draft')->paginate(15)->withQueryString();
        $submitted = (clone $baseQuery)->where('status', 'submitted')->paginate(15)->withQueryString();
        $approved = (clone $baseQuery)->where('status', 'approved')->paginate(15)->withQueryString();
        $rejected = (clone $baseQuery)->where('status', 'rejected')->paginate(15)->withQueryString();
        $paymentMade = (clone $baseQuery)->where('status', 'payment_made')->paginate(15)->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | 4. Badge Counts (Submitted & Approved only)
        |--------------------------------------------------------------------------
        */
        $countsRaw = (clone $baseQuery)
            ->whereIn('status', ['submitted', 'approved'])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'submitted' => (int) ($countsRaw['submitted'] ?? 0),
            'approved'  => (int) ($countsRaw['approved'] ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | 5. DONUT #1 — By Project (Current Tab)
        |--------------------------------------------------------------------------
        */
        $projectDonutRaw = Claim::query()
            ->leftJoin('projects', 'projects.id', '=', 'claims.project_id')
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->where('claims.status', $tab)
            ->select(
                DB::raw("COALESCE(claims.project_id, 0) as project_key"),
                DB::raw("SUM(claims.total_amount) as total")
            )
            ->groupBy('project_key')
            ->get();

        $projectNames = Project::whereIn(
                'id',
                $projectDonutRaw->pluck('project_key')->filter()
            )
            ->pluck('name', 'id');

        $donutByProject = [];

        foreach ($projectDonutRaw as $row) {
            $donutByProject[] = [
                'label' => $row->project_key == 0
                    ? 'Others'
                    : ($projectNames[$row->project_key] ?? 'Unknown'),
                'amount' => (float) $row->total,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 6. DONUT #2 — By Category (Claim Items, Current Tab)
        |--------------------------------------------------------------------------
        */
        $donutByCategory = DB::table('claim_items')
            ->join('claims', 'claims.id', '=', 'claim_items.claim_id')
            ->when($from, fn ($q) =>
                $q->whereDate('claims.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->whereDate('claims.created_at', '<=', $to)
            )
            ->where('claims.status', $tab)
            ->select(
                'claim_items.claim_type as label',
                DB::raw('SUM(claim_items.amount) as amount')
            )
            ->groupBy('claim_items.claim_type')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst(str_replace('_', ' ', $row->label)),
                'amount' => (float) $row->amount,
            ]);

        $projects = Project::select('id', 'name')
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 7. Response
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Transactions/Claims/Index', [
            'claims' => [
                'draft'        => $draft,
                'submitted'    => $submitted,
                'approved'     => $approved,
                'rejected'     => $rejected,
                'payment_made' => $paymentMade,
            ],

            'filters' => [
                'search' => $search,
                'from'   => $from,
                'to'     => $to,
            ],

            'counts' => $counts,

            'donut' => [
                'by_project'  => $donutByProject,
                'by_category' => $donutByCategory,
            ],

            'activeTab' => $tab,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id'   => ['nullable', 'exists:projects,id'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'title'=> ['required', 'max:255']
        ]);

        $claim = Claim::create([
            'user_id'      => $request->user()->id,
            'title'        => $validated['title'],
            'project_id'   => $validated['project_id'] ?? null,
            'total_amount' => $validated['total_amount'],
            'status'       => 'draft',
        ]);

        return redirect()->route('claims.edit', $claim->id);
    }

    public function destroy(string $uuid)
    {
        $claim = Claim::where('uuid', $uuid)->firstOrFail();

        if ($claim->status !== 'draft') {
            abort(403);
        }

        $claim->delete();

        return back();
    }

}
