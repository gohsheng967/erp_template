<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Project;
use App\Models\InventoryStock;
use App\Models\StockCategory;
use Illuminate\Support\Facades\Validator;
use App\Models\Warehouse;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class StockController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Stocks/Index', [
            'warehouses' => $this->activeWarehouses(),
            'stock_categories' => $this->stockCategories(),
            'projects' => Project::query()
                ->select('id', 'code', 'name')
                ->with([
                    'sites:id,site_name',
                ])
                ->orderBy('name')
                ->get(),

            'stocks' => InventoryStock::with([
                    'warehouse:id,title',
                    'purchaseOrderItem:id,item_name,description',
                ])
                ->orderBy('warehouse_id')
                ->get(),
        ]);
    }

    public function movements(Request $request)
    {
        $warehouseId = $request->query('warehouse_id');
        $poItemId    = $request->query('po_item_id');

        $query = InventoryMovement::with([
            'warehouse:id,title',
            'purchaseOrderItem:id,item_name',
            'issueUser:id,name',
            'project:id,code,name',
            'site:id,site_name',
        ])->latest();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($poItemId) {
            $query->where('purchase_order_item_id', $poItemId);
        }

        return Inertia::render('Inventory/Stocks/Movements', [
            'warehouses' => $this->activeWarehouses(),
            'stock_categories' => $this->stockCategories(),

            'filters' => [
                'warehouse_id' => $warehouseId,
                'po_item_id'   => $poItemId,
            ],

            'movements' => $query
                ->limit(500) // safety
                ->get(),
        ]);
    }

    public function browse(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'destination' => (string) $request->query('destination', 'all'),
            'warehouse_id' => (string) $request->query('warehouse_id', 'all'),
            'stock_category' => trim((string) $request->query('stock_category', 'all')),
        ];

        $query = InventoryMovement::with([
            'warehouse:id,title',
            'purchaseOrderItem:id,item_name,description',
            'issueUser:id,name',
            'project:id,code,name',
            'site:id,site_name',
        ])
            ->where('type', InventoryMovement::TYPE_OUT)
            ->whereNotNull('issue_destination_type')
            ->latest();

        $this->applyBrowseFilters($query, $filters);

        return Inertia::render('Inventory/Stocks/Browse', [
            'warehouses' => $this->activeWarehouses(),
            'stock_categories' => $this->stockCategories(),
            'filters' => $filters,
            'rows' => $query->limit(2000)->get(),
        ]);
    }

    public function exportBrowse(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'destination' => (string) $request->query('destination', 'all'),
            'warehouse_id' => (string) $request->query('warehouse_id', 'all'),
            'stock_category' => trim((string) $request->query('stock_category', 'all')),
        ];

        $query = InventoryMovement::with([
            'warehouse:id,title',
            'purchaseOrderItem:id,item_name,description',
            'issueUser:id,name',
            'project:id,code,name',
            'site:id,site_name',
        ])
            ->where('type', InventoryMovement::TYPE_OUT)
            ->whereNotNull('issue_destination_type')
            ->latest();

        $this->applyBrowseFilters($query, $filters);

        $rows = $query->limit(10000)->get();
        $filename = 'stock-browse-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Warehouse',
                'Item',
                'Quantity',
                'Serial Number',
                'Stock Category',
                'Destination',
                'Project',
                'Site',
                'Issued By',
                'Purpose',
                'Remark',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at?->format('Y-m-d H:i:s'),
                    $row->warehouse?->title,
                    $row->purchaseOrderItem?->item_name,
                    $row->quantity,
                    $row->serial_number,
                    $row->stock_category,
                    $row->issue_destination_type,
                    $row->project?->name,
                    $row->site?->site_name,
                    $row->issueUser?->name,
                    $row->purpose,
                    $row->remark,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function issue(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.serial_number' => ['required', 'string', 'max:255'],
            'items.*.stock_category' => ['required', 'string', 'max:100', 'exists:stock_categories,name'],
            'items.*.issue_destination_type' => ['required', 'in:office,project'],
            'items.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'items.*.site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'items.*.purpose' => ['required', 'string', 'max:2000'],
            'items.*.remark' => ['nullable', 'string'],
        ]);

        foreach ($data['items'] as $index => $row) {
            if (($row['issue_destination_type'] ?? null) !== 'project') {
                continue;
            }

            $rowValidator = Validator::make($row, [
                'project_id' => ['required', 'integer', 'exists:projects,id'],
                'site_id' => [
                    'required',
                    'integer',
                    Rule::exists('sites', 'id')->where(function ($query) use ($row) {
                        $projectId = (int) ($row['project_id'] ?? 0);

                        return $query->whereExists(function ($sub) use ($projectId) {
                            $sub->selectRaw('1')
                                ->from('project_site')
                                ->whereColumn('project_site.site_id', 'sites.id')
                                ->where('project_site.project_id', $projectId);
                        });
                    }),
                ],
            ]);

            if ($rowValidator->fails()) {
                $errors = [];

                foreach ($rowValidator->errors()->messages() as $field => $messages) {
                    $errors["items.{$index}.{$field}"] = $messages[0] ?? 'Invalid value.';
                }

                throw ValidationException::withMessages($errors);
            }
        }

        $this->runInventoryAction(function () use ($inventory, $data) {
            foreach ($data['items'] as $row) {
                $inventory->stockOut([
                    'warehouse_id' => $data['warehouse_id'],
                    'purchase_order_item_id' => $data['purchase_order_item_id'],
                    'quantity' => $row['quantity'],
                    'serial_number' => $row['serial_number'],
                    'stock_category' => $row['stock_category'],
                    'issue_destination_type' => $row['issue_destination_type'],
                    'project_id' => $row['issue_destination_type'] === 'project'
                        ? ($row['project_id'] ?? null)
                        : null,
                    'site_id' => $row['issue_destination_type'] === 'project'
                        ? ($row['site_id'] ?? null)
                        : null,
                    'issued_by' => auth()->id(),
                    'purpose' => $row['purpose'],
                    'remark' => $row['remark'] ?? 'Stock issue',
                ]);
            }
        });

        return back()->with('success', 'Stock issued successfully');
    }

    public function transfer(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'exists:warehouses,id'],
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.serial_number' => ['required', 'string', 'max:255'],
            'items.*.remark' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
        ]);

        $this->runInventoryAction(function () use ($inventory, $data) {
            foreach ($data['items'] as $row) {
                $inventory->transfer([
                    'from_warehouse_id' => $data['from_warehouse_id'],
                    'to_warehouse_id'   => $data['to_warehouse_id'],
                    'purchase_order_item_id' => $data['purchase_order_item_id'],
                    'quantity' => $row['quantity'],
                    'serial_number' => $row['serial_number'],
                    'remark' => $row['remark'] ?? $data['remark'] ?? 'Warehouse transfer',
                ]);
            }
        });

        return back()->with('success', 'Stock transferred successfully');
    }

    public function adjust(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.serial_number' => ['required', 'string', 'max:255'],
            'items.*.remark' => ['nullable', 'string'],
            'remark' => ['required', 'string', 'max:255'],
        ]);

        $this->runInventoryAction(function () use ($inventory, $data) {
            $runningTotal = 0;

            foreach ($data['items'] as $row) {
                $runningTotal += (float) $row['quantity'];

                $inventory->adjust([
                    'warehouse_id' => $data['warehouse_id'],
                    'purchase_order_item_id' => $data['purchase_order_item_id'],
                    'quantity' => $runningTotal,
                    'serial_number' => $row['serial_number'],
                    'remark' => $row['remark'] ?? $data['remark'],
                ]);
            }
        });

        return back()->with('success', 'Stock adjusted successfully');
    }

    private function activeWarehouses()
    {
        return Warehouse::where('status', 1)
            ->orderBy('title')
            ->get();
    }

    private function stockCategories()
    {
        $used = InventoryMovement::query()
            ->whereNotNull('stock_category')
            ->where('stock_category', '!=', '')
            ->select('stock_category')
            ->distinct()
            ->orderBy('stock_category')
            ->pluck('stock_category');

        $master = StockCategory::query()
            ->orderBy('name')
            ->pluck('name');

        return $master
            ->merge($used)
            ->unique()
            ->values();
    }

    private function runInventoryAction(callable $action): void
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }
    }

    private function applyBrowseFilters($query, array $filters): void
    {
        if (($filters['destination'] ?? 'all') !== 'all') {
            $query->where('issue_destination_type', $filters['destination']);
        }

        if (($filters['warehouse_id'] ?? 'all') !== 'all') {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (($filters['stock_category'] ?? 'all') !== 'all' && ($filters['stock_category'] ?? '') !== '') {
            $query->where('stock_category', $filters['stock_category']);
        }

        if (!empty($filters['search'])) {
            $keyword = $filters['search'];

            $query->where(function ($q) use ($keyword) {
                $q->where('serial_number', 'like', '%' . $keyword . '%')
                    ->orWhere('stock_category', 'like', '%' . $keyword . '%')
                    ->orWhere('purpose', 'like', '%' . $keyword . '%')
                    ->orWhere('remark', 'like', '%' . $keyword . '%')
                    ->orWhereHas('purchaseOrderItem', function ($qq) use ($keyword) {
                        $qq->where('item_name', 'like', '%' . $keyword . '%');
                    })
                    ->orWhereHas('project', function ($qq) use ($keyword) {
                        $qq->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('code', 'like', '%' . $keyword . '%');
                    })
                    ->orWhereHas('site', function ($qq) use ($keyword) {
                        $qq->where('site_name', 'like', '%' . $keyword . '%');
                    })
                    ->orWhereHas('issueUser', function ($qq) use ($keyword) {
                        $qq->where('name', 'like', '%' . $keyword . '%');
                    });
            });
        }
    }

}
