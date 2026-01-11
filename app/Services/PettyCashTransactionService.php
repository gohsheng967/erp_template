<?php

namespace App\Services;

use App\Models\PettyCashWallet;
use App\Models\PettyCashTransaction;
use App\Models\PettyCashStatement;
use App\Models\PettyCashTopup;
use App\Models\Claim;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PettyCashTransactionService
{
    public function __construct(
        protected RunningNumberService $runningNumberService
    ) {}

    /* =====================================================
       USAGE (DEBIT) — Linked to Claim, No Approval
    ===================================================== */
    public function debitFromClaim(
        PettyCashWallet $wallet,
        Claim $claim,
        float $amount,
        Carbon $date,
        string $display_type,
        string $source_ref_no,
        array $attachments = []
    ): PettyCashTransaction {

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Debit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $wallet, $claim, $amount, $date, $attachments, $source_ref_no, $display_type
        ) {

            $wallet = PettyCashWallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            $this->assertMonthNotLocked($wallet, $date);

            if (bccomp(
                (string) $wallet->current_balance,
                (string) $amount,
                2
            ) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient petty cash balance.',
                ]);
            }

            $newBalance = bcsub(
                (string) $wallet->current_balance,
                (string) $amount,
                2
            );

            $transaction = PettyCashTransaction::create([
                'wallet_id'        => $wallet->id,
                'code'             => $this->generateTransactionCode($date),
                'type'             => 'debit',
                'source'           => 'usage',
                'display_type'     => $display_type,
                'source_type'      => Claim::class,
                'source_id'        => $claim->id,
                'source_ref_no'    => $source_ref_no,
                'claim_id'         => $claim->id,
                'debit_amount'     => $amount,
                'credit_amount'   => 0,
                'balance_after'   => $newBalance,
                'transaction_date'=> $date->toDateString(),
                'created_by'      => auth()->id(),
            ]);

            $this->storeAttachments($transaction, $attachments);

            $wallet->update([
                'current_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /* =====================================================
       TOP-UP (CREDIT) — Standalone + Payment Slip
    ===================================================== */
    public function creditFromTopup(
        PettyCashWallet $wallet,
        PettyCashTopup $topup,
        Carbon $date,
        array $attachments = []
    ): PettyCashTransaction {

        if ($topup->amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Top-up amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $wallet, $topup, $date, $attachments
        ) {

            $wallet = PettyCashWallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            $this->assertMonthNotLocked($wallet, $date);

            $newBalance = bcadd(
                (string) $wallet->current_balance,
                (string) $topup->amount,
                2
            );

            $transaction = PettyCashTransaction::create([
                'wallet_id'        => $wallet->id,
                'code'             => $this->generateTransactionCode($date),
                'type'             => 'credit',
                'display_type'     => 'Topup',
                'source'           => 'topup',
                'source_type'      => PettyCashTopup::class,
                'source_id'        => $topup->id,
                'source_ref_no'    => $topup->topup_no,
                'claim_id'         => null,
                'debit_amount'     => 0,
                'credit_amount'   => $topup->amount,
                'balance_after'   => $newBalance,
                'transaction_date'=> $date->toDateString(),
                'created_by'      => auth()->id(),
            ]);

            // Attach payment slip to BOTH records
            $this->storeAttachments($transaction, $attachments);
            $this->storeAttachments($topup, $attachments);

            $topup->update([
                'status'  => 'paid',
                'paid_at' => now(),
                'paid_by' => auth()->id(),
            ]);

            $wallet->update([
                'current_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /* =====================================================
       MANUAL ADJUSTMENT (Finance Only)
    ===================================================== */
    public function adjustment(
        PettyCashWallet $wallet,
        float $amount,
        Carbon $date,
        array $attachments = []
    ): PettyCashTransaction {

        if ($amount == 0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount cannot be zero.',
            ]);
        }

        return DB::transaction(function () use (
            $wallet, $amount, $date, $attachments
        ) {

            $wallet = PettyCashWallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            $this->assertMonthNotLocked($wallet, $date);

            $newBalance = bcadd(
                (string) $wallet->current_balance,
                (string) $amount,
                2
            );

            $transaction = PettyCashTransaction::create([
                'wallet_id'        => $wallet->id,
                'code'             => $this->generateTransactionCode($date),
                'type'             => 'adjustment',
                'source'           => 'manual',
                'claim_id'         => null,
                'debit_amount'     => $amount < 0 ? abs($amount) : 0,
                'credit_amount'   => $amount > 0 ? $amount : 0,
                'balance_after'   => $newBalance,
                'transaction_date'=> $date->toDateString(),
                'created_by'      => auth()->id(),
            ]);

            $this->storeAttachments($transaction, $attachments);

            $wallet->update([
                'current_balance' => $newBalance,
            ]);

            return $transaction;
        });
    }

    /* =====================================================
       RUNNING NUMBER
    ===================================================== */
    protected function generateTransactionCode(Carbon $date): string
    {
        return $this->runningNumberService->next(documentType: 'petty_cash_transaction'
        );
    }

    /* =====================================================
       ATTACHMENT HANDLER
    ===================================================== */
    protected function storeAttachments($model, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('petty-cash/attachments', 'public');

            $model->attachments()->create([
                'uuid'          => (string) Str::uuid(),
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'file_size'     => $file->getSize(),
            ]);
        }
    }

    /* =====================================================
       MONTH LOCK CHECK (SINGLE SOURCE OF TRUTH)
    ===================================================== */
    protected function assertMonthNotLocked(
        PettyCashWallet $wallet,
        Carbon $date
    ): void {

        $month = $date->format('Y-m');

        $locked = PettyCashStatement::where('wallet_id', $wallet->id)
            ->where('month', $month)
            ->whereNotNull('locked_at')
            ->exists();

        if ($locked) {
            throw ValidationException::withMessages([
                'date' => "Petty cash for {$month} has been locked.",
            ]);
        }
    }
}
