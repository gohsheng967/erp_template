<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class RunningNumberService
{
    public static function next(
        string $documentType,
        ?int $year = null,
        ?int $branchId = null,
        ?string $branchSlug = null
    ): string {
        $year = $year ?? now()->year;
        [$branchId, $branchSlug] = self::resolveBranchContext($branchId, $branchSlug);

        return DB::transaction(function () use ($documentType, $year, $branchId, $branchSlug) {

            $row = DB::table('running_numbers')
                ->where('branch_id', $branchId)
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                $prefix = match ($documentType) {
                    'purchase_request' => 'PR',
                    'purchase_order'   => 'PO',
                    'claim'            => 'CLM',
                    'petty_cash_transaction' => 'PCT',
                    'petty_cash_topup' => 'PTP',
                    'payment_slip'    => 'PS',
                    'sub_con_task'    => 'SCT',
                    'ar_invoice'       => 'ARINV',
                    default            => strtoupper(substr($documentType, 0, 3)),
                };

                DB::table('running_numbers')->insert([
                    'branch_id'     => $branchId,
                    'document_type' => $documentType,
                    'prefix'        => $prefix,
                    'year'          => $year,
                    'current_no'    => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $next = 1;
            } else {
                $prefix = $row->prefix;
                $next   = $row->current_no + 1;

                DB::table('running_numbers')
                    ->where('id', $row->id)
                    ->update([
                        'current_no' => $next,
                        'updated_at' => now(),
                    ]);
            }

            return sprintf(
                '%s/%s/%d/%06d',
                strtoupper($branchSlug),
                $prefix,
                $year,
                $next
            );
        });
    }

    protected static function resolveBranchContext(?int $branchId, ?string $branchSlug): array
    {
        if ($branchId && $branchSlug) {
            return [$branchId, $branchSlug];
        }

        $user = Auth::user();

        if (!$branchId) {
            $branchId = $user?->active_branch_id;
        }

        if (!$branchId) {
            throw new RuntimeException('Active branch is required to generate running number.');
        }

        if (!$branchSlug) {
            $branchSlug = DB::table('branches')
                ->where('id', $branchId)
                ->value('slug');
        }

        if (!$branchSlug) {
            throw new RuntimeException('Branch slug is missing for running number generation.');
        }

        return [$branchId, $branchSlug];
    }
}

