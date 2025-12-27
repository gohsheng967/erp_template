<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RunningNumberService
{
    /**
     * Generate next running number
     *
     * @param string $documentType  e.g. claim, po, invoice
     * @param string $prefix        e.g. CLM, PO, INV
     * @param int|null $year
     */
    public static function next(
        string $documentType,
        string $prefix,
        ?int $year = null
    ): string {
        $year = $year ?? now()->year;

        return DB::transaction(function () use ($documentType, $prefix, $year) {

            $row = DB::table('running_numbers')
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$row) {
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
                $next = $row->current_no + 1;

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
