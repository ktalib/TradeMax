<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 16)->unique();
            $table->string('name', 64);
            $table->unsignedTinyInteger('precision')->comment('ledger decimals; amounts are truncated to this');
            $table->unsignedTinyInteger('display_precision');
            $table->string('icon_path')->nullable();
            $table->boolean('is_fiat')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort')->default(0);
            // In asset units until a price feed exists (ADR-0008).
            $table->decimal('reconcile_tolerance', 36, 18)->default(0);
            $table->timestamp('withdrawals_halted_at')->nullable()->index();
            $table->string('withdrawals_halt_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('networks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique()->comment('BTC, ERC20, BEP20, TRC20, SOL');
            $table->string('name', 64);
            $table->string('explorer_tx_url')->nullable()->comment('{tx} is replaced by the hash');
            $table->string('explorer_address_url')->nullable()->comment('{address} is replaced');
            $table->unsignedInteger('required_confirmations');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('asset_networks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('network_id')->constrained()->restrictOnDelete();
            $table->string('contract_address', 128)->nullable()->comment('null for the native coin');
            $table->boolean('deposit_enabled')->default(false);
            $table->boolean('withdraw_enabled')->default(false);
            $table->decimal('min_deposit', 36, 18)->default(0);
            $table->decimal('min_withdraw', 36, 18)->default(0);
            $table->decimal('withdraw_fee', 36, 18)->default(0);
            $table->string('platform_deposit_address', 128)->nullable()->comment('manual deposit rail (Phase 4)');
            $table->boolean('memo_required')->default(false);
            $table->unsignedInteger('required_confirmations')->nullable()->comment('overrides the network default');
            $table->timestamps();

            $table->unique(['asset_id', 'network_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_networks');
        Schema::dropIfExists('networks');
        Schema::dropIfExists('assets');
    }
};
