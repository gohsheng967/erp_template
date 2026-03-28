<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /* =====================================================
       CORE ENGINE
       ===================================================== */

    /**
     * Record inventory movement and update stock
     *
     * @throws RuntimeException
     */
    public function recordMovement(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {

            $this->validatePayload($data);

            $warehouseId = $data['warehouse_id'];
            $poItemId    = $data['purchase_order_item_id'];
            $type        = $data['type'];
            $qty         = (float) $data['quantity'];

            // 1️⃣ Get or create stock (STATE)
            $stock = InventoryStock::firstOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'purchase_order_item_id' => $poItemId,
                ],
                [
                    'quantity' => 0,
                ]
            );

            // 2️⃣ Calculate new balance
            $newBalance = $this->calculateBalance(
                $stock->quantity,
                $qty,
                $type
            );

            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient stock');
            }

            // 3️⃣ Create movement (HISTORY)
            $movement = InventoryMovement::create([
                'warehouse_id' => $warehouseId,
                'purchase_order_item_id' => $poItemId,
                'type' => $type,
                'quantity' => $qty,
                'serial_number' => $data['serial_number'] ?? null,
                'stock_category' => $data['stock_category'] ?? null,
                'issue_destination_type' => $data['issue_destination_type'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'destination_user_id' => $data['destination_user_id'] ?? null,
                'holder_user_id' => $data['holder_user_id'] ?? null,
                'usage_status' => $data['usage_status'] ?? 'active',
                'usage_action' => $data['usage_action'] ?? null,
                'usage_remark' => $data['usage_remark'] ?? null,
                'usage_updated_by' => $data['usage_updated_by'] ?? null,
                'usage_updated_at' => $data['usage_updated_at'] ?? null,
                'issued_by' => $data['issued_by'] ?? null,
                'issuer_id' => $data['issuer_id'] ?? null,
                'issuer_signature_path' => $data['issuer_signature_path'] ?? null,
                'issuer_approved_by' => $data['issuer_approved_by'] ?? null,
                'issuer_approved_at' => $data['issuer_approved_at'] ?? null,
                'user_approved_by' => $data['user_approved_by'] ?? null,
                'user_approved_at' => $data['user_approved_at'] ?? null,
                'balance_after' => $newBalance,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'remark' => $data['remark'] ?? null,
                'purpose' => $data['purpose'] ?? null,
            ]);

            // 4️⃣ Update stock quantity
            $stock->update([
                'quantity' => $newBalance,
            ]);

            return $movement;
        });
    }

    /* =====================================================
       PUBLIC APIs (USE THESE ONLY)
       ===================================================== */

    /** Purchase / Delivery IN */
    public function stockIn(array $data): InventoryMovement
    {
        return $this->recordMovement([
            ...$data,
            'type' => InventoryMovement::TYPE_IN,
        ]);
    }

    /** Issue / Consumption OUT */
    public function stockOut(array $data): InventoryMovement
    {
        return $this->recordMovement([
            ...$data,
            'type' => InventoryMovement::TYPE_OUT,
        ]);
    }

    /** Manual adjustment (overwrite quantity) */
    public function adjust(array $data): InventoryMovement
    {
        return $this->recordMovement([
            ...$data,
            'type' => InventoryMovement::TYPE_ADJUST,
        ]);
    }

    /** Warehouse → Warehouse transfer */
    public function transfer(array $data): void
    {
        DB::transaction(function () use ($data) {

            $this->validateTransferPayload($data);

            // OUT from source warehouse
            $this->recordMovement([
                'warehouse_id' => $data['from_warehouse_id'],
                'purchase_order_item_id' => $data['purchase_order_item_id'],
                'quantity' => $data['quantity'],
                'type' => InventoryMovement::TYPE_TRANSFER,
                'serial_number' => $data['serial_number'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'remark' => !empty($data['remark'])
                    ? 'Transfer out: ' . $data['remark']
                    : 'Transfer out',
            ]);

            // IN to destination warehouse
            $this->recordMovement([
                'warehouse_id' => $data['to_warehouse_id'],
                'purchase_order_item_id' => $data['purchase_order_item_id'],
                'quantity' => $data['quantity'],
                'type' => InventoryMovement::TYPE_IN,
                'serial_number' => $data['serial_number'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'remark' => !empty($data['remark'])
                    ? 'Transfer in: ' . $data['remark']
                    : 'Transfer in',
            ]);
        });
    }

    /* =====================================================
       INTERNAL HELPERS
       ===================================================== */

    private function validatePayload(array $data): void
    {
        foreach ([
            'warehouse_id',
            'purchase_order_item_id',
            'quantity',
            'type',
        ] as $field) {
            if (!isset($data[$field])) {
                throw new RuntimeException("Missing field: {$field}");
            }
        }

        if ($data['quantity'] <= 0) {
            throw new RuntimeException('Quantity must be greater than zero');
        }

        if (!in_array($data['type'], [
            InventoryMovement::TYPE_IN,
            InventoryMovement::TYPE_OUT,
            InventoryMovement::TYPE_TRANSFER,
            InventoryMovement::TYPE_ADJUST,
        ])) {
            throw new RuntimeException('Invalid movement type');
        }
    }

    private function validateTransferPayload(array $data): void
    {
        foreach ([
            'from_warehouse_id',
            'to_warehouse_id',
            'purchase_order_item_id',
            'quantity',
        ] as $field) {
            if (!isset($data[$field])) {
                throw new RuntimeException("Missing field: {$field}");
            }
        }

        if ($data['from_warehouse_id'] === $data['to_warehouse_id']) {
            throw new RuntimeException('Source and destination warehouse cannot be the same');
        }

        if ($data['quantity'] <= 0) {
            throw new RuntimeException('Quantity must be greater than zero');
        }
    }

    private function calculateBalance(float $current, float $qty, string $type): float
    {
        return match ($type) {
            InventoryMovement::TYPE_IN       => $current + $qty,
            InventoryMovement::TYPE_OUT      => $current - $qty,
            InventoryMovement::TYPE_TRANSFER => $current - $qty,
            InventoryMovement::TYPE_ADJUST   => $qty,
            default => throw new RuntimeException('Invalid movement type'),
        };
    }
}
