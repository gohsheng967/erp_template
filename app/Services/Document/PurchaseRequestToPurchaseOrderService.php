<?php

namespace App\Services\Document;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\RunningNumberService;

class PurchaseRequestToPurchaseOrderService
{
    public function createPO(
        PurchaseRequest $pr,
        int $supplierId,
        array $items,
        array $meta = []
    ): PurchaseOrder {
        return DB::transaction(function () use ($pr, $supplierId, $items, $meta) {
            if ($pr->status !== 'approved') {
                throw ValidationException::withMessages([
                    'purchase_request' => 'Purchase Request is not approved'
                ]);
            }

            $total = 0;

            foreach ($items as $item) {

                if ($item['quantity'] <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Quantity must be greater than zero'
                    ]);
                }

                $total += $item['total_amount'];
            }

            $po = PurchaseOrder::create([
                'code' => $this->generateCode(),
                'purchase_request_id' => $pr->id,
                'branch_id' => $pr->branch_id,
                'supplier_id' => $supplierId,
                'total_amount' => $total,
                'order_date' => now(),
                'expected_delivery_date' => $meta['expected_delivery_date'] ?? null,
                'status' => 'issued',
                'remark' => $meta['remark'] ?? null,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            return $po;
        });
    }

    protected function generateCode(): string
    {
        return  RunningNumberService::next(documentType: 'purchase_order');
    }
}
