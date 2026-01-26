<?php

namespace App\Http\Controllers;

use App\Models\ArInvoice;
use App\Models\ArInvoiceReceipt;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->select('clients.*')
            ->selectSub(function ($q) {
                $q->from('ar_invoices')
                    ->whereColumn('ar_invoices.customer_id', 'clients.id')
                    ->whereIn('ar_invoices.status', ['issued', 'approved'])
                    ->selectRaw('COALESCE(SUM(total_amount), 0)');
            }, 'ar_total')
            ->selectSub(function ($q) {
                $q->from('ar_invoice_receipts')
                    ->join('ar_invoices', 'ar_invoices.id', '=', 'ar_invoice_receipts.ar_invoice_id')
                    ->whereColumn('ar_invoices.customer_id', 'clients.id')
                    ->whereIn('ar_invoices.status', ['issued', 'approved'])
                    ->selectRaw('COALESCE(SUM(ar_invoice_receipts.amount), 0)');
            }, 'ar_received')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        Client::create($validated);

        return redirect()->back()->with('success', 'Client created successfully.');
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        $client->update($validated);

        return redirect()->back()->with('success', 'Client updated successfully.');
    }

    public function show(Client $client)
    {
        $projects = $client->projects()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $totalInvoiced = (float) ArInvoice::where('customer_id', $client->id)
            ->whereIn('status', ['issued', 'approved'])
            ->sum('total_amount');

        $totalReceived = (float) ArInvoiceReceipt::whereHas('invoice', function ($q) use ($client) {
                $q->where('customer_id', $client->id)
                    ->whereIn('status', ['issued', 'approved']);
            })
            ->sum('amount');

        $arSummary = [
            'total_invoiced' => $totalInvoiced,
            'total_received' => $totalReceived,
            'outstanding'    => max($totalInvoiced - $totalReceived, 0),
        ];

        $today = Carbon::today();
        $dueSoonUntil = $today->copy()->addDays(5);

        $overdueInvoices = ArInvoice::query()
            ->where('customer_id', $client->id)
            ->where('status', 'approved')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->with(['project:id,name'])
            ->withSum('receipts', 'amount')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $dueSoonInvoices = ArInvoice::query()
            ->where('customer_id', $client->id)
            ->where('status', 'approved')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $dueSoonUntil])
            ->with(['project:id,name'])
            ->withSum('receipts', 'amount')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $recentReceipts = ArInvoiceReceipt::query()
            ->whereHas('invoice', function ($q) use ($client) {
                $q->where('customer_id', $client->id);
            })
            ->with([
                'invoice:id,uuid,invoice_no,customer_id',
                'receiver:id,name',
            ])
            ->orderByDesc('received_at')
            ->limit(10)
            ->get();

        $projectStatusCounts = $client->projects()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentProjects = $client->projects()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'uuid', 'name', 'status', 'budget', 'updated_at']);

        $projectIds = $recentProjects->pluck('id');

        $purchaseOrderTotals = DB::table('purchase_orders')
            ->join('purchase_requests', 'purchase_requests.id', '=', 'purchase_orders.purchase_request_id')
            ->whereIn('purchase_requests.project_id', $projectIds)
            ->whereNotNull('purchase_orders.confirmed_at')
            ->select('purchase_requests.project_id', DB::raw('SUM(purchase_orders.total_amount) as total'))
            ->groupBy('purchase_requests.project_id')
            ->pluck('total', 'purchase_requests.project_id');

        $claimTotals = DB::table('claims')
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid'])
            ->select('project_id', DB::raw('SUM(total_amount) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $overdueActionTasks = DB::table('milestone_action_tasks')
            ->join('milestones', 'milestones.id', '=', 'milestone_action_tasks.milestone_id')
            ->whereIn('milestones.project_id', $projectIds)
            ->where('milestone_action_tasks.is_done', false)
            ->whereNotNull('milestones.end_date')
            ->whereDate('milestones.end_date', '<', $today)
            ->select('milestones.project_id', DB::raw('COUNT(*) as total'))
            ->groupBy('milestones.project_id')
            ->pluck('total', 'milestones.project_id');

        $overduePhaseTasks = DB::table('milestone_phase_tasks')
            ->join('milestone_phases', 'milestone_phases.id', '=', 'milestone_phase_tasks.milestone_phase_id')
            ->join('milestones', 'milestones.id', '=', 'milestone_phases.milestone_id')
            ->whereIn('milestones.project_id', $projectIds)
            ->where('milestone_phase_tasks.is_done', false)
            ->whereNotNull('milestone_phases.end_date')
            ->whereDate('milestone_phases.end_date', '<', $today)
            ->select('milestones.project_id', DB::raw('COUNT(*) as total'))
            ->groupBy('milestones.project_id')
            ->pluck('total', 'milestones.project_id');

        $recentProjects = $recentProjects->map(function ($project) use (
            $purchaseOrderTotals,
            $claimTotals,
            $overdueActionTasks,
            $overduePhaseTasks
        ) {
            $budgetTotal = (float) ($project->budget ?? 0);
            $poUsed = (float) ($purchaseOrderTotals[$project->id] ?? 0);
            $claimUsed = (float) ($claimTotals[$project->id] ?? 0);
            $used = $poUsed + $claimUsed;

            $project->budget_used = $used;
            $project->budget_remaining = max($budgetTotal - $used, 0);
            $project->budget_percent = $budgetTotal > 0
                ? round(($used / $budgetTotal) * 100, 1)
                : 0;
            $project->overdue_tasks = (int) ($overdueActionTasks[$project->id] ?? 0)
                + (int) ($overduePhaseTasks[$project->id] ?? 0);

            return $project;
        });

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'projects' => $projects,
            'arSummary' => $arSummary,
            'overdueInvoices' => $overdueInvoices,
            'dueSoonInvoices' => $dueSoonInvoices,
            'recentReceipts' => $recentReceipts,
            'projectStatusCounts' => $projectStatusCounts,
            'recentProjects' => $recentProjects,
        ]);
    }

    public function destroy(Client $client)
    {
        if ($client->projects()->exists()) {
            return back()->withErrors([
                'delete' => 'Client cannot be deleted because it has existing projects.',
            ]);
        }

        $client->delete();

        return redirect()->back()->with('success', 'Client deleted successfully.');
    }
}
