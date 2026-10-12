<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\Actions\EnsureSystemWallets;
use Illuminate\Database\Seeder;

final class SystemWalletSeeder extends Seeder
{
    public function run(EnsureSystemWallets $ensure): void
    {
        $ensure->handle();
    }
}
