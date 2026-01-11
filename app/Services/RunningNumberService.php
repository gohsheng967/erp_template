<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RunningNumberService
{
    public static function next(
        string $documentType,
        ?int $year = null
    ): string {
        $year = $year ?? now()->year;

        return DB::transaction(function () use ($documentType, $year) {

            $row = DB::table('running_numbers')
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
                    default            => strtoupper(substr($documentType, 0, 3)),
                };

                DB::table('running_numbers')->insert([
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
                '%s-%d-%06d',
                $prefix,
                $year,
                $next
            );
        });
    }
}

