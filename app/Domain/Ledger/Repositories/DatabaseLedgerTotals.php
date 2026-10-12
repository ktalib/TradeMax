<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories;

use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Repositories\Contracts\LedgerTotals;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DatabaseLedgerTotals implements LedgerTotals
{
    /** Signed effect of an entry on its wallet: credits add, debits subtract. */
    private const SIGNED = "(case when e.direction = 'credit' then e.amount else -e.amount end)";

    public function unbalancedJournals(?CarbonImmutable $since = null, int $limit = 100): array
    {
        $rows = DB::table('journal_entries as e')
            ->when($since !== null, static fn ($q) => $q->where('e.created_at', '>=', $since))
            ->groupBy('e.journal_id', 'e.asset_id')
            ->selectRaw('e.journal_id, e.asset_id, '.$this->sumsByDirection())
            ->havingRaw("sum(case when e.direction = 'debit' then e.amount else 0 end) <> sum(case when e.direction = 'credit' then e.amount else 0 end)")
            ->orderBy('e.journal_id')
            ->limit($limit)
            ->get();

        return array_values($rows->map(fn (stdClass $r): array => [
            'journal_id' => $this->int($r->journal_id),
            'asset_id' => $this->int($r->asset_id),
            'debits' => $this->decimal($r->debits),
            'credits' => $this->decimal($r->credits),
        ])->all());
    }

    public function unbalancedAssets(): array
    {
        $rows = DB::table('journal_entries as e')
            ->groupBy('e.asset_id')
            ->selectRaw('e.asset_id, '.$this->sumsByDirection())
            ->havingRaw("sum(case when e.direction = 'debit' then e.amount else 0 end) <> sum(case when e.direction = 'credit' then e.amount else 0 end)")
            ->get();

        return array_values($rows->map(fn (stdClass $r): array => [
            'asset_id' => $this->int($r->asset_id),
            'debits' => $this->decimal($r->debits),
            'credits' => $this->decimal($r->credits),
        ])->all());
    }

    public function negativeWallets(array $typesAllowedNegative, int $limit = 100): array
    {
        $rows = DB::table('wallets')
            ->whereNotIn('type', $typesAllowedNegative)
            ->where(static fn ($q) => $q->where('available', '<', 0)->orWhere('locked', '<', 0))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'type', 'available', 'locked']);

        return array_values($rows->map(fn (stdClass $r): array => [
            'wallet_id' => $this->int($r->id),
            'type' => $this->string($r->type),
            'available' => $this->decimal($r->available),
            'locked' => $this->decimal($r->locked),
        ])->all());
    }

    public function driftedWallets(int $limit = 100): array
    {
        $rows = DB::table('wallets as w')
            ->leftJoin('journal_entries as e', 'e.wallet_id', '=', 'w.id')
            ->groupBy('w.id', 'w.available', 'w.locked')
            ->selectRaw('w.id, w.available, w.locked, '.$this->sumsByBalanceType())
            ->havingRaw('w.available <> entries_available or w.locked <> entries_locked')
            ->orderBy('w.id')
            ->limit($limit)
            ->get();

        return array_values($rows->map(fn (stdClass $r): array => [
            'wallet_id' => $this->int($r->id),
            'available' => $this->decimal($r->available),
            'locked' => $this->decimal($r->locked),
            'entries_available' => $this->decimal($r->entries_available),
            'entries_locked' => $this->decimal($r->entries_locked),
        ])->all());
    }

    public function entriesWithWrongAsset(int $limit = 100): array
    {
        return array_values(DB::table('journal_entries as e')
            ->join('wallets as w', 'w.id', '=', 'e.wallet_id')
            ->whereColumn('e.asset_id', '<>', 'w.asset_id')
            ->orderBy('e.id')
            ->limit($limit)
            ->pluck('e.id')
            ->map(fn (mixed $id): int => $this->int($id))
            ->all());
    }

    public function custodyLiability(int $assetId, ?CarbonImmutable $asOf = null): Decimal
    {
        $custodyTypes = array_map(static fn (WalletType $t): string => $t->value, WalletType::custodyTypes());

        $now = DB::table('wallets')
            ->where('asset_id', $assetId)
            ->whereNull('user_id')
            ->whereIn('type', $custodyTypes)
            ->selectRaw('coalesce(sum(available + locked), 0) as total')
            ->value('total');

        $total = $this->decimal($now);

        if ($asOf !== null) {
            // Undo everything posted after the custody figure was taken.
            $later = DB::table('journal_entries as e')
                ->join('wallets as w', 'w.id', '=', 'e.wallet_id')
                ->where('w.asset_id', $assetId)
                ->whereNull('w.user_id')
                ->whereIn('w.type', $custodyTypes)
                ->where('e.created_at', '>', $asOf)
                ->selectRaw('coalesce(sum('.self::SIGNED.'), 0) as later')
                ->value('later');

            $total = $total->sub($this->decimal($later));
        }

        return $total->negate();
    }

    public function snapshotEndOfDay(CarbonImmutable $date): int
    {
        $day = $date->utc()->startOfDay();
        $cutoff = $day->addDay();
        $written = 0;

        DB::table('wallets as w')
            ->leftJoin('journal_entries as e', static function (JoinClause $join) use ($cutoff): void {
                $join->on('e.wallet_id', '=', 'w.id')->where('e.created_at', '>=', $cutoff);
            })
            ->where('w.created_at', '<', $cutoff)
            ->groupBy('w.id', 'w.available', 'w.locked')
            ->selectRaw('w.id, w.available, w.locked, '.$this->sumsByBalanceType())
            ->orderBy('w.id')
            ->chunk(1000, function ($rows) use ($day, &$written): void {
                $now = CarbonImmutable::now();
                $inserts = [];

                foreach ($rows as $r) {
                    /** @var stdClass $r */
                    // Balance at the cutoff = balance now minus everything posted since.
                    $inserts[] = [
                        'wallet_id' => $this->int($r->id),
                        'snapshot_date' => $day->toDateString(),
                        'available' => $this->decimal($r->available)->sub($this->decimal($r->entries_available))->toDatabase(),
                        'locked' => $this->decimal($r->locked)->sub($this->decimal($r->entries_locked))->toDatabase(),
                        'created_at' => $now,
                    ];
                }

                $written += DB::table('wallet_balance_snapshots')->insertOrIgnore($inserts);
            });

        return $written;
    }

    public function userTotalsOn(int $assetId, CarbonImmutable $date): array
    {
        $userTypes = array_map(static fn (WalletType $t): string => $t->value, WalletType::userTypes());

        $rows = DB::table('wallet_balance_snapshots as s')
            ->join('wallets as w', 'w.id', '=', 's.wallet_id')
            ->where('s.snapshot_date', $date->toDateString())
            ->where('w.asset_id', $assetId)
            ->whereNotNull('w.user_id')
            ->whereIn('w.type', $userTypes)
            ->groupBy('w.user_id')
            ->selectRaw('w.user_id, sum(s.available + s.locked) as total')
            ->havingRaw('sum(s.available + s.locked) <> 0')
            ->orderBy('w.user_id')
            ->get();

        $totals = [];
        foreach ($rows as $r) {
            $totals[$this->int($r->user_id)] = $this->decimal($r->total);
        }

        return $totals;
    }

    private function sumsByDirection(): string
    {
        return "coalesce(sum(case when e.direction = 'debit' then e.amount else 0 end), 0) as debits, "
            ."coalesce(sum(case when e.direction = 'credit' then e.amount else 0 end), 0) as credits";
    }

    private function sumsByBalanceType(): string
    {
        $available = BalanceType::Available->value;
        $locked = BalanceType::Locked->value;

        return "coalesce(sum(case when e.balance_type = '{$available}' then ".self::SIGNED.' else 0 end), 0) as entries_available, '
            ."coalesce(sum(case when e.balance_type = '{$locked}' then ".self::SIGNED.' else 0 end), 0) as entries_locked';
    }

    private function decimal(mixed $value): Decimal
    {
        return Decimal::of(is_int($value) ? $value : $this->string($value ?? '0'));
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
