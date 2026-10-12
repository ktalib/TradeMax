<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The double-entry ledger (ADR-0003). Balance columns are signed: system counter-accounts
| (system_hot, system_cold, system_demo_faucet) go negative by design. Rows here are never
| updated or deleted except wallets' balance columns, which only LedgerService writes.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->comment('null = system account')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('type', 24)->index();
            $table->decimal('available', 36, 18)->default(0);
            $table->decimal('locked', 36, 18)->default(0);
            $table->unsignedBigInteger('version')->default(0)->comment('bumped on every balance change');
            // UNIQUE treats NULLs as distinct, so system wallets are keyed on owner 0.
            $table->unsignedBigInteger('owner_key')->storedAs('coalesce(`user_id`, 0)');
            $table->timestamps();

            $table->unique(['owner_key', 'asset_id', 'type']);
            $table->index(['asset_id', 'type']);
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->char('fingerprint', 64)->comment('sha256 of the entries; a replay with other entries is refused');
            $table->string('reference_type', 64);
            $table->string('reference_id', 64);
            $table->string('description');
            $table->foreignId('reverses_journal_id')->nullable()->unique()->constrained('journals')->restrictOnDelete();
            $table->string('posted_by_type', 16)->comment('system | user | admin');
            $table->unsignedBigInteger('posted_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('direction', 6)->comment('debit | credit');
            $table->string('balance_type', 9)->comment('available | locked');
            $table->decimal('amount', 36, 18);
            $table->decimal('balance_after', 36, 18);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('wallet_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->date('snapshot_date')->index()->comment('balance at the end of this UTC day');
            $table->decimal('available', 36, 18);
            $table->decimal('locked', 36, 18);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['wallet_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balance_snapshots');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('wallets');
    }
};
