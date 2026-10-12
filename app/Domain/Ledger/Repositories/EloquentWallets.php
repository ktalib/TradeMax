<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories;

use App\Domain\Ledger\DTOs\WalletBalance;
use App\Domain\Ledger\DTOs\WalletRef;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Exceptions\InvalidJournal;
use App\Domain\Ledger\Models\Wallet;
use App\Domain\Ledger\Repositories\Contracts\Wallets;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class EloquentWallets implements Wallets
{
    public function forUser(int $userId, int $assetId, WalletType $type = WalletType::Spot): WalletRef
    {
        if ($type->isSystem()) {
            throw new InvalidArgumentException("{$type->value} is a system wallet type.");
        }

        return $this->findOrCreate($userId, $assetId, $type);
    }

    public function system(int $assetId, WalletType $type): WalletRef
    {
        if (! $type->isSystem()) {
            throw new InvalidArgumentException("{$type->value} is a user wallet type.");
        }

        return $this->findOrCreate(null, $assetId, $type);
    }

    public function balance(int $walletId): WalletBalance
    {
        $wallet = Wallet::query()->find($walletId) ?? throw InvalidJournal::unknownWallet($walletId);

        return $wallet->balance();
    }

    public function balancesForUser(int $userId, WalletType $type = WalletType::Spot): array
    {
        return Wallet::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->get()
            ->mapWithKeys(static fn (Wallet $wallet): array => [$wallet->asset_id => $wallet->balance()])
            ->all();
    }

    private function findOrCreate(?int $userId, int $assetId, WalletType $type): WalletRef
    {
        $existing = $this->find($userId, $assetId, $type);

        if ($existing !== null) {
            return $existing->toRef();
        }

        // Two requests may race to create the same wallet; the unique key keeps one.
        $now = CarbonImmutable::now();
        Wallet::query()->insertOrIgnore([
            'user_id' => $userId,
            'asset_id' => $assetId,
            'type' => $type->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $this->find($userId, $assetId, $type)
            ?? throw new InvalidArgumentException("Could not create a {$type->value} wallet for asset {$assetId}.");

        return $created->toRef();
    }

    private function find(?int $userId, int $assetId, WalletType $type): ?Wallet
    {
        return Wallet::query()
            ->when($userId === null, static fn ($q) => $q->whereNull('user_id'), static fn ($q) => $q->where('user_id', $userId))
            ->where('asset_id', $assetId)
            ->where('type', $type->value)
            ->first();
    }
}
